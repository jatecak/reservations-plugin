<?php

namespace Reservations\Admin\Export;

use Reservations\Models\Local\TranslatableEnums;

class Columns
{
    protected static function genFilterFormatDate($format = "j. n. Y")
    {
        return function ($value) use ($format) {
            return $value->format($format);
        };
    }

    protected static function genFilterTranslate($trans = [])
    {
        return function ($value) use ($trans) {
            return $trans[$value] ?? $value;
        };
    }

    public static function get()
    {
        return [
            "subscriber"     => [
                "type"           => "group",
                "label"          => __('Subscriber', 'reservations'),
                "default_object" => "subscriber",
                "children"       => [
                    "first_name"            => __('First Name', 'reservations'),
                    "last_name"             => __('Last Name', 'reservations'),
                    "full_name"             => [
                        "label"    => __('First Name + Last Name', 'reservations'),
                        "callback" => function ($subscriber, $column) {
                            return $subscriber->first_name . " " . $subscriber->last_name;
                        },
                    ],
                    "full_name2"            => [
                        "label"    => __('Last Name + First Name', 'reservations'),
                        "callback" => function ($subscriber, $column) {
                            return $subscriber->last_name . " " . $subscriber->first_name;
                        },
                    ],
                    "date_of_birth"         => [
                        "label"  => __('Date of Birth (d. m. yyyy)', 'reservations'),
                        "filter" => self::genFilterFormatDate(),
                    ],
                    "address"               => __('Address', 'reservations'),
                    "personal_number"       => __('Personal Number', 'reservations'),
                    "health_insurance_code" => [
                        "label"      => __('Health Insurance Company Code', 'reservations'),
                        "event_only" => true,
                    ],
                    "health_restrictions"   => __('Health Restrictions', 'reservations'),
                    "used_medicine"         => [
                        "label"      => __('Used Medicine', 'reservations'),
                        "event_only" => true,
                    ],
                    "swimmer"               => [
                        "label"      => __('Swimmer (0 or 1)', 'reservations'),
                        "format"     => "integer",
                        "event_only" => true,
                    ],
                    "shirt_size"            => [
                        "label"      => __('Shirt Size', 'reservations'),
                        "filter"     => self::genFilterTranslate(TranslatableEnums::shirtSizes()),
                        "event_only" => true,
                    ],
                    "facebook"              => [
                        "label"          => __('Facebook', 'reservations'),
                        "trainings_only" => true,
                    ],
                ],
            ],
            "representative" => [
                "type"           => "group",
                "label"          => __('Representative', 'reservations'),
                "default_object" => "subscriber",
                "children"       => [
                    "rep_first_name"    => __('First Name', 'reservations'),
                    "rep_last_name"     => __('Last Name', 'reservations'),
                    "rep_full_name"     => [
                        "label"    => __('First Name + Last Name', 'reservations'),
                        "callback" => function ($subscriber, $column) {
                            return $subscriber->rep_first_name . " " . $subscriber->rep_last_name;
                        },
                    ],
                    "rep_full_name2"    => [
                        "label"    => __('Last Name + First Name', 'reservations'),
                        "callback" => function ($subscriber, $column) {
                            return $subscriber->rep_last_name . " " . $subscriber->rep_first_name;
                        },
                    ],
                    "rep_date_of_birth" => [
                        "label"  => __('Date of Birth (d. m. yyyy)', 'reservations'),
                        "filter" => self::genFilterFormatDate(),

                    ],
                    "rep_address"       => __('Address', 'reservations'),
                ],
            ],
            "contact"        => [
                "type"           => "group",
                "label"          => __('Contact', 'reservations'),
                "default_object" => "subscriber",
                "children"       => [
                    "contact_email"   => __('Email', 'reservations'),
                    "contact_phone"   => [
                        "label"          => __('Phone', 'reservations'),
                        "key"            => "contact_phone",
                        "trainings_only" => true,
                    ],
                    "contact_phone_1" => [
                        "label"      => __('Phone (mother)', 'reservations'),
                        "key"        => "contact_phone",
                        "event_only" => true,
                    ],
                    "contact_phone_2" => [
                        "label"      => __('Phone (father)', 'reservations'),
                        "event_only" => true,
                    ],
                ],
            ],
            "other"          => [
                "type"           => "group",
                "label"          => __('Other', 'reservations'),
                "default_object" => "subscriber",
                "event_only"     => true,
                "children"       => [
                    "carpool"         => [
                        "label"  => __('Carpool', 'reservations'),
                        "filter" => self::genFilterTranslate(TranslatableEnums::carpool()),
                    ],
                    "carpool_seats"   => [
                        "label"  => __('Number of Requested/Offered Seats', 'reservations'),
                        "format" => "integer",
                    ],
                    "carpool_contact" => _x('Contact Phone', 'carpool', 'reservations'),

                    "catering"        => [
                        "label"  => __('Catering (0 or 1)', 'reservations'),
                        "format" => "integer",
                    ],
                    "catering2"       => [
                        "label"  => __('Catering', 'reservations'),
                        "key"    => "catering",
                        "filter" => self::genFilterTranslate(TranslatableEnums::catering()),
                    ],
                    "meal"            => _x('Selected Meal', 'export', 'reservations'),

                    "referrer"        => [
                        "label"    => __('Referrer', 'reservations'),
                        "callback" => function ($subscriber, $col) {
                            $referrer = $subscriber->referrer;

                            if ($referrer === "other") {
                                return $subscriber->referrerOther;
                            }

                            return TranslatableEnums::referrers()[$referrer] ?? $referrer;
                        },
                    ],
                    "reason"          => [
                        "label"    => _x('Reason', 'export', 'reservations'),
                        "object"   => "subscription",
                        "callback" => function ($subscription, $col) {
                            $subscriber = $subscription->subscriber;
                            $reason     = $subscriber->reason;

                            if ($subscriber->reason === "other") {
                                return $subscriber->reasonOther;
                            }

                            if ($subscription->event->eventType["id"] === "workshop") {
                                return TranslatableEnums::workshopReasons()[$reason] ?? $reason;
                            } else if ($subscription->event->eventType["id"] === "camp") {
                                return TranslatableEnums::runReasons()[$reason] ?? $reason;
                            }

                            return $reason;
                        },
                    ],
                ],
            ],
            "subscription"   => [
                "type"           => "group",
                "label"          => __('Subscription', 'reservations'),
                "default_object" => "subscription",
                "children"       => [
                    "date_from"                 => [
                        "label"          => __('Start Date', 'reservations'),
                        "filter"         => self::genFilterFormatDate(),
                        "trainings_only" => true,
                    ],
                    "date_to"                   => [
                        "label"          => __('End Date', 'reservations'),
                        "filter"         => self::genFilterFormatDate(),
                        "trainings_only" => true,
                    ],
                    "months_remaining"          => [
                        "label"          => __('Months Remaining', 'reservations'),
                        "key"            => "dateTo",
                        "filter"         => function ($value) {
                            return max(0, Utils::today()->diffInMonths($value));
                        },
                        "trainings_only" => true,
                    ],
                    "months_remaining2"         => [
                        "label"          => __('Months Remaining + months', 'reservations'),
                        "key"            => "dateTo",
                        "filter"         => function ($value) {
                            $monthsLeft = max(0, Utils::today()->diffInMonths($value));
                            return sprintf(_n('%d month', '%d months', $monthsLeft, 'reservations'), $monthsLeft);
                        },
                        "trainings_only" => true,
                    ],
                    "subscription_type"         => [
                        "label"  => __('Subscription Type', 'reservations'),
                        "key"    => "subscriptionType",
                        "filter" => self::genFilterTranslate(TranslatableEnums::subscriptionTypes()),
                    ],
                    "age_group"                 => [
                        "label"  => __('Age Group', 'reservations'),
                        "key"    => "ageGroup",
                        "filter" => function ($value) {
                            return $value["label"];
                        },
                    ],
                    "age_group2"                => [
                        "label"  => __('Age Group (lowercase)', 'reservations'),
                        "key"    => "ageGroup",
                        "filter" => function ($value) {
                            return $value["labelLC"];
                        },
                    ],
                    "active"                    => [
                        "label"          => __('Active (0 or 1)', 'reservations'),
                        "format"         => "integer",
                        "trainings_only" => true,
                    ],
                    "paid"                      => [
                        "label"  => __('Paid (0 or 1)', 'reservations'),
                        "format" => "integer",
                    ],
                    "paid_partially"            => [
                        "label"  => __('Paid Partially (0 or 1)', 'reservations'),
                        "key"    => "paidAmount",
                        "filter" => function ($value) {
                            return $value > 0;
                        },
                        "format" => "integer",
                    ],
                    "total_amount"              => [
                        "label"  => __('Total Amount', 'reservations'),
                        "key"    => "paymentAmount",
                        "format" => "integer",
                    ],
                    "paid_amount"               => [
                        "label"  => __('Paid Amount', 'reservations'),
                        "key"    => "paidAmount",
                        "format" => "integer",
                    ],
                    "application_form_received" => [
                        "label"  => __('Application Form Received (0 or 1)', 'reservations'),
                        "key"    => "applicationFormReceived",
                        "format" => "integer",
                    ],
                    "is_replacement"            => [
                        "label"  => __('Is Replacement (0 or 1)', 'reservations'),
                        "key"    => "isReplacement",
                        "format" => "integer",
                    ],
                    "created_at"                => [
                        "label"  => __('Registration Date (d. m. rrrr h:m:s)', 'reservations'),
                        "filter" => self::genFilterFormatDate("j. n. Y H:i:s"),
                    ],
                ],
            ],
            "tgroup"         => [
                "type"           => "group",
                "label"          => __('Training Group', 'reservations'),
                "default_object" => "tgroup",
                "trainings_only" => true,
                "children"       => [
                    "name" => _x('Name', 'training group', 'reservations'),
                ],
            ],
            "gym"            => [
                "type"           => "group",
                "label"          => __('Gym', 'reservations'),
                "default_object" => "gym",
                "trainings_only" => true,
                "children"       => [
                    "name"              => _x('Name', 'gym', 'reservations'),
                    "address"           => __('Address', 'reservations'),
                    "city_name"         => [
                        "label"  => __('City Name', 'reservations'),
                        "key"    => "name",
                        "object" => "city",
                    ],
                    "name_with_city"    => [
                        "label"  => __('Name with City', 'reservations'),
                        "key"    => "names",
                        "filter" => function ($value) {
                            return $value["with_city"];
                        },
                    ],
                    "name_with_address" => [
                        "label"  => __('Name with Address', 'reservations'),
                        "key"    => "names",
                        "filter" => function ($value) {
                            return $value["with_address"];
                        },
                    ],
                ],
            ],
            "event"          => [
                "type"           => "group",
                "label"          => __('Event', 'reservations'),
                "default_object" => "event",
                "event_only"     => true,
                "children"       => [
                    "title"     => _x('Name', 'event', 'reservations'),
                    "address"   => __('Address', 'reservations'),
                    "city_name" => [
                        "label"  => __('City Name', 'reservations'),
                        "key"    => "name",
                        "object" => "city",
                    ],
                ],
            ],
        ];
    }

