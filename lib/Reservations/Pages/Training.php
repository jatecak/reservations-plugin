<?php

namespace Reservations\Pages;

use Nette\Utils\Strings;
use Reservations;
use Reservations\Base;
use Reservations\Models\Local;
use Reservations\Pages\Utils as PagesUtils;
use Reservations\Utils;

class Training extends Base\Page
{
    use PagesUtils\AdminBar;

    private $training;
    private $gym;
    private $tgroup;

    public function setTraining($training)
    {
        $this->training = $training;
    }

    public function assets()
    {
        wp_enqueue_script("gmaps", "https://maps.googleapis.com/maps/api/js?key=" . $this->plugin->getOption("google_maps_api_key"));
        wp_enqueue_script("training", $this->plugin->url("public/training.js"), ["gmaps", "jquery"]);
        wp_localize_script("training", "training_data", [
            "training" => $this->training->toArray(),
        ]);
        wp_enqueue_style("res-css", $this->plugin->url("public/style.css"));

        if (Reservations::MODE === "lead") {
            wp_enqueue_style("res-lead-css", $this->plugin->url("public/style-lead.css"), ["res-css"]);
        }
    }

    public function prepare()
    {
        $this->title  = $this->training->title;
        $this->gym    = $this->training->gym();
        $this->tgroup = $this->training->trainingGroup();
    }

    public function render()
    {
        $training = $this->training;
        $tgroup   = $this->tgroup;
        $gym      = $this->gym;

        $weekdays = [
            [_x('every %s', 'sunday', 'reservations'), _x('sunday', 'training view', 'reservations')],
            [_x('every %s', 'monday', 'reservations'), _x('monday', 'training view', 'reservations')],
            [_x('every %s', 'tuesday', 'reservations'), _x('tuesday', 'training view', 'reservations')],
            [_x('every %s', 'wednesday', 'reservations'), _x('wednesday', 'training view', 'reservations')],
            [_x('every %s', 'thursday', 'reservations'), _x('thursday', 'training view', 'reservations')],
            [_x('every %s', 'friday', 'reservations'), _x('friday', 'training view', 'reservations')],
            [_x('every %s', 'saturday', 'reservations'), _x('saturday', 'training view', 'reservations')],
        ];

        $timeText  = "";
        $timeslots = Utils::sortTimeslots($training->timeslots);

        foreach ($timeslots as $i => $ts) {
            $text = sprintf(__('%s from %s to %s', 'reservations'), sprintf($weekdays[$ts["weekday"]][0], '<strong>' . $weekdays[$ts["weekday"]][1] . '</strong>'), '<strong>' . Utils::formatTime($ts["start_time"]) . '</strong>', '<strong>' . Utils::formatTime($ts["end_time"]) . '</strong>');

            if ($i === 0) {
                $timeText = Strings::firstUpper($text);

                if ($i === count($timeslots) - 1) {
                    $timeText .= ".";
                }
            } else if ($i === count($timeslots) - 1) {
                $timeText .= sprintf(" %s %s.", __('and', 'reservations'), $text);
            } else {
                $timeText .= ", " . $text;
            }
        }

        $ageGroup = Utils::getAgeGroupPath($training->ageGroup, "labelLC", " ");

        $mapsLink = "https://www.google.cz/maps/search/" . urlencode($gym->name) . "/@" . $gym->lat . "," . $gym->lng . ",17z/?hl=cs";
        $formUrl  = $this->plugin->url("public/application_form.pdf");

        if ($tgroup !== null) {
            if (!$tgroup->subscriptionEnabled && $tgroup->customSubscribeUrl) {
                $subscribeEnabled = true;
                $subscribeUrl     = $tgroup->customSubscribeUrl;

                $capacityFormatted = "";
            } else {
                $subscribeEnabled = $tgroup->subscriptionEnabled;
                $subscribeUrl     = $tgroup->subscribeLink;

                $capacityFormatted = Utils::getFrontendCapacity($tgroup);
            }
        } else {
            $subscribeEnabled = false;
            $subscribeUrl     = "";

            $capacityFormatted = "";
        }

        $contactInstructor = $training->contactInstructor();
        $instructors       = $training->instructors()->get();

        $description = wpautop(wptexturize($this->training->description));

        $contactEmail = $contactInstructor ? $contactInstructor->contactEmail : $this->training->contactEmail;
        $contactPhone = $contactInstructor ? $contactInstructor->contactPhone : $this->training->contactPhone;

        $showInstructors = count($instructors) > 0;
        $showContact     = $contactEmail !== "" || $contactPhone !== "";

        include Reservations::ABSPATH . "/public/training.php";
    }
}
