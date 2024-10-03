<?php

namespace Reservations\Admin;

use Carbon\Carbon;
use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\PostTypes;
use Reservations\Taxonomies;
use Reservations\Utils;
use Reservations\Utils\Arrays;
use WP_Error;

class ImportExport extends Base\AdminPage
{
    protected $importStep = null;
    protected $data;
    protected $id;

    public function register()
    {
        if(!$this->plugin->isFeatureEnabled("import_export")) {
            return;
        }

        $this->slug = $this->plugin->slug("-import-export");

        return add_options_page(
            __('Import & Export Reservations Data', 'reservations'),
            __('Import & Export Reservations Data', 'reservations'),
            "administrator",
            $this->slug,
            [$this, "render"]);
    }

    public function prepare()
    {
        if (isset($_POST['ie_action']) && $_POST['ie_action'] === "export") {
            check_admin_referer($this->plugin->prefix("ie_export"));

            $this->exportJson();
            exit;
        }

        if (isset($_GET['ie_action']) && $_GET['ie_action'] === "import-1") {
            check_admin_referer("import-upload");

            $this->importStep = 1;
            $this->prepareImport1();
        }

        if (isset($_GET['ie_action']) && $_GET['ie_action'] === "import-2") {
            check_admin_referer($this->plugin->prefix("ie_import_2"));

            $this->importStep = 2;
            $this->prepareImport2();
        }
    }

    public function render()
    {
        if ($this->importStep === 1) {
            $this->renderImport1();
            return;
        } else if ($this->importStep === 2) {
            $this->renderImport2();
            return;
        }

        ?>
        <div class="wrap" id="res-settings">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <h2><?php _e('Export', 'reservations');?></h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field($this->plugin->prefix("ie_export"));?>
            <input type="hidden" name="ie_action" value="export">
            <p><?php _e('Export file will contain all data managed by the plugin, including subscriber\'s personal information. Keep this file in a safe place to prevent potential privacy issues.', 'reservations');?></p>
            <button type="submit" class="button-primary"><?php _e('Export all data to JSON', 'reservations');?></button>
        </form>
        <h2><?php _e('Import', 'reservations');?></h2>
            <p><?php _e('After Reservations analyzes your file, you\'ll be able to select a few options and confirm the (potentially dangerous) operation.', 'reservations');?></p>
            <?php wp_import_upload_form(admin_url("options-general.php?page=" . $this->slug . "&ie_action=import-1"));?>
        </div>
        <?php
}

    /** @filter(submenu_file) */
    public function hideFromMenu($submenu_file)
    {
        remove_submenu_page("options-general.php", $this->slug);

        if ($this->isCurrent()) {
            $submenu_file = $this->plugin->slug(); // highlight Reservations Settings
        }

        return $submenu_file;
    }

    protected function serializeAttachment($attachmentId)
    {
        $url = wp_get_attachment_url($attachmentId);

        if (!$url) {
            return null;
        }

        return [
            "id"  => $attachmentId,
            "url" => $url,
        ];
    }

    protected function serializeAttachmentSet($attachmentSet)
    {
        $newFiles = [];

        foreach ($attachmentSet as $id) {
            $serialized = $this->serializeAttachment($id);

            if ($serialized) {
                $newFiles[] = $serialized;
            }

        }

        return $newFiles;
    }

    protected function serializeAttachmentSets($attachmentSets)
    {
        foreach ($attachmentSets as &$set) {
            $set = $this->serializeAttachmentSet($set);
        }

        return $attachmentSets;
    }

    protected function serializeTerms($terms)
    {
        $newTerms = [];
        foreach ($terms as $term) {
            if (!isset($newTerms[$term->taxonomy])) {
                $newTerms[$term->taxonomy] = [];
            }

            $newTerms[$term->taxonomy][] = $term->term_id;
        }
        return $newTerms;
    }

