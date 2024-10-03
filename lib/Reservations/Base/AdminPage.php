<?php

namespace Reservations\Base;

abstract class AdminPage extends Page
{
    public $slug;
    public $hookSuffix;

    public function actionEnqueueScripts()
    {}

    public function filterTheTitle($title, $id)
    {
        return $title;
    }

    public function filterTheContent($content)
    {
        return $content;
    }

    public function handleShortcode($atts) {
        return "";
    }

    public function register()
    {

    }

    public function isCurrent()
    {
        if (!function_exists("get_current_screen")) {
            return false;
        }

        $screen = get_current_screen();

        if ($screen->base !== $this->hookSuffix) {
            return false;
        }

        return true;
    }

    /** @action(admin_menu)  */
    public function actionAdminMenu()
    {
        $hookSuffix = $this->register();

        if (!$this->hookSuffix) {
            $this->hookSuffix = $hookSuffix;
        }

        if ($this->hookSuffix) {
            add_action("load-" . $this->hookSuffix, [$this, "prepare"]);
        }
    }

    /** @action(admin_enqueue_scripts) */
    public function actionAdminEnqueueScripts()
    {
        if (!$this->isCurrent()) {
            return;
        }

        $this->assets();
    }
}
