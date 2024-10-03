<?php

namespace Reservations;

use Carbon\Carbon;
use Nette\Utils\Strings;
use Reservations;
use Reservations\Models;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Local\SubscriptionType;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\Utils\Arrays;

class Utils
{
    public static function allSet($array, $keys)
    {
        foreach ($keys as $key) {
            if (!isset($array[$key])) {
                return false;
            }
        }

        return true;
    }

    public static function getTimezone()
    {
        $timezone = get_option("timezone_string");

        if (!$timezone) {
            $timezone = (int) get_option("gmt_offset");
        }

        return $timezone;
    }

    public static function now()
    {
        return Carbon::now(static::getTimezone());
    }

    public static function today()
    {
        return Carbon::today(static::getTimezone());
    }

    public static function sanitizeTime($time)
    {
        $parts = Strings::match(Strings::normalize($time), '/^([0-9]{1,2}):([0-9]{1,2}$)/');

        if (!$parts) {
            return "00:00";
        }

        return Strings::padLeft($parts[1], 2, "0") . ":" . Strings::padLeft($parts[2], 2, "0");
    }

    public static function parseTime($time)
    {
        $parts = Strings::match(Strings::normalize($time), '/^([0-9]{1,2}):([0-9]{1,2}$)/');

        if (!$parts) {
            return 0;
        }

        return ((int) $parts[1] * 3600) + ((int) $parts[2] * 60);
    }

    public static function parseDate($date)
    {
        try {
            return Carbon::createFromFormat("d. m. Y", $date, self::getTimezone());
        } catch (\InvalidArgumentException $ex) {
            return null;
        }
    }

    public static function formatTime($seconds)
    {
        $hours   = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return Strings::padLeft($hours, 2, "0") . ":" . Strings::padLeft($minutes, 2, "0");
    }

    public static function formatTimeRange($seconds, $secondsEnd = null)
    {
        if (is_null($secondsEnd) || self::formatTime($seconds) === self::formatTime($secondsEnd)) {
            return self::formatTime($seconds);
        }

        return self::formatTime($seconds) . " &ndash; " . self::formatTime($secondsEnd);
    }

    public static function formatNumber($number)
    {
        return number_format($number, 0, ",", " ");
    }

    public static function formatPhone($phone)
    {
        $parts = Strings::match(Strings::normalize($phone), '/^((?:00|\+)[0-9]{3})?\s*([0-9]{3})\s*([0-9]{2,3})\s*([0-9]{2,3})$/');

        if (!$parts) {
            return $phone;
        }

        return ($parts[1] !== "" ? $parts[1] . " " : "") . $parts[2] . " " . $parts[3] . " " . $parts[4];
    }

    public static function formatDate($date)
    {
        return $date->format("j. n. Y");
    }

    public static function formatDateRange($date, $dateTo = null)
    {
        if (is_null($dateTo) || $date->format("j. n. Y") === $dateTo->format("j. n. Y")) {
            return $date->format("j. n. Y");
        }

        if ($date->format("n. Y") === $dateTo->format("n. Y")) {
            return $date->format("j.") . " &ndash; " . $dateTo->format("j. n. Y");
        }

        if ($date->format("n. Y") === $dateTo->format("n. Y")) {
            return $date->format("j. n.") . " &ndash; " . $dateTo->format("j. n. Y");
        }

        return $date->format("j. n. Y") . " &ndash; " . $dateTo->format("j. n. Y");
    }

    public static function compareDates($a, $b)
    {
        if ($a->eq($b)) {
            return 0;
        }

        return $a->gt($b) ? 1 : -1;
    }

    public static function getWeekdayPairs($pairs = null)
    {
        if (is_null($pairs)) {
            $pairs = TranslatableEnums::weekdaysUcFirst();
        }

        $weekstart = (int) get_option("start_of_week");

        while ($weekstart > 0) {
            Arrays::rotate($pairs);
            $weekstart--;
        }

        return $pairs;
    }

    public static function sortTimeslots($timeslots)
    {
        $weekstart = (int) get_option("start_of_week");

        return collect($timeslots)->sortBy("start_time")->sortBy(function ($ts) use ($weekstart) {
            return ($ts["weekday"] - $weekstart) % 7;
        })->values()->toArray();
    }

    public static function advanceTermPeriods($termPeriods)
    {
        $advance = (int) Reservations::instance()->getOption("term_advance", "0");

        if (is_nan($advance) || $advance < 0) {
            $advance = 0;
        }

        return collect($termPeriods)->sortBy(function ($term) {
            return $term[0];
        })->map(function ($term) use ($advance) {
            return [
                $term[0]->copy()->subDays($advance),
                $term[1],
            ];
        })->all();
    }