    protected function exportJson()
    {
        // Save Users
        $users = [];

        $customerIds = Models\Subscriber::select("user_id")->whereNotNull("user_id")->pluck("user_id", "user_id");
        $authorIds   = $usedInstructorIds   = [];
        foreach (Models\Training::all() as $event) {
            $authorIds[$event->post_author] = $event->post_author;
            $contactInstructorId            = $event->getPrefixedMeta("contact_instructor_id");

            if ($contactInstructorId) {
                $usedInstructorIds[$contactInstructorId] = $contactInstructorId;
            }

        }
        foreach (Models\Event::all() as $event) {
            $authorIds[$event->post_author] = $event->post_author;
            $contactInstructorId            = $event->getPrefixedMeta("contact_instructor_id");

            if ($contactInstructorId) {
                $usedInstructorIds[$contactInstructorId] = $contactInstructorId;
            }

        }

        $allUsers = Models\User::all();
        foreach ($allUsers as $user) {
            $save = false;

            if ($this->plugin->isFeatureEnabled("city_acl") && count($user->getAccessibleCityIdsAttribute()) > 0) {
                $save = "administrator";
            } else if (isset($authorIds[$user->id])) {
                $save = "author";
            } else if (isset($usedInstructorIds[$user->id]) || $user->getPrefixedMeta("is_instructor") === "1") {
                $save = "instructor";
            } else if (isset($customerIds[$user->id])) {
                $save = "customer";
            }

            if (!$save) {
                continue;
            }

            $res                = $user->toArray();
            $res["_first_name"] = $user->getMeta("first_name", "");
            $res["_last_name"]  = $user->getMeta("last_name", "");
            $res["_meta"]       = $user->getPrefixedMetadata(true);
            $res["_roles"]      = get_userdata($user->id)->roles;
            $res["_res_role"]   = $save;

            $users[] = $res;
        }

        // Save Cities
        $cities = [];
        foreach (Models\City::all() as $term) {
            $res          = $term->toArray();
            $res["_meta"] = $term->getPrefixedMetadata(true);

            $cities[] = $res;
        }

        // Save Gyms
        $gyms = [];
        foreach (Models\Gym::all() as $term) {
            $res          = $term->toArray();
            $res["_meta"] = $term->getPrefixedMetadata(true);

            $gyms[] = $res;
        }

        // Save Training Groups
        $trainingGroups = [];
        foreach (Models\TrainingGroup::all() as $term) {
            $res          = $term->toArray();
            $res["_meta"] = $term->getPrefixedMetadata(true);

            if (isset($res["_meta"]["attachment_sets"][0])) {
                $res["_meta"]["attachment_sets"][0] = $this->serializeAttachmentSets($res["_meta"]["attachment_sets"][0]);
            }

            $trainingGroups[] = $res;
        }

        // Save Events
        $events = [];
        foreach (Models\Event::all() as $post) {
            $res           = $post->toArray();
            $res["_meta"]  = $post->getPrefixedMetadata(true);
            $res["_terms"] = $this->serializeTerms(wp_get_object_terms($post->ID, [
                Taxonomies\City::NAME,
            ]));
            $res["_instructors"] = $post->instructors()->get()->pluck("ID");

            if (isset($res["_meta"]["attachment_sets"][0])) {
                $res["_meta"]["attachment_sets"][0] = $this->serializeAttachmentSets($res["_meta"]["attachment_sets"][0]);
            }

            $events[] = $res;
        }

        // Save Trainings
        $trainings = [];
        foreach (Models\Training::all() as $post) {
            $res           = $post->toArray();
            $res["_meta"]  = $post->getPrefixedMetadata(true);
            $res["_terms"] = $this->serializeTerms(wp_get_object_terms($post->ID, [
                Taxonomies\City::NAME, Taxonomies\Gym::NAME, Taxonomies\TrainingGroup::NAME,
            ]));
            $res["_instructors"] = $post->instructors()->get()->pluck("ID");

            $trainings[] = $res;
        }

        // Save Options

        $options = $this->plugin->getOptions(true);

        if (isset($options["message_templates"])) {
            foreach ($options["message_templates"] as $id => &$template) {
                $template["_id"]         = $id;
                $template["attachments"] = $this->serializeAttachmentSet($template["attachments"] ?? []);
            }
        }

        // Save Subscribers

        $subscribers   = Models\Subscriber::all()->toArray();
        $subscriptions = Models\Subscription::all()->toArray();
        $payments      = Models\Payment::all()->toArray();
        $transactions  = Models\Transaction::all()->toArray();

        header("Content-Disposition: attachment; filename=export.json");

        wp_send_json([
            "_version"        => 1,
            "users"           => $users,
            "cities"          => $cities,
            "gyms"            => $gyms,
            "training_groups" => $trainingGroups,
            "events"          => $events,
            "trainings"       => $trainings,
            "subscribers"     => $subscribers,
            "subscriptions"   => $subscriptions,
            "payments"        => $payments,
            "transactions"    => $transactions,
            "options"         => $options,
        ], 200);
    }

