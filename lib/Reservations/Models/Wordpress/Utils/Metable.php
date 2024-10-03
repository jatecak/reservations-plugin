<?php

namespace Reservations\Models\Wordpress\Utils;

use Nette\Utils\Strings;
use Reservations\Models\Wordpress\Comment;
use Reservations\Models\Wordpress\Post;
use Reservations\Models\Wordpress\Term;
use Reservations\Models\Wordpress\User;

trait Metable
{
    // use Hookable;

    abstract public function meta();

    private function getMetaType()
    {
        $metaType = null;

        if ($this instanceof Post) {
            $metaType = "post";
        } else if ($this instanceof Term) {
            $metaType = "term";
        } else if ($this instanceof User) {
            $metaType = "user";
        } else if ($this instanceof Comment) {
            $metaType = "comment";
        }

        return $metaType;
    }

    public function getMetadata($unserialize = false)
    {
        $metaType = $this->getMetaType();
        $rowId    = $this->{$this->primaryKey};

        if ($metaType !== null) {
            $values = get_metadata($metaType, $rowId);
        } else {
            $query  = $this->meta()->get();
            $values = [];

            foreach ($query as $meta) {
                if (!isset($values[$meta->key])) {
                    $values[$meta->key] = [];
                }

                $values[$meta->key][] = $meta->value;
            }
        }

        if ($unserialize) {
            return array_map("maybe_unserialize", $values);
        } else {
            return $values;
        }
    }

    public function getMeta($key, $default = null, $single = true)
    {
        $meta = $this->getMetadata();

        if ($single) {
            return isset($meta[$key]) ? maybe_unserialize($meta[$key][0]) : $default;
        } else {
            return isset($meta[$key]) ? array_map("maybe_unserialize", $meta[$key]) : [];
        }
    }

    public function setMeta($key, $value)
    {
        $metaType = $this->getMetaType();
        $rowId    = $this->{$this->primaryKey};

        if ($metaType !== null) {
            return update_metadata($metaType, $rowId, $key, $value);
        }

        $meta             = $this->meta()->firstOrNew(['meta_key' => $key]);
        $meta->meta_value = $value;
        $meta->save();

        return $this;
    }

    public function getMetaBulk($keys, $default = null, $single = true)
    {
        $meta = $this->getMetadata();

        $values = [];
        foreach ($keys as $key) {
            if ($single) {
                $values[$key] = isset($meta[$key]) ? maybe_unserialize($meta[$key][0]) : $default;
            } else {
                $values[$key] = isset($meta[$key]) ? array_map("maybe_unserialize", $meta[$key]) : [];
            }
        }

        return $values;
    }

    public function setMetaBulk($pairs)
    {
        foreach ($pairs as $key => $value) {
            $this->setMeta($key, $value);
        }

        return $this;
    }

    public function scopeMetaValue($builder, $key, $value, $op = "=")
    {
        $builder->whereHas("meta", function ($builder) use ($key, $value, $op) {
            $builder->where("meta_key", $key);

            if (Strings::compare($op, "IN")) {
                $builder->whereIn("meta_value", $value);
            } else {
                $builder->where("meta_value", $op, $value);
            }
        });
    }

    public function newCollection(array $models = [])
    {
        $collection = parent::newCollection($models);
        $metaType   = $this->getMetaType();

        if ($metaType !== null) {
            update_meta_cache($this->getMetaType(), $collection->pluck($this->primaryKey)->all());
        }

        return $collection;
    }

    // public static function bootMetable() {
    //     static::hook("getAttribute", function($next, $value, $args) {
    //         $key = $args->get("key");
    //         $metaKey = $this->getMetaKeyForAttribute($key);

    //         if($metaKey !== null) {
    //             $value = $this->getMeta($metaKey);
    //         }

    //         return $next($value, $args);
    //     });

    // //     static::hook("setAttribute", function($next, $value, $args) {
    // //         $key = $args->get("key");
    // //         $metaKey = $this->getMetaKeyForAttribute($key);

    // //         if($metaKey !== null) {
    // //             return $this->setMeta($metaKey, $value);
    // //         }

    // //         return $next($value, $args);
    // //     });
    // }

    // protected function getMetaKeyForAttribute($key) {
    //     $metaKeys = property_exists($this, "metaKeys") ? $this->metaKeys : [];
    //     $prefixedMetaKeys = property_exists($this, "prefixedMetaKeys") ? $this->prefixedMetaKeys : [];
    //     $metaPrefix = property_exists($this, "metaPrefix") ? $this->metaPrefix : "";

    //     if(in_array($key, $metaKeys))
    //         return $key;

    //     if(in_array($key, $prefixedMetaKeys))
    //         return $metaPrefix.$key;

    //     return null;
    // }
}
