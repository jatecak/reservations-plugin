<?php

namespace Reservations\Pages;

use Carbon\Carbon;
use DateTime;
use Nette\Utils\Strings;
use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\Models\Local;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Local\SubscriptionType;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\Pages\Utils as PagesUtils;
use Reservations\Utils;

class Subscribe extends Base\Page
{
    use PagesUtils\PasswordProtection, PagesUtils\AdminBar, PagesUtils\SubscriberAccount;

    private $gym;
    private $ref;
    private $tgroup;
    private $submitted;
    private $oldValues = [];
    private $errors    = [];
    private $paidSubscriptions;
    private $formData;
    private $defaultAgeGroup;

    public function getObjectType()
    {
        return ObjectType::TRAININGS;
    }

    public function setTrainingGroup($tgroup)
    {
        $this->tgroup = $tgroup;
    }

    public function assets()
    {
        wp_enqueue_script("res-moment", $this->plugin->url("public/js/moment.min.js"));
        wp_enqueue_script("res-datepicker", $this->plugin->url("public/js/bootstrap-datepicker.min.js"), ["jquery"]);

        if ($this->isUnlocked()) {
            $tgroup         = $this->tgroup;
            $maxMonthlyDate = Utils::getMaxMonthlySubscriptionEndDate($tgroup);

            wp_enqueue_script("res-subscribe", $this->plugin->url("public/subscribe.js"), ["jquery", "res-moment", "res-datepicker"], Utils::getFileVersion($this->plugin->path("public/subscribe.js")));
            wp_localize_script("res-subscribe", "subscribe_data", [
                "data" => [
                    "paidSubscriptions"        => $this->paidSubscriptions->map(function ($sub) {
                        return [
                            "date_from" => $sub->date_from->toIso8601String(),
                            "date_to"   => $sub->date_to->toIso8601String(),
                        ];
                    }),
                    "capacity"                 => $tgroup->capacity,
                    "enabledSubscriptionTypes" => $tgroup->enabledSubscriptionTypes,
                    "initialAmount"            => collect(Local\SubscriptionType::forObjectType(Local\ObjectType::TRAININGS))->flip()->map(function ($v, $type) use ($tgroup) {
                        return $tgroup->getInitialPaymentAmount(null, $type, 1);
                    }),
                    "totalAmount"              => collect(Local\SubscriptionType::forObjectType(Local\ObjectType::TRAININGS))->flip()->map(function ($v, $type) use ($tgroup) {
                        return $tgroup->getPaymentAmount(null, $type, 1);
                    }),
                    "activeTerm"               => $tgroup->activeTerm ? [
                        $tgroup->activeTerm[0]->toIso8601String(),
                        $tgroup->activeTerm[1]->toIso8601String(),
                    ] : null,
                    "maxMonthlyDate"           => $maxMonthlyDate ? $maxMonthlyDate->toIso8601String() : null,
                    "activeYear"               => $tgroup->activeYear ? [
                        $tgroup->activeYear[0]->toIso8601String(),
                        $tgroup->activeYear[1]->toIso8601String(),
                    ] : null,
                    "replacementsEnabled"      => $this->plugin->isFeatureEnabled("replacements"),
                    "defaultDuration"          => [
                        "term" => $this->plugin->getOption("term_duration"),
                        "year" => $this->plugin->getOption("year_duration"),
                    ],
                ],
            ]);
        }

        wp_enqueue_style("res-datepicker", $this->plugin->url("public/css/bootstrap-datepicker3.standalone.min.css"));

        $this->enqueueGlobalStyle(["res-datepicker"]);
    }

