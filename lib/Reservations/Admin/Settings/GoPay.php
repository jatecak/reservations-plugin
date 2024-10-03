<?php

namespace Reservations\Admin\Settings;

use Reservations\Models;
use Reservations\Models\Local\ObjectType;
use Reservations\Utils;

class GoPay extends AbstractPlug
{
    public $name = "gopay";

    public function render()
    {
        $goPaySettings = Models\Local\GoPaySettings::all();

        $settingsTabs = [];

        if (!isset($goPaySettings[ObjectType::TRAININGS])) {
            $goPaySettings[ObjectType::TRAININGS] = [];
        }

        $settingsTabs[] = [
            "key"   => ObjectType::TRAININGS,
            "label" => __('Trainings', 'reservations'),
        ];

        foreach (Models\Local\EventType::all() as $eventType) {
            if (!isset($goPaySettings[ObjectType::EVENT . "_" . $eventType["id"]])) {
                $goPaySettings[ObjectType::EVENT . "_" . $eventType["id"]] = [];
            }

            $settingsTabs[] = [
                "key"   => ObjectType::EVENT . "_" . $eventType["id"],
                "label" => $eventType["labelPlural"],
            ];
        }

        foreach ($goPaySettings as $key => $settings) {
            $goPaySettings[$key] = Utils::defaults($settings, [
                "goid"             => 0,
                "clientId"         => "",
                "clientSecret"     => "",
                "isProductionMode" => false,
            ]);
        }

        ?>
        <h2><?php _e('GoPay Settings', 'reservations');?></h2>

        <ul class="res-tabs res-tabs-lg res-gopay-settings-tabs">
            <?php foreach ($settingsTabs as $i => $tab): ?>
                <li<?php if ($i === 0): ?> class="res-tab-active"<?php endif;?>><a href="#"><?=esc_html($tab["label"])?></a></li>
            <?php endforeach;?>
        </ul>

        <?php
foreach ($settingsTabs as $i => $tab) {
            $settings = $goPaySettings[$tab["key"]];
            $tName    = $this->sName . "[" . $tab["key"] . "]";
            ?>

        <table class="form-table res-gopay-settings<?php if ($i === 0): ?> res-active<?php endif;?>"><tbody>
                <tr>
                    <th scope="row"><?php _e('GoID', 'reservations');?></th>
                    <td><input type="text" name="<?=esc_attr($tName)?>[goid]" value="<?=esc_attr($settings["goid"])?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Client ID', 'reservations');?></th>
                    <td><input type="text" name="<?=esc_attr($tName)?>[client_id]" value="<?=esc_attr($settings["clientId"])?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Client Secret', 'reservations');?></th>
                    <td><input type="text" name="<?=esc_attr($tName)?>[client_secret]" value="<?=esc_attr($settings["clientSecret"])?>"></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Environment', 'reservations');?></th>
                    <td><label><input type="checkbox" name="<?=esc_attr($tName)?>[production_mode]" value="1"<?php checked($settings["isProductionMode"]);?>> <?php _e('Production mode', 'reservations');?></label></td>
                </tr>
        </tbody></table>
        <?php }
    }

    public function sanitize($settings)
    {
        if (!is_array($settings)) {
            return [];
        }

        $newSettings = [];
        foreach ($settings as $key => $row) {
            if (isset($row["isProductionMode"])) {
                $isProductionMode = (bool) $row["isProductionMode"];
            } else {
                $isProductionMode = isset($row["production_mode"]) && $row["production_mode"] === "1";
            }

            $newSettings[$key] = [
                "goid"             => (int) sanitize_text_field($row["goid"]) ?? 0,
                "clientId"         => sanitize_text_field($row["client_id"] ?? $row["clientId"]) ?? "",
                "clientSecret"     => sanitize_text_field($row["client_secret"] ?? $row["clientSecret"]) ?? "",
                "isProductionMode" => $isProductionMode,
            ];
        }

        return $newSettings;
    }
}