    protected function prepareImport1()
    {
        $pageUrl = admin_url("options-general.php?page=" . $this->slug);

        $file = wp_import_handle_upload();

        if (isset($file['error'])) {
            wp_redirect(add_query_arg("ie_error", urlencode(esc_html($file['error'])), $pageUrl));
            exit;
        } else if (!file_exists($file['file'])) {
            wp_redirect(add_query_arg("ie_error", sprintf(__('The export file could not be found at "%s". It is likely that this was caused by a permissions problem.', 'reservations'), $file['file'])));
            exit;
        }

        $this->id   = (int) $file['id'];
        $this->data = json_decode(file_get_contents($file['file']), true);

        if (!$this->data) {
            wp_redirect(add_query_arg("ie_error", sprintf(__('Failed to parse export file: %s', 'reservations'), json_last_error_msg()), $pageUrl));
            exit;
        }

        $this->autoAssignUsers();
    }

    protected function autoAssignUsers()
    {
        $allUsers = Models\User::all();

        foreach ($this->data["users"] as &$user) {
            $targetUser = $allUsers->first(function ($u) use ($user) {
                return $u->user_email === $user["user_email"];
            });

            if ($targetUser) {
                $user["_target_id"] = $targetUser->ID;
            } else {
                $user["_target_id"] = null;
            }
        }
    }

    protected function renderImport1()
    {
        $file = get_attached_file($this->id);

        $stats = [
            "all"           => count($this->data["users"]),
            "administrator" => 0,
            "author"        => 0,
            "instructor"    => 0,
            "customer"      => 0,
        ];
        foreach ($this->data["users"] as $user) {
            $stats[$user["_res_role"]]++;
        }

        $usersToAssign = [];
        foreach ($this->data["users"] as $user) {
            if ($user["_res_role"] === "customer") // customers will be auto assigned
            {
                continue;
            }

            $usersToAssign[] = [
                "ID"        => $user["ID"],
                "label"     => "<strong>" . esc_html($user["display_name"]) . "</strong> (" . esc_html($user["user_email"]) . ")",
                "target_id" => $user["_target_id"] ?: 0,
            ];
        }

        ?>
        <div class="wrap" id="res-settings">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="<?php echo admin_url("options-general.php?page=" . $this->slug . "&ie_action=import-2"); ?>" method="post">
            <?php wp_nonce_field($this->plugin->prefix("ie_import_2"));?>
            <input type="hidden" name="import_id" value="<?php echo $this->id; ?>" />

            <p><?php _e('Reading file:', 'reservations');?> <code><?=$file?></code></p>

            <h2><?php _e('Import Users', 'reservations');?></h2>

            <p><?php printf(__('Total users: %d (administrators: %d, authors: %d, instructors: %d, customers: %d)', 'reservations'), $stats["all"], $stats["administrator"], $stats["author"], $stats["instructor"], $stats["customer"]);?></p>

            <h4><?php _e('Assign Users', 'reservations');?></h4>

            <p><?php _e('Customers will be automatically paired by their email and their password will remain unchanged.', 'reservations');?></p>

            <?php foreach ($usersToAssign as $user): ?>
                <label><?=$user["label"]?>
                <?php wp_dropdown_users([
            "name"            => "ie_users[" . $user["ID"] . "]",
            "selected"        => $user["target_id"],
            "multi"           => true,
            "show_option_all" => __('&mdash; Create New User &mdash;', 'reservations'),
        ]);?></label><br>
            <?php endforeach;?>

            <p class="submit">
                <button type="submit" class="button-primary"><?php _e('Confirm Import', 'reservations');?></button>
            </p>
        </form>

        <?php
}

