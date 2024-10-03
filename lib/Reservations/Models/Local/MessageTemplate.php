<?php

namespace Reservations\Models\Local;

use Reservations;

class MessageTemplate
{
    public static function all()
    {
        $plugin = Reservations::instance();

        $templates = $plugin->getOption("message_templates", []);

        if (!is_array($templates)) {
            $templates = [];
        }

        return $templates;
    }

    public static function find($id)
    {
        return self::all()[$id] ?? null;
    }
}
