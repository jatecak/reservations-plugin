<?php

namespace Reservations\Admin;

use Reservations;
use Reservations\Admin\Settings;
use Reservations\Base;
use Reservations\Models;
use Reservations\Models\Local\ObjectType;
use Reservations\Utils;

class Settings extends Base\Service
{
    public $plugs = [];

    /** @action(admin_init) */
    public function registerSettings()
    {
        $plugin = $this->plugin;

        register_setting($plugin->slug(), $plugin->prefix("form_filler_url"));

        add_settings_section($plugin->prefix("general"), __('General', 'reservations'), "__return_false", $plugin->slug());

        add_settings_field($plugin->prefix("form_filler_url"), __('Form Filler Url', 'reservations'), [$this, "renderField"], $plugin->slug(), $plugin->prefix("general"), [
            "type" => "url",
            "name" => $plugin->prefix("form_filler_url"),
        ]);

        $this->addField("general", "google_maps_api_key", __('Google Maps API Key', 'reservations'));

        if ($this->plugin->isFeatureEnabled("email_from")) {
            $this->addField("general", "email_from", __('Email From', 'reservations'), [
                "type"              => "email",
                "sanitize_callback" => [$this, "sanitizeEmail"],
            ]);
        }

        $this->addField("general", "email_from_name", __('Email From Name', 'reservations'));
        $this->addField("general", "privacy_policy_url", __('Privacy Policy URL', 'reservations'));

        if ($this->plugin->isFeatureEnabled("subscriber_account")) {
            $messageTemplatesPairs = collect(Models\Local\MessageTemplate::all())->pluck("name")->toArray();

            $this->addField("general", "new_account_template", __('New Account Email Template', 'reservations'), [
                "type"        => "select",
                "options"     => array_merge([
                    "" => __('None', 'reservations'),
                ], $messageTemplatesPairs),
                "description" => __('Email sent on account creation from subscription form. Don\'t forget to include the {{password}} variable.', 'reservations'),
            ]);
        }

        register_setting($plugin->slug(), $plugin->prefix("gopay_goid"));
        register_setting($plugin->slug(), $plugin->prefix("gopay_client_id"));
        register_setting($plugin->slug(), $plugin->prefix("gopay_client_secret"));
        register_setting($plugin->slug(), $plugin->prefix("gopay_production"));

        add_settings_section($plugin->prefix("schedule"), __('Schedule Settings', 'reservations'), "__return_false", $plugin->slug());

        $this->addField("schedule", "schedule_time_min", __('Minimal Time', 'reservations'), [
            "type"              => "time",
            "sanitize_callback" => [$this, "sanitizeTime"],
        ]);
        $this->addField("schedule", "schedule_time_max", __('Maximal Time', 'reservations'), [
            "type"              => "time",
            "sanitize_callback" => [$this, "sanitizeTime"],
        ]);

        $this->addSection("application_form", __('Application Form Settings', 'reservations'));

        $this->addField("application_form", "form_filename", __('Filename', 'reservations'));
        $this->addField("application_form", "form_filename_workshops", __('Filename (Workshops)', 'reservations'));
        $this->addField("application_form", "form_filename_camps", __('Filename (Camps)', 'reservations'));

        if (Reservations::MODE === "lubo") {
            $this->addField("application_form", "organisation", __('Organisation', 'reservations'));
            $this->addField("application_form", "registration_number", __('Registration Number', 'reservations'), [
                "type" => "number",
            ]);
        }

        $this->addSection("subscription", __('Subscription Settings', 'reservations'));

        $this->addField("subscription", "term_advance", __('Term Advance', 'reservations'), [
            "type"        => "number",
            "default"     => "0",
            "after"       => " " . __('days', 'reservations'),
            "description" => __('How many days in advance allow registration for next term.', 'reservations'),
        ]);

        $this->addField("subscription", "term_duration", __('Default Biannual Subscription Duration', 'reservations'), [
            "type"              => "date_interval",
            "default"           => ["months" => 6, "days" => 0],
            "description"       => __('Putting zeroes there will enable biannual subscription only when a term is active.', 'reservations'),
            "sanitize_callback" => [$this, "sanitizeDateInterval"],
        ]);

        if ($this->plugin->isFeatureEnabled("annual_subscription")) {
            $this->addField("subscription", "year_duration", __('Default Annual Subscription Duration', 'reservations'), [
                "type"              => "date_interval",
                "default"           => ["months" => 12, "days" => 0],
                "description"       => __('Putting zeroes there will enable biannual subscription only when a year is active.', 'reservations'),
                "sanitize_callback" => [$this, "sanitizeDateInterval"],
            ]);
        }

        if ($this->plugin->isFeatureEnabled("subscription_notification")) {
            $this->addSection("subscription_notification", __('Subscription End Notification Settings', 'reservations'));

            $this->addField("subscription_notification", "subscription_notification_enable", __('Enable Notification Email', 'reservations'), [
                "type"    => "checkbox",
                "default" => false,
            ]);

            $this->addField("subscription_notification", "subscription_notification_advance", __('Notification Advance', 'reservations'), [
                "type"        => "number",
                "default"     => "0",
                "after"       => " " . __('days', 'reservations'),
                "description" => _x('How many days before the subscription ends should the notification email be sent.', 'subscription end notification', 'reservations'),
            ]);

            $messageTemplatesPairs = collect(Models\Local\MessageTemplate::all())->pluck("name")->toArray();

            $this->addField("subscription_notification", "subscription_notification_template", _x('Notification Email Template', 'subscription end notification', 'reservations'), [
                "type"    => "select",
                "options" => array_merge([
                    "" => __('None', 'reservations'),
                ], $messageTemplatesPairs),
            ]);
        }

        if ($this->plugin->isFeatureEnabled("invoices_idoklad")) {
            $this->addSection("invoices", __('Invoice Generation', 'reservations'));

            $this->addField("invoices", "invoices_enable", __('Enable Invoice Generation', 'reservations'), [
                "type" => "checkbox",
                "default" => false
            ]);

            $this->addField("invoices", "idoklad_client_id", __('iDoklad Client Id', 'reservations'));
            $this->addField("invoices", "idoklad_client_secret", __('iDoklad Client Secret', 'reservations'));
            $this->addField("invoices", "idoklad_numeric_sequence_name", __('Numeric Sequence Name', 'reservations'), [
                "description" => __('Leave empty for default.', 'reservations'),
            ]);

            $this->plugs["testInvoice"] = new Settings\TestInvoice($this->plugin);
        }

        $this->plugs["goPay"]            = new Settings\GoPay($this->plugin);
        $this->plugs["messageTemplates"] = new Settings\MessageTemplates($this->plugin);
        $this->plugs["importExport"]     = new Settings\ImportExport($this->plugin);

        foreach ($this->plugs as $plug) {
            $plug->register();
        }
    }

