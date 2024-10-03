<?php

namespace Reservations\Pages\Utils;

use Reservations\PostTypes;
use Reservations\Taxonomies;

trait AdminBar
{
    /**
     * @action(admin_bar_menu)
     * @priority(100)
     */
    public function displayEditButtons($adminBar)
    {
        $training = isset($this->training) ? $this->training : null;

        if ($training) {
            $gym    = $training->gym();
            $tgroup = $training->trainingGroup();
        } else {
            $gym    = isset($this->gym) ? $this->gym : null;
            $tgroup = isset($this->tgroup) ? $this->tgroup : null;
        }

        $event = isset($this->event) ? $this->event : null;

        if ($gym) {
            $editLink = get_edit_term_link($gym->cityId, Taxonomies\City::NAME, PostTypes\Training::NAME);

            if ($editLink) {
                $adminBar->add_node([
                    "id"    => $this->plugin->prefix("city-edit"),
                    "title" => __('Edit City', 'reservations'),
                    "href"  => $editLink,
                    "meta"  => [],
                ]);
            }

            if ($gym->editLink) {
                $adminBar->add_node([
                    "id"    => $this->plugin->prefix("gym-edit"),
                    "title" => __('Edit Gym', 'reservations'),
                    "href"  => $gym->editLink,
                    "meta"  => [],
                ]);
            }
        }

        if ($tgroup && $tgroup->editLink) {
            $adminBar->add_node([
                "id"    => $this->plugin->prefix("tgroup-edit"),
                "title" => __('Edit Training Group', 'reservations'),
                "href"  => $tgroup->editLink,
                "meta"  => [],
            ]);
        }

        if ($training && $training->editLink) {
            $adminBar->add_node([
                "id"    => $this->plugin->prefix("training-edit"),
                "title" => __('Edit Training', 'reservations'),
                "href"  => $training->editLink,
                "meta"  => [],
            ]);
        }

        if ($event) {
            $editLink = get_edit_term_link($event->city()->id, Taxonomies\City::NAME, PostTypes\Event::NAME);

            if ($editLink) {
                $adminBar->add_node([
                    "id"    => $this->plugin->prefix("city-edit"),
                    "title" => __('Edit City', 'reservations'),
                    "href"  => $editLink,
                    "meta"  => [],
                ]);
            }

            if ($event->editLink) {
                $adminBar->add_node([
                    "id"    => $this->plugin->prefix("event-edit"),
                    "title" => __('Edit Event', 'reservations'),
                    "href"  => $event->editLink,
                    "meta"  => [],
                ]);
            }
        }
    }
}