    public function prepareImport2()
    {
        $pageUrl = admin_url("options-general.php?page=" . $this->slug);

        $this->id = (int) ($_POST['import_id'] ?? -1);

        $file = get_attached_file($this->id);

        if (!$file) {
            wp_redirect(add_query_arg("ie_error", __('The export file could not be loaded.', 'reservations'), $pageUrl));
            exit;
        }

        $this->data = json_decode(file_get_contents($file), true);
        wp_import_cleanup($this->id);

        if (!$this->data) {
            wp_redirect(add_query_arg("ie_error", sprintf(__('Failed to parse export file: %s', 'reservations'), json_last_error_msg()), $pageUrl));
            exit;
        }

        $this->autoAssignUsers();

        $manualAssignments = (array) ($_POST['ie_users'] ?? []);

        foreach ($this->data["users"] as &$user) {
            if (isset($manualAssignments[$user["ID"]])) {
                $user["_target_id"] = (int) $manualAssignments[$user["ID"]];
            }
        }

        $error = $this->importCities();
        if (!is_wp_error($error)) {
            $error = $this->importGyms();
        }
        if (!is_wp_error($error)) {
            $error = $this->importTrainingGroups();
        }
        if (!is_wp_error($error)) {
            $error = $this->importUsers();
        }
        if (!is_wp_error($error)) {
            $error = $this->importEvents();
        }
        if (!is_wp_error($error)) {
            $error = $this->importTrainings();
        }
        if (!is_wp_error($error)) {
            $error = $this->importSubscribers();
        }
        if (!is_wp_error($error)) {
            $error = $this->importSubscriptions();
        }
        if (!is_wp_error($error)) {
            $error = $this->importPayments();
        }
        if (!is_wp_error($error)) {
            $error = $this->importTransactions();
        }
        if (!is_wp_error($error)) {
            $error = $this->importOptions();
        }

        if (Reservations::DEBUG) {
            if (is_wp_error($error)) {
                var_dump($error);
            } else {
                echo "Success.";
            }
        } else {
            if (is_wp_error($error)) {
                wp_redirect(add_query_arg("ie_error", $error->get_error_message(), $pageUrl));
            } else {
                wp_redirect(add_query_arg("ie_success", __('Import successful.', 'reservations'), $pageUrl));
            }
        }

        exit;
    }

    protected $termMap         = [];
    protected $postMap         = [];
    protected $userMap         = [];
    protected $attachmentMap   = [];
    protected $subscriberMap   = [];
    protected $subscriptionMap = [];
    protected $paymentMap      = [];

    protected function importTerms($data, $taxonomy, $metaFilter = null)
    {
        if (!isset($this->termMap[$taxonomy])) {
            $this->termMap[$taxonomy] = [];
        }

        foreach ($data as $term) {
            $localTerm = get_term_by("slug", $term["slug"], $taxonomy);

            if ($localTerm) {
                $localId = $localTerm->term_id;
            } else {
                $res = wp_insert_term($term["name"], $taxonomy, ["slug" => $term["slug"]]);

                if (is_wp_error($res)) {
                    return $res;
                }

                $localId = $res["term_id"];
            }

            foreach ($term["_meta"] as $key => $value) {
                $value = $value[0];

                if ($metaFilter !== null) {
                    $value = call_user_func($metaFilter, $value, $key, $term, $localId);
                }

                $res = update_term_meta($localId, $this->plugin->prefix($key), $value);
                if (is_wp_error($res)) {
                    return $res;
                }
            }

            $res = update_term_meta($localId, $this->plugin->prefix("_import_id"), $term["term_id"]);
            if (is_wp_error($res)) {
                return $res;
            }

            $this->termMap[$taxonomy][$term["term_id"]] = $localId;
        }
    }

