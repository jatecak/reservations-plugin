<?php

namespace Reservations\Models\Local;

use Reservations;
use Reservations\Utils;

class AgeGroup
{
    public static function all()
    {
        return Reservations::instance()->getAgeGroups();
    }

    public static function getTree()
    {
        return Reservations::instance()->getAgeGroupTree();
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
