<?php

namespace Reservations\Models\Local;

use Reservations;
use Reservations\Utils;

class EventType
{
    public static function all()
    {
        return Reservations::instance()->getEventTypes();
    }

    public static function find($id)
    {
        return self::all()[$id] ?? null;
    }

    public static function getDefault()
    {
        return Utils::getFirstElement(self::all());
    }
}