    protected function getLocalTermId($remoteId, $taxonomy)
    {
        return $this->termMap[$taxonomy][$remoteId] ?? null;
    }

    protected function importPosts($data, $postType, $metaFilter = null, $afterSave = null)
    {
        if (!isset($this->postMap[$postType])) {
            $this->postMap[$postType] = [];
        }

        foreach ($data as $post) {
            $localPosts = get_posts([
                "post_type"   => $postType,
                "name"        => $post["post_name"],
                "fields"      => "ids",
                "numberposts" => 1,
            ]);

            if (count($localPosts)) {
                $localId = $localPosts[0];

                // $res = wp_insert_post(Arrays::pick($post, [
                //     "post_date", "post_date_gmt", "post_content", "post_title", "post_excerpt", "post_status", "post_name", "post_modified", "post_modified_gmt", "post_type",
                // ]) + [
                //     "ID" => $localId,
                // ]);

                //  if (is_wp_error($res)) {
                //     return $res;
                // }
            } else {
                $localId = wp_insert_post(Arrays::pick($post, [
                    "post_date", "post_date_gmt", "post_content", "post_title", "post_excerpt", "post_status", "post_name", "post_modified", "post_modified_gmt", "post_type",
                ]) + [
                    "post_author" => $this->userMap[$post["post_author"]],
                ], true);

                if (is_wp_error($localId)) {
                    return $localId;
                }
            }

            foreach ($post["_meta"] as $key => $value) {
                $value = $value[0];

                if ($metaFilter !== null) {
                    $value = call_user_func($metaFilter, $value, $key, $post, $localId);
                }

                $res = update_post_meta($localId, $this->plugin->prefix($key), $value);
                if (is_wp_error($res)) {
                    return $res;
                }
            }

            foreach ($post["_terms"] as $taxonomy => $ids) {
                $newIds = [];
                foreach ($ids as $id) {
                    if (isset($this->termMap[$taxonomy][$id])) {
                        $newIds[] = $this->termMap[$taxonomy][$id];
                    }
                }

                wp_set_object_terms($localId, $newIds, $taxonomy);
            }

            if ($afterSave !== null) {
                $res = call_user_func($afterSave, $post, $localId);

                if (is_wp_error($res)) {
                    return $res;
                }
            }

            $res = update_post_meta($localId, $this->plugin->prefix("_import_id"), $post["ID"]);
            if (is_wp_error($res)) {
                return $res;
            }

            $this->postMap[$postType][$post["ID"]] = $localId;
        }
    }

    protected function getLocalPostId($remoteId, $postType)
    {
        return $this->postMap[$postType][$remoteId] ?? null;
    }

    protected function fetchRemoteFile($url)
    {
        // extract the file name and extension from the url
        $file_name = basename($url);

        // get placeholder file in the upload dir with a unique, sanitized filename
        $upload = wp_upload_bits($file_name, 0, '');
        if ($upload['error']) {
            return new WP_Error('upload_dir_error', $upload['error']);
        }

        // fetch the remote url and write it to the placeholder file
        $remote_response = wp_safe_remote_get($url, array(
            'timeout'  => 300,
            'stream'   => true,
            'filename' => $upload['file'],
        ));

        $headers = wp_remote_retrieve_headers($remote_response);

        // request failed
        if (!$headers) {
            @unlink($upload['file']);
            return new WP_Error('import_file_error', __('Remote server did not respond', 'reservations'));
        }

        $remote_response_code = wp_remote_retrieve_response_code($remote_response);

        // make sure the fetch was successful
        if ($remote_response_code != '200') {
            @unlink($upload['file']);
            return new WP_Error('import_file_error', sprintf(__('Remote server returned error response %1$d %2$s', 'reservations'), esc_html($remote_response_code), get_status_header_desc($remote_response_code)));
        }

        $filesize = filesize($upload['file']);

        if (isset($headers['content-length']) && $filesize != $headers['content-length']) {
            @unlink($upload['file']);
            return new WP_Error('import_file_error', __('Remote file is incorrect size', 'reservations'));
        }

        if (0 == $filesize) {
            @unlink($upload['file']);
            return new WP_Error('import_file_error', __('Zero size file downloaded', 'reservations'));
        }

        return $upload;
    }

