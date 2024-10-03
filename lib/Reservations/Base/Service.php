<?php

namespace Reservations\Base;

use Reservations\Utils;

class Service
{
    use Utils\PluginAccess;

    public function init()
    {
        $this->plugin->addHooks($this);
    }
}
