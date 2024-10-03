<?php

namespace Reservations\Pages;

use Illuminate\Database\Eloquent\Builder;
use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\PostTypes;
use Reservations\Utils;

class Trainings extends Base\Page
{
    private $cities;
    private $usedCities;
    private $gyms;

    public function assets()
    {
        $this->enqueueScript("gmaps", "https://maps.googleapis.com/maps/api/js?key=" . $this->plugin->getOption("google_maps_api_key"));
        $this->enqueueScript("marker-clusterer", "public/js/markerclusterer.js", ["gmaps"]);
        $this->enqueueScript("res-map", "public/js/map.js", ["jquery", "gmaps", "marker-clusterer"]);

        $this->enqueueScript("trainings", "public/trainings.js", ["res-map", "jquery"], [
            "cities"             => $this->cities->values()->toArray(),
            "gyms"               => $this->gyms->values()->toArray(),
            "marker_url"         => $this->plugin->url("public/img/marker.png"),
            "marker_url_cluster" => $this->plugin->url("public/img/marker-cluster.png"),
        ]);

        $this->enqueueGlobalStyle();
    }

    public function prepare()
    {
        $this->cities = Models\City::orderByName()->get();
        $this->gyms   = Models\Gym::used(PostTypes\Training::NAME)->orWhere(function($builder) {
            return $builder->prefixedMetaValue("custom_subscribe_url", "", "!=");
        })->orderByName()->get();

        $usedCityIds = [];
        foreach ($this->gyms as $gym) {
            $gym->city_id = $gym->cityId; // backwards compat

            $usedCityIds[$gym->cityId] = $gym->cityId;

            $gym->url = $this->link($gym->slug, true);

            $tgroups = collect($gym->trainingGroups)->filter(function ($tgroup) {
                return $tgroup->subscriptionEnabled || $tgroup->customSubscribeUrl;
            })->all();

            $gym->subscriptionEnabled = count($tgroups) > 0;

            if (count($tgroups) === 1 && !$this->plugin->isFeatureEnabled("force_schedule")) {
                $tgroup = Utils\Arrays::getFirstElement($tgroups);
                $gym->subscribeLinkDisplay = $tgroup->subscriptionEnabled ? $tgroup->subscribeLink : $tgroup->customSubscribeUrl;
            } else if (count($tgroups) > 1) {
                $gym->subscribeLinkDisplay = $gym->url;
            } else {
                $gym->subscribeLinkDisplay = null;
            }

            if ($gym->customSubscribeUrl) {
                $gym->url = $gym->customSubscribeUrl;
                $gym->subscribeLinkDisplay = $gym->customSubscribeUrl;
            }

            $gym->ageGroupsFormatted = collect($gym->ageGroups)->values()->sortBy(function ($group) {
                return $group["order"];
            })->map(function ($group, $key) {
                return Utils::getAgeGroupPath($group, "labelLC", " ");
            })->implode(", ");
        }

        $this->usedCities = $this->cities->filter(function($city) use (&$usedCityIds) {
            return isset($usedCityIds[$city->id]);
        });
    }

    public function render()
    {
        $showMap    = $this->plugin->isFeatureEnabled("trainings_map");

        $cities = $this->usedCities->values()->all();
        $gyms   = $this->gyms->values();

        foreach ($cities as $city) {
            $city->gyms = $gyms->where("city_id", $city->id)->all();
        }

        include Reservations::ABSPATH . "/public/trainings.php";
    }
}
