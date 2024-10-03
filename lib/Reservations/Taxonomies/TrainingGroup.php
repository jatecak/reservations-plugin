<?php

namespace Reservations\Taxonomies;

use Carbon\Carbon;
use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\Models\Local;
use Reservations\PostTypes;
use Reservations\Utils;

class TrainingGroup extends Base\Taxonomy
{
    const NAME = Reservations::PREFIX . "tgroup";

    /** @action(init) */
    public function register()
    {
        register_taxonomy(self::NAME, PostTypes\Training::NAME, [
            "labels"             => [
                'name'                       => _x('Training Groups', 'taxonomy general name', 'reservations'),
                'singular_name'              => _x('Training Group', 'taxonomy singular name', 'reservations'),
                'search_items'               => __('Search Training Groups', 'reservations'),
                'all_items'                  => __('All Training Groups', 'reservations'),
                'edit_item'                  => __('Edit Training Group', 'reservations'),
                'update_item'                => __('Update Training Group', 'reservations'),
                'add_new_item'               => __('Add New Training Group', 'reservations'),
                'new_item_name'              => __('New Training Group Name', 'reservations'),
                'separate_items_with_commas' => __('Separate training groups with commas', 'reservations'),
                'add_or_remove_items'        => __('Add or remove training group', 'reservations'),
                'choose_from_most_used'      => __('Choose from the most used training groups', 'reservations'),
                'not_found'                  => __('No training groups found', 'reservations'),
                'no_terms'                   => __('No training groups', 'reservations'),
            ],
            "show_in_quick_edit" => false,
            "meta_box_cb"        => false,
        ]);
    }

    /**
     * @action(res_tgroup_add_form)
     * @action(res_tgroup_edit_form)
     */
    public function removeDescriptionTextBox()
    {
        echo '<style type="text/css">
            .term-description-wrap { display: none; }
            .wpcustom-category-form-field { display: none; }
        </style>';
    }

    /**
     * @action(manage_edit-res_tgroup_columns)
     * @priority(15)
     */
    public function removeDescriptionAndImageColumn($columns)
    {
        if (isset($columns["image"])) {
            unset($columns["image"]);
        }

        if (isset($columns["description"])) {
            unset($columns["description"]);
        }

        return $columns;
    }

