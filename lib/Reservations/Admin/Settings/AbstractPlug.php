<?php

namespace Reservations\Admin\Settings;

use Reservations\Utils\PluginAccess;

abstract class AbstractPlug
{
    use PluginAccess;

    public $name;
    public $sName;

    public function register()
    {
        $this->sName = $this->plugin->prefix($this->name);

        register_setting($this->plugin->slug(), $this->sName, [
            "sanitize_callback" => [$this, "sanitize"],
        ]);

        add_filter("pre_update_option_" . $this->sName, [$this, "beforeSave"], 10, 2);
        add_action("update_option_" . $this->sName, [$this, "afterSave"], 10, 2);

        $this->plugin->addHooks($this);
    }

    public function prepare()
    {
    }

    public function enqueue()
    {
    }

    public function sanitize($value)
    {
        return $value;
    }

    public function beforeSave($value, $oldValue)
    {
        if ($value == $oldValue || maybe_serialize($value) === maybe_serialize($oldValue)) {
            $this->afterSave($oldValue, $value);
        }

        return $value;
    }

    public function afterSave($oldValue, $value)
    {

    }

    abstract public function render();
}
