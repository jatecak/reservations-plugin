<?php

namespace Reservations\PostTypes;

use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\Taxonomies;
use Reservations\Utils;

class Training extends Base\PostType
{
    const NAME = "training";

    /** @action(init) */
    public function register()
    {
        \register_post_type(self::NAME, [
            "labels"               => [
                'name'               => _x('Trainings', 'post type general name', 'reservations'),
                'singular_name'      => _x('Training', 'post type singular name', 'reservations'),
                'menu_name'          => _x('Trainings', 'admin menu', 'reservations'),
                'name_admin_bar'     => _x('Training', 'add new on admin bar', 'reservations'),
                'add_new'            => _x('Add New', 'training', 'reservations'),
                'add_new_item'       => __('Add New Training', 'reservations'),
                'new_item'           => __('New Training', 'reservations'),
                'edit_item'          => __('Edit Training', 'reservations'),
                'view_item'          => __('View Training', 'reservations'),
                'all_items'          => __('All Trainings', 'reservations'),
                'search_items'       => __('Search Trainings', 'reservations'),
                'parent_item_colon'  => __('Parent Trainings:', 'reservations'),
                'not_found'          => __('No trainings found.', 'reservations'),
                'not_found_in_trash' => __('No trainings found in Trash.', 'reservations'),
            ],
            "public"               => true,
            "supports"             => ["title"],
            "has_archive"          => true,
            "register_meta_box_cb" => [$this, "addMetaBoxes"],
        ]);
    }

    /** @action(restrict_manage_posts) */
    public function displayFilters()
    {
        $screen = get_current_screen();

        if ($screen->post_type !== self::NAME) {
            return;
        }

        $gyms       = Models\Gym::accessible()->sortByName()->get();
        $currentGym = null;

        if (isset($_GET[Taxonomies\Gym::NAME])) {
            $gym = $gyms->where("slug", (string) $_GET[Taxonomies\Gym::NAME])->first();

            if ($gym) {
                $currentGym = $gym->id;
            }

        }

        if (!$currentGym && isset($_GET['training_filter_gym'])) {
            $currentGym = (int) $_GET['training_filter_gym'];
        }

        $gymSelect = Utils\Html::getGymTreeSelect($gyms, $currentGym);

        $tgroups       = Models\TrainingGroup::accessible()->sortByName()->get();
        $currentTgroup = null;

        if (isset($_GET[Taxonomies\TrainingGroup::NAME])) {
            $tgroup = $tgroups->where("slug", (string) $_GET[Taxonomies\TrainingGroup::NAME])->first();

            if ($tgroup) {
                $currentTgroup = $tgroup->id;
            }

        }

        if (!$currentTgroup && isset($_GET['training_filter_tgroup'])) {
            $currentTgroup = (int) $_GET['training_filter_tgroup'];
        }

        $tgroupSelect = Utils\Html::getTrainingGroupSelect($tgroups, $currentTgroup);
        ?>
        <select name="training_filter_gym">
            <option value=""><?php _e('&mdash; Gym &mdash;', 'reservations');?></option>

            <?=$gymSelect?>
        </select>
        <select name="training_filter_tgroup">
            <option value=""><?php _e('&mdash; Training Group &mdash;', 'reservations');?></option>

            <?=$tgroupSelect?>
        </select>
        <?php
}

    /** @filter(parse_query) */
    public function applyFilters($query)
    {
        if (!is_admin() || !function_exists("get_current_screen")) {
            return $query;
        }

        $screen = get_current_screen();

        if (is_null($screen) || $screen->base !== "edit" || $screen->post_type !== self::NAME) {
            return $query;
        }

        // $accessibleCities = Models\City::accessible()->get()->pluck("id");

        // $query->query_vars["tax_query"][] = [
        //     "taxonomy" => Taxonomies\City::NAME,
        //     "terms"    => $accessibleCities->all(),
        // ];

        if (isset($_GET['training_filter_gym']) && !empty($_GET['training_filter_gym'])) {
            $query->query_vars["tax_query"][] = [
                "taxonomy" => Taxonomies\Gym::NAME,
                "terms"    => (int) $_GET['training_filter_gym'],
            ];
        }

        if (isset($_GET['training_filter_tgroup']) && !empty($_GET['training_filter_tgroup'])) {
            $query->query_vars["tax_query"][] = [
                "taxonomy" => Taxonomies\TrainingGroup::NAME,
                "terms"    => (int) $_GET['training_filter_tgroup'],
            ];
        }

        return $query;
    }

    public function addMetaBoxes()
    {
        add_meta_box("training-time-location", __('Time and Location', 'reservations'), [$this, "displayTimeLocationMetaBox"], null, "normal");
        add_meta_box("training-instructors-contact", __('Instructors and Contact', 'reservations'), [$this, "displayInstructorsContactMetaBox"], null, "normal");
        add_meta_box("training-additional-info", __('Additional Info', 'reservations'), [$this, "displayAdditionalInfoMetaBox"], null, "normal");
    }