    public function prepare()
    {
        if (!session_id()) {
            session_start();
        }

        if (!$this->tgroup->subscriptionEnabled) {
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
        $this->paidSubscriptions = $this->tgroup->subscriptions()->where("paid", true)->get();

        if (isset($_GET['ref'])) {
            $ref = Models\Training::find((int) $_GET['ref']);

            if ($ref && $ref->trainingGroup()->id === $this->tgroup->id) {
                $this->ref = $ref;
            }
        }

        if (isset($_POST['do']) && $_POST['do'] === "submit") {
            $this->submitted = true;
            $this->submit();
        } else if (isset($_POST['do']) && $_POST['do'] === "submit-replacement" && $this->plugin->isFeatureEnabled("replacements")) {
            $this->submitted = true;
            $this->submitReplacement();
        }
    }

    public function validateForm()
    {
        $this->oldValues = $_POST;

        if (!Utils::allSet($_POST, [
            "first_name", "last_name", "date_of_birth", "address", "health_restrictions", "age_group",
            "rep_first_name", "rep_last_name", "rep_address", "contact_email", "contact_phone",
            "subscription_type", "_onetime", "_wpnonce",
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
            "first_name"          => sanitize_text_field($_POST['first_name']),
            "last_name"           => sanitize_text_field($_POST['last_name']),
            "date_of_birth"       => Carbon::parse(sanitize_text_field($_POST['date_of_birth']), Utils::getTimezone()),
            "address"             => sanitize_text_field($_POST['address']),
            "health_restrictions" => sanitize_textarea_field($_POST['health_restrictions']),
            "age_group"           => (int) sanitize_text_field($_POST['age_group']),

            "rep_first_name"      => sanitize_text_field($_POST['rep_first_name']),
            "rep_last_name"       => sanitize_text_field($_POST['rep_last_name']),
            "rep_address"         => sanitize_text_field($_POST['rep_address']),

            "contact_email"       => filter_var(sanitize_text_field($_POST['contact_email']), FILTER_VALIDATE_EMAIL),
            "contact_phone"       => sanitize_text_field($_POST['contact_phone']),

            "subscription_type"   => sanitize_text_field($_POST['subscription_type']),

            "agree_pp"            => isset($_POST['agree_pp']) && $_POST['agree_pp'] === "1",
        ];

        $values["contact_email"] = Strings::lower($values["contact_email"]);
        $values["contact_phone"] = Utils::formatPhone($values["contact_phone"]);

        if (Reservations::MODE === "lubo") {
            if (!Utils::allSet($_POST, [
                "personal_number", "facebook", "rep_date_of_birth", "rep_personal_number", "preferred_level",
            ])) {
                return false;
            }

            $values["personal_number"]     = sanitize_text_field($_POST['personal_number']);
            $values["facebook"]            = sanitize_text_field($_POST['facebook']);
            $values["rep_date_of_birth"]   = Carbon::parse(sanitize_text_field($_POST['rep_date_of_birth']), Utils::getTimezone());
            $values["rep_personal_number"] = sanitize_text_field($_POST['rep_personal_number']);
            $values["preferred_level"]     = sanitize_text_field($_POST['preferred_level']);

            if (!isset(TranslatableEnums::levels()[$values["preferred_level"]])) {
                return false;
            }
        } else if (Reservations::MODE === "lead") {
            if (!Utils::allSet($_POST, [
                "independent_leave",
            ])) {
                return false;
            }

            $values["independent_leave"] = (bool) sanitize_text_field($_POST['independent_leave']);
        }

        if (!Local\AgeGroup::find($values["age_group"])) {
            return false;
        }

        if (!in_array($values["subscription_type"], $this->tgroup->enabledSubscriptionTypes)) {
            return false;
        }

        if ($values["subscription_type"] === "monthly") {
            if (!isset($_POST['num_months'])) {
                return false;
            }

            $values["num_months"] = (int) sanitize_text_field($_POST['num_months']);
        }

        $startDateDisabled = false;

        if ($values["subscription_type"] === SubscriptionType::BIANNUAL && $this->tgroup->activeTerm) {
            $startDateDisabled = true;
        }

        if ($values["subscription_type"] === SubscriptionType::ANNUAL && $this->tgroup->activeYear) {
            $startDateDisabled = true;
        }

        if (!$startDateDisabled) {
            if (!isset($_POST['start_date'])) {
                return false;
            }

            $values["date_start"] = Carbon::parse(sanitize_text_field($_POST['start_date']), Utils::getTimezone());
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

        if ($address === "") {
            $this->errors[] = sprintf(__('Please fill %s.', 'reservations'), "<strong>" . __('Address', 'reservations') . "</strong>");
        }

        if (Reservations::MODE === "lubo") {
            if ($personal_number === "") {
                $this->errors[] = sprintf(__('Please fill %s.', 'reservations'), "<strong>" . __('Personal Number', 'reservations') . "</strong>");
            }

            if (!$rep_date_of_birth) {
                $this->errors[] = sprintf(__('Please fill representative %s in correct format.', 'reservations'), "<strong>" . __('Date of Birth', 'reservations') . "</strong>");
            }

            if ($rep_personal_number === "") {
                $this->errors[] = sprintf(__('Please fill representative %s.', 'reservations'), "<strong>" . __('Personal Number', 'reservations') . "</strong>");
            }
        } else if (Reservations::MODE === "lead") {

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
            $this->errors[] = sprintf(__('Please fill representative %s.', 'reservations'), "<strong>" . __('Phone', 'reservations') . "</strong>");
        }

        if ($subscription_type === "monthly" && (is_nan($num_months) || $num_months < 1)) {
            $this->errors[] = sprintf(__('Please fill %s in correct format.', 'reservations'), "<strong>" . __('Number of Months', 'reservations') . "</strong>");
        }

        if (!$startDateDisabled) {
            if (!$date_start) {
                $this->errors[] = sprintf(__('Please fill %s in correct format.', 'reservations'), "<strong>" . __('Subscription Start', 'reservations') . "</strong>");
            }

            if ($date_start->lt(Utils::today())) {
                $this->errors[] = sprintf(__('%s must be in the future.', 'reservations'), "<strong>" . __('Subscription Start', 'reservations') . "</strong>");
            }
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
            "hash"                => Utils::createHash(),

            "first_name"          => $first_name,
            "last_name"           => $last_name,
            "date_of_birth"       => $date_of_birth,
            "address"             => $address,
            "health_restrictions" => $health_restrictions,

            "rep_first_name"      => $rep_first_name,
            "rep_last_name"       => $rep_last_name,
            "rep_address"         => $rep_address,

            "contact_email"       => $contact_email,
            "contact_phone"       => $contact_phone,
        ];

        if (Reservations::MODE === "lubo") {
            $subscriberData["personal_number"]   = $personal_number;
            $subscriberData["facebook"]          = $facebook;
            $subscriberData["rep_date_of_birth"] = $rep_date_of_birth;
            $subscriberData["rep_personal_number"] = $rep_personal_number;
            $subscriberData["preferred_level"]   = $preferred_level;
        } else if (Reservations::MODE === "lead") {
            $subscriberData["independent_leave"] = $independent_leave;
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

        if ($subscription_type === SubscriptionType::MONTHLY) {
            $price       = $this->tgroup->getInitialPaymentAmount(null, $subscription_type, $num_months);
            $description = sprintf(_n('L.E.A.D. Parkour subscription for %d month', 'L.E.A.D. Parkour subscription for %d months', $num_months, 'reservations'), $num_months);
            $date_end    = (clone $date_start)->addMonths($num_months);
        } else if ($subscription_type === SubscriptionType::BIANNUAL) {
            $price       = $this->tgroup->getInitialPaymentAmount(null, $subscription_type, 1);
            $description = __('L.E.A.D. Parkour biannual subscription', 'reservations');

            if ($this->tgroup->activeTerm) {
                $date_start = $this->tgroup->activeTerm[0];
                $date_end   = $this->tgroup->activeTerm[1];
            } else {
                $duration = $this->plugin->getOption("term_duration");
                $date_end = (clone $date_start)->addMonths($duration["months"])->addDays($duration["days"]);
            }
        } else if ($subscription_type === SubscriptionType::ANNUAL) {
            $price       = $this->tgroup->getInitialPaymentAmount(null, $subscription_type, 1);
            $description = __('L.E.A.D. Parkour annual subscription', 'reservations');

            if ($this->tgroup->activeYear) {
                $date_start = $this->tgroup->activeYear[0];
                $date_end   = $this->tgroup->activeYear[1];
            } else {
                $duration = $this->plugin->getOption("year_duration");
                $date_end = (clone $date_start)->addMonths($duration["months"])->addDays($duration["days"]);
            }
        }

        $maxMonthlyDate = Utils::getMaxMonthlySubscriptionEndDate($this->tgroup);

        if ($subscription_type === SubscriptionType::MONTHLY && $maxMonthlyDate !== null && $date_end->gt($maxMonthlyDate)) {
            $this->errors[] = __('Subscription end date must be before the end of the current term.', 'reservations');
            return;
        }

        if ($this->tgroup->getFreeCapacity($date_start->max(Utils::today()), $date_end) <= 0) {
            $this->errors[] = __('These trainings are full on the selected date. Please try a different one.', 'reservations');
            return;
        }

        if ($price === 0) {
            $this->errors[] = __('Invalid subscription price.', 'reservations');
            return;
        }

        $subscriber = $this->createSubscriber();

        $subscription = new Models\Subscription([
            "hash"                      => Utils::createHash(),

            "date_from"                 => $date_start,
            "date_to"                   => $date_end,
            "age_group"                 => $age_group,
            "paid"                      => false,
            "application_form_received" => false,
            "created_at"                => Utils::now(),
            "type"                      => $subscription_type,
            "num_months"                => $subscription_type === "monthly" ? $num_months : null,
        ]);

        $subscription->tgroup_id     = $this->tgroup->id;
        $subscription->subscriber_id = $subscriber->subscriber_id;

        $subscription->save();

        $payment = $subscription->getInitialPayment();

        if ($this->plugin->isFeatureEnabled("initial_payment_notification")) {
            $payment->sendNotificationEmail();
        }

        list($transaction, $response) = $payment->createTransaction([
            "order_description" => __('L.E.A.D. Parkour subscription', 'reservations'),
            "item_description"  => $description,
            "item_url"          => $this->tgroup->subscribeLink,

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

        if ($subscription_type === SubscriptionType::MONTHLY) {
            $price       = $this->tgroup->getInitialPaymentAmount(null, $subscription_type, $num_months);
            $description = sprintf(_n('L.E.A.D. Parkour subscription for %d month', 'L.E.A.D. Parkour subscription for %d months', $num_months, 'reservations'), $num_months);
            $date_end    = (clone $date_start)->addMonths($num_months);
        } else if ($subscription_type === SubscriptionType::BIANNUAL) {
            $price       = $this->tgroup->getInitialPaymentAmount(null, $subscription_type, 1);
            $description = __('L.E.A.D. Parkour biannual subscription', 'reservations');

            if ($this->tgroup->activeTerm) {
                $date_start = $this->tgroup->activeTerm[0];
                $date_end   = $this->tgroup->activeTerm[1];
            } else {
                $duration = $this->plugin->getOption("term_duration");
                $date_end = (clone $date_start)->addMonths($duration["months"])->addDays($duration["days"]);
            }
        } else if ($subscription_type === SubscriptionType::ANNUAL) {
            $price       = $this->tgroup->getInitialPaymentAmount(null, $subscription_type, 1);
            $description = __('L.E.A.D. Parkour annual subscription', 'reservations');

            if ($this->tgroup->activeYear) {
                $date_start = $this->tgroup->activeYear[0];
                $date_end   = $this->tgroup->activeYear[1];
            } else {
                $duration = $this->plugin->getOption("year_duration");
                $date_end = (clone $date_start)->addMonths($duration["months"])->addDays($duration["days"]);
            }
        }

        if ($this->plugin->isFeatureEnabled("limit_monthly_to_term") && $subscription_type === SubscriptionType::MONTHLY && $this->tgroup->activeTerm && $date_end->gt($this->tgroup->activeTerm[1])) {
            $this->errors[] = __('Subscription end date must be before the end of the current term.', 'reservations');
            return;
        }

        if ($this->tgroup->getFreeCapacity($date_start->max(Utils::today()), $date_end) > 0) {
            $this->errors[] = __('These trainings are available on the selected date. Why don\'t you subscribe as regular participant?', 'reservations');
            return;
        }

        if ($price === 0) {
            $this->errors[] = __('Invalid subscription price.', 'reservations');
            return;
        }

        $subscriber = $this->createSubscriber();

        $subscription = new Models\Subscription([
            "hash"                      => Utils::createHash(),

            "date_from"                 => $date_start,
            "date_to"                   => $date_end,
            "age_group"                 => $age_group,
            "is_replacement"            => true,
            "paid"                      => false,
            "application_form_received" => false,
            "created_at"                => Utils::now(),
            "type"                      => $subscription_type,
            "num_months"                => $subscription_type === "monthly" ? $num_months : null,
        ]);

        $subscription->tgroup_id     = $this->tgroup->id;
        $subscription->subscriber_id = $subscriber->subscriber_id;

        $subscription->save();

        $this->redirect($this->permalink . _x('thank-you', 'url slug', 'reservations') . "/?replacement");
    }

    public function render()
    {
        if ($this->renderUnlockForm()) {
            return;
        }

        $tgroup = $this->tgroup;

        $enabledSubscriptionTypes = $tgroup->enabledSubscriptionTypes;

        $annualEnabled   = in_array(SubscriptionType::ANNUAL, $enabledSubscriptionTypes);
        $biannualEnabled = in_array(SubscriptionType::BIANNUAL, $enabledSubscriptionTypes);
        $monthlyEnabled  = in_array(SubscriptionType::MONTHLY, $enabledSubscriptionTypes);

        if ($annualEnabled) {
            $activeType = SubscriptionType::ANNUAL;
        } else if ($biannualEnabled) {
            $activeType = SubscriptionType::BIANNUAL;
        } else if ($monthlyEnabled) {
            $activeType = SubscriptionType::MONTHLY;
        }

        $priceFormatted = [];

        foreach ($enabledSubscriptionTypes as $type) {
            $totalAmount   = $tgroup->getPaymentAmount(null, $type, 1);
            $initialAmount = $tgroup->getInitialPaymentAmount(null, $type, 1);

            if ($type === SubscriptionType::MONTHLY) {
                $priceFormatted[$type] = Utils::formatNumber($totalAmount) . " " . __('US$/month', 'reservations');
            } else if ($totalAmount !== $initialAmount) {
                $priceFormatted[$type] = sprintf('%s %s (%s %s %s)',
                    Utils::formatNumber($totalAmount),
                    __('US$', 'reservations'),
                    __('deposit', 'reservations'),
                    Utils::formatNumber($initialAmount),
                    __('US$', 'reservations')
                );
            } else {
                $priceFormatted[$type] = Utils::formatNumber($totalAmount) . " " . __('US$', 'reservations');
            }
        }

        $trainings      = $tgroup->trainings();
        $subscribedGyms = Utils::formatSubscribedTrainings($trainings);

        $gyms = collect($trainings)->pluck("_gym")->uniqueStrict(function ($gym) {
            return $gym->id;
        })->values()->all();

        $ageGroupIds = collect($trainings)->pluck("ageGroup")->uniqueStrict()->all();

        if ($this->ref) {
            $backLink = $this->parentLink($this->ref->gym()->slug, true);
        } else if (count($gyms) === 1) {
            $backLink = $this->parentLink($gyms[0]->slug, true);
        } else {
            $backLink = null;
        }

        $oldValues = $this->oldValues;

        $val = function ($key) use ($oldValues) {
            return isset($oldValues[$key]) ? $oldValues[$key] : "";
        };

        if ($val("start_date")) {
            $startDate = $val("start_date");
        }

        if ($val("end_date")) {
            $endDate = $val("end_date");
        }

        if (in_array($val("subscription_type"), $enabledSubscriptionTypes)) {
            $activeType = $val("subscription_type");
        }

        if ($activeType === SubscriptionType::BIANNUAL && $tgroup->activeTerm) {
            $startDate         = $tgroup->activeTerm[0]->format("Y-m-d");
            $endDate           = $tgroup->activeTerm[1]->format("Y-m-d");
            $startDateDisabled = true;
        } else if ($activeType === SubscriptionType::ANNUAL && $tgroup->activeYear) {
            $startDate         = $tgroup->activeYear[0]->format("Y-m-d");
            $endDate           = $tgroup->activeYear[1]->format("Y-m-d");
            $startDateDisabled = true;
        } else {
            $startDate         = Utils::today()->format("Y-m-d");
            $endDate           = Utils::today()->addMonths($activeType === "biannual" ? 6 : 1)->format("Y-m-d");
            $startDateDisabled = false;
        }

        $totalAmount   = Utils::formatNumber($tgroup->getPaymentAmount(null, $activeType, 1));
        $initialAmount = Utils::formatNumber($tgroup->getInitialPaymentAmount(null, $activeType, 1));

        if (isset($oldValues["age_group"]) && in_array((int) $oldValues["age_group"], $ageGroupIds)) {
            $selectedAgeGroup = (int) $oldValues["age_group"];
        } else if ($this->ref) {
            $selectedAgeGroup = $this->ref->ageGroup;
        } else {
            $selectedAgeGroup = Utils\Arrays::getFirstElement($ageGroupIds);
        }

        $ageGroupSelect = Utils::getAgeGroupSelect($ageGroupIds, $selectedAgeGroup);

        $independentLeaveSelect = Utils\Html::getSelect(TranslatableEnums::yesNoUcFirst(), $oldValues["independent_leave"] ?? "0");

        $preferredLevelSelect = [];
        foreach ($ageGroupIds as $group) {
            $levels = TranslatableEnums::levelsUcFirst();

            if ($group === 0) {
                unset($levels["advanced"]);
            }

            $preferredLevelSelect[$group] = Utils\Html::getSelect($levels, $val("preferred_level"));
        }

        $privacyPolicyLink = sprintf('<a href="%s">%s</a>', $this->plugin->getOption("privacy_policy_url", "#"), _x('Privacy Policy', 'link', 'reservations'));

        $gdprContent  = str_replace("%s", $privacyPolicyLink, Utils::texturize(__('>> [GDPR CONTENT] <<', 'reservations')));
        $gdprContent2 = str_replace("%s", $privacyPolicyLink, Utils::texturize(__('>> [GDPR CONTENT 2] <<', 'reservations')));

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
            include Reservations::ABSPATH . "/public/subscribe-lead.php";
        } else {
            include Reservations::ABSPATH . "/public/subscribe.php";
        }
    }
}
