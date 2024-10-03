<?php

namespace Reservations\Models;

use Reservations;
use Reservations\Models\Utils\Cached;
use Reservations\Models\Utils\Metable;
use Reservations\Taxonomies;

class User extends Wordpress\User
{
    use Cached, Metable;

    protected $cached = [
        "accessibleCityIds", "accessibleCities", "accessibleGyms", "accessibleTrainingGroups",
    ];

    private $canAccessCache = [];

    private static $currentUser;

    public static function current()
    {
        $uid = get_current_user_id();

        if ($uid === 0) {
            return null;
        }

        if (self::$currentUser && self::$currentUser->id === $uid) {
            return self::$currentUser;
        }

        $user = self::$currentUser = User::find($uid);

        return $user;
    }

    /* Relationships */

    public function subscribers()
    {
        return $this->hasMany(Subscriber::class, "user_id");
    }

    /* Attributes */

    public function getAccessibleCityIdsAttribute()
    {
        return $this->getPrefixedMeta("accessible_city_ids", []);
    }

    public function getAccessibleCitiesAttribute()
    {
        if (count($this->accessibleCityIds) === 0 || $this->can("administrator")) {
            return City::all();
        }

        return City::whereIn("term_id", (array) $this->accessibleCityIds)->get();
    }

    public function getAccessibleGymsAttribute()
    {
        if (count($this->accessibleCityIds) === 0 || $this->can("administrator")) {
            return Gym::all();
        }

        return Gym::inCity($this->accessibleCityIds)->get();
    }

    public function getAccessibleTrainingGroupsAttribute()
    {
        if (count($this->accessibleCityIds) === 0 || $this->can("administrator")) {
            return TrainingGroup::all();
        }

        return Training::inCity($this->accessibleCityIds)->with(["termTaxonomies" => function ($builder) {
            $builder->where("taxonomy", Taxonomies\TrainingGroup::NAME);
        }])->distinct("termTaxonomies.term_id")->get()->map(function ($training) {
            if (!count($training->termTaxonomies)) {
                return null;
            }

            return TrainingGroup::find($training->termTaxonomies[0]->term_id);
        })->filter();
    }

    /* Methods */

    public function canAccess($resource)
    {
        if (count($this->accessibleCityIds) === 0 || $this->can("administrator")) {
            return true;
        }

        if ($resource instanceof Training) {
            return $this->canAccess($resource->city);
        } else if ($resource instanceof Subscription) {
            return $this->canAccess($resource->object);
        } else if ($resource instanceof Gym) {
            if (isset($this->canAccessCache[$resource->id])) {
                return $this->canAccessCache[$resource->id];
            }

            return ($this->canAccessCache[$resource->id] = $this->canAccess($resource->city));
        } else if ($resource instanceof City) {
            if (isset($this->canAccessCache[$resource->id])) {
                return $this->canAccessCache[$resource->id];
            }

            return ($this->canAccessCache[$resource->id] = in_array($resource->id, $this->accessibleCityIds));
        } else if ($resource instanceof Event) {
            return $this->canAccess($resource->city());
        } else if ($resource instanceof TrainingGroup) {
            if (isset($this->canAccessCache[$resource->id])) {
                return $this->canAccessCache[$resource->id];
            }

            return ($this->canAccessCache[$resource->id] = $this->getAccessibleTrainingGroups(true)->contains($resource->id));
        }

        return false;
    }

    public function getAccessibleCities($returnIds = true)
    {
        if ($returnIds) {
            $accessibleCityIds = $this->accessibleCityIds;

            if (count($accessibleCityIds) === 0 || $this->can("administrator")) {
                return City::all()->pluck("id");
            } else {
                return collect($accessibleCityIds);
            }
        } else {
            return $this->accessibleCities;
        }
    }

    public function getAccessibleGyms($returnIds = false)
    {
        $gyms = $this->accessibleGyms;

        if ($returnIds) {
            return $gyms->pluck("term_id");
        }

        return $gyms;
    }

    public function getAccessibleTrainingGroups($returnIds = false)
    {
        $tgroups = $this->accessibleTrainingGroups;

        if ($returnIds) {
            return $tgroups->pluck("term_id");
        }

        return $tgroups;
    }
}
