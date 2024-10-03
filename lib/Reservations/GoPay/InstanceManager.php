<?php

namespace Reservations\GoPay;

use Reservations;
use Reservations\Base\Service;
use Reservations\Models;
use Reservations\Models\Local\ObjectType;

class InstanceManager extends Service
{
    private $instances = [];

    public function getGoPay($objectType, $eventType = null)
    {
        $key = $objectType;

        if ($objectType === ObjectType::EVENT) {
            $key .= "_" . $eventType["id"];
        }

        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        $settings = Models\Local\GoPaySettings::find($key);

        if (!$settings) {
            return null;
        }

        $instance = \GoPay\payments($settings);

        $this->instances[$key] = $instance;

        return $instance;
    }
}
