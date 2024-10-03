<?php

namespace Reservations\Utils;

use Reservations;
use Reservations\Models;
use Reservations\PostTypes;
use Reservations\Taxonomies;

class ACL
{
    use PluginAccess;

    private $userCache   = [];
    private $tgroupCache = [];

    public function init()
    {
        $this->plugin->addHooks($this);
    }

    private function getUser($userId)
    {
        if ($userId === get_current_user_id()) {
            // current user is cached
            return Models\User::current();
        }

        if (isset($this->userCache[$userId]) && $this->userCache[$userId]) {
            return $this->userCache[$userId];
        }

        $user = $this->userCache[$userId] = Models\User::find($userId);

        return $user;
    }

    private function getTrainingGroup($tgroupId)
    {
        if (isset($this->tgroupCache[$tgroupId]) && $this->tgroupCache[$tgroupId]) {
            return $this->tgroupCache[$tgroupId];
        }

        $tgroup = $this->tgroupCache[$tgroupId] = Models\TrainingGroup::find($tgroupId);

        $tgroup->_cityIds = collect($tgroup->cities)->pluck("id");

        return $tgroup;
    }

    /** @filter(user_has_cap) */
    public function applyACLs($allcaps, $_cap, $args)
    {
        // This function uses the crappy WP API to utilize its object cache, because this function gets called A LOT

        if (!$this->plugin->isFeatureEnabled("city_acl")) {
            return $allcaps;
        }

        $cap   = $args[0];
        $allow = true;

        if ($cap === "edit_post" || $cap === "delete_post") {
            $post = get_post($args[2]);

            if ($post->post_type === PostTypes\Training::NAME) {
                $user = $this->getUser($args[1]);

                if (!$user->can("administrator")) {
                    $accessibleCityIds = $user->getAccessibleCities(true);

                    $terms = wp_get_object_terms([$post->ID], [Taxonomies\City::NAME]);

                    if (count($terms) && !$accessibleCityIds->contains($terms[0]->term_id)) {
                        $allow = false;
                    }
                }
            }
        } else if ($cap === "edit_term" || $cap === "delete_term") {
            $term = get_term($args[2]);

            if ($term->taxonomy === Taxonomies\City::NAME || $term->taxonomy === Taxonomies\Gym::NAME) {
                $user = $this->getUser($args[1]);

                if (!$user->can("administrator")) {
                    $accessibleCityIds = $user->getAccessibleCities(true);

                    if ($term->taxonomy === Taxonomies\City::NAME) {
                        $allow = $accessibleCityIds->contains($term->term_id);
                    } else {
                        $cityId = (int) get_term_meta($term->term_id, $this->plugin->prefix("city_id"), true);

                        $allow = $accessibleCityIds->contains($cityId);
                    }
                }
            } else if ($term->taxonomy === Taxonomies\TrainingGroup::NAME) {
                $user = $this->getUser($args[1]);

                if (!$user->can("administrator")) {
                    $tgroup            = $this->getTrainingGroup($term->term_id);
                    $accessibleCityIds = $user->getAccessibleCities(true);

                    $cityIds = $tgroup->_cityIds;

                    $allow = $cityIds->every(function ($cityId) use ($accessibleCityIds) {
                        return $accessibleCityIds->contains($cityId);
                    });
                }
            }
        }

        if (!$allow) {
            foreach ($_cap as $c) {
                $allcaps[$c] = false;
            }
        }

        return $allcaps;
    }
}
