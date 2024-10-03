<?php

namespace Reservations\PostTypes;

use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\PostTypes;
use Reservations\Taxonomies;
use Reservations\Utils;

class Event extends Base\PostType
{
    const NAME = Reservations::PREFIX . "event";

    /** @action(init) */
    public function register()
    {
        register_post_type(self::NAME, [
            "labels"               => [
                'name'               => _x('Events', 'post type general name', 'reservations'),
                'singular_name'      => _x('Event', 'post type singular name', 'reservations'),
                'menu_name'          => _x('Events', 'admin menu', 'reservations'),
                'name_admin_bar'     => _x('Event', 'add new on admin bar', 'reservations'),
                'add_new'            => _x('Add New', 'event', 'reservations'),
                'add_new_item'       => __('Add New Event', 'reservations'),
                'new_item'           => __('New Event', 'reservations'),
                'edit_item'          => __('Edit Event', 'reservations'),
                'view_item'          => __('View Event', 'reservations'),
                'all_items'          => __('All Events', 'reservations'),
                'search_items'       => __('Search Events', 'reservations'),
                'parent_item_colon'  => __('Parent Events:', 'reservations'),
                'not_found'          => __('No events found.', 'reservations'),
                'not_found_in_trash' => __('No events found in Trash.', 'reservations'),
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

        $currentCity = isset($_GET['event_filter_city']) ? (int) $_GET['event_filter_city'] : "";
        $currentType = $_GET['event_filter_type'] ?? "";

        $cities     = Utils\Html::getCitySelect(Models\City::used(self::NAME)->get(), $currentCity);
        $eventTypes = Utils\Html::getEventTypeSelect(null, $currentType);

        ?>
        <select name="event_filter_type">
            <option value=""><?php _e('&mdash; Event Type &mdash;', 'reservations');?></option>
            <?=$eventTypes?>
        </select>
        <select name="event_filter_city">
            <option value=""><?php _e('&mdash; City &mdash;', 'reservations');?></option>
            <?=$cities?>
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

        if (isset($_GET['event_filter_city']) && !empty($_GET['event_filter_city'])) {
            $query->query_vars["tax_query"][] = [
                "taxonomy" => Taxonomies\City::NAME,
                "terms"    => (int) $_GET['event_filter_city'],
            ];
        }

        if (isset($_GET['event_filter_type']) && !empty($_GET['event_filter_type'])) {
            $query->query_vars["meta_query"][] = [
                "key"   => $this->plugin->prefix("event_type"),
                "value" => $_GET['event_filter_type'],
            ];
        }

        return $query;
    }

    public function addMetaBoxes()
    {
        add_meta_box("event-time-location", __('Time and Location', 'reservations'), [$this, "displayTimeLocationMetaBox"], null, "normal");
        add_meta_box("event-instructors-contact", __('Instructors and Contact', 'reservations'), [$this, "displayInstructorsContactMetaBox"], null, "normal");
        add_meta_box("event-additional-info", __('Additional Info', 'reservations'), [$this, "displayAdditionalInfoMetaBox"], null, "normal");
    }

    public function displayTimeLocationMetaBox()
    {
        global $post;

        $event = Models\Event::find($post->ID);

        $dateFrom = $event->dateFrom ?: Utils::today();
        $dateTo   = $event->dateTo ?: $dateFrom;

        $values = [
            "date_from"  => esc_attr($dateFrom->format("d. m. Y")),
            "date_to"    => esc_attr($dateTo->format("d. m. Y")),
            "address"    => esc_html($event->address),
            "start_time" => esc_attr(Utils::formatTime($event->startTime)),
            "end_time"   => esc_attr(Utils::formatTime($event->endTime)),
        ];

        $cityId = $event->city() ? $event->city()->id : null;
        $cities = Utils\Html::getCitySelect(null, $cityId);

        wp_nonce_field("event_save", "event_nonce");

        ?>
        <div class="form-wrap">
            <div class="form-field event-date-from-wrap">
                <label for="event-date-from"><?php _e('Date From', 'reservations');?></label>
                <input type="text" name="event_meta[date_from]" id="event-date-from" value="<?=$values["date_from"]?>">
            </div>
            <div class="form-field event-start-time-wrap">
                <label for="event-start-time"><?php _e('Start Time', 'reservations');?></label>
                <input type="text" name="event_meta[start_time]" id="event-start-time" placeholder="00:00" value="<?=$values["start_time"]?>">
            </div>
            <div class="form-field event-date-to-wrap">
                <label for="event-date-to"><?php _e('Date To', 'reservations');?></label>
                <input type="text" name="event_meta[date_to]" id="event-date-to" value="<?=$values["date_to"]?>">
            </div>
            <div class="form-field event-end-time-wrap">
                <label for="event-end-time"><?php _e('End Time', 'reservations');?></label>
                <input type="text" name="event_meta[end_time]" id="event-end-time" placeholder="00:00" value="<?=$values["end_time"]?>">
            </div>
            <div class="form-field event-city-id-wrap">
                <label for="event-city-id"><?php _e('City', 'reservations');?></label>
                <select name="event_meta[city_id]" id="event-city-id">
                    <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
                    <?=$cities?>
                </select>
            </div>
            <div class="form-field event-address-wrap">
                <label for="event-address"><?php _e('Address', 'reservations');?></label>
                <textarea name="event_meta[address]" id="event-address" rows="4" cols="45"><?=$values["address"]?></textarea>
            </div>
        </div>
        <?php
}

    public function displayInstructorsContactMetaBox()
    {
        global $post;

        $instructors          = Models\Event::find($post->ID)->instructors()->get();
        $availableInstructors = Models\Instructor::all()->filter(function ($avail) use ($instructors) {
            return !$instructors->contains(function ($used) use ($avail) {
                return $used->id === $avail->id;
            });
        });

        $event = Models\Event::find($post->ID);
        $meta  = $event->getPrefixedMetaBulk([
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
            <label for="rse-instructors-add"><?php _ex('Add New:', 'instructor', 'reservations');?></label>
            <select id="res-instructors-add">
                <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
                <?php foreach ($availableInstructors as $instructor): ?>
                    <option value="<?=esc_attr($instructor->id)?>"><?=esc_attr($instructor->displayName)?></option>
                <?php endforeach;?>
            </select>
            <input type="hidden" name="event_meta[instructor_ids]" id="res-instructor-ids" value="<?=$ids?>">

            <h3><?php _e('Contact Info', 'reservations');?></h3>
            <div class="form-field res-contact-instructor-id-wrap">
                <label for="res-contact-instructor-id"><?php _e('Select Responsible Instructor', 'reservations');?></label>
                <select name="event_meta[contact_instructor_id]" id="res-contact-instructor-id">
                    <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
                    <?php foreach ($instructors as $instructor): ?>
                        <option value="<?=esc_attr($instructor->id)?>"<?php selected($meta["contact_instructor_id"], $instructor->id);?>><?=esc_attr($instructor->displayName)?></option>
                    <?php endforeach;?>
                </select>
            </div>
            <p class="or"><?php _e('&mdash; or &mdash;', 'reservations');?>
            <div class="form-field event-contact-email-wrap">
                <label for="event-contact-email"><?php _e('Contact Email', 'reservations');?></label>
                <input type="email" name="event_meta[contact_email]" id="event-contact-email" value="<?=$values["contact_email"]?>">
            </div>
            <div class="form-field event-contact-phone-wrap">
                <label for="event-contact-phone"><?php _e('Contact Phone', 'reservations');?></label>
                <input type="tel" name="event_meta[contact_phone]" id="event-contact-phone" value="<?=$values["contact_phone"]?>">
            </div>
        </div>
        <?php
}

    public function displayAdditionalInfoMetaBox()
    {
        global $post;

        // $categories = get_terms([
        //     "taxonomy"   => "event_category",
        //     "hide_empty" => false,
        // ]);

        $event = Models\Event::find($post->ID);

        $meta = $event->getPrefixedMetaBulk([
            "age_group", "description", "event_type", "price", "capacity", "files", "password", "event_type", "subscription_enabled", "custom_subscribe_url",
        ]);

        $eventTypeSelect = Utils\Html::getEventTypeSelect(null, $event->eventType["id"]);
        $campTypeSelect  = Utils\Html::getSelect(TranslatableEnums::campTypesUcFirst(), $event->campType);

        $paymentTemplates = $event->paymentTemplates;

        if (!count($paymentTemplates)) {
            $paymentTemplates[] = [
                "id"     => 0,
                "amount" => 0,
                "hash"   => "",
            ];
        }

        $attachmentSets = $event->attachmentSets;

        if (!count($attachmentSets)) {
            $attachmentSets[] = [];
        }

        ?>
         <div class="form-wrap res-wrap">
            <div class="form-field event-type-wrap">
                <label for="event-type"><?php _e('Event Type', 'reservations');?></label>
                <select name="event_meta[event_type]" id="event-type">
                    <?=$eventTypeSelect?>
                </select>
            </div>
            <?php if ($this->plugin->isFeatureEnabled("camp_type")): ?>
                <div class="form-field camp-type-wrap">
                    <label for="camp-type"><?php _e('Camp Type', 'reservations');?></label>
                    <select name="event_meta[camp_type]" id="camp-type">
                        <?=$campTypeSelect?>
                    </select>
                </div>
            <?php endif;?>
            <div class="form-field event-capacity-wrap">
                <label for="event-capacity"><?php _e('Capacity', 'reservations');?></label>
                <input name="event_meta[capacity]" id="event-capacity" class="res-inline" type="number" min="0" step="1" value="<?=esc_attr($meta["capacity"])?>">
            </div>
            <?php if ($this->plugin->isFeatureEnabled("event_subscription_control")): ?>
                <div class="form-field subscription-enabled-wrap">
                    <label for="subscription-enabled"><input type="checkbox" id="subscription-enabled" name="event_meta[subscription_enabled]" value="1"<?php checked($meta["subscription_enabled"]);?>> <?php _e('Enable subscription', 'reservations');?></label>
                    <p class="description"><?php _e('Enable access to local subscription form', 'reservations');?></p>
                </div>
                <div class="form-field custom-subscribe-url-wrap">
                    <label for="custom-subscribe-url"><?php _e('Custom Subscribe URL', 'reservations');?></label>
                    <input type="url" id="custom-subscribe-url" name="event_meta[custom_subscribe_url]" value="<?=esc_attr($meta["custom_subscribe_url"])?>">
                    <p class="description"><?php _e('This field is used only when the checkbox above is left unchecked', 'reservations');?></p>
                </div>
            <?php endif;?>
            <div class="form-field event-price-wrap">
                <label for=""><?php _e('Price', 'reservations');?></label>

                <?php Utils\Html::renderPaymentTemplatesEditor("event_meta[payments]", $paymentTemplates, $attachmentSets);?>

                <p class="description">
                    <?php _e('Notification Email Advance &ndash; how many days before the start of this event should be the notification email sent', 'reservations');?>
                </p>

                <p class="description">
                    <?php _e('Notification email for initial payment will be sent immediately after registration', 'reservations');?>
                </p>
            </div>

            <div class="form-field event-attachment-sets-wrap">
                <label for="event-files"><?php _e('Attachment Sets', 'reservations');?></label>

                <?php Utils\Html::renderAttachmentSetsEditor("event_meta[attachment_sets]", $attachmentSets);?>
            </div>

            <div class="form-field event-password-wrap">
                <label for="event-password"><?php _e('Access Password', 'reservations');?></label>
                <input type="text" name="event_meta[password]" id="event-password" value="<?=esc_attr($meta["password"])?>">
                <p class="description"><?php _e('If left empty, access will be unrestricted', 'reservations');?></p>
            </div>

            <div class="form-field event-description-wrap">
                <label for="event-description"><?php _e('Description (optional)', 'reservations');?></label>
            <?php wp_editor(wpautop($event->description), "event_description", [
            "textarea_name" => "event_meta[description]",
            "textarea_rows" => 10,
            "media_buttons" => false,
        ]);?>
            </div>
        </div>
        <?php
}

/** @action(save_post_res_event) */
    public function saveEvent($eventId)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST['event_nonce']) || !wp_verify_nonce($_POST['event_nonce'], "event_save")) {
            return;
        }

        if (!current_user_can("edit_post", $eventId)) {
            return;
        }

        if (!isset($_POST["event_meta"])) {
            return;
        }

        $meta = $_POST["event_meta"];

        if (!Utils::allSet($meta, [
            "date_from", "date_to", "start_time", "end_time",
            "city_id", "contact_instructor_id", "instructor_ids", "contact_email", "contact_phone",

            "description", "event_type", "capacity", "password", "payments", "attachment_sets",
        ])) {
            return;
        }

        if ($this->plugin->isFeatureEnabled("camp_type")) {
            if (!Utils::allSet($meta, [
                "camp_type",
            ])) {
                return;
            }
        }

        if ($this->plugin->isFeatureEnabled("event_subscription_control")) {
            if (!Utils::allSet($meta, [
                "custom_subscribe_url",
            ])) {
                return;
            }
        }

        $event = Models\Event::find($eventId);

        // Date

        $dateFrom = Utils::parseDate($meta["date_from"]) ?: Utils::today();
        $dateTo   = Utils::parseDate($meta["date_to"]) ?: $dateFrom;

        if ($dateTo->lt($dateFrom)) {
            $dateTo = $dateFrom;
        }

        $event->setPrefixedMetaBulk([
            "date_from" => $dateFrom->getTimestamp(),
            "date_to"   => $dateTo->getTimestamp(),
        ]);

        // Time

        $event->setPrefixedMetaBulk([
            "start_time" => Utils::sanitizeTime(sanitize_text_field($meta["start_time"])),
            "end_time"   => Utils::sanitizeTime(sanitize_text_field($meta["end_time"])),
        ]);

        // City

        $city = !empty($meta["city_id"]) ? (int) $meta["city_id"] : null;

        $event->setCity($city);

        // Instructors

        $instructors = empty($meta["instructor_ids"]) ? [] : array_map(function ($id) {
            return (int) $id;
        }, explode(",", $meta["instructor_ids"]));

        $event->instructors()->sync($instructors);

        $contact_instructor = !empty($meta["contact_instructor_id"]) ? (int) $meta["contact_instructor_id"] : null;

        if ($contact_instructor !== null && in_array($contact_instructor, $instructors)) {
            $event->setPrefixedMetaBulk([
                "contact_instructor_id" => $contact_instructor,
                "contact_email"         => "",
                "contact_phone"         => "",
            ]);
        } else {
            $event->setPrefixedMetaBulk([
                "contact_instructor_id" => null,
                "contact_email"         => sanitize_text_field($meta["contact_email"]),
                "contact_phone"         => sanitize_text_field($meta["contact_phone"]),
            ]);
        }

        // Event Type

        $eventType = Models\Local\EventType::find($meta["event_type"]) ?: Models\Local\EventType::getDefault();

        $event->setPrefixedMeta("event_type", $eventType["id"]);

        // Attachment Sets

        $attachmentSets = is_array($meta["attachment_sets"]) ? $meta["attachment_sets"] : [];
        $attachmentSets = collect($attachmentSets)->map(function ($ids) {
            return collect(explode(",", $ids))->map(function ($id) {
                return (int) $id;
            })->reject(function ($id) {
                return !get_attached_file($id);
            })->toArray();
        })->toArray();

        $event->setPrefixedMeta("attachment_sets", $attachmentSets);

        // Payment Templates

        $paymentTemplates = is_array($meta["payments"]) ? $meta["payments"] : [];
        $paymentTemplates = collect($paymentTemplates)->map(function ($template) use ($attachmentSets) {
            $template = Utils::defaults($template, [
                "hash"                              => "",
                "amount"                            => "0",
                "advance"                           => "0",
                "initial"                           => false,
                "notification_email_template"       => "",
                "notification_email_attachment_set" => "",
                "confirmation_email_template"       => "",
                "confirmation_email_attachment_set" => "",
            ]);

            if (!$template["hash"]) {
                $template["hash"] = Utils::createHash();
            }

            $template["amount"]  = max(0, (int) $template["amount"]);
            $template["advance"] = (int) $template["advance"];

            if ($template["notification_email_attachment_set"] === "" || !isset($attachmentSets[$template["notification_email_attachment_set"]])) {
                $template["notification_email_attachment_set"] = null;
            } else {
                $template["notification_email_attachment_set"] = (int) $template["notification_email_attachment_set"];
            }

            if ($template["confirmation_email_attachment_set"] === "" || !isset($attachmentSets[$template["confirmation_email_attachment_set"]])) {
                $template["confirmation_email_attachment_set"] = null;
            } else {
                $template["confirmation_email_attachment_set"] = (int) $template["confirmation_email_attachment_set"];
            }

            $template["initial"] = (bool) $template["initial"];

            return $template;
        })->filter(function ($template) {
            if (isset($template["amount_monthly"]) && $template["amount_monthly"] > 0) {
                return true;
            }

            return $template["amount"] > 0;
        })->all();

        $event->setPrefixedMeta("payment_templates", $paymentTemplates);

        // Other

        $event->setPrefixedMetaBulk([
            "description" => wp_kses_post($meta["description"]),

            "capacity"    => (int) sanitize_text_field($meta["capacity"]),
            "address"     => sanitize_textarea_field($meta["address"]),
            "password"    => sanitize_text_field($meta["password"]),
        ]);

        if ($this->plugin->isFeatureEnabled("camp_type")) {
            $campType = sanitize_text_field($meta["camp_type"]);

            if (isset(TranslatableEnums::campTypes()[$campType])) {
                $event->setPrefixedMeta("camp_type", $campType);
            }
        }

        if ($this->plugin->isFeatureEnabled("event_subscription_control")) {
            $event->setPrefixedMeta("subscription_enabled", isset($meta["subscription_enabled"]));

            $event->setPrefixedMeta("custom_subscribe_url", sanitize_text_field($meta["custom_subscribe_url"]));
        }
    }
}