    /** @action(res_tgroup_add_form_fields) */
    public function addFormFields()
    {
        $ageGroups         = Utils::getAgeGroupsFlat();
        $subscriptionTypes = Local\SubscriptionType::forObjectType(Local\ObjectType::TRAININGS);

        $attachmentSets[] = [];
        $paymentTemplates = [];

        foreach ($subscriptionTypes as $type) {
            $paymentTemplates[$type][] = [
                "id"     => 0,
                "amount" => 0,
                "hash"   => "",
            ];
        }
?>
        <div class="form-field">
            <label for="res-tgroup-capacity"><?php _e('Capacity', 'reservations'); ?></label>
            <input type="number" name="tgroup_meta[capacity]" id="res-tgroup-capacity" class="res-inline" min="0" step="1">
        </div>

        <?php if ($this->plugin->isFeatureEnabled("annual_subscription")) : ?>
            <div class="form-field checkbox-wrap">
                <input type="checkbox" name="tgroup_meta[annual_enabled]" id="res-tgroup-annual-enabled" checked>
                <label for="res-tgroup-annual-enabled"><?php _e('Enable annual subscription', 'reservations'); ?></label>
            </div>
        <?php endif; ?>
        <div class="form-field checkbox-wrap">
            <input type="checkbox" name="tgroup_meta[biannual_enabled]" id="res-tgroup-biannual-enabled" checked>
            <label for="res-tgroup-biannual-enabled"><?php _e('Enable biannual subscription', 'reservations'); ?></label>
        </div>
        <div class="form-field checkbox-wrap">
            <input type="checkbox" name="tgroup_meta[monthly_enabled]" id="res-tgroup-monthly-enabled" checked>
            <label for="res-tgroup-monthly-enabled"><?php _e('Enable monthly subscription', 'reservations'); ?></label>
        </div>

        <div class="form-field">
            <label for="custom-subscribe-url"><?php _e('Custom Subscribe URL', 'reservations'); ?></label>
            <input type="url" id="custom-subscribe-url" name="tgroup_meta[custom_subscribe_url]">
            <p class="description"><?php _e('This field is used only when local subscription is not enabled', 'reservations'); ?></p>
        </div>

        <?php if ($this->plugin->isFeatureEnabled("annual_subscription")) : ?>
            <div class="form-field tgroup-price-wrap event-price-wrap">
                <label for=""><?php _e('Price of annual subscription', 'reservations'); ?></label>

                <?php Utils\Html::renderPaymentTemplatesEditor("tgroup_meta[payment_templates][annual]", $paymentTemplates["annual"], $attachmentSets); ?>

                <p class="description">
                    <?php _e('Notification Email Advance &ndash; how many days after subscribing should the notification email be sent', 'reservations'); ?>
                </p>
                <p class="description">
                    <?php _e('Notification email for initial payment will be sent immediately after registration', 'reservations'); ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="form-field tgroup-price-wrap event-price-wrap">
            <label for=""><?php _e('Price of biannual subscription', 'reservations'); ?></label>

            <?php Utils\Html::renderPaymentTemplatesEditor("tgroup_meta[payment_templates][biannual]", $paymentTemplates["biannual"], $attachmentSets); ?>

            <p class="description">
                <?php _e('Notification Email Advance &ndash; how many days after subscribing should the notification email be sent', 'reservations'); ?>
            </p>
            <p class="description">
                <?php _e('Notification email for initial payment will be sent immediately after registration', 'reservations'); ?>
            </p>
        </div>

        <div class="form-field tgroup-price-wrap event-price-wrap">
            <label for=""><?php _e('Price of monthly subscription', 'reservations'); ?></label>

            <?php Utils\Html::renderPaymentTemplatesEditor("tgroup_meta[payment_templates][monthly]", $paymentTemplates["monthly"], $attachmentSets); ?>

            <p class="description">
                <?php _e('Notification Email Advance &ndash; how many days after subscribing should the notification email be sent', 'reservations'); ?>
            </p>
            <p class="description">
                <?php _e('Notification email for initial payment will be sent immediately after registration', 'reservations'); ?>
            </p>
        </div>

        <div class="form-field gym-price-single-wrap">
            <label><?php _e('Default price of single training (no subscription)', 'reservations'); ?></label>

            <input type="number" name="tgroup_meta[price_single]" class="gym-price-single res-inline" min="0" step="1"> <?php _e('US$', 'reservations'); ?><br>
        </div>

        <div class="form-field event-attachment-sets-wrap">
            <label for="event-files"><?php _e('Attachment Sets', 'reservations'); ?></label>

            <?php Utils\Html::renderAttachmentSetsEditor("tgroup_meta[attachment_sets]", $attachmentSets); ?>
        </div>

        <div class="form-field tgroup-term-1-period-wrap">
            <label for="tgroup-term-1-period-start"><?php _e('Start and end date of 1. term', 'reservations'); ?></label>
            <input type="date" class="res-inline" name="tgroup_meta[term_periods][0][0]" id="tgroup-term-1-period-start">
            &ndash;
            <input type="date" class="res-inline" name="tgroup_meta[term_periods][0][1]" id="tgroup-term-1-period-end">
        </div>
        <div class="form-field tgroup-term-2-period-wrap">
            <label for="tgroup-term-2-period-start"><?php _e('Start and end date of 2. term', 'reservations'); ?></label>
            <input type="date" class="res-inline" name="tgroup_meta[term_periods][1][0]" id="tgroup-term-2-period-start">
            &ndash;
            <input type="date" class="res-inline" name="tgroup_meta[term_periods][1][1]" id="tgroup-term-2-period-end">
        </div>

        <?php if ($this->plugin->isFeatureEnabled("annual_subscription")) : ?>
            <div class="form-field tgroup-year-wrap">
                <label for="tgroup-year-start"><?php _e('Start and end of year', 'reservations'); ?></label>
                <input type="date" class="res-inline" name="tgroup_meta[year][0]" id="tgroup-year-start">
                &ndash;
                <input type="date" class="res-inline" name="tgroup_meta[year][1]" id="tgroup-year-end">
                <p class="description"><?php _e('If left empty, earliest and latest date entered above will be used', 'reservations'); ?></p>
            </div>
        <?php endif; ?>

        <div class="form-field tgroup-password-wrap">
            <label for="tgroup-password"><?php _e('Access Password', 'reservations'); ?></label>
            <input type="text" name="tgroup_meta[password]" id="tgroup-password">
            <p class="description"><?php _e('If left empty, access will be unrestricted', 'reservations'); ?></p>
        </div>
    <?php
    }

