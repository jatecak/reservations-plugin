<?php

namespace Reservations\Models\Wordpress\Base;

use Illuminate\Database\Eloquent\Model;
use Reservations\Model\Wordpress;
use Sofa\Eloquence;

abstract class Meta extends Model
{
    use Eloquence\Eloquence, Eloquence\Mappable;

    protected $primaryKey = 'meta_id';
    public $timestamps    = false;

    protected $maps = [
        "key"   => "meta_key",
        "value" => "meta_value",
    ];

    protected $fillable = ["meta_key", "meta_value"];

    public function getMetaValueAttribute($value)
    {
        return maybe_unserialize($value);
    }

    public function setMetaValueAttribute($value)
    {
        $this->attributes["meta_value"] = maybe_serialize($value);
    }

    public function getRawMetaValueAttribute($value)
    {
        return $this->attributes["meta_value"];
    }

    public function setRawMetaValueAttribute($value)
    {
        $this->attributes["meta_value"] = $value;
    }
}
