<?php

namespace Reservations\Admin\Events;

use Reservations\Admin;
use Reservations\Models;
use Reservations\PostTypes;
use Reservations\Utils;

class Export extends Admin\Export
{
    protected $isEvent = true;

    public function register()
    {
        $this->slug = $this->plugin->slug("-export-event");

        return add_submenu_page(
            "edit.php?post_type=" . PostTypes\Event::NAME,
            __('Export', 'reservations'),
            __('Export', 'reservations'),
            "edit_posts",
            $this->slug,
            [$this, "render"]);
    }

    public function submit()
    {
        if (!Utils::allSet($_POST, [
            "events", "columns",
        ])) {
            return;
        }

        $this->selectedColumns = $this->getSelectedColumns();

        $event_ids = explode(",", $_POST['events']);

        $events = Models\Event::accessible()->get();

        $exported_events = [];
        foreach ($event_ids as $id) {
            $id = (int) $id;

            if (is_nan($id) || $id <= 0) {
                continue;
            }

            $event = $events->first(function ($event) use ($id) {
                return $event->ID === $id;
            });

            if ($event) {
                $exported_events[$event->ID] = $event;
            }
        }

        $subscriptions = Models\Subscription::forEvent()->active();

        $subscriptions->whereIn("event_id", collect($exported_events)->pluck("ID"));

        if (isset($_POST['object_types']) && is_array($_POST['object_types'])) {
            $types = $_POST['object_types'];

            $values = [];
            if (in_array("subscriptions", $types)) {
                $values[] = 0;
            }
            if (in_array("replacements", $types)) {
                $values[] = 1;
            }

            $subscriptions->whereIn("is_replacement", $values);
        }

        if (isset($_POST['only_paid'])) {
            $subscriptions->paid();
        }

        if (isset($_POST['only_paid_deposit'])) {
            $subscriptions->paidPartially();
        }

        if (isset($_POST['only_application_form_received'])) {
            $subscriptions->where("application_form_received", true);
        }

        $subscriptions = $subscriptions->get();

        $options = [
            "group_by_location" => isset($_POST['group_by_location']),
            "sort_by_last_name" => isset($_POST['sort_by_last_name']),
        ];

        $subscriptions = $subscriptions->sortBy(function ($subscription) use ($options) {
            $sortBy = "";
            if ($options["group_by_location"]) {
                $sortBy .= $subscription->event->city()->name . "|" . $subscription->event->title . "|";
            }

            if ($options["sort_by_last_name"]) {
                $sortBy .= $subscription->subscriber->last_name . "|" . $subscription->subscriber->first_name;
            } else {
                $sortBy .= $subscription->subscriber->first_name . "|" . $subscription->subscriber->last_name;
            }

            return $sortBy;
        });

        $this->exportXlsx($subscriptions);

        exit;
    }

