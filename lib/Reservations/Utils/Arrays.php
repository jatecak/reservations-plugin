<?php

namespace Reservations\Utils;

use Nette\Utils\Strings;

class Arrays
{
    public static function allSet($array, $keys)
    {
        foreach ($keys as $key) {
            if (!isset($array[$key])) {
                return false;
            }
        }

        return true;
    }

    public static function makePairs($array, $keyProperty, $valueProperty)
    {
        $pairs = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $pairs[$value[$keyProperty]] = $value[$valueProperty];
            } else if (is_object($value)) {
                $pairs[$value->{$keyProperty}] = $value->{$valueProperty};
            } else {
                $pairs[$key] = $value;
            }
        }

        return $pairs;
    }

    public static function getFirstElement($array)
    {
        if (!count($array)) {
            return null;
        }

        return $array[array_keys($array)[0]];
    }

    public static function defaults($array, $defaults = [])
    {
        $array = (array) $array;

        return wp_parse_args($array, $defaults);
    }

    public static function fillDefaults(&$array, $defaults = [])
    {
        $array = self::defaults($array, $defaults);
    }

    public static function ucFirst($array)
    {
        return collect($array)->map(function ($value) {
            return Strings::firstUpper($value);
        })->toArray();
    }

    public static function mirror($array, $flip = false)
    {
        $result = [];
        foreach ($array as $key => $value) {
            if ($flip) {
                $result[$key] = $key;
            } else {
                $result[$value] = $value;
            }
        }
        return $result;
    }

    public static function rotate(&$arr)
    {
        $keys = array_keys($arr);
        $val  = $arr[$keys[0]];
        unset($arr[$keys[0]]);
        $arr[$keys[0]] = $val;
    }

    public static function removeElement(&$arr, $el, $strict = true)
    {
        if (($key = array_search($el, $arr, $strict)) !== false) {
            unset($arr[$key]);
        }
    }

    public static function getNextElement($arr, $el, $strict = true)
    {
        $keys = array_keys($arr);

        if (($key = array_search($el, $arr, $strict)) !== false) {
            $index = array_search($key, $keys, true);

            if ($index < count($keys) - 1) {
                return $arr[$keys[$index + 1]];
            }
        }

        return null;
    }

    public static function prefixKeys($arr, $prefix)
    {
        $newArr = [];

        foreach ($arr as $key => $value) {
            $newArr[$prefix . $key] = $value;
        }

        return $newArr;
    }

    public static function arrayToPairs($values, $pairs)
    {
        $arr = [];

        foreach ($values as $value) {
            $arr[$value] = $pairs[$value] ?? $value;
        }

        return $arr;
    }

    public static function removePrefixedKeys($arr, $prefix)
    {
        $newArr = [];

        foreach ($arr as $key => $value) {
            if (!Strings::startsWith($key, "_")) {
                $newArr[$key] = $value;
            }
        }

        return $newArr;
    }

    public static function pick($arr, $keys)
    {
        return array_intersect_key($arr, array_flip($keys));
    }

}
