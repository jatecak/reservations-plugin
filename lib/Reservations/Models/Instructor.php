<?php

namespace Reservations\Models;

use Illuminate\Database\Eloquent\Builder;
use Reservations;

class Instructor extends User
{
    protected $appends = ["displayName"];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope("isInstructor", function (Builder $builder) {
            $builder->whereHas("meta", function (Builder $builder) {
                $builder->where("meta_key", Reservations::PREFIX . "is_instructor")->where("meta_value", 1);
            });
        });
    }

    public function getDisplayNameAttribute()
    {
        $nick      = $this->getPrefixedMeta("nickname");
        $firstName = $this->getMeta("first_name");
        $lastName  = $this->getMeta("last_name");

        if ($nick) {
            return $firstName . " ‚" . $nick . "‘ " . $lastName;
        } else if (empty($firstName) && empty($lastName)) {
            return $this->login;
        } else {
            return $firstName . " " . $lastName;
        }
    }

    public function getExperienceAttribute()
    {
        return $this->getMeta(Reservations::PREFIX . "experience");
    }

    public function getContactEmailAttribute()
    {
        return $this->getMeta(Reservations::PREFIX . "contact_email");
    }

    public function getContactPhoneAttribute()
    {
        return $this->getMeta(Reservations::PREFIX . "contact_phone");
    }
}
