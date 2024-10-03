<?php

namespace Reservations\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Reservations;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\Models\Utils\Cached;
use Reservations\Models\Utils\InTaxonomy;
use Reservations\Models\Utils\Metable;
use Reservations\Models\Utils\PaymentTemplates;
use Reservations\PostTypes;
use Reservations\Taxonomies;
use Reservations\Utils;

class Event extends Wordpress\Post
{
    use Cached, Metable, PaymentTemplates, InTaxonomy;

    protected static $postType = PostTypes\Event::NAME;

    protected $cached = [
        "dateFrom",
        "dateTo",
        "description",
        "contactEmail",
        "contactPhone",
        "contactInstructor",
    ];

    protected static function boot()
    {
        parent::boot();

        $postType = static::$postType;
        static::addGlobalScope("isEvent", function (Builder $builder) use ($postType) {
            $builder->where("post_type", $postType);
        });
    }

    /* Relationships */

    public function instructors()
    {
        return $this->belongsToMany(Instructor::class, "instructors_trainings", "training_id", "instructor_id");
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, "event_id");
    }

    public function city()
    {
        $taxonomy = $this->termTaxonomies()->where("taxonomy", Reservations::instance()->prefix("city"))->first();

        if ($taxonomy) {
            return City::find($taxonomy->term_id);
        }

        return null;
    }

    public function contactInstructor()
    {
        $id = $this->getMeta(Reservations::PREFIX . "contact_instructor_id");

        if ($id) {
            return Instructor::find($id);
        }

        return null;
    }

    /* Scopes */

    public function scopeInCity($builder, $cityId)
    {
        return $builder->inTaxonomy(Taxonomies\City::NAME, $cityId);
    }

    public function scopeEventType($builder, $eventType)
    {
        if (is_array($eventType) && isset($eventType["id"])) {
            $eventType = $eventType["id"];
        }

        $builder->whereHas("meta", function (Builder $builder) use ($eventType) {
            $builder->where("meta_key", Reservations::instance()->prefix("event_type"))->where("meta_value", $eventType);
        });
    }

    public function scopeAccessible($builder, $user = null)
    {
        if (is_null($user)) {
            $user = User::current();
        }

        $accessibleCities = $user->accessibleCities;

        $builder->whereHas("termTaxonomies", function ($builder) use ($accessibleCities) {
            $builder->where("taxonomy", Taxonomies\City::NAME)->whereIn("term_id", $accessibleCities->pluck("term_id"));
        });
    }

    public function scopeSearchQuery($builder, $q, $searchCity = false)
    {
        $builder->where("post_title", "like", "%" . $q . "%")
            ->orWhere(function ($builder) use ($q) {
                $builder->prefixedMetaValue("description", "%" . $q . "%", "like");
            });

        if ($searchCity) {
            $matchedCities = City::searchQuery($q)->get();
            $builder->orWhere(function ($builder) use ($matchedCities) {
                $builder->inCity($matchedCities->pluck("id")->all());
            });
        }
    }

    /* Attributes */

    public function getDateFromAttribute()
    {
        $date = $this->getPrefixedMeta("date_from");

        if (!$date) {
            $date = $this->getPrefixedMeta("date");

            if ($date) {
                $this->setPrefixedMeta("date_from", $date);
            }
        }

        return $date ? Carbon::createFromTimestamp($date, Utils::getTimezone()) : null;
    }

    public function getDateToAttribute()
    {
        $date = $this->getPrefixedMeta("date_to");

        return $date ? Carbon::createFromTimestamp($date, Utils::getTimezone()) : null;
    }

    public function getDescriptionAttribute()
    {
        return $this->getPrefixedMeta("description", "");
    }

    public function getEventTypeAttribute()
    {
        return Local\EventType::find($this->getPrefixedMeta("event_type"));
    }

    public function getCampTypeAttribute()
    {
        return $this->getPrefixedMeta("camp_type", "trip");
    }

    public function getCapacityAttribute()
    {
        return (int) $this->getPrefixedMeta("capacity");
    }

    public function getAddressAttribute()
    {
        return $this->getPrefixedMeta("address");
    }

    public function getContactEmailAttribute()
    {
        return $this->getPrefixedMeta("contact_email");
    }

    public function getContactPhoneAttribute()
    {
        return $this->getPrefixedMeta("contact_phone");
    }

    public function getStartTimeAttribute()
    {
        $startTime = $this->getPrefixedMeta("start_time");

        // COMPAT
        if (!$startTime && ($oldStartTime = $this->getPrefixedMeta("time"))) {
            $startTime = Utils::sanitizeTime($oldStartTime);

            $this->setPrefixedMeta("start_time", $startTime);
        }

        return Utils::parseTime($startTime);
    }

    public function getEndTimeAttribute()
    {
        return Utils::parseTime($this->getPrefixedMeta("end_time"));
    }

    public function getPasswordAttribute()
    {
        return $this->getPrefixedMeta("password", "");
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

    public function getFreeCapacityAttribute()
    {
        return $this->capacity - $this->subscriptions()->paidPartially()->count();
    }

    public function getEditLinkAttribute()
    {
        return get_edit_post_link($this->id, "");
    }

    public function getMealOptionsAttribute()
    {
        // TODO: make admin interface for this

        return TranslatableEnums::mealOptionsUcFirst();
    }

    public function getSubscriptionEnabledAttribute()
    {
        if (!Reservations::instance()->isSubscriptionEnabled(Local\ObjectType::EVENT, $this->eventType)) {
            return false;
        }

        if (Reservations::instance()->isFeatureEnabled("event_subscription_control") && !$this->getPrefixedMeta("subscription_enabled", false)) {
            return false;
        }

        return $this->getPaymentAmount() > 0;
    }

    public function getSubscribeLinkAttribute()
    {
        if (Reservations::instance()->isFeatureEnabled("unified_events")) {
            $page = Reservations::instance()->pageRouter->eventsPage;
        } else {
            $page = Reservations::instance()->pageRouter->eventTypePages[$this->eventType["id"]];
        }

        $permalink = get_permalink($page);

        return Utils::joinPaths($permalink, $this->slug, _x('subscribe', 'url slug', 'reservations'), "/");
    }

    /* Methods */

    public function setCity($city)
    {
        if ($city instanceof City) {
            $city = $city->id;
        } else if (!is_null($city)) {
            $city = (int) $city;
        }

        wp_set_object_terms($this->id, is_null($city) ? [] : [$city], Taxonomies\City::NAME);
    }

    public function getEmailVariables()
    {
        $variables = [];

        $variables["event"]       = $this->title;
        $variables["eventType"]   = $this->eventType["label"];
        $variables["eventTypeLC"] = $this->eventType["labelLC"];
        $variables["city"]        = $this->city()->name;
        $variables["address"]     = $this->address;
        $variables["dateFrom"]    = $this->dateFrom->format("j. n. Y");
        $variables["dateTo"]      = $this->dateTo->format("j. n. Y");
        $variables["startTime"]   = Utils::formatTime($this->startTime);
        $variables["endTime"]     = Utils::formatTime($this->endTime);

        return $variables;
    }
}