    public static function makePairs($array, $keyProperty, $valueProperty)
    {
        $pairs = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $pairs[$value[$keyProperty]] = $value[$valueProperty];
            } else if (is_object($value)) {
                $pairs[$value->{$keyProperty}] = $value->{$valueProperty};
            } else {
                $pairs[$key] = $value;
            }

        }

        return $pairs;
    }

    public static function getFirstElement($array)
    {
        if (!count($array)) {
            return null;
        }

        return $array[array_keys($array)[0]];
    }

    public static function defaults($array, $defaults = [])
    {
        $array = (array) $array;

        return wp_parse_args($array, $defaults);
    }

    public static function fillDefaults(&$array, $defaults = [])
    {
        $array = self::defaults($array, $defaults);
    }

    public static function ucFirstArray($array)
    {
        return collect($array)->map(function ($value) {
            return ucfirst($value);
        })->toArray();
    }

    public static function joinPaths(...$parts)
    {
        if (!count($parts)) {
            return "";
        }

        $result = collect($parts)->map(function ($part) {
            return trim($part, "/");
        })->reject(function ($part) {
            return $part === "";
        })->implode("/");

        if (substr($parts[0], 0, 1) === "/") {
            $result = "/"+$result;
        }

        if (substr(end($parts), -1) === "/") {
            $result .= "/";
        }

        return $result;
    }

    public static function isAbsoluteUrl($url)
    {
        return (bool) Strings::match($url, '/((https?)|(ftp)):\/\//i');
    }

    public static function resolveAttachmentIds($ids, $basename = true)
    {
        $ids = (array) $ids;

        $resolved = [];
        foreach ($ids as $id) {
            $url = get_attached_file($id);

            if ($url) {
                $resolved[$id] = $basename ? basename($url) : $url;
            }

        }

        return $resolved;
    }

    public static function createHash()
    {
        return md5(random_bytes(16));
    }

    public static function texturize($text, $autop = true)
    {
        $text = wptexturize($text);

        if ($autop) {
            $text = wpautop($text);
        }

        return $text;
    }

    public static function getFileVersion($file)
    {
        $mtime = filemtime($file);

        return implode(".", str_split(substr($mtime, -3)));
    }

    public static function filterPaymentTemplates($templates, $ageGroup = null, $subscriptionType = null, $numMonths = 1)
    {
        if (!is_null($ageGroup) && !is_array($ageGroup)) {
            $ageGroup = ["id" => $ageGroup];
        }

        return collect($templates)->filter(function ($template) use ($ageGroup, $subscriptionType) {
            if (isset($template["age_groups"]) && !is_null($ageGroup) && !in_array($ageGroup["id"], $template["age_groups"])) {
                return false;
            }

            if (isset($template["subscription_types"]) && !is_null($subscriptionType) && !in_array($subscriptionType, $template["subscription_types"])) {
                return false;
            }

            return true;
        })->map(function ($template) use ($numMonths) {
            if (isset($template["amount_monthly"])) {
                $template["amount"] = $template["amount_monthly"] * $numMonths;
            }

            return $template;
        })->toArray();
    }

    public static function sumPaymentTemplates($templates)
    {
        return collect($templates)->sum(function ($template) {
            return $template["amount"];
        });
    }

    public static function getInitialPaymentTemplate($templates)
    {
        $initial = collect($templates)->first(function ($template) {
            return $template["initial"];
        });

        return $initial ?? $templates[0] ?? Utils::getFirstElement($templates);
    }

    private static function getAgeGroupSelectOptgroup($spec, $allowedIds, $selected)
    {
        $groups = Reservations::instance()->getAgeGroups();

        $inner = "";
        foreach ($spec["children"] as $cid) {
            if (is_array($cid)) {
                $inner .= self::getAgeGroupSelectOptgroup($cid, $allowedIds, $selected);
                continue;
            }

            if (is_array($allowedIds) && !in_array($cid, $allowedIds)) {
                continue;
            }

            $child = $groups[$cid];

            $inner .= '<option value="' . esc_attr($child["id"]) . '"' . selected($cid, $selected, false) . '>' . esc_html($child["label"]) . '</option>';
        }

        if (empty($inner)) {
            return "";
        }

        $parent = $groups[$spec["parent"]];
        return '<optgroup label="' . esc_attr($parent["label"]) . '">' . $inner . '</optgroup>';
    }

    public static function getAgeGroupSelect($allowedIds = null, $selected)
    {
        $tree   = Reservations::instance()->getAgeGroupTree();
        $groups = Reservations::instance()->getAgeGroups();

        $out = "";

        foreach ($tree as $id) {
            if (is_array($id)) {
                $out .= self::getAgeGroupSelectOptgroup($id, $allowedIds, $selected);
                continue;
            }

            if (is_array($allowedIds) && !in_array($id, $allowedIds)) {
                continue;
            }

            $group = $groups[$id];

            $out .= '<option value="' . esc_attr($group["id"]) . '"' . selected($id, $selected, false) . '>' . esc_html($group["label"]) . '</option>';
        }

        return $out;
    }

    private static function getAgeGroupsFlatInner($children, $parent)
    {
        $groups = Reservations::instance()->getAgeGroups();

        $out = [];
        foreach ($children as $id) {
            if (is_array($id)) {
                $out = array_merge($out, self::getAgeGroupsFlatInner($id["children"]));
            } else {
                $group = $groups[$id];

                $group["label"]   = $parent["label"] . " > " . $group["label"];
                $group["labelUC"] = $parent["labelUC"] . " > " . $group["labelUC"];
                $group["labelLC"] = $parent["labelLC"] . " > " . $group["labelLC"];

                $out[] = $group;
            }
        }

        return $out;
    }

    public static function getAgeGroupsFlat()
    {
        $tree   = Reservations::instance()->getAgeGroupTree();
        $groups = Reservations::instance()->getAgeGroups();

        $out = [];
        foreach ($tree as $id) {
            if (is_array($id)) {
                $out = array_merge($out, self::getAgeGroupsFlatInner($id["children"], $groups[$id["parent"]]));
            } else {
                $group = $groups[$id];

                $out[] = $group;
            }
        }

        return $out;
    }

    public static function getAgeGroupParents($ageGroup, $inclCurrent = true)
    {
        if (is_integer($ageGroup)) {
            $ageGroup = Models\Local\AgeGroup::find($ageGroup);
        }

        $tree = Models\Local\AgeGroup::getTree();

        $getParent = function ($ageGroup) use ($tree) {
            foreach ($tree as $id) {
                if (!is_array($id)) {
                    continue;
                }

                if (in_array($ageGroup["id"], $id["children"])) {
                    return Models\Local\AgeGroup::find($id["parent"]);
                }

            }

            return null;
        };

        $parents = [];

        $curr = $ageGroup;
        while ($parent = $getParent($curr)) {
            $parents[] = $parent;
            $curr      = $parent;
        }

        $parents = array_reverse($parents);

        if ($inclCurrent) {
            $parents[] = $ageGroup;
        }

        return $parents;
    }

    public static function getAgeGroupPath($ageGroup, $labelProp = "label", $sep = " > ")
    {
        $path = self::getAgeGroupParents($ageGroup);

        return collect($path)->implode($labelProp, $sep);
    }

    public static function expandAgeGroups(&$ageGroups)
    {
        foreach ($ageGroups as $i => $group) {
            $group["labelLC"] = mb_strtolower($group["label"]);
            $group["labelUC"] = mb_strtoupper($group["label"]);

            $ageGroups[$i] = $group;
        }
    }

    public static function expandEventTypes(&$eventTypes)
    {
        foreach ($eventTypes as $i => $type) {
            if (!isset($type["id"])) {
                $type["id"] = $i;
            }

            if (!isset($type["slug"])) {
                $type["slug"] = $type["id"];
            }

            $type["labelLC"]       = mb_strtolower($type["label"]);
            $type["labelUC"]       = mb_strtoupper($type["label"]);
            $type["labelPluralLC"] = mb_strtolower($type["labelPlural"]);
            $type["labelPluralUC"] = mb_strtoupper($type["labelPlural"]);

            $eventTypes[$i] = $type;
        }
    }

    public static function formatSubscribedTrainings($trainings)
    {
        $basePermalink = get_permalink(Reservations::instance()->pageRouter->trainingsPage);

        return collect($trainings)->map(function ($training) use ($basePermalink) {
            $weekdays = [
                [_x('every %s', 'sunday', 'reservations'), _x('sunday', 'training view', 'reservations')],
                [_x('every %s', 'monday', 'reservations'), _x('monday', 'training view', 'reservations')],
                [_x('every %s', 'tuesday', 'reservations'), _x('tuesday', 'training view', 'reservations')],
                [_x('every %s', 'wednesday', 'reservations'), _x('wednesday', 'training view', 'reservations')],
                [_x('every %s', 'thursday', 'reservations'), _x('thursday', 'training view', 'reservations')],
                [_x('every %s', 'friday', 'reservations'), _x('friday', 'training view', 'reservations')],
                [_x('every %s', 'saturday', 'reservations'), _x('saturday', 'training view', 'reservations')],
            ];

            $timeslots = Utils::sortTimeslots($training->timeslots);

            foreach ($timeslots as $i => $ts) {
                $text = sprintf(__('%s from %s to %s', 'reservations'), sprintf($weekdays[$ts["weekday"]][0], '<strong>' . $weekdays[$ts["weekday"]][1] . '</strong>'), '<strong>' . Utils::formatTime($ts["start_time"]) . '</strong>', '<strong>' . Utils::formatTime($ts["end_time"]) . '</strong>');

                if ($i === 0) {
                    $timeText = $text;
                } else if ($i === count($timeslots) - 1) {
                    $timeText .= sprintf(" %s %s.", __('and', 'reservations'), $text);
                } else {
                    $timeText .= ", " . $text;
                }
            }

            $training->timeText      = $timeText;
            $training->ageGroupLabel = Utils::getAgeGroupPath($training->ageGroup, "labelLC", " ");

            $gym = $training->_gym = $training->gym();

            $training->permalink = Utils::joinPaths($basePermalink, $gym->slug, $training->id, "/");

            return $training;
        })->groupBy(function ($training) {
            return $training->_gym->names["with_city"];
        })->all();
    }

    public static function getFirstAvailableDate($tgroup)
    {
        if (!$tgroup->subscriptionEnabled) {
            return null;
        }

        $date = Utils::today();

        $enabledSubscriptionTypes = $tgroup->enabledSubscriptionTypes;

        if (in_array(SubscriptionType::MONTHLY, $enabledSubscriptionTypes)) {

        } else if (in_array(SubscriptionType::BIANNUAL, $enabledSubscriptionTypes)) {
            if ($tgroup->activeTerm) {
                $date = $date->max($tgroup->activeTerm[0]);
            }
        } else if (in_array(SubscriptionType::ANNUAL, $enabledSubscriptionTypes)) {
            if ($tgroup->activeYear) {
                $date = $date->max($tgroup->activeYear[0]);
            }
        }

        return $date;
    }

    public static function getMaxMonthlySubscriptionEndDate($tgroup)
    {
        if (!Reservations::instance()->isFeatureEnabled("limit_monthly_to_term")) {
            return null;
        }

        if ($tgroup->activeTermNoAdvance) {
            return $tgroup->activeTermNoAdvance[1];
        }

        //  else if ($tgroup->activeTerm) {
        //     return $tgroup->activeTerm[1];
        // }

        return null;
    }

    public static function getFrontendCapacity($tgroup)
    {
        $acceptingReplacements = $tgroup->subscriptionEnabled && Reservations::instance()->isFeatureEnabled("replacements");
        $firstAvailableDate    = Utils::getFirstAvailableDate($tgroup);
        $today                 = Utils::today();

        $formatCapacity = function ($num, $acceptingReplacements, $prefix) {
            if ($num > 0) {
                return sprintf(_n('%s %d vacancy left', '%s %d vacancies left', $num, 'reservations'), $prefix, $num);
            } else if ($acceptingReplacements) {
                return sprintf(__('%s accepting replacements', 'reservations'), $prefix);
            } else {
                return sprintf(__('%s occupied', 'reservations'), $prefix);
            }
        };

        if (Reservations::instance()->isFeatureEnabled("terms_capacity") && in_array(SubscriptionType::BIANNUAL, $tgroup->enabledSubscriptionTypes) && $tgroup->activeTerm !== null) {
            $currentTerm = $tgroup->activeTermNoAdvance;
            $nextTerm    = null;

            if ($currentTerm === null) {
                $capacityFormatted = __('no term in progress', 'reservations');
                $nextTerm          = $tgroup->activeTerm;
            } else {
                $capacity = $tgroup->getFreeCapacity($currentTerm[0], $currentTerm[1]);
                $nextTerm = Utils\Arrays::getNextElement($tgroup->termPeriods, $currentTerm);

                $capacityFormatted = $formatCapacity($capacity, $acceptingReplacements && $firstAvailableDate->lte($currentTerm[1]), __('current term', 'reservations'));
            }

            if ($nextTerm !== null) {
                $capacity = $tgroup->getFreeCapacity($nextTerm[0], $nextTerm[1]);
                $capacityFormatted .= "<br>" . $formatCapacity($capacity, $acceptingReplacements && $firstAvailableDate->lte($nextTerm[1]), __('next term', 'reservations'));
            }

            return $capacityFormatted;
        }

        $num = $tgroup->getFreeCapacity();

        if ($num > 0) {
            return sprintf(_n('%d vacancy left', '%d vacancies left', $num, 'reservations'), $num);
        } else if ($acceptingReplacements) {
            return __('accepting replacements', 'reservations');
        } else {
            return __('occupied', 'reservations');
        }
    }
}
