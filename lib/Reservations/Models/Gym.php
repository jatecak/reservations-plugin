<?php

namespace Reservations\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Reservations;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Utils\Cached;
use Reservations\Models\Utils\Metable;
use Reservations\Models\Utils\PaymentTemplates;
use Reservations\PostTypes;
use Reservations\Taxonomies;
use Reservations\Utils;
use Reservations\Utils\SubscriptionTypes;

class Gym extends Wordpress\Term
{
    use Cached, Metable, PaymentTemplates;

    protected $table      = 'terms';
    protected $primaryKey = 'term_id';

    protected $appends = [
        "lat",
        "lng",
    ];

    protected $cached = [
        "lat",
        "lng",
        "address",
        "priceSingle",
        "capacity",
        "monthlyEnabled",
        "biannualEnabled",
        "enabledAgeGroups",
        "termPeriods",
        "activeTerm",
        "names",
        "cityId",
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope("isGym", function (Builder $builder) {
            $builder->whereHas("termTaxonomy", function (Builder $builder) {
                $builder->where("taxonomy", Taxonomies\Gym::NAME);
            });
        });
    }

    /* Relationships */

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, "gym_id");
    }

    public function activeSubscriptions($ageGroup = null)
    {
        $query = $this->subscriptions()->active()->paid();

        if (!is_null($ageGroup)) {
            $query->ageGroup($ageGroup);
        }

        return $query;
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

        $accessibleGyms = $user->getAccessibleGyms(true);
        $builder->whereIn("term_id", $accessibleGyms);
    }

    public function scopeSearchQuery($builder, $q, $searchCity = false)
    {
        $builder->where("name", "like", "%" . $q . "%")
            ->orWhere(function ($builder) use ($q) {
                $builder->prefixedMetaValue("address", "%" . $q . "%", "like");
            });

        if ($searchCity) {
            $matchedCities = City::searchQuery($q)->get();
            $builder->orWhere(function ($builder) use ($matchedCities) {
                $builder->inCity($matchedCities->pluck("id")->all());
            });
        }
    }

    public function scopeInCity($builder, $cityId)
    {
        $builder->prefixedMetaValue("city_id", $cityId, is_array($cityId) ? "IN" : "=");
    }

    public function scopeSortByName($builder, $order = "ASC")
    {
        $builder->orderBy("name", $order);
    }

    /* Attributes */

    public function getCityAttribute()
    {
        return City::find((int) $this->getPrefixedMeta("city_id"));
    }

    public function setCityAttribute($city)
    {
        $this->setMeta(Reservations::PREFIX . "city_id", $city ? $city->id : null);
    }

    public function getCityIdAttribute()
    {
        return (int) $this->getPrefixedMeta("city_id");
    }

    public function getAddressAttribute()
    {
        return $this->getPrefixedMeta("address");
    }

    public function getLatAttribute()
    {
        return (float) $this->getPrefixedMeta("lat");
    }

    public function getLngAttribute()
    {
        return (float) $this->getPrefixedMeta("lng");
    }

    public function getPriceSingleAttribute()
    {
        return $this->getPrefixedMeta("price_single");
    }

    public function getPriceMonthlyAttribute()
    {
        return $this->getPrefixedMeta("price_monthly");
    }

    public function getPriceBiannualAttribute()
    {
        return $this->getPrefixedMeta("price_biannual");
    }

    public function getBiannualEnabledAttribute()
    {
        return (bool) $this->getPrefixedMeta("biannual_enable");
    }

    public function getMonthlyEnabledAttribute()
    {
        return (bool) $this->getPrefixedMeta("monthly_enable");
    }

    public function getCapacityAttribute()
    {
        return (int) $this->getPrefixedMeta("capacity");
    }

    public function getPasswordAttribute()
    {
        return $this->getPrefixedMeta("password", "");
    }

    public function getCustomSubscribeUrlAttribute()
    {
        return $this->getPrefixedMeta("custom_subscribe_url", "");
    }

    public function getEnabledAgeGroupsAttribute()
    {
        $gym = $this;

        return collect(Local\AgeGroup::all())->filter(function ($group) use ($gym) {
            return ($gym->biannualEnabled && $gym->getPaymentAmount($group, "biannual", 1) > 0) || ($gym->monthlyEnabled && $gym->getPaymentAmount($group, "monthly", 1) > 0);
        })->all();
    }

    public function getAgeGroupsAttribute()
    {
        return $this->trainings()->pluck("ageGroup")->uniqueStrict()->map(function ($ageGroup) {
            return Local\AgeGroup::find($ageGroup);
        })->filter()->all();
    }

    public function getTrainingGroupsAttribute()
    {
        return $this->trainings()->map(function ($training) {
            return $training->trainingGroup();
        })->filter()->uniqueStrict(function ($tgroup) {
            return $tgroup->term_id;
        })->all();
    }

    // public function getSubscriptionEnabledAttribute()
    // {
    //     return count($this->enabledAgeGroups) > 0 && Reservations::instance()->isSubscriptionEnabled(Local\ObjectType::TRAININGS);
    // }

    public function getTermPeriodsAttribute()
    {
        // if(Reservations::MODE !== "lubo") {
        //     return [];
        // }

        return ((array) $this->getPrefixedMeta("term_periods")) ?: [];
    }

    public function getActiveTermAttribute()
    {
        // if(Reservations::MODE !== "lubo") {
        //     return null;
        // }

        $terms   = $this->termPeriods;
        $advance = (int) Reservations::instance()->getOption("term_advance", "0");

        if (is_nan($advance) || $advance < 0) {
            $advance = 0;
        }

        $termsAdvanced = array_map(function ($term) use ($advance) {
            return [
                $term[0]->copy()->subDays($advance),
                $term[1],
            ];
        }, $terms);

        $today = Carbon::today(Utils::getTimezone());

        foreach (array_reverse($termsAdvanced, true) as $i => $term) {
            if ($term[0]->lte($today) && $term[1]->gte($today)) {
                return $terms[$i];
            }
        }

        return null;
    }

    public function getNamesAttribute()
    {
        $names = [];

        $names["default"]      = $this->name;
        $names["with_city"]    = $this->name . ($this->city ? ", " . $this->city->name : "");
        $names["with_address"] = $this->name . ", " . $this->address;

        return $names;
    }

    public function getPaymentTemplatesAttribute()
    {
        $templates = $this->getPrefixedMeta("payment_templates", []);

        // COMPAT
        if (!count($templates) && (count($this->priceMonthly) || count($this->priceBiannual))) {
            foreach (Local\AgeGroup::all() as $ageGroup) {
                $templates[] = [
                    "age_groups"                  => [$ageGroup["id"]],
                    "subscription_types"          => ["biannual"],
                    "amount"                      => $this->priceBiannual[$ageGroup["id"]] ?? 0,
                    "hash"                        => md5($ageGroup["id"] . "|" . "biannual"),
                    "confirmation_email_template" => 0,
                    "initial"                     => true,
                ];

                $templates[] = [
                    "age_groups"                  => [$ageGroup["id"]],
                    "subscription_types"          => ["monthly"],
                    "amount_monthly"              => $this->priceMonthly[$ageGroup["id"]] ?? 0,
                    "hash"                        => md5($ageGroup["id"] . "|" . "monthly"),
                    "confirmation_email_template" => 0,
                    "initial"                     => true,
                ];
            }
        }

        return $templates;
    }

    public function getAttachmentSetsAttribute()
    {
        return $this->getPrefixedMeta("attachment_sets", []);
    }

    public function getEditLinkAttribute()
    {
        return get_edit_term_link($this->id, Taxonomies\Gym::NAME, PostTypes\Training::NAME);
    }

    /* Methods */

    public function getActiveSubscriptionCount($dateFrom, $dateTo = null, $ageGroup = null)
    {
        if (is_null($dateTo)) {
            $dateTo = $dateFrom;
        }

        $activeSubscriptions = $this->subscriptions()->where("paid", true);

        if (!is_null($ageGroup)) {
            $activeSubscriptions->where("age_group", (int) $ageGroup);
        }

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
}
