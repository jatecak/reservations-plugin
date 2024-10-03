<?php

namespace Reservations\Models\Wordpress;

use Sofa\Eloquence;
use \Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    use Eloquence\Eloquence, Eloquence\Mappable;

    protected $table      = 'options';
    protected $primaryKey = 'option_id';
    public $timestamps    = false;

    protected $fillable = [
        'option_name', 'option_value', 'autoload',
    ];

    /* Attributes */

    public function getOptionValueAttribute($value)
    {
        return maybe_unserialize($value);
    }

    public function setOptionValueAttribute($value)
    {
        $this->attributes["option_value"] = maybe_serialize($value);
    }

    public function getRawOptionValueAttribute($value)
    {
        return $this->attributes["option_value"];
    }

    public function setRawOptionValueAttribute($value)
    {
        $this->attributes["option_value"] = $value;
    }
}
