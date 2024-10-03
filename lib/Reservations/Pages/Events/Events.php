<?php

namespace Reservations\Pages\Events;

use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\Pages;
use Reservations\PostTypes;
use Reservations\Utils;

class Events extends Base\Page
{
    use EventsBase;

    private $cities;
    private $events;

    public function assets()
    {
        $this->eventsAssets();

        $this->enqueueScript("gmaps", "https://maps.googleapis.com/maps/api/js?key=" . $this->plugin->getOption("google_maps_api_key"));
        $this->enqueueScript("marker-clusterer", "public/js/markerclusterer.js", ["gmaps"]);
        $this->enqueueScript("res-map", "public/js/map.js", ["jquery", "gmaps", "marker-clusterer"]);

        $this->enqueueScript("events", "public/events/events.js", ["res-map", "jquery"], [
            "cities"             => $this->cities->values()->toArray(),
            "marker_url"         => $this->plugin->url("public/img/marker.png"),
            "marker_url_cluster" => $this->plugin->url("public/img/marker-cluster.png"),
        ]);

        $this->enqueueGlobalStyle();
    }

    public function prepare()
    {
        $this->eventsPrepare();

        $eventType    = $this->eventType;
        $this->cities = Models\City::used(PostTypes\Event::NAME)->get()->filter(function ($city) use ($eventType) {
            $builder = Models\Event::inCity($city->id);

            if ($eventType !== null) {
                $builder->eventType($eventType);
            }
            return $builder->count() > 0;
        });

        $builder = Models\Event::status("publish");

        if ($this->eventType !== null) {
            $builder->eventType($this->eventType);
        }

        $this->events = $builder->get()->sort(function ($a, $b) {
            if ($a->menu_order > $b->menu_order) {
                return 1;
            } else if ($a->menu_order < $b->menu_order) {
                return -1;
            }

            $dateFromCmp = Utils::compareDates($a->dateFrom, $b->dateFrom);

            if ($dateFromCmp === 0) {
                return Utils::compareDates($a->dateTo, $b->dateTo);
            }

            return $dateFromCmp;
        });
    }

    public function render()
    {
        $events    = $this->events;
        $eventType = $this->eventType;

        $showMap    = $this->plugin->isFeatureEnabled("events_map");
        $isUnified  = $eventType === null;
        $eventTypes = Models\Local\EventType::all();

        if ($isUnified) {
            $eventType = Utils\Arrays::getFirstElement($eventTypes);
        }

        foreach ($events as $event) {
            $event->priceFormatted = Utils::formatNumber($event->getPaymentAmount(null));
            $event->dateFormatted  = Utils::formatDateRange($event->dateFrom, $event->dateTo);

            if (Reservations::MODE === "lead" && $event->eventType["id"] == "camp" && $event->campType !== "suburban") {
                $weekdays = TranslatableEnums::weekdays();

                $startWeekday = $weekdays[$event->dateFrom->format("w")];
                $endWeekday   = $weekdays[$event->dateTo->format("w")];

                $event->timeFormatted = $startWeekday . " " . Utils::formatTime($event->startTime) . " &ndash; " . $endWeekday . " " . Utils::formatTime($event->endTime);
            } else {
                $event->timeFormatted = Utils::formatTimeRange($event->startTime, $event->endTime);
            }

            $event->descriptionFormatted = wpautop(wptexturize($event->description));
            $event->campTypeFormatted    = TranslatableEnums::campTypes()[$event->campType];
            if ($event->subscriptionEnabled) {
                $event->subscribeLinkDisplay = $event->subscribeLink;
            } else if ($this->plugin->isFeatureEnabled("event_subscription_control") && $event->customSubscribeUrl) {
                $event->subscribeLinkDisplay = $event->customSubscribeUrl;
            } else {
                $event->subscribeLinkDisplay = null;
            }

            $event->location = $event->city()->name;

            $event->showTime     = $event->startTime !== 0;
            $event->showPrice    = $event->getPaymentAmount(null) > 0;
            $event->showAddress  = $event->address !== "";
            $event->showCampType = $this->plugin->isFeatureEnabled("camp_type") && $event->eventType["id"] === "camp";
        }

        include Reservations::ABSPATH . "/public/events/events.php";
    }
}
