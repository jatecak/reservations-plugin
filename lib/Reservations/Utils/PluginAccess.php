<?php

namespace Reservations\Utils;

use \Reservations\Base\Plugin;

trait PluginAccess
{
    protected $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }
}