    protected function downloadAttachments($attachments)
    {
        $newIds = [];
        foreach ($attachments as $key => $value) {
            if (isset($this->attachmentMap[$value["id"]])) {
                $newIds[$key] = $this->attachmentMap[$value["id"]];
                continue;
            }

            // try to find matching local attachment
            $posts = get_posts([
                "post_type"   => "attachment",
                "meta_key"    => $this->plugin->prefix("_import_url"),
                "meta_value"  => $value["url"],
                "numberposts" => 1,
                "fields"      => "ids",
            ]);

            if (count($posts)) {
                $newIds[$key]                      = $posts[0];
                $this->attachmentMap[$value["id"]] = $posts[0];
                continue;
            }

            // download attachment and create new post
            $upload = $this->fetchRemoteFile($value["url"]);
            if (is_wp_error($upload)) {
                return $upload;
            }

            $post = [
                "post_title"   => pathinfo($upload["file"], PATHINFO_FILENAME),
                "post_content" => "",
            ];

            if ($info = wp_check_filetype($upload["file"])) {
                $post['post_mime_type'] = $info['type'];
            } else {
                return new WP_Error('attachment_processing_error', __('Invalid file type', 'reservations'));
            }

            // as per wp-admin/includes/upload.php
            $localId = wp_insert_attachment($post, $upload["file"], 0, true);

            if (is_wp_error($localId)) {
                return $localId;
            }

            wp_update_attachment_metadata($localId, wp_generate_attachment_metadata($localId, $upload["file"]));

            update_post_meta($localId, $this->plugin->prefix("_import_url"), $value["url"]);

            $this->attachmentMap[$value["id"]] = $localId;
            $newIds[$key]                      = $localId;
        }

        return $newIds;
    }

    protected function unserializeCarbon($data)
    {
        return new Carbon($data["date"], $data["timezone"]);
    }

    protected function importCities()
    {
        return $this->importTerms(
            $this->data["cities"],
            Taxonomies\City::NAME);
    }

    protected function importGyms()
    {
        return $this->importTerms(
            $this->data["gyms"],
            Taxonomies\Gym::NAME,
            function ($value, $key, $term, $localId) {
                switch ($key) {
                    case "city_id":
                        $value = $this->getLocalTermId($value, Taxonomies\City::NAME);
                        break;

                    case "term_periods":
                        $value = null; // deprecated attribute
                        break;
                }

                return $value;
            });
    }

    protected function importTrainingGroups()
    {
        return $this->importTerms(
            $this->data["training_groups"],
            Taxonomies\TrainingGroup::NAME,
            function ($value, $key, $term, $localId) {
                switch ($key) {
                    case "term_periods":
                        foreach ($value as &$period) {
                            if (!is_array($period)) // null
                            {
                                continue;
                            }

                            $period[0] = $this->unserializeCarbon($period[0]);
                            $period[1] = $this->unserializeCarbon($period[1]);
                        }
                        break;
                    case "year":
                        if (is_array($value)) {
                            $value[0] = $this->unserializeCarbon($value[0]);
                            $value[1] = $this->unserializeCarbon($value[1]);
                        }
                        break;
                    case "attachment_sets":
                        foreach ($value as &$set) {
                            $set = $this->downloadAttachments($set);
                        }
                        break;
                }

                return $value;
            });
    }

    private $currentImportUserPass = null;

