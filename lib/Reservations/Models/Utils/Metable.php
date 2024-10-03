<?php

namespace Reservations\Models\Utils;

use Nette\Utils\Strings;
use Reservations;
use Reservations\Utils;

trait Metable
{
    public function getPrefixedMetadata($unserialize = false)
    {
        $allMetadata = $this->getMetadata();
        $metadata    = [];
        foreach ($allMetadata as $key => $values) {
            if (Strings::startsWith($key, Reservations::PREFIX)) {
                $metadata[substr($key, strlen(Reservations::PREFIX))] = $unserialize ? array_map("maybe_unserialize", $values) : $values;
            }
        }
        return $metadata;
    }

    public function getPrefixedMeta($key, $default = null, $single = true)
    {
        return $this->getMeta(Reservations::instance()->prefix($key), $default, $single);
    }

    public function setPrefixedMeta($key, $value)
    {
        return $this->setMeta(Reservations::instance()->prefix($key), $value);
    }

    public function getPrefixedMetaBulk($keys, $default = null)
    {
        $prefixedToNormal = [];
        $prefixed         = [];

        foreach ($keys as $key) {
            $p = $prefixed[] = Reservations::instance()->prefix($key);

            $prefixedToNormal[$p] = $key;
        }

        $meta   = $this->getMetaBulk($prefixed);
        $values = [];

        foreach ($meta as $key => $value) {
            if (!isset($prefixedToNormal[$key])) {
                continue;
            }

            $values[$prefixedToNormal[$key]] = $value;
        }

        return $values;
    }

    public function setPrefixedMetaBulk($pairs)
    {
        $this->setMetaBulk(Utils\Arrays::prefixKeys($pairs, Reservations::PREFIX));

        return $this;
    }

    public function scopePrefixedMetaValue($builder, $key, $value, $op = "=")
    {
        $builder->metaValue(Reservations::instance()->prefix($key), $value, $op);
    }
}
