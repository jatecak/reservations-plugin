<?php

namespace Reservations\Admin\Events;

use Reservations;
use Reservations\Admin;
use Reservations\Models;
use Reservations\Utils;

class SubscriptionsListTable extends Admin\SubscriptionsListTable
{
    public function get_columns()
    {
        return [
            "name"    => __('Name', 'reservations'),
            "contact" => _x('Contact', 'subscriptions', 'reservations'),
            "event"   => _x('Event', 'subscriptions', 'reservations'),
            "paid"    => _x('Paid', 'subscriptions', 'reservations'),
        ];
    }

    public function column_event($item)
    {
        $event = $item->event()->first();
        $city  = $event->city();

        return '<strong><a href="' . esc_attr($event->editLink) . '">' . esc_html($event->title) . '</a></strong><br>' . esc_html($city->name);
    }

    public function column_paid($item)
    {
        $event    = $item->event()->first();
        $ageGroup = $item->ageGroup;

        $paid = [];

        $ordering = collect($item->paymentTemplates)->values()->pluck("hash")->flip()->toArray();
        $payments = $item->payments()->get()->sortBy(function($payment) use (&$ordering) {
            return $ordering[$payment->templateHash] ?? 999; // unknown payments at the end
        });

        foreach ($payments as $payment) {
            if ($payment->paidAmount > 0) {
                $paid[] = sprintf(__('%s US$', 'reservations'), Utils::formatNumber($payment->paidAmount));
            }
        }

        $paid = implode(" + ", $paid);

        if ($paid && strpos($paid, " + ") !== false) {
            $paid .= " = " . sprintf(__('%s US$', 'reservations'), Utils::formatNumber($item->paidAmount));
        }

        $total = sprintf(__('total price: %s US$', 'reservations'), Utils::formatNumber($item->paymentAmount));

        if ($paid) {
            $paid = esc_html($paid) . "<br>" . $total;
        } else {
            $paid = esc_html($paid);
        }

        return $paid;
    }

    public function column_contact($item)
    {
        $subscriber = $item->subscriber()->first();

        return '<a href="mailto:' . esc_attr($subscriber->contactEmail) . '">' . esc_html($subscriber->contactEmail) . '</a><br>' . esc_html(Utils::formatPhone($subscriber->contactPhone)) . ', ' . esc_html(Utils::formatPhone($subscriber->contactPhone2));
    }

    public function single_row($item)
    {
        if ($item->applicationFormReceived && $item->paid) {
            $class = "res-ok";
        } else if ($item->paid && !$item->applicationFormReceived) {
            $class = "res-no-form";
        } else {
            $class = "";
        }

        $class .= " res-type-" . $item->type;

        echo '<tr class="' . $class . '">';
        echo $this->single_row_columns($item);
        echo "</tr>\n";
    }

    public function extra_tablenav($which)
    {
        if ($which !== "top") {
            return;
        }

        $filters = $this->get_filters();

        $eventSelect     = Utils\Html::getEventTreeSelect(Models\Event::accessible()->get(), !is_null($filters["event_id"]) ? $filters["event_id"] : -1);
        $citySelect      = Utils\Html::getCitySelect(Models\City::accessible()->get(), !is_null($filters["city_id"]) ? $filters["city_id"] : -1);
        $eventTypeSelect = Utils\Html::getEventTypeSelect(null, !is_null($filters["event_type"]) ? $filters["event_type"] : -1);

        // $ageGroups = $this->plugin->getAgeGroups();

        // $ageGroupSelect = Utils::getAgeGroupSelect(null, !is_null($filters["age_group"]) ? $filters["age_group"] : -1);

        ?>
        <div class="alignleft actions">
        <select name="city_id">
            <option value=""><?php _e('&mdash; City &mdash;', 'reservations');?></option>
            <?=$citySelect?>
        </select>
        <select name="event_type">
            <option value=""><?php _e('&mdash; Event Type &mdash;', 'reservations');?></option>
            <?=$eventTypeSelect?>
        </select>
        <select name="event_id">
            <option value=""><?php _e('&mdash; Event &mdash;', 'reservations');?></option>
            <?=$eventSelect?>
        </select>
        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'reservations');?>">
        </div>
        <?php
}
}
