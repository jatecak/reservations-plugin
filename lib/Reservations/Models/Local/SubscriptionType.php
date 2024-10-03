<?php

namespace Reservations\Models\Local;

use Reservations;

class SubscriptionType
{
    const ANNUAL = "annual",
    BIANNUAL     = "biannual",
    MONTHLY      = "monthly",
    SINGLE       = "single";

    public static function all()
    {
        return [
            self::ANNUAL,
            self::BIANNUAL,
            self::MONTHLY,
            self::SINGLE,
        ];
    }

    public static function forObjectType($objType)
    {
        if ($objType === ObjectType::TRAININGS) {
            $types = [];

            if (Reservations::instance()->isFeatureEnabled("annual_subscription")) {
                $types[] = self::ANNUAL;
            }

            $types[] = self::BIANNUAL;
            $types[] = self::MONTHLY;

            return $types;
        } else if ($objType === ObjectType::EVENT) {
            return [
                self::SINGLE,
            ];
        }

        return [];
    }
}
