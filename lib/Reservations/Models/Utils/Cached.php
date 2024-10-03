<?php

namespace Reservations\Models\Utils;

trait Cached
{
    protected $_cache = [];

    public static function bootCached()
    {
        static::hook("getAttribute", function ($next, $value, $args) {
            if (!property_exists($this, "cached")) {
                return $next($value, $args);
            }

            $key = $args->get("key");

            if (isset($this->_cache[$key])) {
                return $this->_cache[$key];
            }

            $value = $next($value, $args);

            if (in_array($key, $this->cached)) {
                $this->_cache[$key] = $value;
            }

            return $value;
        });

        static::hook("setAttribute", function ($next, $value, $args) {
            if (!property_exists($this, "cached")) {
                return $next($value, $args);
            }

            $key = $args->get("key");

            $value = $next($value, $args);

            if (in_array($key, $this->cached)) {
                $this->_cache[$key] = $value;
            }

            return $value;
        });
    }
}