    protected function importUsers()
    {
        foreach ($this->data["users"] as $user) {
            if (!$user["_target_id"]) {
                // create new user
                $currentImportUserPass = $user["user_pass"];
                $localId               = wp_insert_user([
                    "user_login"      => $user["user_login"],
                    "user_pass"       => "", // plaintext password
                    "user_nicename"   => $user["user_nicename"],
                    "user_email"      => $user["user_email"],
                    "user_registered" => $user["user_registered"],
                    "first_name"      => $user["_first_name"],
                    "last_name"       => $user["_last_name"],
                    "display_name"    => $user["display_name"],
                ]);
                $currentImportUserPass = null;

                if (is_wp_error($localId)) {
                    return $localId;
                }
            } else {
                $localId = $user["_target_id"];
            }

            foreach ($user["_meta"] as $key => $value) {
                $value = $value[0];

                switch ($key) {
                    case "accessible_city_ids":
                        $newIds = [];
                        foreach ($value as $id) {
                            $newId = $this->getLocalTermId($id, Taxonomies\City::NAME);
                            if ($newId) {
                                $newIds[] = $newId;
                            }

                        }
                        $value = $newIds;
                        break;
                }

                $res = update_user_meta($localId, $this->plugin->prefix($key), $value);

                if (is_wp_error($res)) {
                    return $res;
                }
            }

            $res = update_user_meta($localId, $this->plugin->prefix("_import_id"), $user["ID"]);

            if (is_wp_error($res)) {
                return $res;
            }

            $this->userMap[$user["ID"]] = $localId;
        }
    }

    /** @filter(wp_pre_insert_user_data) */
    public function filterNewUserData($data)
    {
        if ($this->currentImportUserPass) {
            $data["user_pass"] = $this->currentImportUserPass;
        }
        // keep old user password
        return $data;
    }

    protected function importEvents()
    {
        return $this->importPosts(
            $this->data["events"],
            PostTypes\Event::NAME,
            function ($value, $key, $post, $localId) {
                switch ($key) {
                    case "contact_instructor_id":
                        $value = $this->userMap[$value] ?? null;
                        break;
                    case "attachment_sets":
                        foreach ($value as &$set) {
                            $set = $this->downloadAttachments($set);
                        }
                        break;
                }

                return $value;
            }, function ($post, $localId) {
                $model     = new Models\Event;
                $model->ID = $localId;

                $newIds = [];
                foreach ($post["_instructors"] as $id) {
                    $newId = $this->userMap[$id] ?? null;
                    if ($newId) {
                        $newIds[] = $newId;
                    }

                }

                $model->instructors()->sync($newIds);
            });
    }

    protected function importTrainings()
    {
        return $this->importPosts(
            $this->data["trainings"],
            PostTypes\Training::NAME,
            function ($value, $key, $term, $localId) {
                switch ($key) {
                    case "contact_instructor_id":
                        $value = $this->userMap[$value] ?? null;
                        break;
                }

                return $value;
            }, function ($post, $localId) {
                $model     = new Models\Training;
                $model->ID = $localId;

                $newIds = [];
                foreach ($post["_instructors"] as $id) {
                    $newId = $this->userMap[$id] ?? null;
                    if ($newId) {
                        $newIds[] = $newId;
                    }
                }

                $model->instructors()->sync($newIds);
            });
    }

    protected function importSubscribers()
    {
        foreach ($this->data["subscribers"] as $subscriber) {
            $localSubscriber = Models\Subscriber::where("hash", $subscriber["hash"])->first();

            if (!$localSubscriber) {
                $localSubscriber = new Models\Subscriber();
            }

            $remoteId = $subscriber["subscriber_id"];
            unset($subscriber["subscriber_id"]);

            if ($subscriber["user_id"]) {
                $subscriber["user_id"] = $this->userMap[$subscriber["user_id"]] ?? null;
            }

            $localSubscriber->forceFill($subscriber);
            $localSubscriber->save();

            $this->subscriberMap[$remoteId] = $localSubscriber->subscriber_id;
        }
    }

