<?php

namespace Reservations\Models\Local;

use Reservations;

class GoPaySettings
{
    public static function all()
    {
        $plugin = Reservations::instance();

        $settings = $plugin->getOption("gopay", []);

        if (!is_array($settings)) {
            $settings = [];
        }

        if (!count($settings) && $plugin->getOption("gopay_goid")) {
            $old = [
                "goid"             => (int) $plugin->getOption("gopay_goid"),
                "clientId"         => $plugin->getOption("gopay_client_id"),
                "clientSecret"     => $plugin->getOption("gopay_client_secret"),
                "isProductionMode" => $plugin->getOption("gopay_production") === "1",
            ];

            $settings[ObjectType::TRAININGS] = $old;

            foreach (EventType::all() as $eventType) {
                $settings[ObjectType::EVENT . "_" . $eventType["id"]] = $old;
            }
        }

        return $settings;
    }

    public static function find($id)
    {
        return self::all()[$id] ?? null;
    }
}