    public static function expand(&$columns, $parent = null)
    {
        foreach ($columns as $id => $column) {
            if (is_string($column)) {
                $column = [
                    "label" => $column,
                ];
            }

            if (!isset($column["type"])) {
                $column["type"] = "column";
            }

            $column["id"]         = $id;
            $column["full_id"]    = $parent ? $parent["full_id"] . "." . $column["id"] : $column["id"];
            $column["full_label"] = $parent ? $parent["full_label"] . ": " . $column["label"] : $column["label"];

            if ($column["type"] === "group") {
                if (!isset($column["default_object"]) && $parent && isset($parent["default_object"])) {
                    $column["default_object"] = $parent["default_object"];
                }

                if (!isset($column["children"])) {
                    $column["children"] = [];
                }

                self::expand($column["children"], $column);
            } else if ($column["type"] === "column") {
                if (!isset($column["object"]) && $parent && isset($parent["default_object"])) {
                    $column["object"] = $parent["default_object"];
                }

                if (!isset($column["format"])) {
                    $column["format"] = "string";
                }

                if (!isset($column["key"])) {
                    $column["key"] = $column["id"];
                }
            }

            $columns[$id] = $column;
        }

        return $columns;
    }

    public static function flatten($columns, $output = [])
    {
        foreach ($columns as $column) {
            if ($column["type"] === "group") {
                $output = self::flatten($column["children"], $output);
            } else if ($column["type"] === "column") {
                $output[$column["full_id"]] = $column;
            }
        }

        return $output;
    }
}