    /** @action(res_tgroup_edit_form_fields) */
    public function editFormFields($tgroupTerm)
    {
        $tgroup = Models\TrainingGroup::find($tgroupTerm->term_id);

        $ageGroups         = Utils::getAgeGroupsFlat();
        $subscriptionTypes = Local\SubscriptionType::forObjectType(Local\ObjectType::TRAININGS);

        $attachmentSets = $tgroup->attachmentSets;

        if (!count($attachmentSets)) {
            $attachmentSets[] = [];
        }

        $paymentTemplates = [];

        foreach ($tgroup->paymentTemplates as $template) {
            if (!count($template["subscription_types"])) {
                continue;
            }

            $paymentTemplates[$template["subscription_types"][0]][] = $template;
        }

        foreach ($subscriptionTypes as $type) {
            if (!isset($paymentTemplates[$type])) {
                $paymentTemplates[$type][] = [
                    "id"     => 0,
                    "amount" => 0,
                    "hash"   => "",
                ];
            }
        }

        $enabledSubscriptionTypes = $tgroup->getPrefixedMeta("enabled_subscription_types", []);

        $values = [
            "price_single"     => esc_attr($tgroup->priceSingle),
            "capacity"         => esc_attr($tgroup->capacity),
            "password"         => esc_attr($tgroup->password),

            "annual_enabled"   => in_array("annual", $enabledSubscriptionTypes),
            "biannual_enabled" => in_array("biannual", $enabledSubscriptionTypes),
            "monthly_enabled"  => in_array("monthly", $enabledSubscriptionTypes),

            "custom_subscribe_url" => esc_attr($tgroup->customSubscribeUrl),

            "term_periods"     => [],
            "year"             => [],
        ];

        $year = $tgroup->getPrefixedMeta("year", []);

        if (!is_array($year)) {
            $year = [];
        }

        if (count($year) >= 2) {
            $values["year"] = [
                $year[0]->format("Y-m-d"),
                $year[1]->format("Y-m-d"),
            ];
        } else {
            $values["year"] = ["", ""];
        }

        foreach ($tgroup->termPeriods as $period) {
            $values["term_periods"][] = [
                $period[0]->format("Y-m-d"),
                $period[1]->format("Y-m-d"),
            ];
        }

        for ($i = 2 - count($values["term_periods"]); $i > 0; $i--) {
            $values["term_periods"][] = [
                "", "",
            ];
        }

    ?>
        <tr class="form-field">
            <th scope="row"><label for="res-tgroup-capacity"><?php _e('Capacity', 'reservations'); ?></label></th>
            <td><input type="number" name="tgroup_meta[capacity]" id="res-tgroup-capacity" class="res-inline" min="0" step="1" value="<?= $values["capacity"] ?>"></td>
        </tr>

        <?php if ($this->plugin->isFeatureEnabled("annual_subscription")) : ?>
            <tr class="form-field">
                <th scope="row"><label for="res-tgroup-annual-enabled"><?php _e('Enable annual subscription', 'reservations'); ?></label></th>
                <td><input type="checkbox" name="tgroup_meta[annual_enabled]" id="res-tgroup-annual-enabled" <?php checked($values["annual_enabled"]) ?>></td>
            </tr>
        <?php endif; ?>
        <tr class="form-field">
            <th scope="row"><label for="res-tgroup-biannual-enabled"><?php _e('Enable biannual subscription', 'reservations'); ?></label></th>
            <td><input type="checkbox" name="tgroup_meta[biannual_enabled]" id="res-tgroup-biannual-enabled" <?php checked($values["biannual_enabled"]) ?>></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="res-tgroup-monthly-enabled"><?php _e('Enable monthly subscription', 'reservations'); ?></label></th>
            <td><input type="checkbox" name="tgroup_meta[monthly_enabled]" id="res-tgroup-monthly-enabled" <?php checked($values["monthly_enabled"]) ?>></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="custom-subscribe-url"><?php _e('Custom Subscribe URL', 'reservations'); ?></label></th>
            <td><input type="url" id="custom-subscribe-url" name="tgroup_meta[custom_subscribe_url]" value="<?=$values["custom_subscribe_url"]?>">
            <p class="description"><?php _e('This field is used only when local subscription is not enabled', 'reservations'); ?></p></td>
        </div>

        <?php if ($this->plugin->isFeatureEnabled("annual_subscription")) : ?>
            <tr class="form-field gym-price-wrap event-price-wrap">
                <th scope="row"><label for=""><?php _e('Price of annual subscription', 'reservations'); ?></label></th>

                <td>
                    <?php Utils\Html::renderPaymentTemplatesEditor("tgroup_meta[payment_templates][annual]", $paymentTemplates["annual"], $attachmentSets); ?>

                    <p class="description">
                        <?php _e('Notification Email Advance &ndash; how many days after subscribing should the notification email be sent', 'reservations'); ?>
                    </p>
                    <p class="description">
                        <?php _e('Notification email for initial payment will be sent immediately after registration', 'reservations'); ?>
                    </p>
                </td>
            </tr>
        <?php endif; ?>

        <tr class="form-field gym-price-wrap event-price-wrap">
            <th scope="row"><label for=""><?php _e('Price of biannual subscription', 'reservations'); ?></label></th>

            <td>
                <?php Utils\Html::renderPaymentTemplatesEditor("tgroup_meta[payment_templates][biannual]", $paymentTemplates["biannual"], $attachmentSets); ?>

                <p class="description">
                    <?php _e('Notification Email Advance &ndash; how many days after subscribing should the notification email be sent', 'reservations'); ?>
                </p>
                <p class="description">
                    <?php _e('Notification email for initial payment will be sent immediately after registration', 'reservations'); ?>
                </p>
            </td>
        </tr>

        <tr class="form-field gym-price-wrap event-price-wrap">
            <th scope="row"><label for=""><?php _e('Price of monthly subscription', 'reservations'); ?></label></th>

            <td>
                <?php Utils\Html::renderPaymentTemplatesEditor("tgroup_meta[payment_templates][monthly]", $paymentTemplates["monthly"], $attachmentSets, true); ?>

                <p class="description">
                    <?php _e('Notification Email Advance &ndash; how many days after subscribing should the notification email be sent', 'reservations'); ?>
                </p>
                <p class="description">
                    <?php _e('Notification email for initial payment will be sent immediately after registration', 'reservations'); ?>
                </p>
            </td>
        </tr>

        <tr class="form-field event-attachment-sets-wrap">
            <th scope="row"><label for="event-files"><?php _e('Attachment Sets', 'reservations'); ?></label></th>

            <td>
                <?php Utils\Html::renderAttachmentSetsEditor("tgroup_meta[attachment_sets]", $attachmentSets); ?>
            </td>
        </tr>
        <tr class="form-field gym-price-single-wrap">
            <th scope="row"><label for="gym-price-single"><?php _e('Default price of single training (no subscription)', 'reservations'); ?></label></th>
            <td>
                <input type="number" name="tgroup_meta[price_single]" class="gym-price-single res-inline" min="0" step="1" value="<?= $values["price_single"] ?>"> <?php _e('US$', 'reservations'); ?>
            </td>
        </tr>
        <tr class="form-field tgroup-term-1-period-wrap">
            <th scope="row"><label for="tgroup-term-1-period-start"><?php _e('Start and end date of 1. term', 'reservations'); ?></label></th>
            <td><input type="date" class="res-inline" name="tgroup_meta[term_periods][0][0]" id="tgroup-term-1-period-start" value="<?= $values["term_periods"][0][0] ?>">
                &ndash;
                <input type="date" class="res-inline" name="tgroup_meta[term_periods][0][1]" id="tgroup-term-1-period-end" value="<?= $values["term_periods"][0][1] ?>">
            </td>
        </tr>
        <tr class="form-field tgroup-term-2-period-wrap">
            <th scope="row"><label for="tgroup-term-2-period-start"><?php _e('Start and end date of 2. term', 'reservations'); ?></label></th>
            <td><input type="date" class="res-inline" name="tgroup_meta[term_periods][1][0]" id="tgroup-term-2-period-start" value="<?= $values["term_periods"][1][0] ?>">
                &ndash;
                <input type="date" class="res-inline" name="tgroup_meta[term_periods][1][1]" id="tgroup-term-2-period-end" value="<?= $values["term_periods"][1][1] ?>">
            </td>
        </tr>

        <?php if ($this->plugin->isFeatureEnabled("annual_subscription")) : ?>
            <tr class="form-field tgroup-year-wrap">
                <th scope="row"><label for="tgroup-year-start"><?php _e('Start and end of year', 'reservations'); ?></label></th>
                <td><input type="date" class="res-inline" name="tgroup_meta[year][0]" id="tgroup-year-start" value="<?= $values["year"][0] ?>">
                    &ndash;
                    <input type="date" class="res-inline" name="tgroup_meta[year][1]" id="tgroup-year-end" value="<?= $values["year"][1] ?>">
                    <p class="description"><?php _e('If left empty, earliest and latest date entered above will be used', 'reservations'); ?></p>
                </td>
                </div>
            <?php endif; ?>

            <tr class="form-field tgroup-password-wrap">
                <th scope="row"><label for="tgroup-password"><?php _e('Access Password', 'reservations'); ?></label></th>
                <td><input type="text" name="tgroup_meta[password]" id="tgroup-password" value="<?= $values["password"]; ?>">
                    <p class="description"><?php _e('If left empty, access will be unrestricted', 'reservations'); ?></p>
                </td>
            </tr>
        <?php
    }

