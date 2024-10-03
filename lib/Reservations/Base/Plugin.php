<?php

namespace Reservations\Base;

use Illuminate\Database\Capsule\Manager as Capsule;
use Nette\Reflection;
use Reservations\Models\Wordpress\Option;
use Reservations\Utils\Singleton;

abstract class Plugin
{
    use Singleton;

    private function initDatabase()
    {
        global $wpdb;

        $capsule = new Capsule;

        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => DB_HOST,
            'database'  => DB_NAME,
            'username'  => DB_USER,
            'password'  => DB_PASSWORD,
            'charset'   => DB_CHARSET,
            'collation' => DB_COLLATE ?: (DB_CHARSET . "_unicode_ci"),
            'prefix'    => $wpdb->prefix,
        ]);

        if (static::DEBUG) {
            $capsule->getConnection()->enableQueryLog();
        }

        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    public function loadTextdomain()
    {
        load_plugin_textdomain('reservations', false, 'reservations/languages');
    }

    public function getOption($name, $default = null)
    {
        return get_option($this->prefix($name), $default);
    }

    public function updateOption($name, $value)
    {
        update_option($this->prefix($name), $value);
    }

    public function getOptions($unserialize = false)
    {
        $options = [];

        foreach (Option::where("option_name", "LIKE", static::PREFIX . "%")->get() as $option) {
            $name = substr($option->option_name, strlen(static::PREFIX));

            $options[$name] = $unserialize ? $option->option_value : $option->raw_option_value;
        }

        return $options;
    }

    public function path($path = null)
    {
        return realpath(static::ABSPATH) . ($path !== null ? "/" . ltrim($path, "/") : "");
    }

    public function url($path = null)
    {
        return plugins_url($path, $this->path("index.php"));
    }

    public function prefix($str = "", $withSlug = false)
    {
        return ($withSlug ? static::SLUG : static::PREFIX) . $str;
    }

    public function slug($str = "")
    {
        return $this->prefix($str, true);
    }

    public function addHooks($obj)
    {
        $refClass   = Reflection\ClassType::from(get_class($obj));
        $refMethods = $refClass->getMethods(Reflection\Method::IS_PUBLIC);

        foreach ($refMethods as $method) {
            $annotations = $method->annotations;

            $priority = isset($annotations["priority"]) ? (int) end($annotations["priority"]) : 10;

            if (isset($annotations["filter"])) {
                foreach ($annotations["filter"] as $hook) {
                    add_filter($hook, [$obj, $method->name], $priority, $method->numberOfParameters);
                }
            }

            if (isset($annotations["action"])) {
                foreach ($annotations["action"] as $hook) {
                    add_action($hook, [$obj, $method->name], $priority, $method->numberOfParameters);
                }
            }

            if (isset($annotations["shortcode"])) {
                foreach ($annotations["shortcode"] as $hook) {
                    add_shortcode($hook, [$obj, $method->name]);
                }
            }
        }
    }

    public function activate()
    {

    }

    public function run()
    {
        $this->addHooks($this);

        $this->initDatabase();
        $this->loadTextdomain();

        register_activation_hook($this->path($this->slug() . ".php"), [$this, "activate"]);
    }
}