    public function displayTimeLocationMetaBox()
    {
        global $post;

        $trainingModel = Models\Training::find($post->ID);

        $gyms = Models\Gym::accessible()->sortByName()->get();
        $gym  = $trainingModel->gym();

        $gymSelect = Utils\Html::getGymTreeSelect($gyms, $gym ? $gym->id : null);

        $timeslots = collect($trainingModel->timeslots)->map(function ($slot) {
            return [
                "weekday"    => $slot["weekday"],
                "start_time" => Utils::formatTime($slot["start_time"]),
                "end_time"   => Utils::formatTime($slot["end_time"]),
            ];
        })->toArray();

        $weekdays = Utils::getWeekdayPairs();

        wp_nonce_field("training_save", "training_nonce");

        ?>
        <?php if (Reservations::MODE === "lead"): ?>
            <div class="form-wrap res-timeslots-wrap res-wrap">
                <h3><?php _e('Timeslots', 'reservations');?></h3>

                <table class="timeslots">
                    <thead><tr>
                        <th><?php _e('Day of week', 'reservations');?></th>
                        <th><?php _e('Start time', 'reservations');?></th>
                        <th><?php _e('End time', 'reservations');?></th>
                        <th></th>
                    </tr></thead>

                    <tbody>
                        <?php foreach ($timeslots as $i => $slot): ?>
                            <tr>
                                <td>
                                    <select class="res-inline" name="training_meta[timeslots][<?=$i?>][weekday]">
                                        <?=Utils\Html::getSelect($weekdays, $slot["weekday"])?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="res-inline" name="training_meta[timeslots][<?=$i?>][start_time]" placeholder="00:00" value="<?=$slot["start_time"]?>">
                                </td>
                                <td>
                                    <input type="text" class="res-inline" name="training_meta[timeslots][<?=$i?>][end_time]" placeholder="00:00" value="<?=$slot["end_time"]?>">
                                </td>
                                <td>
                                    <?php if ($i !== 0): ?>
                                        <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a>
                                    <?php endif;?>
                                </td>
                            </tr>
                        <?php endforeach;?>

                        <tr class="res-template">
                            <td>
                                <select class="res-inline" name="training_meta[timeslots_tpl][][weekday]">
                                    <?=Utils\Html::getSelect($weekdays)?>
                                </select>
                            </td>
                            <td>
                                <input type="text" class="res-inline" name="training_meta[timeslots_tpl][][start_time]" placeholder="00:00" value="">
                            </td>
                            <td>
                                <input type="text" class="res-inline" name="training_meta[timeslots_tpl][][end_time]" placeholder="00:00" value="">
                            </td>
                            <td>
                                <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <button class="button res-add-timeslot" type="button"><?php _e('Add timeslot', 'reservations');?></button>
            </div>
        <?php endif;?>

        <div class="form-wrap">
            <?php if (Reservations::MODE !== "lead"): ?>
                <div class="form-field training-weekday-wrap">
                    <label for="training-weekday"><?php _e('Day of week', 'reservations');?></label>
                    <select name="training_meta[timeslots][0][weekday]" id="training-weekday">
                        <?=Utils\Html::getSelect($weekdays, $timeslots[0]["weekday"])?>
                    </select>
                </div>
                <div class="form-field training-start-time-wrap">
                    <label for="training-start-time"><?php _e('Start time', 'reservations');?></label>
                    <input type="text" name="training_meta[timeslots][0][start_time]" id="training-start-time" placeholder="00:00" value="<?=$timeslots[0]["start_time"]?>">
                </div>
                <div class="form-field training-end-time-wrap">
                    <label for="training-end-time"><?php _e('End time', 'reservations');?></label>
                    <input type="text" name="training_meta[timeslots][0][end_time]" id="training-end-time" placeholder="00:00" value="<?=$timeslots[0]["end_time"]?>">
                </div>
            <?php endif;?>

            <div class="form-field training-gym-id-wrap">
                <label for="training-gym-id"><?php _e('Location', 'reservations');?></label>
                <select name="training_meta[gym_id]" id="training-gym-id">
                    <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
                    <?=$gymSelect?>
                </select>
            </div>
        </div>
        <?php
}

