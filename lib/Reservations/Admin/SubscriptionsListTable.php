<?php

namespace Reservations\Admin;

use Carbon\Carbon;
use Reservations;
use Reservations\Models;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Local\SubscriptionType;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\PostTypes;
use Reservations\Taxonomies;
use Reservations\Utils;

if (!class_exists("\WP_List_Table")) {
    require_once ABSPATH . "wp-admin/includes/class-wp-list-table.php";
}

class SubscriptionsListTable extends \WP_List_Table
{
    public $plugin;
    protected $isEvent;

    protected $baseQueryArgs = [
        "remove"           => false,
        "remove_unpaid"    => false,
        "_removenonce"     => false,
        "edit"             => false,
        "editok"           => false,
        "confirm_form"     => false,
        "_confirmnonce"    => false,
        "_wp_http_referer" => false,
    ];

    public function __construct($plugin)
    {
        parent::__construct(array(
            'singular' => 'subscription',
            'plural'   => 'subscriptions',
            'ajax'     => false,
        ));

        $this->plugin = $plugin;

        $screen = get_current_screen();

        if ($screen->post_type === PostTypes\Event::NAME) {
            $this->isEvent = true;
        }
    }

    protected function get_url_esc($query = [])
    {
        return esc_url(add_query_arg($query + $baseQueryArgs));
    }

    public function display()
    {
        if (Reservations::MODE === "lead") {
            $this->search_box(__('Search', 'reservations'), 'search');
        }

        $this->views();
        parent::display();
    }

    public function get_columns()
    {
        return [
            "name"       => __('Full Name', 'reservations'),
            "contact"    => _x('Contact', 'subscriptions', 'reservations'),
            "tgroup"     => _x('Training Group', 'subscriptions', 'reservations'),
            "start_date" => __('Start Date', 'reservations'),
            "end_date"   => __('End Date', 'reservations'),
            // "period"   => __('Period', 'reservations'),
            // "other"    => __('Age Group', 'reservations') . "<br>" . __('Subscription Type', 'reservations'),
        ];
    }

    public function get_sortable_columns()
    {
        if (Reservations::MODE === "lubo") {
            return [];
        }

        return [
            "name"       => ["name", Reservations::MODE === "lead"],
            // "location"   => ["location", false],
            "start_date" => ["start_date", false],
            "end_date"   => ["end_date", false],
        ];
    }

    protected function get_filter_value($key, $valid_options = null, $default = null)
    {
        if (!isset($_GET[$key])) {
            return $default;
        }

        $value = trim((string) $_GET[$key]);

        if (!is_null($valid_options) && !in_array($value, $valid_options)) {
            return $default;
        }

        return $value;
    }

    protected function get_filters()
    {
        $sub_status = (isset($_GET['sub_status']) && in_array($_GET['sub_status'], ['paid', 'application_form_received'])) ? $_GET['sub_status'] : "all";
        $gym_id     = (isset($_GET['gym_id']) && $_GET['gym_id'] !== "") ? (int) $_GET['gym_id'] : null;
        $tgroup_id  = (isset($_GET['tgroup_id']) && $_GET['tgroup_id'] !== "") ? (int) $_GET['tgroup_id'] : null;
        $age_group  = (isset($_GET['age_group']) && $_GET['age_group'] !== "") ? (int) $_GET['age_group'] : null;
        $event_type = (isset($_GET['event_type']) && $_GET['event_type'] !== "") ? (string) $_GET['event_type'] : null;
        $sub_type   = (isset($_GET['sub_type']) && in_array($_GET['sub_type'], SubscriptionType::forObjectType(ObjectType::TRAININGS))) ? $_GET['sub_type'] : null;
        $event_id   = (isset($_GET['event_id']) && $_GET['event_id'] !== "") ? (int) $_GET['event_id'] : null;
        $city_id    = (isset($_GET['city_id']) && $_GET['city_id'] !== "") ? (int) $_GET['city_id'] : null;
        $search     = (isset($_GET['s']) && trim($_GET['s']) !== "") ? trim($_GET['s']) : null;

        return [
            "sub_status" => $sub_status,
            "sub_type"   => $sub_type,
            "gym_id"     => $gym_id,
            "tgroup_id"  => $tgroup_id,
            "age_group"  => $age_group,
            "event_type" => $event_type,
            "event_id"   => $event_id,
            "city_id"    => $city_id,
            "search"     => $search,

            "orderby"    => $this->get_filter_value("orderby", ["name", "location", "start_date", "end_date"]),
            "order"      => $this->get_filter_value("order", ["asc", "desc"], "asc"),
        ];
    }

    protected function get_query()
    {
        $query = Models\Subscription::active(true);

        $query->where("is_replacement", false);

        if ($this->isEvent) {
            $query->forEvent();
        } else {
            $query->forTrainings();
        }

        return $query;
    }