    public function render()
    {
        $events  = []; // TODO: saved presets
        $columns = [];

        $column_ids = [];
        foreach ($columns as $id => $column) {
            $column_ids[] = $id;
        }
        $column_ids = esc_attr(implode(",", $column_ids));

        $event_ids = [];
        foreach ($events as $event) {
            $event_ids[] = $event->ID;
        }
        $event_ids = esc_attr(implode(",", $event_ids));

        $eventSelect  = Utils\Html::getEventTreeSelect(Models\Event::accessible()->get(), -1);
        $columnSelect = $this->getColumnSelect();

        $presets = [];
        for ($i = 0; $i < 5; $i++) {
            $presets[] = __('Preset #', 'reservations') . ($i + 1);
        }

        $presetSelect = Utils\Html::getSelect($presets);
        ?>
        <div class="wrap res-wrap" id="res-export">
        <h1 class="wp-heading-inline"><?php _e('Export', 'reservations');?></h1>
        <hr class="wp-header-end">
        <form method="post" data-no-columns-message="<?php esc_attr_e('Please select columns to export.', 'reservations');?>" data-no-events-message="<?php esc_attr_e('Please select events to export.', 'reservations');?>">

        <h2><?php _e('Exported events', 'reservations');?></h2>
        <ul class="res-events" data-delete-text="<?=esc_attr(__('Delete', 'reservations'))?>" data-no-events-text="<?=esc_attr(__('No selected events.', 'reservations'))?>">
            <?php if (!count($events)): ?>
                <li class="no-events"><?php _e('No selected events.', 'reservations')?></li>
            <?php endif;?>

            <?php foreach ($events as $event): ?>
                <li data-id="<?=esc_attr($event->ID)?>"><?=esc_html($event->title)?> <a href="#" class="delete"><?php _e('Delete', 'reservations');?></a></li>
            <?php endforeach;?>
        </ul>
        <label for="events-add"><?php _e('Add Event:', 'reservations');?></label>
        <select id="events-add">
            <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
            <?=$eventSelect?>
        </select>
        <input type="hidden" name="events" id="event-ids" value="<?=$event_ids?>">

        <h2 class="res-exported-columns"><?php _e('Exported columns', 'reservations');?></h2>
        <p class="description"><?php _e('Reorder columns using drag and drop.', 'reservations');?></p>
        <ul class="res-columns" data-delete-text="<?=esc_attr(__('Delete', 'reservations'))?>" data-no-locations-text="<?=esc_attr(__('No selected columns.', 'reservations'))?>">
             <?php if (!count($columns)): ?>
                <li class="no-columns"><?php _e('No selected columns.', 'reservations')?></li>
            <?php endif;?>
        </ul>
        <label for="columns-add"><?php _e('Add Column:', 'reservations');?></label>
        <select id="columns-add">
            <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
            <?=$columnSelect?>
        </select>
        <input type="hidden" name="columns" id="column-ids" value="<?=$column_ids?>">

        <?php if ($this->plugin->isFeatureEnabled("export_presets")): ?>
            <h2><?php _e('Presets', 'reservations');?></h2>
            <select id="presets">
                <?=$presetSelect?>
            </select>
            <button type="button" id="presets-load" class="button"><?php _e('Load', 'reservations');?></button>
            <button type="button" id="presets-save" class="button" data-prompt="<?php esc_attr_e('Enter new preset name:', 'reservations');?>"><?php _e('Save', 'reservations');?></button>
        <?php endif;?>

        <h2><?php _e('Options', 'reservations');?></h2>
        <div class="form-field checkbox-wrap">
            <input type="checkbox" id="export-subscriptions" name="object_types[]" value="subscriptions" checked>
            <label for="export-subscriptions"><?php _ex('Export subscriptions', 'event', 'reservations');?></label>
        </div>
        <div class="form-field checkbox-wrap export-replacements-wrap">
            <input type="checkbox" id="export-replacements" name="object_types[]" value="replacements">
            <label for="export-replacements"><?php _e('Export replacements', 'reservations');?></label>
        </div>
        <div class="form-field checkbox-wrap">
            <input type="checkbox" id="export-only-paid" name="only_paid" value="1">
            <label for="export-only-paid"><?php _e('Export only paid', 'reservations');?></label>
        </div>
        <div class="form-field checkbox-wrap">
            <input type="checkbox" id="export-only-paid-deposit" name="only_paid_deposit" value="1">
            <label for="export-only-paid-deposit"><?php _e('Export only paid deposit', 'reservations');?></label>
        </div>
        <div class="form-field checkbox-wrap export-only-application-form-received-wrap">
            <input type="checkbox" id="export-only-application-form-received" name="only_application_form_received" value="1">
            <label for="export-only-application-form-received"><?php _e('Export only with application form', 'reservations');?></label>
        </div>
        <div class="form-field checkbox-wrap">
            <input type="checkbox" id="export-group-by-location" name="group_by_location" value="1" checked>
            <label for="export-group-by-location"><?php _e('Group by location', 'reservations');?></label>
        </div>
        <div class="form-field checkbox-wrap">
            <input type="checkbox" id="export-sort-by-last-name" name="sort_by_last_name" value="1">
            <label for="export-sort-by-last-name"><?php _e('Sort by last name first', 'reservations');?></label>
        </div>

        <p class="submit">
            <input type="submit" name="export" class="button button-primary" value="<?php esc_attr_e('Export to XLSX', 'reservations');?>">
        </p>

        </form>
        </div>
        <?php
}
}
