<?php

namespace Reservations\Admin;

use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\PostTypes;
use Reservations\Utils;
use XLSXWriter;

class Export extends Base\AdminPage
{
    protected $isEvent = false;
    protected $columns;
    protected $columnsFlat;
    protected $selectedColumns = [];

    public function register()
    {
        $this->slug = $this->plugin->slug("-export");

        return add_submenu_page(
            "edit.php?post_type=" . PostTypes\Training::NAME,
            __('Export', 'reservations'),
            __('Export', 'reservations'),
            "edit_posts",
            $this->slug,
            [$this, "render"]
        );
    }

    public function prepare()
    {
        $this->columns = Export\Columns::get();
        Export\Columns::expand($this->columns);
        $this->columnsFlat = Export\Columns::flatten($this->columns);

        if (isset($_POST['export'])) {
            $this->submit();
        }
    }

    public function submit()
    {
        if (!Utils::allSet($_POST, [
            "locations", "columns",
        ])) {
            return;
        }

        $this->selectedColumns = $this->getSelectedColumns();

        $trainingGroups = Models\TrainingGroup::accessible()->get();
        $cities         = Models\City::accessible()->get();
        $gyms           = Models\Gym::accessible()->get();

        $locations = collect(explode(",", $_POST['locations']))->map(function ($id) use ($trainingGroups, $cities, $gyms) {
            $id = (int) $id;

            if (is_nan($id) || $id <= 0) {
                return;
            }

            $tgroup = $trainingGroups->first(function ($tgroup) use ($id) {
                return $tgroup->id === $id;
            });

            if ($tgroup) {
                return $tgroup;
            }

            $city = $cities->first(function ($city) use ($id) {
                return $city->id === $id;
            });

            if ($city) {
                return $city;
            }

            $gym = $gyms->first(function ($gym) use ($id) {
                return $gym->id === $id;
            });

            return $gym;
        })->filter();

        $tgroups = [];
        foreach ($locations as $loc) {
            if ($loc instanceof Models\TrainingGroup) {
                $tgroups[] = $loc;
                continue;
            }

            $tgroups = array_merge($tgroups, $loc->trainingGroups);
        }

        $subscriptions = Models\Subscription::forTrainings();

        $subscriptions->whereIn("tgroup_id", collect($tgroups)->pluck("id")->uniqueStrict());

        if (isset($_POST['only_active'])) {
            $subscriptions->active();
        }

        if (isset($_POST['object_types']) && is_array($_POST['object_types'])) {
            $types = $_POST['object_types'];

            $values = [];
            if (in_array("subscriptions", $types)) {
                $values[] = 0;
            }
            if ($this->plugin->isFeatureEnabled("replacements") && in_array("replacements", $types)) {
                $values[] = 1;
            }

            $subscriptions->whereIn("is_replacement", $values);
        }

        if (isset($_POST['only_paid'])) {
            $subscriptions->where("paid", true);
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
                $sortBy .= $subscription->trainingGroup->name . "|";
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

    protected function getSelectedColumns()
    {
        if (!isset($_POST['columns'])) {
            return [];
        }

        $ids = explode(",", $_POST['columns']);

        $columns = [];
        foreach ($ids as $id) {
            if (!isset($this->columnsFlat[$id])) {
                continue;
            }

            $columns[] = $this->columnsFlat[$id];
        }

        return $columns;
    }

    protected function getColumnValue($subscription, $column)
    {
        $object = $subscription;
        switch ($column["object"]) {
            case "gym":
                $gyms   = $subscription->trainingGroup->gyms;
                $object = count($gyms) > 0 ? Utils\Arrays::getFirstElement($gyms) : null;
                break;

            case "subscriber":
                $object = $subscription->subscriber;
                break;

            case "event":
                $object = $subscription->event;
                break;

            case "tgroup":
                $object = $subscription->trainingGroup;
                break;

            case "city":
                if ($this->isEvent) {
                    $object = $subscription->event->city();
                } else {
                    $cities = $subscription->trainingGroup->cities;
                    $object = count($cities) > 0 ? Utils\Arrays::getFirstElement($cities) : null;
                }
                break;
        }

        $value = "";
        if (isset($column["key"])) {
            $value = $object->{$column["key"]} ?? "";
        }

        if (isset($column["filter"])) {
            $value = $column["filter"]($value);
        }

        if (isset($column["callback"])) {
            $value = $column["callback"]($object, $column);
        }

        if ($column["format"] === "integer") {
            $value = (int) $value;
        }

        return $value;
    }

    public function exportXlsx($subscriptions)
    {
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=export.xlsx");

        $writer = new XLSXWriter();

        $writer->setTempDir(Reservations::TEMP_DIR);

        $header       = [];
        $headerStyles = [];
        foreach ($this->selectedColumns as $col) {
            $header[$col["full_label"]] = $col["format"];
            $headerStyles[]             = ["font-style" => "bold"];
        }

        $sheetName = $this->isEvent ? _x('Subscribers', 'event', 'reservations') : __('Subscribers', 'reservations');
        $writer->writeSheetHeader($sheetName, $header, $headerStyles);

        foreach ($subscriptions as $subscription) {
            $row = [];
            foreach ($this->selectedColumns as $col) {
                $row[] = $this->getColumnValue($subscription, $col);
            }

            $writer->writeSheetRow($sheetName, $row);
        }

        $writer->writeToStdout();
    }

    protected function printCsvRow(&$row, $sep = ",")
    {
        $i = 0;
        foreach ($row as $col) {
            if (strpos($col, $sep) !== false || strpos($col, "\n") !== false) {
                echo '"' . str_replace('"', '""', $col) . '"';
            } else {
                echo $col;
            }

            if ($i !== count($row) - 1) {
                echo $sep;
            }

            $i++;
        }

        echo "\r\n";
    }

    protected function getColumnSelect()
    {
        $inner = function ($columns) use (&$inner) {
            $tree = [];
            foreach ($columns as $column) {
                if (isset($column["trainings_only"]) && $column["trainings_only"] && $this->isEvent) {
                    continue;
                }

                if (isset($column["event_only"]) && $column["event_only"] && !$this->isEvent) {
                    continue;
                }

                if ($column["type"] === "group") {
                    $tree[$column["label"]] = $inner($column["children"]);
                } else if ($column["type"] === "column") {
                    $tree[$column["full_id"]] = $column["label"];
                }
            }
            return $tree;
        };

        return Utils\Html::getSelectRecursive($inner($this->columns), null);
    }

    public function exportCsv($columns, $subscriptions)
    {
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=export.csv");

        $header = array_map(function ($col) {
            return $col["full_label"];
        }, $columns);
        $this->printCsvRow($header);

        foreach ($subscriptions as $subscription) {
            $row = [];
            foreach ($columns as $col) {
                $row[] = $this->getColumnValue($subscription, $col);
            }

            $this->printCsvRow($row);
        }
    }

    public function render()
    {
        $locations = []; // TODO: saved presets
        $columns   = [];

        $column_ids = [];
        foreach ($columns as $id => $column) {
            $column_ids[] = $id;
        }
        $column_ids = esc_attr(implode(",", $column_ids));

        $location_ids = [];
        foreach ($locations as $location) {
            $location_ids[] = $location->id;
        }
        $location_ids = esc_attr(implode(",", $location_ids));

        $tgroups             = Models\TrainingGroup::accessible()->sortByName()->get();
        $trainingGroupSelect = Utils\Html::getTrainingGroupSelect($tgroups);

        $cities     = Models\City::accessible()->sortByName()->get();
        $citySelect = Utils\Html::getCitySelect($cities);

        $gyms      = Models\Gym::accessible()->sortByName()->get();
        $gymSelect = Utils\Html::getGymTreeSelect($gyms);

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
        <form method="post" data-no-columns-message="<?php esc_attr_e('Please select columns to export.', 'reservations');?>" data-no-locations-message="<?php esc_attr_e('Please select training groups to export.', 'reservations');?>">

        <h2><?php _e('Exported training groups', 'reservations');?></h2>
        <ul class="res-locations" data-delete-text="<?=esc_attr(__('Delete', 'reservations'))?>" data-no-locations-text="<?=esc_attr(__('No selected locations.', 'reservations'))?>">
            <?php if (!count($locations)): ?>
                <li class="no-locations"><?php _e('No selected locations.', 'reservations')?></li>
            <?php endif;?>

            <?php foreach ($locations as $location): ?>
                <li data-id="<?=esc_attr($location->id)?>"><?=esc_html($location->name)?> <a href="#" class="delete"><?php _e('Delete', 'reservations');?></a></li>
            <?php endforeach;?>
        </ul>
        <label for="locations-add-city"><?php _e('Add City:', 'reservations');?></label>
        <select id="locations-add-city">
            <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
            <?=$citySelect?>
        </select><br>
        <label for="locations-add"><?php _e('Add Gym:', 'reservations');?></label>
        <select id="locations-add">
            <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
            <?=$gymSelect?>
        </select><br>
        <label for="tgroups-add"><?php _e('Add Training Group:', 'reservations');?></label>
        <select id="tgroups-add">
            <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
            <?=$trainingGroupSelect?>
        </select><br>
        <input type="hidden" name="locations" id="tgroup-ids" value="<?=$location_ids?>">

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
            <label for="export-subscriptions"><?php _e('Export subscriptions', 'reservations');?></label>
        </div>
        <?php if ($this->plugin->isFeatureEnabled("replacements")): ?>
            <div class="form-field checkbox-wrap export-replacements-wrap">
                <input type="checkbox" id="export-replacements" name="object_types[]" value="replacements">
                <label for="export-replacements"><?php _e('Export replacements', 'reservations');?></label>
            </div>
        <?php endif;?>
        <div class="form-field checkbox-wrap mt-5">
            <input type="checkbox" id="export-only-active" name="only_active" value="1" checked>
            <label for="export-only-active"><?php _e('Export only active', 'reservations');?></label>
        </div>
        <div class="form-field checkbox-wrap">
            <input type="checkbox" id="export-only-paid" name="only_paid" value="1">
            <label for="export-only-paid"><?php _e('Export only paid', 'reservations');?></label>
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
