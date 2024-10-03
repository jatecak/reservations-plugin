<?php

namespace Reservations\Models;

use Illuminate\Database\Eloquent\Builder;
use Reservations;
use Reservations\Models\Utils\Cached;
use Reservations\Models\Utils\Metable;
use Reservations\Taxonomies;

class City extends Wordpress\Term
{
    use Cached, Metable;

    protected $appends = ["lat", "lng"];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope("isCity", function (Builder $builder) {
            $builder->whereHas("termTaxonomy", function (Builder $builder) {
                $builder->where("taxonomy", Taxonomies\City::NAME);
            });
        });
    }

    /* Relationships */

    public function trainings()
    {
        return Training::inCity($this->id)->get();
    }

    public function events()
    {
        return Event::inCity($this->id)->get();
    }

    public function gyms()
    {
        return Gym::inCity($this->id)->get();
    }

    /* Scopes */

    public function scopeAccessible($builder, $user = null)
    {
        if (is_null($user)) {
            $user = User::current();
        }

        $accessibleCities = $user->getAccessibleCities(true);
        $builder->whereIn("term_id", $accessibleCities);
    }

    public function scopeSearchQuery($builder, $q)
    {
        $builder->where("name", "like", "%" . $q . "%");
    }

    public function scopeSortByName($builder, $order = "ASC")
    {
        $builder->orderBy("name", $order);
    }

    /* Attributes */

    public function getLatAttribute()
    {
        return (float) $this->getMeta(Reservations::PREFIX . "lat");
    }

    public function getLngAttribute()
    {
        return (float) $this->getMeta(Reservations::PREFIX . "lng");
    }

    public function getTrainingGroupsAttribute()
    {
        return $this->trainings()->map(function ($training) {
            return $training->trainingGroup();
        })->filter()->uniqueStrict(function ($tgroup) {
            return $tgroup->term_id;
        })->all();
    }

}
