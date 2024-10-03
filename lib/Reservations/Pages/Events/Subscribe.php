<?php

namespace Reservations\Pages\Events;

use Carbon\Carbon;
use DateTime;
use Nette\Utils\Strings;
use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\Pages;
use Reservations\Pages\Utils as PagesUtils;
use Reservations\Utils;

class Subscribe extends Base\Page
{
    use EventsBase, PagesUtils\PasswordProtection, PagesUtils\AdminBar, PagesUtils\SubscriberAccount;

    private $submitted;
    private $oldValues = [];
    private $errors    = [];
    private $paidSubscriptions;
    private $formData;
    protected $event;

    public function getObjectType()
    {
        return ObjectType::EVENT;
    }

    public function setEvent($event)
    {
        $this->event = $event;
    }

    protected function isWorkshop()
    {
        return $this->event->eventType["id"] === "workshop";
    }

    protected function isCamp()
    {
        return $this->event->eventType["id"] === "camp";
    }

    public function assets()
    {
        $this->eventsAssets();

        wp_enqueue_script("res-moment", $this->plugin->url("public/js/moment.min.js"));
        wp_enqueue_script("res-datepicker", $this->plugin->url("public/js/bootstrap-datepicker.min.js"), ["jquery"]);

        if ($this->isUnlocked()) {
            // $paidSubscriptions = [];
            // foreach (Models\Local\AgeGroup::all() as $group) {
            //     $paidSubscriptions[$group["id"]] = $this->paidSubscriptions->filter(function ($sub) use ($group) {
            //         return $sub->age_group === $group["id"];
            //     })->count();
            // }

            wp_enqueue_script("res-subscribe", $this->plugin->url("public/events/subscribe.js"), ["jquery", "res-moment", "res-datepicker"], Utils::getFileVersion($this->plugin->path("public/events/subscribe.js")));
            wp_localize_script("res-subscribe", "subscribe_data", [
                "data" => [
                    "paidSubscriptions"   => $this->paidSubscriptions->count(),
                    "capacity"            => $this->event->capacity,
                    "initialAmount"       => $this->event->getInitialPaymentAmount(),
                    "totalAmount"         => $this->event->getPaymentAmount(),
                    "replacementsEnabled" => $this->plugin->isFeatureEnabled("replacements"),
                ],
            ]);
        }

        wp_enqueue_style("res-datepicker", $this->plugin->url("public/css/bootstrap-datepicker3.standalone.min.css"));

        $this->enqueueGlobalStyle(["res-datepicker"]);
    }

    public function prepare()
    {
        $this->eventsPrepare();

        if (!session_id()) {
            session_start();
        }

        if (!$this->event->subscriptionEnabled) {
            $this->redirect($this->permalink);
        }

        $this->handleUnlockForm();

        if (!$this->isUnlocked()) {
            return;
        }

        if (isset($_GET['id']) && !empty($_GET['id'])) {
            $subscriber = Models\Subscriber::hash(sanitize_text_field($_GET['id']))->first();

            if ($subscriber) {
                foreach ($subscriber->getFillable() as $key) {
                    $val = $subscriber->{$key};

                    if ($val instanceof DateTime) {
                        $val = $val->format("Y-m-d");
                    }

                    $this->oldValues[$key] = $val;
                }
            }
        }

        $this->handleSubscriberAccount();

        $this->submitted         = false;
        $this->paidSubscriptions = $this->event->subscriptions()->paidPartially()->get();

        bdump(collect($this->paidSubscriptions)->pluck("id"));;

        if (isset($_POST['do']) && $_POST['do'] === "submit") {
            $this->submitted = true;
            $this->submit();
        } else if (isset($_POST['do']) && $_POST['do'] === "submit-replacement") {
            $this->submitted = true;
            $this->submitReplacement();
        }
    }

