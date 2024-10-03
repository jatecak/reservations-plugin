<?php

namespace Reservations\Models;

use Reservations;
use Reservations\PostTypes;
use Reservations\Taxonomies;
use Reservations\Utils;

class Training extends Event
{
    protected static $postType = PostTypes\Training::NAME;

    protected $cached = [
        "startTime", "endTime", "weekday", "ageGroup", "description", "contactEmail", "contactPhone", "priceSingle", "contactInstructor",
    ];

    /* Relationships */

    public function gym()
    {
        $taxonomy = $this->termTaxonomies()->where("taxonomy", Taxonomies\Gym::NAME)->first();

        if ($taxonomy) {
            return Gym::find($taxonomy->term_id);
        }

        return null;
    }

    public function trainingGroup()
    {
        $taxonomy = $this->termTaxonomies()->where("taxonomy", Taxonomies\TrainingGroup::NAME)->first();

        if ($taxonomy) {
            return TrainingGroup::find($taxonomy->term_id);
        }

        return null;
    }

    /* Scopes */

    // scopeInCity is defined in Models\Event

    public function scopeInGym($builder, $gymId)
    {
        return $builder->inTaxonomy(Taxonomies\Gym::NAME, $gymId);
    }

    public function scopeInTrainingGroup($builder, $tgroupId)
    {
        return $builder->inTaxonomy(Taxonomies\TrainingGroup::NAME, $tgroupId);
    }

    public function scopeSearchQuery($builder, $q, $searchGym = false)
    {
        $builder->where("post_title", "like", "%" . $q . "%");

        if ($searchGym) {
            $matchedGyms = Gym::searchQuery($q, true);
            $builder->orWhereHas("termTaxonomies", function ($builder) use ($matchedGyms) {
                $builder->where("taxonomy", Taxonomies\Gym::NAME)->whereIn("term_id", $matchedGyms->pluck("id"));
            });
        }
    }

    /* Attributes */

    /** @deprecated */
    public function getStartTimeAttribute()
    {
        return Utils::parseTime($this->getMeta(Reservations::PREFIX . "start_time"));
    }

    /** @deprecated */
    public function getEndTimeAttribute()
    {
        return Utils::parseTime($this->getMeta(Reservations::PREFIX . "end_time"));
    }

    /** @deprecated */
    public function getWeekdayAttribute()
    {
        return (int) $this->getMeta(Reservations::PREFIX . "weekday");
    }

    public function getTimeslotsAttribute()
    {
        $timeslots = $this->getPrefixedMeta("timeslots", null);

        // COMPAT
        if (is_null($timeslots)) {
            $timeslots = [
                [
                    "weekday"    => $this->weekday,
                    "start_time" => $this->startTime,
                    "end_time"   => $this->endTime,
                ],
            ];
        }

        return $timeslots;
    }

    public function getPriceSingleAttribute()
    {
        $price = (int) $this->getPrefixedMeta("price_single");

        if (!$price) {
            $price = $this->gym()->priceSingle[$this->ageGroup];
        }

        return $price;
    }

    public function getAgeGroupAttribute()
    {
        return (int) $this->getPrefixedMeta("age_group");
    }

    public function getEnabledAgeGroupsAttribute()
    {
        $enabledAgeGroupIds = $this->getPrefixedMeta("enabled_age_groups") ?: [];

        $enabledAgeGroups = [];
        foreach ($enabledAgeGroupIds as $id) {
            $group = Local\AgeGroup::find($id);

            if ($group) {
                $enabledAgeGroups[$group["id"]] = $group;
            }

        }
        return $enabledAgeGroups;
    }

    // public function getPermalinkAttribute()
    // {
    //     $permalink = get_permalink(Reservations::instance()->pageRouter->trainingsPage);

    //     return Utils::joinPaths($permalink, $this->gym->slug, $this->id, "/");
    // }
}