    protected function get_filtered_query($filterByStatus = true)
    {
        $query = $this->get_query();

        $filters = $this->get_filters();

        if ($filterByStatus && $filters["sub_status"] === "paid") {
            $query->paid();
        } else if ($filterByStatus && $filters["sub_status"] === "application_form_received") {
            $query->where("application_form_received", 1);
        }

        if (!is_null($filters["sub_type"])) {
            $query->where("type", $filters["sub_type"]);
        }

        if (!is_null($filters["gym_id"])) {
            $gym = Models\Gym::find($filters["gym_id"]);

            if ($gym) {
                $tgroups = $gym->trainingGroups;
                $query->whereIn("tgroup_id", collect($tgroups)->pluck("term_id"));
            }
        }

        if (!is_null($filters["tgroup_id"])) {
            $query->where("tgroup_id", $filters["tgroup_id"]);
        }

        if (!is_null($filters["search"])) {
            $qs = explode(" ", $filters["search"]);
            foreach ($qs as $q) {
                $q = trim($q);

                if ($q === "") {
                    continue;
                }

                $query->searchQuery($q);
            }
        }

        if (!is_null($filters["event_id"])) {
            $query->where("event_id", $filters["event_id"]);
        }

        if (!is_null($filters["event_type"])) {
            $query->whereHas("event", function ($builder) use ($filters) {
                return $builder->whereHas("meta", function ($builder) use ($filters) {
                    $builder->where("meta_key", Reservations::instance()->prefix("event_type"))->where("meta_value", $filters["event_type"]);
                });
            });
        }

        if (!is_null($filters["city_id"])) {
            $query->whereHas("event", function ($builder) use ($filters) {
                return $builder->whereHas("termTaxonomies", function ($builder) use ($filters) {
                    $builder->where("taxonomy", Taxonomies\City::NAME)->where("term_id", $filters["city_id"]);
                });
            });
        }

        if (!is_null($filters["age_group"])) {
            $query->where("age_group", $filters["age_group"]);
        }

        $query->accessible();

        if (!$this->isEvent) {
            $query->whereIn("tgroup_id", Models\User::current()->getAccessibleTrainingGroups(true));
        }

        if ($filters["orderby"] === "name" || (is_null($filters["orderby"]) && Reservations::MODE === "lead")) {
            $query->sortByName($filters["order"]);
        } else if ($filters["orderby"] === "location") {
            $query->sortByLocation($filters["order"]);
        } else if ($filters["orderby"] === "start_date") {
            $query->sortByDateFrom($filters["order"]);
        } else if ($filters["orderby"] === "end_date") {
            $query->sortByDateTo($filters["order"]);
        }

        return $query;
    }

    public function has_items()
    {
        return !empty($this->items) && count($this->items) > 0;
    }

    protected function get_per_page()
    {
        return (int) $this->plugin->getOption("subscriptions_per_page", 20);
    }

    public function prepare_items()
    {
        $this->_column_headers  = [$this->get_columns(), [], $this->get_sortable_columns(), "name"];
        $_SERVER['REQUEST_URI'] = remove_query_arg('_wp_http_referer', $_SERVER['REQUEST_URI']);

        $perPage    = $this->get_per_page();
        $totalItems = $this->get_filtered_query()->count();

        $query = $this->get_filtered_query()->limit($perPage)->offset($perPage * ($this->get_pagenum() - 1));

        $this->items = $query->get();

        $this->set_pagination_args([
            "total_items" => $totalItems,
            "per_page"    => $perPage,
        ]);
    }

    public function no_items()
    {
        _e('No subscriptions found.', 'reservations');
    }

    protected function build_link($args = [], $cssClass = "", $appendCurrent = false)
    {
        if ($appendCurrent) {
            $cssClass = trim($cssClass . " current");
        }

        return '<a href="' . esc_url(add_query_arg($args + $this->baseQueryArgs)) . '" class="' . $cssClass . '">';
    }

    protected function get_views()
    {
        $counts = [
            "all"                       => $this->get_filtered_query(false)->count(),
            "paid"                      => $this->get_filtered_query(false)->where("paid", 1)->count(),
            "application_form_received" => $this->get_filtered_query(false)->where("application_form_received", 1)->count(),
        ];
        $current = $this->get_filters()["sub_status"];

        $links["all"] = $this->build_link([
            "sub_status" => false,
            "paged"      => false,
        ], "", $current === "all") . __("All", 'reservations') . '</a> (' . $counts["all"] . ')';

        $links["paid"] = $this->build_link([
            "sub_status" => "paid",
            "paged"      => false,
        ], "", $current === "paid") . __("Paid", 'reservations') . '</a> (' . $counts["paid"] . ')';

        $links["application_form_received"] = $this->build_link([
            "sub_status" => "application_form_received",
            "paged"      => false,
        ], "", $current === "application_form_received") . __("With Application Form", 'reservations') . '</a> (' . $counts["application_form_received"] . ')';

        return $links;
    }

    protected function add_row_actions($item, &$actions)
    {

    }

