<?php

namespace Reservations\Pages;

use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\Models\Local;
use Reservations\Pages\Utils as PagesUtils;
use Reservations\Utils;

class Schedule extends Base\Page
{
    use PagesUtils\AdminBar;

    private $gym;
    private $minTime;
    private $maxTime;

    public function setGym($gym)
    {
        $this->gym = $gym;
    }

    public function assets()
    {
        wp_enqueue_style("res-css", $this->plugin->url("public/style.css"));

        if (Reservations::MODE === "lead") {
            wp_enqueue_style("res-lead-css", $this->plugin->url("public/style-lead.css"), ["res-css"]);
        }
    }

    protected function loadPermalink()
    {
        parent::loadPermalink();

        $this->permalink = $this->parentLink($this->gym->slug, true);
    }

    public function prepare()
    {
        if($this->gym->customSubscribeUrl) {
            $this->redirect($this->gym->customSubscribeUrl);
        }

        $this->minTime = Utils::parseTime($this->plugin->getOption("schedule_time_min"));
        $this->maxTime = Utils::parseTime($this->plugin->getOption("schedule_time_max"));

        // defaults
        $this->minTime = floor($this->minTime / 1800) * 1800;
        $this->maxTime = ceil($this->maxTime / 1800) * 1800;

        $gymId        = $this->gym->id;
        $this->events = Models\Training::status("publish")->inGym($this->gym->id)->get();

        $this->timeslots = [];

        foreach ($this->events as $event) {
            $tgroup = $event->trainingGroup();

            if ($tgroup !== null) {
                if (!$tgroup->subscriptionEnabled && $tgroup->customSubscribeUrl) {
                    $event->capacityFormatted = "";
                } else {
                    $event->capacityFormatted = Utils::getFrontendCapacity($tgroup);
                }
            } else {
                $event->capacityFormatted = "";
            }

            $event->permalink = $this->link($event->post_id, true);

            foreach ($event->timeslots as $ts) {
                $ts["event"] = $event;

                $this->timeslots[] = $ts;
            }
        }

        $this->timeslots = collect($this->timeslots);

        foreach ($this->timeslots as $ts) {
            if ($this->minTime >= $ts["start_time"]) {
                $this->minTime = max(0, $ts["start_time"] - 1800);
            }

            if ($this->maxTime <= $ts["end_time"]) {
                $this->minTime = min(86399, $ts["end_time"] + 1800);
            }
        }
    }

    public function render()
    {
        $gym = $this->gym;

        $overflow = [];
        for ($i = 0; $i < 7; $i++) {
            $overflow[] = 0;
        }

        $today = Utils::today();

        $weekStart = $today->copy()->startOfWeek();

        $buttonPosition = Reservations::MODE === "lead" ? "bottom" : "top";

        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $dates[] = $weekStart->copy()->addDays($i)->format("j. n.");
        }

        $usedAgeGroups = [];

        $table = [];
        for ($time = $this->minTime, $i = 0; $time < $this->maxTime; $time += 1800, $i++) {
            $row = [
                "timeEmpty" => false,
                "time"      => null,
                "days"      => [],
                "classes"   => [],
            ];

            $rowEndTime = $time + 1800;

            if ($i === 0 && $time % 3600 !== 0) {
                // start time is not whole hour
                $row["timeEmpty"] = true;
            } else if ($time % 3600 === 0) {
                $row["time"] = Utils::formatTime($time);
            }

            if ($this->minTime % 3600 !== 0 ? ($i + 1) % 4 >= 2 : $i % 4 >= 2) {
                $row["classes"][] = "dark";
            }

            for ($j = 0; $j < 7; $j++) {
                $dayi = ($j + 1) % 7;
                $day  = [
                    "event"    => null,
                    "timeslot" => null,
                    "empty"    => false,
                    "span"     => 1,
                ];

                if ($overflow[$dayi] > 0) {
                    $overflow[$dayi]--;
                    $row["days"][] = $day;
                    continue;
                }

                $timeslots = $this->timeslots->filter(function ($timeslot) use ($dayi, $time, $rowEndTime) {
                    $startTime = $timeslot["start_time"];
                    $endTime   = $timeslot["end_time"];

                    return $startTime !== $endTime && $timeslot["weekday"] === $dayi && $startTime >= $time && $startTime < $rowEndTime;
                });

                $nextTimeslots = $this->timeslots->filter(function ($timeslot) use ($dayi, $time, $rowEndTime) {
                    $startTime = $timeslot["start_time"];
                    $endTime   = $timeslot["end_time"];

                    return $startTime !== $endTime && $timeslot["weekday"] === $dayi && $startTime >= $time + 1800 && $startTime < $rowEndTime + 1800;
                });

                if (!count($timeslots)) {
                    $day["empty"] = true;

                    // ($this->minTime % 3600 === 0 || $i > 0) &&
                    if (!count($nextTimeslots) && ($this->minTime % 3600 === 0 ? ($i % 4 === 0 || $i % 4 === 2) : ($i % 4 === 1 || $i % 4 === 3))) {
                        $day["span"]     = 2;
                        $overflow[$dayi] = 1;
                    }
                } else {
                    $timeslot = $timeslots->first();
                    $event    = $timeslot["event"];

                    $duration  = abs(min($this->maxTime, $timeslot["end_time"]) - $timeslot["start_time"]);
                    $duration2 = $timeslot["start_time"] % 1800 + $duration;

                    $span = ceil($duration2 / 1800);

                    $day["span"]     = $span;
                    $overflow[$dayi] = $span - 1;

                    if (!isset($usedAgeGroups[$event->ageGroup])) {
                        $usedAgeGroups[$event->ageGroup] = count($usedAgeGroups) + 1;
                    }

                    $timeslot["time_formatted"] = Utils::formatTime($timeslot["start_time"]) . "&mdash;" . Utils::formatTime($timeslot["end_time"]);

                    // $event->class         = ["res-junior", "res-mature"][$event->ageGroup];
                    $event->class = "res-group-" . $usedAgeGroups[$event->ageGroup];

                    $day["event"]    = $event;
                    $day["timeslot"] = $timeslot;
                }

                $row["days"][] = $day;
            }

            $row["class"] = implode(" ", $row["classes"]);

            $table[] = $row;
        }

        $tgroups = collect($gym->trainingGroups)->filter(function ($tgroup) {
            return $tgroup->subscriptionEnabled;
        })->values()->all();

        $subscribeEnabled = false;

        if (count($tgroups) === 1 && !$this->plugin->isFeatureEnabled("force_schedule")) {
            $subscribeEnabled = true;
            $subscribeUrl     = $tgroups[0]->subscribeLink;
        }

        $usedAgeGroups = collect($usedAgeGroups)->sortBy(function ($count, $key) {
            $group = Local\AgeGroup::find($key);

            return $group ? $group["order"] : $key; // just in case
        })->all();

        $legend = [];
        foreach ($usedAgeGroups as $id => $classId) {
            $legend[] = [
                "label" => Utils::getageGroupPath($id),
                "class" => "res-group-" . $classId,
            ];
        }
        


        include Reservations::ABSPATH . "/public/schedule.php";
        

    }
}
