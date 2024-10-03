<?php

namespace Reservations\Models\Local;

class ObjectType
{
    const TRAININGS = "trainings",
    EVENT           = "event";

    public static function all()
    {
        return [
            self::TRAININGS,
            self::EVENT,
        ];
    }
}
