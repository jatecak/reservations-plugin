<?php

use Reservations\Admin;
use Reservations\Base;
use Reservations\Cron;
use Reservations\GoPay;
use Reservations\Invoices\FakturoidInvoiceGenerator;
use Reservations\Invoices\IDokladInvoiceGenerator;
use Reservations\Mail;
use Reservations\Models;
use Reservations\Models\Local\ObjectType;
use Reservations\PageRouter;
use Reservations\PostTypes;
use Reservations\Taxonomies;
use Reservations\Utils;

class Reservations extends Base\Plugin
{
    use Utils\ServiceContainer;

    const SLUG    = RESERVATIONS_SLUG;
    const VERSION = RESERVATIONS_VERSION;
    const MODE    = RESERVATIONS_MODE;
    const DEBUG   = RESERVATIONS_DEBUG;

    const ABSPATH  = __DIR__ . "/..";
    const PREFIX   = "res_";
    const TEMP_DIR = WP_CONTENT_DIR . '/cache';

    public $activating = false;

    /** @deprecated */
    public function getGymTree($onlyAccessible = false)
    {
        $gyms = $onlyAccessible ? Models\Gym::accessible()->get() : Models\Gym::all();
        $tree = [];

        foreach ($gyms as $gym) {
            $city     = $gym->city;
            $cityName = $city ? $city->name : __('Without city', 'reservations');

            $tree[$cityName][$gym->id] = $gym->name;
        }

        return $tree;
    }

    public function getAgeGroupTree()
    {
        if (self::MODE === "lead") {
            return [
                0,
                [
                    "parent"   => 0,
                    "children" => [5, 6, 7],
                ],

                1,
                [
                    "parent"   => 1,
                    "children" => [8, 9, 10],
                ],

                2,
                [
                    "parent"   => 2,
                    "children" => [11, 12, 13],
                ],

                3,
                [
                    "parent"   => 3,
                    "children" => [14, 15, 16],
                ],

                4,
                [
                    "parent"   => 4,
                    "children" => [17, 18, 19],
                ],
            ];
        }

        return [0, 1];
    }

    public function getAgeGroups()
    {
        if (self::MODE === "lead") {
            $ageGroups = [
                [
                    "id"    => 0,
                    "slug"  => "6_9",
                    "label" => __('6-9 years', 'reservations'),
                    "order" => 0,
                ], [
                    "id"    => 1,
                    "slug"  => "10_13",
                    "label" => __('10-13 years', 'reservations'),
                    "order" => 4,
                ], [
                    "id"    => 2,
                    "slug"  => "14",
                    "label" => __('14+ years', 'reservations'),
                    "order" => 8,
                ], [
                    "id"    => 3,
                    "slug"  => "adults",
                    "label" => __('Adults', 'reservations'),
                    "order" => 16,
                ], [
                    "id"    => 4,
                    "slug"  => "mix",
                    "label" => __('Mix', 'reservations'),
                    "order" => 14,
                ],
            ];

            $index = 5;
            foreach ($ageGroups as $parent) {
                $ageGroups[] = [
                    "id"    => $index++,
                    "slug"  => $parent["slug"] . "_b",
                    "label" => __('Beginners', 'reservations'),
                    "order" => $parent["order"] + 1,
                ];

                $ageGroups[] = [
                    "id"    => $index++,
                    "slug"  => $parent["slug"] . "_i",
                    "label" => __('Intermediate', 'reservations'),
                    "order" => $parent["order"] + 2,
                ];

                $ageGroups[] = [
                    "id"    => $index++,
                    "slug"  => $parent["slug"] . "_a",
                    "label" => __('Advanced', 'reservations'),
                    "order" => $parent["order"] + 3,
                ];
            }
        } else {
            $ageGroups = [
                [
                    "id"    => 0,
                    "slug"  => "junior",
                    "label" => __('Junior', 'reservations'),
                    "order" => 0,
                ], [
                    "id"    => 1,
                    "slug"  => "mature",
                    "label" => __('Mature', 'reservations'),
                    "order" => 1,
                ],
            ];
        }

        Utils::expandAgeGroups($ageGroups);
        return $ageGroups;
    }

