<?php

namespace Reservations\Utils;

use Reservations;

class Compatibility
{
    use PluginAccess;

    public function init()
    {
        $this->plugin->addHooks($this);
    }

    /** @action(init) */
    public function removeViewportHeader()
    {
        if (Reservations::MODE !== "lead") {
            return;
        }

        remove_action('wp_head', 'mk_head_meta_tags', 1);
    }

    /**
     * @action(wp_head)
     * @priority(1)
     */
    public function overrideWpHead()
    {
        if (Reservations::MODE !== "lead") {
            return;
        }

        echo "\n";
        echo '<meta charset="' . get_bloginfo('charset') . '" />' . "\n";

        if (!$this->plugin->pageRouter->isActive()) {
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=0" />' . "\n";
        }

        echo '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />' . "\n";
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>' . "\n";
        echo '<meta name="format-detection" content="telephone=no">' . "\n";
    }

    /**
     * @action(init)
     */
    public function enableErrors()
    {
        return;

        if (Reservations::MODE !== "lead") {
            return;
        }

        @error_reporting(E_ALL);
        @ini_set("display_errors", 1);
    }
}