    public function addSection($name, $title)
    {
        add_settings_section($this->plugin->prefix($name), $title, "__return_false", $this->plugin->slug());
    }

    public function addField($section, $name, $label, $args = [])
    {
        register_setting($this->plugin->slug(), $this->plugin->prefix($name), array_intersect_key($args, array_flip(["default", "sanitize_callback"])));

        add_settings_field($this->plugin->prefix($name), $label, [$this, "renderField"], $this->plugin->slug(), $this->plugin->prefix($section), [
            "name" => $this->plugin->prefix($name),
        ] + $args);
    }

    public function sanitizeTime($value)
    {
        return Utils::sanitizeTime($value);
    }

    public function sanitizeEmail($value)
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $value;
        }

        return null;
    }

    public function sanitizeDateInterval($value)
    {
        if (!is_array($value)) {
            $value = [];
        }

        $value["months"] = max(0, (int) ($value["months"] ?? 0));
        $value["days"]   = max(0, (int) ($value["days"] ?? 0));

        return $value;
    }

    public function renderField($args = [])
    {
        $args = wp_parse_args($args, [
            "type" => "text",
        ]);

        $value = get_option($args["name"]);

        switch ($args["type"]) {
            case "checkbox":
                $args["label"] = $args["label"] ?? "";

                echo '<label><input type="checkbox" name="' . esc_attr($args["name"]) . '" value="1"' . checked($value, true, false) . '> ' . $args["label"] . '</label>';
                break;

            case "date_interval":
                if (!is_array($value)) {
                    $value = [];
                }

                $value["months"] = $value["months"] ?? 0;
                $value["days"]   = $value["days"] ?? 0;

                echo '<input type="text" name="' . esc_attr($args["name"]) . '[months]" value="' . esc_attr($value["months"]) . '"> měsíců a <input type="text" name="' . esc_attr($args["name"]) . '[days]" value="' . esc_attr($value["days"]) . '"> dní';
                break;

            case "select":
                $options   = $args["options"] ?? [];
                $recursive = isset($args["recursive"]) && $args["recursive"];

                echo '<select name="' . esc_attr($args["name"]) . '">';
                if ($recursive) {
                    echo Utils\Html::getSelectRecursive($options, $value);
                } else {
                    echo Utils\Html::getSelect($options, $value);
                }
                echo '</select>';
                break;

            default:
                echo '<input type="' . $args["type"] . '" name="' . esc_attr($args["name"]) . '" value="' . esc_attr($value) . '">';
        }

        if (isset($args["after"])) {
            echo $args["after"];
        }

        if (isset($args["description"])) {
            echo '<p class="description">' . $args["description"] . '</p>';
        }
    }
}
