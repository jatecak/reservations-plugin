<?php

namespace Reservations\Admin;

use Reservations;
use Reservations\Models;

class ReplacementsListTable extends SubscriptionsListTable
{
    protected function get_query()
    {
        $query = Models\Subscription::query();

        $query->where("is_replacement", true);

        if ($this->isEvent) {
            $query->whereNotNull("event_id");
        } else {
            $query->whereNotNull("tgroup_id");
        }

        return $query;
    }

    protected function get_per_page()
    {
        return (int) $this->plugin->getOption("replacements_per_page", 20);
    }

    protected function get_views()
    {
        return [];
    }

    public function no_items()
    {
        _e('No replacements found.', 'reservations');
    }

    public function column_name($item)
    {
        $subscriber = $item->subscriber()->first();

        $actions = [];

        if (Reservations::MODE === "lead") {
            $editLink = '<a href="' . esc_url(add_query_arg([
                "edit" => $item->subscription_id,
            ] + $this->baseQueryArgs)) . '" class="res-edit">';

            $actions[] = $editLink . __('Edit', 'reservations') . '</a>';
        }

        if (Reservations::MODE === "lubo") {
            $actions[] = '<a href="' . esc_url(add_query_arg([
                "delete"       => $item->subscription_id,
                "_deletenonce" => wp_create_nonce($this->plugin->prefix("delete_nonce_" . $item->subscription_id)),
            ])) . '" class="res-delete">' . __('Delete', 'reservations') . '</a>';
        }

        $this->add_row_actions($item, $actions);

        if (Reservations::MODE === "lead") {
            return $editLink . esc_html($subscriber->fullName) . "</a>" . $this->row_actions($actions);
        } else {
            return esc_html($subscriber->fullName) . $this->row_actions($actions);
        }
    }

    public function column_end_date($item)
    {
        return $item->dateTo->format("j. n. Y");
    }
}