    protected function importSubscriptions()
    {
        foreach ($this->data["subscriptions"] as $subscription) {
            $localSubscription = Models\Subscription::where("hash", $subscription["hash"])->first();

            if (!$localSubscription) {
                $localSubscription = new Models\Subscription();
            }

            $remoteId = $subscription["subscription_id"];
            unset($subscription["subscription_id"]);

            $subscription["date_from"] = $this->unserializeCarbon($subscription["date_from"]);
            $subscription["date_to"]   = $this->unserializeCarbon($subscription["date_to"]);

            if ($subscription["subscriber_id"]) {
                $subscription["subscriber_id"] = $this->subscriberMap[$subscription["subscriber_id"]] ?? null;
            }

            if ($subscription["event_id"]) {
                $subscription["event_id"] = $this->getLocalPostId($subscription["event_id"], PostTypes\Event::NAME);
            }

            if ($subscription["tgroup_id"]) {
                $subscription["tgroup_id"] = $this->getLocalTermId($subscription["tgroup_id"], Taxonomies\TrainingGroup::NAME);
            }

            // if(!$subscription["event_id"] && !$subscription["tgroup_id"]) {
            //     continue;
            // }

            if (is_array($subscription["age_group"])) {
                $subscription["age_group"] = $subscription["age_group"]["id"];
            }

            if ($subscription["gym_id"]) {
                $subscription["gym_id"] = null; // deprecated
            }

            $localSubscription->forceFill($subscription);
            $localSubscription->save();

            $this->subscriptionMap[$remoteId] = $localSubscription->subscription_id;
        }
    }

    protected function importPayments()
    {
        foreach ($this->data["payments"] as $payment) {
            $localPayment = Models\Payment::where("hash", $payment["hash"])->first();

            if (!$localPayment) {
                $localPayment = new Models\Payment();
            } else {
                $localPayment->transactions()->delete(); // transactions don't have hashes, drop them if we are updating an existing payment to prevent duplicates
            }

            $remoteId = $payment["payment_id"];
            unset($payment["payment_id"]);

            if ($payment["subscription_id"]) {
                if (!isset($this->subscriptionMap[$payment["subscription_id"]])) {
                    continue;
                }

                $payment["subscription_id"] = $this->subscriptionMap[$payment["subscription_id"]] ?? null;
            }

            $localPayment->forceFill($payment);
            $localPayment->save();

            $this->paymentMap[$remoteId] = $localPayment->payment_id;
        }
    }

    protected function importTransactions()
    {
        $toInsert = [];
        foreach ($this->data["transactions"] as $transaction) {
            unset($transaction["transaction_id"]);

            if ($transaction["payment_id"]) {
                $transaction["payment_id"] = $this->paymentMap[$transaction["payment_id"]] ?? null;
            }

            if (!$transaction["payment_id"]) {
                continue;
            }

            $toInsert[] = $transaction;
        }

        Models\Transaction::insert($toInsert);
    }

    protected function isPageIdOption($key)
    {
        if ($key === "trainings_page_id" || $key === "events_page_id") {
            return true;
        }

        foreach (Models\Local\EventType::all() as $eventType) {
            if ($key === $eventType["slug"] . "_page_id") {
                return true;
            }

        }

        return false;
    }

    protected function importOptions()
    {
        foreach ($this->data["options"] as $key => $value) {
            if ($this->isPageIdOption($key)) {
                if ($this->plugin->getOption($key)) {
                    continue;
                }

                $value = null; // let PageRouter generate new page
            } else if ($key === "message_templates") {
                $newTemplates = []; // @TODO: this overwrites all message templates, index them by hash instead of id
                foreach ($value as $template) {
                    $template["attachments"] = $this->downloadAttachments($template["attachments"]);

                    $newTemplates[$template["_id"]] = $template;
                    unset($template["_id"]);
                }
                $value = $newTemplates;
            }

            $this->plugin->updateOption($key, $value);
        }
    }

    public function renderImport2()
    {

    }

    /** @action(admin_notices) */
    public function displayAdminNotices()
    {
        $error = $_GET['ie_error'] ?? "";

        ?>
        <?php if ($error): ?>
            <div class="res-notice notice-error">
                <p><?=esc_html($error)?></p>
            </div>
        <?php endif;?>
    <?php }
}