    public function displayInstructorsContactMetaBox()
    {
        global $post;

        $instructors          = Models\Training::find($post->ID)->instructors()->get();
        $availableInstructors = Models\Instructor::all()->filter(function ($avail) use ($instructors) {
            return !$instructors->contains(function ($used) use ($avail) {
                return $used->id === $avail->id;
            });
        });

        $trainingModel = Models\Training::find($post->ID);
        $meta          = $trainingModel->getPrefixedMetaBulk([
            "contact_email", "contact_phone", "contact_instructor_id",
        ]);

        $values = [
            "contact_email" => esc_attr($meta["contact_email"]),
            "contact_phone" => esc_attr($meta["contact_phone"]),
        ];

        $ids = [];
        foreach ($instructors as $instructor) {
            $ids[] = $instructor->id;
        }
        $ids = esc_attr(implode(",", $ids));

        ?>
        <div class="form-wrap res-instructors-wrap res-wrap">
            <h3><?php _e('Instructors', 'reservations');?></h3>
            <ul data-delete-text="<?=esc_attr(__('Delete', 'reservations'))?>" data-no-instructors-text="<?=esc_attr(__('No instructors.', 'reservations'))?>">
                <?php if (!count($instructors)): ?>
                    <li class="no-instructors"><?php _e('No instructors.', 'reservations')?></li>
                <?php endif;?>

                <?php foreach ($instructors as $instructor): ?>
                    <li data-id="<?=esc_attr($instructor->id)?>"><?=esc_html($instructor->displayName)?> <a href="#" class="delete"><?php _e('Delete', 'reservations');?></a></li>
                <?php endforeach;?>
            </ul>
            <label for="res-instructors-add"><?php _ex('Add New:', 'instructor', 'reservations');?></label>
            <select id="res-instructors-add">
                <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
                <?php foreach ($availableInstructors as $instructor): ?>
                    <option value="<?=esc_attr($instructor->id)?>"><?=esc_attr($instructor->displayName)?></option>
                <?php endforeach;?>
            </select>
            <input type="hidden" name="training_meta[instructor_ids]" id="res-instructor-ids" value="<?=$ids?>">

            <h3><?php _e('Contact Info', 'reservations');?></h3>
            <div class="form-field res-contact-instructor-id-wrap">
                <label for="res-contact-instructor-id"><?php _e('Select Responsible Instructor', 'reservations');?></label>
                <select name="training_meta[contact_instructor_id]" id="res-contact-instructor-id">
                    <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
                    <?php foreach ($instructors as $instructor): ?>
                        <option value="<?=esc_attr($instructor->id)?>"<?php selected($meta["contact_instructor_id"], $instructor->id);?>><?=esc_attr($instructor->displayName)?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <p class="or"><?php _e('&mdash; or &mdash;', 'reservations');?>
            <div class="form-field training-contact-email-wrap">
                <label for="training-contact-email"><?php _e('Contact Email', 'reservations');?></label>
                <input type="email" name="training_meta[contact_email]" id="training-contact-email" value="<?=$values["contact_email"]?>">
            </div>
            <div class="form-field training-contact-phone-wrap">
                <label for="training-contact-phone"><?php _e('Contact Phone', 'reservations');?></label>
                <input type="tel" name="training_meta[contact_phone]" id="training-contact-phone" value="<?=$values["contact_phone"]?>">
            </div>
        </div>
        <?php
}

    public function displayAdditionalInfoMetaBox()
    {
        global $post;

        // $categories = get_terms([
        //     "taxonomy"   => "training_category",
        //     "hide_empty" => false,
        // ]);

        $training = Models\Training::find($post->ID);
        $meta     = $training->getPrefixedMetaBulk([
            "age_group", "description", "price_single",
        ]);

        $ageGroups = Utils::getAgeGroupSelect(null, $meta["age_group"]);

        $tgroups       = Models\TrainingGroup::accessible()->sortByName()->get();
        $currentTgroup = $training->trainingGroup();

        $trainingGroups = Utils\Html::getTrainingGroupSelect($tgroups, $currentTgroup ? $currentTgroup->id : null);

        $values = [
            "price_single" => esc_attr($meta["price_single"]),
        ];

        ?>
         <div class="form-wrap">
            <div class="form-field training-age-group-wrap">
                <label for="training-age-group"><?php _e('Age Group', 'reservations');?></label>
                <select name="training_meta[age_group]" id="training-age-group">
                    <?=$ageGroups?>
                </select>
            </div>
            <div class="form-field training-tgroup-wrap">
                <label for="training-tgroup"><?php _e('Training Group', 'reservations');?></label>
                <select name="training_meta[tgroup]" id="training-tgroup">
                    <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
                    <?=$trainingGroups?>
                </select>
            </div>
            <div class="form-field training-price-single-wrap">
                <label for="training-price-single"><?php _e('Price', 'reservations');?></label>
                <input type="number" name="training_meta[price_single]" id="training-price-single" value="<?=$values["price_single"]?>"> <?php _e('US$', 'reservations');?>
                <p class="description"><?php _e('Price of single training. If left empty, price from training group info will be used.', 'reservations');?></p>
            </div>
            <div class="form-field training-description-wrap">
                <label for="training-description"><?php _e('Description (optional)', 'reservations');?></label>
                <?php wp_editor($meta["description"], "training_description", [
            "textarea_name" => "training_meta[description]",
            "textarea_rows" => 4,
            "media_buttons" => false,
            "teeny"         => true,
            "quicktags"     => false,
        ]);?>
            </div>
        </div>
        <?php
}

