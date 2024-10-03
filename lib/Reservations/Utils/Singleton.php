<?php

namespace Reservations\Utils;

trait Singleton
{
    protected static $_instance;

    public static function instance()
    {
        if (empty(static::$_instance)) {
            static::$_instance = new static;
        }

        return static::$_instance;
    }
}