    /**
     * @action(create_res_tgroup)
     * @action(edit_res_tgroup)
     */
    public function saveTrainingGroup($tgroupId, $taxonomyId)
    {
        if (!isset($_POST["tgroup_meta"])) {
            return;
        }

        $meta = $_POST["tgroup_meta"];

        if (!Utils::allSet($meta, [
            "payment_templates", "price_single", "capacity", "custom_subscribe_url",
            "password", "attachment_sets", "term_periods",
        ])) {
            return;
        }

        if ($this->plugin->isFeatureEnabled("annual_subscription") && !isset($meta["year"])) {
            return;
        }

        if (!is_array($meta["payment_templates"])) {
            return;
        }

        $subscriptionTypes = Local\SubscriptionType::forObjectType(Local\ObjectType::TRAININGS);

        $tgroup = Models\TrainingGroup::find($tgroupId);

        $enabledSubscriptionTypes = [];
        $paymentTemplates         = [];

        foreach ($subscriptionTypes as $type) {
            if (isset($meta[$type . "_enabled"])) {
                $enabledSubscriptionTypes[] = $type;
            }

            $templates = $meta["payment_templates"][$type];

            foreach ($templates as $template) {
                $template["subscription_types"] = [$type];

                $paymentTemplates[] = $template;
            }
        }

        $tgroup->setPrefixedMeta("enabled_subscription_types", $enabledSubscriptionTypes);

        // Attachment Sets

        $attachmentSets = is_array($meta["attachment_sets"]) ? $meta["attachment_sets"] : [];
        $attachmentSets = collect($attachmentSets)->map(function ($ids) {
            return collect(explode(",", $ids))->map(function ($id) {
                return (int) $id;
            })->reject(function ($id) {
                return !get_attached_file($id);
            })->toArray();
        })->toArray();

        $tgroup->setPrefixedMeta("attachment_sets", $attachmentSets);

        // Payment Templates

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

            if (isset($template["amount_monthly"])) {
                $template["amount_monthly"] = max(0, (int) $template["amount_monthly"]);
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

        $tgroup->setPrefixedMeta("payment_templates", $paymentTemplates);

        // Term Periods

        $termPeriods = [];
        foreach ((array) $meta["term_periods"] as $period) {
            if (!is_array($period) || count($period) < 2) {
                continue;
            }

            $from = sanitize_text_field($period[0]);
            $to   = sanitize_text_field($period[1]);

            if ($from === "" || $to === "") {
                continue;
            }

            $termPeriods[] = [
                Carbon::parse($from, Utils::getTimezone()),
                Carbon::parse($to, Utils::getTimezone()),
            ];
        }

        $tgroup->setPrefixedMeta("term_periods", $termPeriods);

        // Year

        if ($this->plugin->isFeatureEnabled("annual_subscription")) {
            $year = null;

            if (is_array($meta["year"]) && count($meta["year"]) >= 2) {
                $from = sanitize_text_field($meta["year"][0]);
                $to   = sanitize_text_field($meta["year"][1]);

                if ($from !== "" && $to !== "") {
                    $year = [
                        Carbon::parse($from, Utils::getTimezone()),
                        Carbon::parse($to, Utils::getTimezone()),
                    ];
                }
            }

            $tgroup->setPrefixedMeta("year", $year);
        }

        // Other

        $tgroup->setPrefixedMetaBulk([
            "price_single" => (int) sanitize_text_field($meta["price_single"]),
            "capacity"     => max(0, (int) sanitize_text_field($meta["capacity"])),
            "password"     => sanitize_text_field($meta["password"]),
            "custom_subscribe_url" => sanitize_text_field($meta["custom_subscribe_url"]),
        ]);
    }

    public function displayFilters()
    {
        $gyms    = Models\Gym::accessible()->get();
        $current = isset($_GET['tgroup_filter_gym']) ? (int) $_GET['tgroup_filter_gym'] : "";
        ?>
            <select name="tgroup_filter_gym">
                <option value=""><?php _e('&mdash; Gym &mdash;', 'reservations'); ?></option>

                <?= Utils\Html::getGymTreeSelect($gyms, $current) ?>
            </select>
            <input type="submit" name="filter_action" id="term-query-submit" class="button" value="<?php esc_attr_e('Filter'); ?>" formmethod="get">
        <?php
    }

    public function applyFilters($args)
    {
        if (isset($_GET['tgroup_filter_gym']) && !empty($_GET['tgroup_filter_gym'])) {
            $taxonomyIds = Models\Training::inGym((int) $_GET['tgroup_filter_gym'])->get()->map(function ($training) {
                return $training->termTaxonomies()->where("taxonomy", self::NAME)->first();
            })->filter()->pluck("term_taxonomy_id")->uniqueStrict()->all();

            $args["term_taxonomy_id"] = $taxonomyIds;

            if (!count($taxonomyIds)) {
                $args["object_ids"] = [0]; // force empty result set
            }
        }

        return $args;
    }

    public function registerBulkActions($bulkActions)
    {
        if ($this->plugin->isFeatureEnabled("annual_subscription")) {
            $bulkActions['enable_annual']  = __('Enable annual subscription', 'reservations');
            $bulkActions['disable_annual'] = __('Disable annual subscription', 'reservations');
        }

        $bulkActions['enable_biannual']  = __('Enable biannual subscription', 'reservations');
        $bulkActions['disable_biannual'] = __('Disable biannual subscription', 'reservations');
        $bulkActions['enable_monthly']   = __('Enable monthly subscription', 'reservations');
        $bulkActions['disable_monthly']  = __('Disable monthly subscription', 'reservations');

        return $bulkActions;
    }

    public function handleBulkActions($redirectTo, $action, $tgroupIds)
    {
        if (!in_array($action, ["enable_annual", "disable_annual", "enable_biannual", "disable_biannual", "enable_monthly", "disable_monthly"])) {
            return $redirectTo;
        }

        foreach ($tgroupIds as $id) {
            $tgroup = Models\TrainingGroup::find((int) $id);

            if (!$tgroup) {
                continue;
            }

            $enabledSubscriptionTypes = $tgroup->getPrefixedMeta("enabled_subscription_types", []);

            list($action, $type) = explode("_", $action);

            if ($action === "enable" && !in_array($type, $enabledSubscriptionTypes)) {
                $enabledSubscriptionTypes[] = $type;
            } else if ($action === "disable") {
                Utils\Arrays::removeElement($enabledSubscriptionTypes, $type);
            }

            $tgroup->setPrefixedMeta("enabled_subscription_types", $enabledSubscriptionTypes);
        }

        return $redirectTo;
    }

    public function registerRowActions($rowActions, $term)
    {
        $tgroup = Models\TrainingGroup::find((int) $term->term_id);

        $subCount      = $tgroup->subscriptions()->count();
        $trainingCount = $tgroup->trainings()->count();

        if ($subCount > 0 || $trainingCount > 0) {
            unset($rowActions["delete"]);
        }

        return $rowActions;
    }

    public function beforeDelete($termId)
    {
        $tgroup = Models\TrainingGroup::find((int) $termId);

        if (!$tgroup) {
            return;
        }

        $subCount      = $tgroup->subscriptions()->count();
        $trainingCount = $tgroup->trainings()->count();

        if ($subCount > 0 || $trainingCount > 0) {
            $message = '<p><strong>' . sprintf(__('An error occured during the deletion of training group %s', 'reservations'), esc_html($tgroup->name)) . '</strong></p>';
            $message .= '<p>' . __('The following are causes of this error:', 'reservations') . '</p><ul>';
            if ($subCount > 0) {
                $message .= '<li>' . sprintf(_n('This training group is used by %d subscription.', 'This training group is used by %d subscriptions.', $subCount, 'reservations'), $subCount) . '</li>';
            }

            if ($trainingCount > 0) {
                $message .= '<li>' . sprintf(_n('This training group is used by %d training.', 'This training group is used by %d trainings.', $trainingCount, 'reservations'), $trainingCount) . '</li>';
            }

            $message .= '</ul>';

            wp_die($message);
        }
    }

    /** @action(admin_head) */
    public function addActiveSubscriptionsButton()
    {
        if (!function_exists("get_current_screen")) {
            return;
        }

        $screen = get_current_screen();

        if ($screen->base !== "term" || $screen->post_type !== PostTypes\Training::NAME || $screen->taxonomy !== self::NAME) {
            return;
        }

        $subscriptionsButtonUrl = admin_url("edit.php?post_type=" . PostTypes\Training::NAME . "&page=" . $this->plugin->slug("-subscriptions") . "&tgroup_id=" . ((int) $_GET['tag_ID']));
        $trainingsButtonUrl     = admin_url("edit.php?post_type=" . PostTypes\Training::NAME . "&training_filter_tgroup=" . ((int) $_GET['tag_ID']));

        $html = ' <a class="page-title-action" href="' . esc_attr($trainingsButtonUrl) . '">' . __('View trainings', 'reservations') . '</a>';
        $html .= '<a class="page-title-action" href="' . esc_attr($subscriptionsButtonUrl) . '">' . __('View active subscriptions', 'reservations') . '</a>';

        ?>

            <script type="text/javascript">
                jQuery(function($) {
                    var $h1 = $("#wpbody-content > .wrap > h1");
                    $h1.addClass("wp-heading-inline").after(<?= json_encode($html) ?>);
                });
            </script>

    <?php
    }
}