    /** @action(save_post_training) */
    public function saveTraining($trainingId)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST['training_nonce']) || !wp_verify_nonce($_POST['training_nonce'], "training_save")) {
            return;
        }

        if (!current_user_can("edit_post", $trainingId)) {
            return;
        }

        if (!isset($_POST["training_meta"])) {
            return;
        }

        $meta = $_POST["training_meta"];

        if (!Utils::allSet($meta, [
            "gym_id", "contact_instructor_id", "instructor_ids", "contact_email", "contact_phone", "age_group", "description", "price_single", "timeslots", "tgroup",
        ])) {
            return;
        }

        $trainingModel = Models\Training::find($trainingId);

        if (Utils::allSet($meta, ["weekday", "start_time", "end_time"])) {
            $trainingModel->setPrefixedMetaBulk([
                "weekday"    => (int) $meta["weekday"],
                "start_time" => Utils::sanitizeTime(sanitize_text_field($meta["start_time"])),
                "end_time"   => Utils::sanitizeTime(sanitize_text_field($meta["end_time"])),
            ]);
        }

        $gym_id = !empty($meta["gym_id"]) ? (int) $meta["gym_id"] : null;

        if ($gym_id !== null) {
            $city_id = (int) get_term_meta($gym_id, Reservations::PREFIX . "city_id", true);

            wp_set_object_terms($trainingId, [$gym_id], Taxonomies\Gym::NAME);
            wp_set_object_terms($trainingId, [$city_id], Taxonomies\City::NAME);
        } else {
            wp_set_object_terms($trainingId, [], Taxonomies\Gym::NAME);
            wp_set_object_terms($trainingId, [], Taxonomies\City::NAME);
        }

        $tgroup_id = !empty($meta["tgroup"]) ? (int) $meta["tgroup"] : null;

        if ($tgroup_id !== null) {
            wp_set_object_terms($trainingId, [$tgroup_id], Taxonomies\TrainingGroup::NAME);
        } else {
            wp_set_object_terms($trainingId, [], Taxonomies\TrainingGroup::NAME);
        }

        $instructors = empty($meta["instructor_ids"]) ? [] : array_map(function ($id) {
            return (int) $id;
        }, explode(",", $meta["instructor_ids"]));

        $trainingModel->instructors()->sync($instructors);

        $contact_instructor = !empty($meta["contact_instructor_id"]) ? (int) $meta["contact_instructor_id"] : null;

        if ($contact_instructor !== null && in_array($contact_instructor, $instructors)) {
            $trainingModel->setPrefixedMetaBulk([
                "contact_instructor_id" => $contact_instructor,
                "contact_email"         => "",
                "contact_phone"         => "",
            ]);
        } else {
            $trainingModel->setPrefixedMetaBulk([
                "contact_instructor_id" => null,
                "contact_email"         => sanitize_text_field($meta["contact_email"]),
                "contact_phone"         => sanitize_text_field($meta["contact_phone"]),
            ]);
        }

        // Timeslots

        $timeslots = is_array($meta["timeslots"]) ? $meta["timeslots"] : [];
        $timeslots = collect($timeslots)->map(function ($timeslot) {
            $timeslot = Utils::defaults($timeslot, [
                "weekday"    => "0",
                "start_time" => "00:00",
                "end_time"   => "00:00",
            ]);

            $timeslot["weekday"]    = min(6, max(0, (int) $timeslot["weekday"]));
            $timeslot["start_time"] = Utils::parseTime($timeslot["start_time"]);
            $timeslot["end_time"]   = Utils::parseTime($timeslot["end_time"]);

            return $timeslot;
        })->reject(function ($timeslot) {
            return $timeslot["start_time"] >= $timeslot["end_time"];
        })->toArray();

        $newTimeslots = [];

        foreach ($timeslots as $ts) {
            $found = false;
            foreach ($newTimeslots as $nts) {
                if ($ts["weekday"] === $nts["weekday"] && $nts["end_time"] >= $ts["start_time"] && $nts["start_time"] <= $ts["end_time"]) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                continue;
            }

            $newTimeslots[] = $ts;
        }

        $trainingModel->setPrefixedMeta("timeslots", $newTimeslots);

        $trainingModel->setPrefixedMetaBulk([
            "age_group"    => (int) $meta["age_group"],
            "price_single" => intval(sanitize_text_field($meta["price_single"])),
            "description"  => wp_kses_post($meta["description"]),
        ]);
    }
}