    public function validateForm()
    {
        $this->oldValues = $_POST;

        if (!Utils::allSet($_POST, [
            "first_name", "last_name", "date_of_birth", "health_restrictions", "health_insurance_code", "used_medicine",
            "rep_first_name", "rep_last_name", "rep_address", "contact_email", "contact_phone",
            "_onetime", "_wpnonce",
        ])) {
            return false;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], $this->plugin->prefix("subscribe"))) {
            return false;
        }

        if (!isset($_SESSION['onetime']) || $_SESSION['onetime'] !== $_POST['_onetime']) {
            return false;
        }

        $_SESSION['onetime'] = md5(rand());

        $values = [
            "first_name"            => sanitize_text_field($_POST['first_name']),
            "last_name"             => sanitize_text_field($_POST['last_name']),
            "date_of_birth"         => Carbon::parse(sanitize_text_field($_POST['date_of_birth']), Utils::getTimezone()),

            "health_restrictions"   => sanitize_textarea_field($_POST['health_restrictions']),
            "used_medicine"         => sanitize_textarea_field($_POST['used_medicine']),
            "health_insurance_code" => sanitize_text_field($_POST['health_insurance_code']),

            "rep_first_name"        => sanitize_text_field($_POST['rep_first_name']),
            "rep_last_name"         => sanitize_text_field($_POST['rep_last_name']),
            "rep_address"           => sanitize_text_field($_POST['rep_address']),

            "contact_email"         => filter_var(sanitize_text_field($_POST['contact_email']), FILTER_VALIDATE_EMAIL),
            "contact_phone"         => sanitize_text_field($_POST['contact_phone']),

            "agree_pp"              => isset($_POST['agree_pp']) && $_POST['agree_pp'] === "1",
        ];

        $values["contact_email"] = Strings::lower($values["contact_email"]);
        $values["contact_phone"] = Utils::formatPhone($values["contact_phone"]);

        if (Reservations::MODE === "lubo") {
            if (!Utils::allSet($_POST, [
                "address", "personal_number",
                "rep_date_of_birth", "contact_phone_2",
                "referrer", "reason",
            ])) {
                return false;
            }

            $values["address"]         = sanitize_text_field($_POST['address']);
            $values["personal_number"] = sanitize_text_field($_POST['personal_number']);

            $values["rep_date_of_birth"] = Carbon::parse(sanitize_text_field($_POST['rep_date_of_birth']), Utils::getTimezone());
            $values["contact_phone_2"]   = sanitize_text_field($_POST['contact_phone_2']);

            $values["referrer"]       = sanitize_text_field($_POST['referrer']);
            $values["referrer_other"] = "";
            $values["reason"]         = sanitize_text_field($_POST['reason']);
            $values["reason_other"]   = "";

            $values["contact_phone_2"] = Utils::formatPhone($values["contact_phone_2"]);

            if ($values["referrer"] === "other") {
                if (!isset($_POST['referrer_other'])) {
                    return false;
                }

                $values["referrer_other"] = sanitize_text_field($_POST['referrer_other']);
            }

            if ($values["reason"] === "other") {
                if (!isset($_POST['reason_other'])) {
                    return false;
                }

                $values["reason_other"] = sanitize_text_field($_POST['reason_other']);
            }

            if (!isset(TranslatableEnums::referrers()[$values["referrer"]])) {
                return false;
            }
        }

        if (Reservations::MODE === "lubo" && $this->isWorkshop()) {
            if (!Utils::allSet($_POST, [
                "carpool", "catering", "meal",
            ])) {
                return false;
            }

            $values["carpool"] = sanitize_text_field($_POST['carpool']);

            if ($values["carpool"] !== "none") {
                if (!Utils::allSet($_POST, [
                    "carpool_contact", "carpool_seats",
                ])) {
                    return false;
                }

                $values["carpool_seats"]   = (int) sanitize_text_field($_POST['carpool_seats']);
                $values["carpool_contact"] = sanitize_text_field($_POST['carpool_contact']);

                $values["carpool_contact"] = Utils::formatPhone($values["carpool_contact"]);
            }

            $values["catering"] = (bool) sanitize_text_field($_POST['catering']);

            if ($values["catering"]) {
                if (!Utils::allSet($_POST, [
                    "meal",
                ])) {
                    return false;
                }

                $values["meal"] = sanitize_text_field($_POST['meal']);

                if (!in_array($values["meal"], $this->event->mealOptions)) {
                    return false;
                }
            }

            if (!isset(TranslatableEnums::workshopReasons()[$values["reason"]])) {
                return false;
            }
        } else if (Reservations::MODE === "lubo" && $this->isCamp()) {
            if (!isset(TranslatableEnums::runReasons()[$values["reason"]])) {
                return false;
            }
        }

        if (Reservations::MODE === "lead" || $this->isCamp()) {
            if (!Utils::allSet($_POST, [
                "swimmer", "shirt_size",
            ])) {
                return false;
            }

            $values["shirt_size"] = sanitize_text_field($_POST['shirt_size']);
            $values["swimmer"]    = (bool) sanitize_text_field($_POST['swimmer']);
        }

        extract($values);

        $this->errors = [];

        if ($first_name === "") {
            $this->errors[] = sprintf(__('Please fill %s.', 'reservations'), "<strong>" . __('First Name', 'reservations') . "</strong>");
        }

        if ($last_name === "") {
            $this->errors[] = sprintf(__('Please fill %s.', 'reservations'), "<strong>" . __('Last Name', 'reservations') . "</strong>");
        }

        if (!$date_of_birth) {
            $this->errors[] = sprintf(__('Please fill %s in correct format.', 'reservations'), "<strong>" . __('Date of Birth', 'reservations') . "</strong>");
        }

        if ($health_insurance_code === "") {
            $this->errors[] = sprintf(__('Please fill %s.', 'reservations'), "<strong>" . __('Health Insurance Company Code', 'reservations') . "</strong>");
        }

        if (Reservations::MODE === "lubo" && $this->isWorkshop()) {
            if ($carpool !== "none" && $carpool_seats <= 0) {
                $this->errors[] = sprintf(__('Please fill %s.', 'reservations'), "<strong>" . __('Carsharing Number of Seats', 'reservations') . "</strong>");
            }
        } else if (Reservations::MODE === "lubo" && $this->isCamp()) {

        }

        if (Reservations::MODE === "lead" || $this->isCamp()) {
            if ($shirt_size === "") {
                $this->errors[] = sprintf(__('Please fill %s.', 'reservations'), "<strong>" . __('Shirt Size', 'reservations') . "</strong>");
            }
        }

        if (Reservations::MODE === "lubo") {
            if ($address === "") {
                $this->errors[] = sprintf(__('Please fill %s.', 'reservations'), "<strong>" . __('Address', 'reservations') . "</strong>");
            }

            if ($personal_number === "") {
                $this->errors[] = sprintf(__('Please fill %s.', 'reservations'), "<strong>" . __('Personal Number', 'reservations') . "</strong>");
            }

            if (!$rep_date_of_birth) {
                $this->errors[] = sprintf(__('Please fill representative %s in correct format.', 'reservations'), "<strong>" . __('Date of Birth', 'reservations') . "</strong>");
            }

            if ($contact_phone_2 === "") {
                $this->errors[] = sprintf(__('Please fill representative %s.', 'reservations'), "<strong>" . __('Phone (father)', 'reservations') . "</strong>");
            }
        }

        if ($rep_first_name === "") {
            $this->errors[] = sprintf(__('Please fill representative %s.', 'reservations'), "<strong>" . __('First Name', 'reservations') . "</strong>");
        }

        if ($rep_last_name === "") {
            $this->errors[] = sprintf(__('Please fill representative %s.', 'reservations'), "<strong>" . __('Last Name', 'reservations') . "</strong>");
        }

        if ($rep_address === "") {
            $this->errors[] = sprintf(__('Please fill representative %s.', 'reservations'), "<strong>" . __('Address', 'reservations') . "</strong>");
        }

        if (!$contact_email) {
            $this->errors[] = sprintf(__('Please fill representative %s in correct format.', 'reservations'), "<strong>" . __('Email', 'reservations') . "</strong>");
        }

        if ($contact_phone === "") {
            $this->errors[] = sprintf(__('Please fill representative %s.', 'reservations'), "<strong>" . __('Phone (mother)', 'reservations') . "</strong>");
        }

        if (!$agree_pp) {
            $this->errors[] = __('You have to agree with the Privacy Policy', 'reservations');
        }

        if (count($this->errors)) {
            return false;
        }

        $this->formData = $values;

        return true;
    }

    protected function createSubscriber()
    {
        extract($this->formData);

        $subscriberData = [
            "hash"                  => Utils::createHash(),

            "first_name"            => $first_name,
            "last_name"             => $last_name,
            "date_of_birth"         => $date_of_birth,
            "health_restrictions"   => $health_restrictions,
            "used_medicine"         => $used_medicine,
            "health_insurance_code" => $health_insurance_code,

            "rep_first_name"        => $rep_first_name,
            "rep_last_name"         => $rep_last_name,
            "rep_address"           => $rep_address,

            "contact_email"         => $contact_email,
            "contact_phone"         => $contact_phone,
        ];

        if (Reservations::MODE === "lubo") {
            $subscriberData["address"]           = $address;
            $subscriberData["personal_number"]   = $personal_number;
            $subscriberData["rep_date_of_birth"] = $rep_date_of_birth;
            $subscriberData["referrer"]          = $referrer;
            $subscriberData["referrer_other"]    = $referrer_other;
            $subscriberData["reason"]            = $reason;
            $subscriberData["reason_other"]      = $reason_other;
            $subscriberData["contact_phone_2"]   = $contact_phone_2;
        }

        if (Reservations::MODE === "lubo" && $this->isWorkshop()) {
            $subscriberData["carpool"]         = $carpool;
            $subscriberData["carpool_seats"]   = $carpool !== "none" ? $carpool_seats : null;
            $subscriberData["carpool_contact"] = $carpool !== "none" ? $carpool_contact : null;
            $subscriberData["catering"]        = $catering;
            $subscriberData["meal"]            = $catering ? $meal : null;
        } else if (Reservations::MODE === "lubo" && $this->isCamp()) {

        }

        if (Reservations::MODE === "lead" || $this->isCamp()) {
            $subscriberData["swimmer"]    = $swimmer;
            $subscriberData["shirt_size"] = $shirt_size;
        }

        $subscriber = new Models\Subscriber($subscriberData);

        $subscriber->save();

        $this->saveSubscriberToAccount($subscriber);

        return $subscriber;
    }

    public function submit()
    {
        if (!$this->validateForm()) {
            return;
        }

        extract($this->formData);

        $description = __('Subscription for', 'reservations') . " " . $this->event->title;
        $price       = (int) $this->event->getInitialPaymentAmount();

        if ($this->paidSubscriptions->count() >= $this->event->capacity) {
            $this->errors[] = __('This event is already full.', 'reservations');
            return;
        }

        if ($price === 0) {
            $this->errors[] = __('Invalid subscription price.', 'reservations');
            return;
        }

        $subscriber = $this->createSubscriber();

        $subscription = new Models\Subscription([
            "hash"                      => Utils::createHash(),

            "is_replacement"            => false,
            "application_form_received" => false,
            "paid"                      => false,
            "created_at"                => Utils::now(),
            "type"                      => "single",
        ]);

        $subscription->event_id      = $this->event->id;
        $subscription->subscriber_id = $subscriber->subscriber_id;

        $subscription->save();

        $payment = $subscription->getInitialPayment();

        if ($this->plugin->isFeatureEnabled("initial_payment_notification")) {
            $payment->sendNotificationEmail();
        }

        list($transaction, $response) = $payment->createTransaction([
            "order_description" => __('L.E.A.D. Parkour subscription', 'reservations'),
            "item_description"  => $description,
            "item_url"          => $this->permalink . "/",

            "return_url"        => $this->permalink . _x('thank-you', 'url slug', 'reservations') . "/",
            "notification_url"  => $this->permalink . _x('ajax', 'url slug', 'reservations') . "/?gopay",
        ]);

        if ($transaction) {
            $this->redirect($response->json['gw_url']);
        }

        $this->errors[] = __('An error occured during payment.', 'reservations');
    }

    public function submitReplacement()
    {
        if (!$this->validateForm()) {
            return;
        }

        extract($this->formData);

        $price = (int) $this->event->getInitialPaymentAmount();

        if ($this->paidSubscriptions->count() < $this->event->capacity) {
            $this->errors[] = __('This event is still available. Why don\'t you subscribe as regular participant?', 'reservations');
            return;
        }

        if ($price === 0) {
            $this->errors[] = __('Invalid subscription price.', 'reservations');
            return;
        }

        $subscriber = $this->createSubscriber();

        $subscription = new Models\Subscription([
            "hash"                      => Utils::createHash(),

            "is_replacement"            => true,
            "application_form_received" => false,
            "paid"                      => false,
            "created_at"                => Utils::now(),
            "type"                      => "single",
        ]);

        $subscription->event_id      = $this->event->id;
        $subscription->subscriber_id = $subscriber->subscriber_id;

        $subscription->save();

        $this->redirect($this->permalink . _x('thank-you', 'url slug', 'reservations') . "/?replacement");
    }

    public function render()
    {
        if ($this->renderUnlockForm()) {
            return;
        }

        $event = $this->event;

        $initialAmount = $event->getInitialPaymentAmount();
        $totalAmount   = $event->getPaymentAmount();

        if ($totalAmount > $initialAmount) {
            $buyText = sprintf(__('Pay deposit %s US$ (total price: %s US$)', 'reservations'), "<span>" . Utils::formatNumber($initialAmount) . "</span>", "<span>" . Utils::formatNumber($totalAmount) . "</span>");
        } else {
            $buyText = sprintf(__('Pay %s US$', 'reservations'), "<span>" . Utils::formatNumber($totalAmount) . "</span>");
        }

        $eventType  = $event->eventType["id"];
        $isWorkshop = $this->isWorkshop();
        $isCamp     = $this->isCamp();

        $backLink  = $this->permalink;
        $oldValues = $this->oldValues;

        $val = function ($key) use ($oldValues) {
            return $oldValues[$key] ?? "";
        };

        $carpoolDescription = Utils::texturize(__('>> [CARSHARING DESCRIPTION] <<', 'reservations'));

        $privacyPolicyLink = sprintf('<a href="%s">%s</a>', $this->plugin->getOption("privacy_policy_url", "#"), _x('Privacy Policy', 'link', 'reservations'));

        $gdprContent  = str_replace("%s", $privacyPolicyLink, Utils::texturize(__('>> [GDPR CONTENT] <<', 'reservations')));
        $gdprContent2 = str_replace("%s", $privacyPolicyLink, Utils::texturize(__('>> [GDPR CONTENT 2] <<', 'reservations')));

        $shirtSizePairs = TranslatableEnums::shirtSizes();

        if (Reservations::MODE === "lead") {
            $shirtSizePairs = TranslatableEnums::shirtSizesLead();
        }

        $swimmerSelect   = Utils\Html::getSelect(TranslatableEnums::yesNoUcFirst(), $val("swimmer"));
        $shirtSizeSelect = Utils\Html::getSelect($shirtSizePairs, $val("shirt_size"));
        $referrerSelect  = Utils\Html::getSelect(TranslatableEnums::referrersUcFirst(), $val("referrer"));

        if ($event->eventType["id"] === "camp") {
            $reasonSelect = Utils\Html::getSelect(TranslatableEnums::runReasonsUcFirst(), $val("reason"));
        } else {
            $reasonSelect = Utils\Html::getSelect(TranslatableEnums::workshopReasonsUcFirst(), $val("reason"));
        }

        $cateringSelect = Utils\Html::getSelect(TranslatableEnums::cateringUcFirst(), $val("catering"));
        $carpoolSelect  = Utils\Html::getSelect(TranslatableEnums::carpoolUcFirst(), $val("carpool"));

        $mealOptions = Utils\Arrays::mirror($event->mealOptions);
        $mealSelect  = Utils\Html::getSelect($mealOptions, $val("meal"));

        $errors = $this->submitted ? $this->errors : [];

        $nonceField = wp_nonce_field($this->plugin->prefix("subscribe"), "_wpnonce", true, false);
        $token      = $_SESSION['onetime']      = md5(rand());
        $nonceField .= '<input type="hidden" name="_onetime" value="' . $token . '">';

        $account = $this->getAccountVariables();

        $logos = [];
        for ($i = 1; $i <= 7; $i++) {
            $logos[] = $this->plugin->url("public/img/logo" . $i . ".png");
        }

        if (Reservations::MODE === "lead") {
            include Reservations::ABSPATH . "/public/events/subscribe-lead.php";
        } else {
            include Reservations::ABSPATH . "/public/events/subscribe.php";
        }
    }
}