    public function getEventTypes()
    {
        if (self::MODE === "lubo") {
            $eventTypes = [
                "workshop" => [
                    "slugPlural"        => "workshops",
                    "label"             => __('Workshop', 'reservations'),
                    "labelPlural"       => __('Workshops', 'reservations'),
                    "listTitle"         => __('Workshop List', 'reservations'),
                    "listTitleFiltered" => __('Workshops in %s', 'reservations'),
                    "listEmpty"         => __('No workshops.', 'reservations'),
                ],
                "camp"     => [
                    "slugPlural"        => "camps",
                    "label"             => __('Camp', 'reservations'),
                    "labelPlural"       => __('Camps', 'reservations'),
                    "listTitle"         => __('Camp List', 'reservations'),
                    "listTitleFiltered" => __('Camps in %s', 'reservations'),
                    "listEmpty"         => __('No camps.', 'reservations'),
                ],

            ];
        } else {
            $eventTypes = [
                "workshop" => [
                    "slugPlural"        => "workshops",
                    "label"             => __('Workshop', 'reservations'),
                    "labelPlural"       => __('Workshops', 'reservations'),
                    "listTitle"         => __('Workshop List', 'reservations'),
                    "listTitleFiltered" => __('Workshops in %s', 'reservations'),
                    "listEmpty"         => __('No workshops.', 'reservations'),
                ],
                "camp"     => [
                    "slugPlural"        => "camps",
                    "label"             => __('Camp', 'reservations'),
                    "labelPlural"       => __('Camps', 'reservations'),
                    "listTitle"         => __('Camp List', 'reservations'),
                    "listTitleFiltered" => __('Camps in %s', 'reservations'),
                    "listEmpty"         => __('No camps.', 'reservations'),
                ],
            ];
        }

        Utils::expandEventTypes($eventTypes);
        return $eventTypes;
    }

    public function isSubscriptionEnabled($objectType, $eventType = null)
    {
        $currentUser = Models\User::current();
        $isAdmin     = $currentUser && $currentUser->can("administrator");

        if ($isAdmin) {
            return true;
        }

        switch ($objectType) {
            case ObjectType::TRAININGS:
                return true;

            case ObjectType::EVENT:
                return true;
        }

        return false;
    }

    public function isFeatureEnabled($feature)
    {
        $mode = self::MODE;

        switch ($feature) {
            case "replacements":
                return true;

            case "annual_subscription":
                return $mode === "lubo";

            case "city_acl":
                return $mode === "lubo" || self::DEBUG;

            case "export_presets":
                return self::DEBUG;

            case "force_schedule":
                return $mode === "lead";

            case "initial_payment_notification":
                return $mode === "lead";

            case "limit_monthly_to_term":
                return $mode === "lubo";

            case "unified_events":
                return $mode === "lead";

            case "terms_capacity":
                return $mode === "lubo";

            case "camp_type":
                return true;

            case "event_subscription_control":
                return true;

            case "test_message":
                return $mode === "lead" || self::DEBUG;

            case "email_from":
                return $mode === "lead";

            case "event_subscriber_account":
                return $mode === "lubo";

            case "trainings_subscriber_account":
                return true;

            case "subscription_notification":
                return true;

            case "import_export":
                return $mode === "lead";

            case "events_map":
                // return $mode === "lubo";
                return false;

            case "invoices_idoklad":
                return $mode === "lubo";

            case "trainings_map":
                return false;
        }

        return true;
    }

    /** @filter(load_textdomain_mofile) */
    public function filterMofile($mofile, $domain)
    {
        if ($domain !== "reservations") {
            return $mofile;
        }

        return str_replace("reservations-", "reservations-" . self::MODE . "-", $mofile);
    }

    public function activate()
    {
        parent::activate();

        $this->activating = true;

        $this->misc->updateDatabaseSchema();

        flush_rewrite_rules();
    }

    public function run()
    {
        parent::run();

        $this->createService(PostTypes\Training::class, "trainingPostType");
        $this->createService(PostTypes\Event::class, "eventPostType");

        $this->createService(Taxonomies\City::class, "cityTaxonomy");
        $this->createService(Taxonomies\Gym::class, "gymTaxonomy");
        $this->createService(Taxonomies\TrainingGroup::class, "trainingGroupTaxonomy");

        $this->createService(Admin\InstructorProfile::class, "instructorProfile");
        $this->createService(Admin\Settings::class, "settings");
        $this->createService(Admin\SettingsPage::class, "settingsPage");

        $this->createService(Cron::class, "cron");
        $this->createService(Mail\Mailer::class, "mailer");
        $this->createService(GoPay\InstanceManager::class, "goPayManager");
        $this->createService(IDokladInvoiceGenerator::class, "invoiceGenerator");
        $this->createService(PageRouter::class, "pageRouter");
        $this->createService(Utils\Misc::class, "misc");

        $this->createService(Admin\Subscriptions::class, "subscriptions");
        $this->createService(Admin\Events\Subscriptions::class, "eventsSubscriptions");
        $this->createService(Admin\Replacements::class, "replacements");
        $this->createService(Admin\Events\Replacements::class, "eventsReplacements");
        $this->createService(Admin\Export::class, "export");
        $this->createService(Admin\Events\Export::class, "eventsExport");
        $this->createService(Admin\MergeGym::class, "mergeGym");
        $this->createService(Admin\ImportExport::class, "importExport");

        $this->createService(Utils\Compatibility::class, "compatibility");
        $this->createService(Utils\ACL::class, "acl");

        if (static::DEBUG) {
            Tracy\Debugger::getBar()->addPanel(new Utils\TracyEloquentPanel);
        }
    }
}