    public function column_default($item, $column_name)
    {
        $subscriber = $item->subscriber;

        switch ($column_name) {
            case "name":
                $actions = [];

                $editLink = '<a href="' . esc_url(add_query_arg([
                    "edit" => $item->subscription_id,
                ] + $this->baseQueryArgs)) . '" class="res-edit">';

                $actions[] = $editLink . __('Edit', 'reservations') . '</a>';

                if (!$item->applicationFormReceived) {
                    $actions["confirm_application_form"] = '<a href="' . esc_url(add_query_arg([
                        "confirm_form"  => $item->subscription_id,
                        "_confirmnonce" => wp_create_nonce($this->plugin->prefix("confirm_nonce_" . $item->subscription_id)),
                    ] + $this->baseQueryArgs)) . '" class="res-confirm-form">' . __('Confirm application form', 'reservations') . '</a>';
                }

                $actions[] = '<a href="' . esc_url(add_query_arg([
                    "delete"       => $item->subscription_id,
                    "_deletenonce" => wp_create_nonce($this->plugin->prefix("delete_nonce_" . $item->subscription_id)),
                ] + $this->baseQueryArgs)) . '" class="res-delete" onclick="return confirm(' . esc_attr(json_encode(__('Really?', 'reservations'))) . ');">' . __('Delete', 'reservations') . '</a>';

                $this->add_row_actions($item, $actions);

                return $editLink . esc_html($subscriber->fullName) . "</a>" . $this->row_actions($actions);

            case "location":
                return '<strong>' . esc_html($gym->name) . '</strong><br>' . esc_html($city->name);

            case "tgroup":
                $tgroup = $item->trainingGroup;
                $cities = $tgroup->cities;

                $text = '<strong><a href="' . esc_attr($tgroup->editLink) . '">' . esc_html($tgroup->name) . '</a></strong>';

                if (count($cities) === 1) {
                    $city = Utils\Arrays::getFirstElement($cities);

                    $text .= '<br>' . esc_html($city->name);
                }

                return $text;

            case "contact":
                return '<a href="mailto:' . esc_attr($subscriber->contactEmail) . '">' . esc_html($subscriber->contactEmail) . '</a><br>' . esc_html(Utils::formatPhone($subscriber->contactPhone));

            case "start_date":
                return $item->dateFrom->format("j. n. Y");

            case "end_date":
                $monthsLeft = Carbon::today()->diffInMonths($item->dateTo);
                $text       = $item->dateTo->format("j. n. Y") . " (" . sprintf(_n('%d month left', '%d months left', $monthsLeft, 'reservations'), $monthsLeft) . ")<br>";

                $text .= TranslatableEnums::subscriptionTypesTable()[$item->subscriptionType] ?? "";

                return $text;

            case "period":
                $text = $item->dateFrom->format("j. n. Y") . "<br>";
                $text .= "<strong>" . $item->dateTo->format("j. n. Y") . " (" . sprintf(_n('%d month left', '%d months left', $monthsLeft, 'reservations'), $monthsLeft) . ")</strong>";

                return $text;

            case "other":
                $text = $item->ageGroup["labelLC"] . "<br>";
                $text .= TranslatableEnums::subscriptionTypesTable()[$item->subscriptionType] ?? "";

                if ($item->subscriptionType === SubscriptionType::MONTHLY) {
                    $text .= " (" . sprintf(_n('%d months', '%d months', $item->numMonths, 'reservations'), $item->numMonths);
                }

                return $text;

            default:
                return "unknown: " . $column_name;
        }
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

        $ageGroupSelect      = Utils::getAgeGroupSelect(null, !is_null($filters["age_group"]) ? $filters["age_group"] : -1);
        $gymTreeSelect       = Utils\Html::getGymTreeSelect(Models\Gym::accessible()->sortByName()->get(), $filters["gym_id"]);
        $trainingGroupSelect = Utils\Html::getTrainingGroupSelect(Models\TrainingGroup::accessible()->sortByName()->get(), $filters["tgroup_id"]);

        $subscriptionTypes      = Models\Local\SubscriptionType::forObjectType(ObjectType::TRAININGS);
        $subscriptionTypeSelect = Utils\Html::getSelect(collect($subscriptionTypes)->map(function ($type) {
            return TranslatableEnums::subscriptionTypesUcFirst()[$type];
        }), $filters["sub_type"]);

        ?>
        <div class="alignleft actions">
        <select name="gym_id">
            <option value=""><?php _e('&mdash; Gym &mdash;', 'reservations');?></option>
            <?=$gymTreeSelect?>
        </select>
        <select name="tgroup_id">
            <option value=""><?php _e('&mdash; Training Group &mdash;', 'reservations');?></option>
            <?=$trainingGroupSelect?>
        </select>
        <select name="age_group">
            <option value=""><?php _e('&mdash; Age Group &mdash;', 'reservations');?></option>
            <?=$ageGroupSelect?>
        </select>
        <?php if (Reservations::MODE === "lead"): ?>
            <select name="sub_type">
                <option value=""><?php _e('&mdash; Subscription Type &mdash;', 'reservations');?></option>
                <?=$subscriptionTypeSelect?>
            </select>
        <?php endif;?>
        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'reservations');?>">
        </div>
        <?php
}
}
