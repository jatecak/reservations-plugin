<?php

namespace Reservations\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Reservations;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Local\SubscriptionType;
use Reservations\Models\Utils\Cached;
use Reservations\Models\Utils\Metable;
use Reservations\Models\Utils\PaymentTemplates;
use Reservations\PostTypes;
use Reservations\Taxonomies;
use Reservations\Utils;
use Reservations\Utils\SubscriptionTypes;

class TrainingGroup extends Wordpress\Term
{
    use Cached, Metable, PaymentTemplates;

    protected $table      = 'terms';
    protected $primaryKey = 'term_id';

    protected $appends = [

    ];

    protected $cached = [
        "priceSingle",
        "enabledSubscriptionTypes",
        "capacity",
        "termPeriods",
        "activeTerm",
        "activeTermNoAdvance",
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope("isTrainingGroup", function (Builder $builder) {
            $builder->whereHas("termTaxonomy", function (Builder $builder) {
                $builder->where("taxonomy", Taxonomies\TrainingGroup::NAME);
            });
        });
    }

    /* Relationships */

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, "tgroup_id");
    }

    public function activeSubscriptions()
    {
        return $this->subscriptions()->active()->paid();
    }

    public function trainings()
    {
        return $this->posts()->get()->map(function ($post) {
            return Training::find($post->ID);
        });
    }

    /* Scopes */

    public function scopeAccessible($builder, $user = null)
    {
        if (is_null($user)) {
            $user = User::current();
        }

        $accessibleTgroups = $user->getAccessibleTrainingGroups(true);
        $builder->whereIn("term_id", $accessibleTgroups);
    }

    public function scopeSearchQuery($builder, $q, $searchTrainings = false)
    {
        $builder->where("name", "like", "%" . $q . "%");

        if ($searchTrainings) {
            //$matchedTrainings = Training::searchQuery($q, true);
            // TODO
        }
    }

    public function scopeSortByName($builder, $order = "ASC")
    {
        $builder->orderBy("name", $order);
    }

    /* Attributes */

    public function getPriceSingleAttribute()
    {
        return $this->getPrefixedMeta("price_single");
    }

    public function getCapacityAttribute()
    {
        return (int) $this->getPrefixedMeta("capacity");
    }

    public function getPasswordAttribute()
    {
        return $this->getPrefixedMeta("password", "");
    }

    public function getEnabledSubscriptionTypesAttribute()
    {
        $adminEnabledTypes = $this->getPrefixedMeta("enabled_subscription_types", []);

        $self = $this;
        return collect($adminEnabledTypes)->filter(function ($type) use ($self) {
            if ($type === SubscriptionType::ANNUAL) {
                if (!Reservations::instance()->isFeatureEnabled("annual_subscription")) {
                    return false;
                }

                $defaultDuration = Reservations::instance()->getOption("year_duration");

                if ($defaultDuration["months"] === 0 && $defaultDuration["days"] === 0 && !$this->activeYear) {
                    return false;
                }
            } else if ($type === SubscriptionType::BIANNUAL) {
                $defaultDuration = Reservations::instance()->getOption("term_duration");

                if ($defaultDuration["months"] === 0 && $defaultDuration["days"] === 0 && !$this->activeTerm) {
                    return false;
                }
            } else if ($type === SubscriptionType::MONTHLY && Reservations::instance()->isFeatureEnabled("limit_monthly_to_term")) {
                $minEndDate = Utils::today()->addMonths(1);
                $maxEndDate = Utils::getMaxMonthlySubscriptionEndDate($this);

                if (!$maxEndDate || $minEndDate->gt($maxEndDate)) {
                    return false;
                }
            }

            return $self->getPaymentAmount(null, $type, 1) > 0;
        })->all();
    }

    public function getSubscriptionEnabledAttribute()
    {
        return Reservations::instance()->isSubscriptionEnabled(Local\ObjectType::TRAININGS) && count($this->enabledSubscriptionTypes) > 0;
    }

    public function getTermPeriodsAttribute()
    {
        return ((array) $this->getPrefixedMeta("term_periods")) ?: [];
    }

    public function getActiveTermAttribute()
    {
        $terms         = $this->termPeriods;
        $termsAdvanced = Utils::advanceTermPeriods($terms);

        $today = Carbon::today(Utils::getTimezone());

        $termIndex = collect($termsAdvanced)->reverse()->search(function ($term) use ($today) {
            return $term[0]->lte($today) && $term[1]->gte($today);
        });

        if ($termIndex !== false) {
            return $terms[$termIndex];
        }

        $termIndex = collect($termsAdvanced)->search(function ($term) use ($today) {
            return $term[0]->gte($today) && $term[1]->gte($today);
        });

        return $termIndex !== false ? $terms[$termIndex] : null;
    }

    public function getActiveTermNoAdvanceAttribute()
    {
        $terms = $this->termPeriods;
        $today = Utils::today();

        return collect($terms)->reverse()->first(function ($term) use ($today) {
            return $term[0]->lte($today) && $term[1]->gte($today);
        });
    }

    public function getActiveYearAttribute()
    {
        $year = $this->getPrefixedMeta("year", []);

        if (!is_array($year) || count($year) < 2) {
            $termPeriods = $this->termPeriods;

            if (count($termPeriods) === 0) {
                return null;
            }

            $dates = [];
            foreach ($termPeriods as $period) {
                $dates[] = $period[0];
                $dates[] = $period[1];
            }

            sort($dates);

            return [
                $dates[0],
                $dates[count($dates) - 1],
            ];
        }

        return [
            $year[0],
            $year[1],
        ];
    }

    public function getPaymentTemplatesAttribute()
    {
        return $this->getPrefixedMeta("payment_templates", []);
    }

    public function getAttachmentSetsAttribute()
    {
        return $this->getPrefixedMeta("attachment_sets", []);
    }

    public function getCustomSubscribeUrlAttribute()
    {
        return $this->getPrefixedMeta("custom_subscribe_url", "");
    }

    public function getSubscribeLinkAttribute()
    {
        $permalink = get_permalink(Reservations::instance()->pageRouter->trainingsPage);

        return Utils::joinPaths($permalink, $this->slug, _x('subscribe', 'url slug', 'reservations'), "/");
    }

    public function getEditLinkAttribute()
    {
        return get_edit_term_link($this->id, Taxonomies\TrainingGroup::NAME, PostTypes\Training::NAME);
    }

    public function getGymsAttribute()
    {
        return $this->trainings()->map(function ($training) {
            return $training->gym();
        })->filter()->uniqueStrict(function ($gym) {
            return $gym->term_id;
        })->values()->all();
    }

    public function getCitiesAttribute()
    {
        return $this->posts()->with(["termTaxonomies" => function ($builder) {
            $builder->where("taxonomy", Taxonomies\City::NAME);
        }])->distinct("termTaxonomies.term_id")->get()->map(function ($training) {
            if (!count($training->termTaxonomies)) {
                return null;
            }

            return City::find($training->termTaxonomies[0]->term_id);
        })->filter()->all();
    }

    public function getAgeGroupsAttribute()
    {
        return $this->trainings()->pluck("ageGroup")->uniqueStrict()->map(function ($ageGroup) {
            return Local\AgeGroup::find($ageGroup);
        })->filter()->values()->all();
    }

    /* Methods */

    public function getActiveSubscriptionCount($dateFrom, $dateTo = null)
    {
        if (is_null($dateTo)) {
            $dateTo = $dateFrom;
        }

        $activeSubscriptions = $this->subscriptions()->where("paid", true);

        $activeSubscriptions = $activeSubscriptions->get()->filter(function ($sub) use ($dateFrom, $dateTo) {
            return $dateFrom->lte($sub->date_to) && $dateTo->gte($sub->date_from);
        });

        $datesToCheck = [$dateFrom, $dateTo];

        foreach ($activeSubscriptions as $sub) {
            if ($sub->date_from->gt($dateFrom)) {
                $datesToCheck[] = $sub->date_from;
            }

            if ($sub->date_to->lt($dateTo)) {
                $datesToCheck[] = $sub->date_to;
            }
        }

        $maxCount = 0;

        foreach ($datesToCheck as $date) {
            $count = $activeSubscriptions->filter(function ($sub) use ($date) {
                return $sub->date_from->lte($date) && $sub->date_to->gte($date);
            })->count();

            $maxCount = max($count, $maxCount);
        }

        return $maxCount;
    }

    public function getFreeCapacity($dateFrom = null, $dateTo = null)
    {
        if (!is_null($dateTo) && $dateTo->lt($dateFrom)) {
            $dateTo = null;
        }

        if (is_null($dateFrom)) {
            $dateFrom = Utils::today();

            $enabledSubscriptionTypes = $this->enabledSubscriptionTypes;

            if (in_array(SubscriptionType::MONTHLY, $enabledSubscriptionTypes)) {
                if (is_null($dateTo)) {
                    $dateTo = (clone $dateFrom)->addMonths(1);
                }
            } else if (in_array(SubscriptionType::BIANNUAL, $enabledSubscriptionTypes)) {
                if ($this->activeTerm) {
                    $dateFrom = $dateFrom->max($this->activeTerm[0]);

                    if (is_null($dateTo)) {
                        $dateTo = $this->activeTerm[1];
                    }
                }
            } else if (in_array(SubscriptionType::ANNUAL, $enabledSubscriptionTypes)) {
                if ($this->activeYear) {
                    $dateFrom = $dateFrom->max($this->activeYear[0]);

                    if (is_null($dateTo)) {
                        $dateTo = $this->activeYear[1];
                    }
                }
            }
        }

        return max(0, $this->capacity - $this->getActiveSubscriptionCount($dateFrom, $dateTo));
    }

    public function getEmailVariables()
    {
        $variables = [];

        $variables["trainingGroup"] = $this->name;

        $gyms = $this->gyms;

        // if (count($gyms) === 1) {
        //     $gym  = Utils\Arrays::getFirstElement($gyms);
        //     $city = $gym->city;

        //     $variables["gym"]            = $gym->name;
        //     $variables["gymWithCity"]    = $gym->names["with_city"];
        //     $variables["gymWithAddress"] = $gym->names["with_address"];
        // } else {
        //     $cities = collect($gyms)->pluck("city")->uniqueStrict(function ($gym) {
        //         return $gym->id;
        //     })->all();

        //     if (count($cities) === 1) {
        //         $city = Utils::getFirstElement($cities);
        //     }
        // }

        if (count($gyms) > 0) {
            $gym  = Utils\Arrays::getFirstElement($gyms);
            $city = $gym->city;

            $variables["gym"]            = $gym->name;
            $variables["gymWithCity"]    = $gym->names["with_city"];
            $variables["gymWithAddress"] = $gym->names["with_address"];
            $variables["city"]           = $city->name;
            $variables["addresss"]       = $gym->names["with_address"];
        }

        $ageGroups = $this->ageGroups;

        if (count($ageGroups) > 0) {
            $ageGroup = Utils\Arrays::getFirstElement($ageGroups);

            $variables["ageGroup"]   = Utils::getAgeGroupPath($ageGroup);
            $variables["ageGroupLC"] = Utils::getAgeGroupPath($ageGroup, "labelLC");
        }

        $subscribedTrainings = Utils::formatSubscribedTrainings($this->trainings());
        $basePermalink       = get_permalink(Reservations::instance()->pageRouter->trainingsPage);

        $html = '<ul>';
        foreach ($subscribedTrainings as $gymName => $trainings) {
            $html .= '<li><strong>' . esc_html($gymName) . '</strong><br><ul>';
            foreach ($trainings as $training) {
                $permalink = Utils::joinPaths($basePermalink, $training->_gym->slug, $training->id, "/");

                $html .= '<li><a href="' . esc_url($training->permalink) . '">' . esc_html($training->title) . '</a> &ndash; ' . __('age group:', 'reservations') . ' ' . $training->ageGroupLabel . ' &ndash; ' . $training->timeText . '</li>';
            }
            $html .= '</ul></li>';
        }
        $html .= '</ul>';

        $variables["subscribedTrainings"] = $html;

        return $variables;
    }
}
