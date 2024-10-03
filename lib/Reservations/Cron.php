<?php

namespace Reservations;

use Reservations;
use Reservations\Base\Service;
use Reservations\Models;
use Reservations\Models\Local;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Local\SubscriptionType;
use Reservations\Taxonomies;
use Reservations\Utils;
use Reservations\Utils\EventTypes;
use Reservations\Utils\PluginAccess;

class Cron extends Service
{
    /** @action(init) */
    public function scheduleEvents()
    {
        if (!wp_next_scheduled("res_cron_hourly")) {
            wp_schedule_event(time(), "hourly", "res_cron_hourly");
        }

        if (!wp_next_scheduled("res_cron_daily")) {
            wp_schedule_event(time(), "daily", "res_cron_daily");
        }
    }

    /** @action(res_cron_hourly) */
    public function runHourly()
    {
    }

    /** @action(res_cron_daily) */
    public function runDaily()
    {
        $this->sendPaymentNotificationEmails();
        $this->sendSubscriptionNotificationEmails();
    }

    /** @action(admin_init) */
    public function runDebug()
    {
        if (!Reservations::DEBUG) {
            return;
        }

        if (isset($_GET['debugcron'])) {
            $this->sendPaymentNotificationEmails();
            $this->sendSubscriptionNotificationEmails();

            $this->log("Done.");
            exit;
        }

        // if (isset($_GET['migrate'])) {
        //     $this->migrateModels();
        //     exit;
        // }

        // if (isset($_GET['convert'])) {
        //     $this->convertGyms();
        //     exit;
        // }
    }

    protected function log($message)
    {
        if (wp_doing_cron())
            return;

        echo '<pre style="margin:0;padding:0">' . esc_html($message) . "</pre>";
    }

    public function sendPaymentNotificationEmails()
    {
        $subscriptions = Models\Subscription::paidPartially()->where("paid", false)->get();

        foreach ($subscriptions as $subscription) {
            if (!$subscription->object)
                continue;

            $templates = $subscription->paymentTemplates;

            if (!$templates) {
                continue;
            }

            foreach ($templates as $template) {
                if ($template["initial"]) {
                    continue;
                }

                if ($subscription->objectType === ObjectType::TRAININGS) {
                    // for trainings, send the email X days after subscription starts (if it is still active)
                    if ($template["advance"] > $subscription->createdAt->diffInDays(Utils::today(), false) || !$subscription->isActive(true)) {
                        continue;
                    }
                }

                if ($subscription->objectType === ObjectType::EVENT) {
                    // for events, send the email X days before the event starts, try to send it only until the end of the event
                    if ($template["advance"] < Utils::today()->diffInDays($subscription->event->dateFrom, false) || Utils::today()->gt($subscription->event->dateTo)) {
                        continue;
                    }
                }

                $this->log("About to send payment notification for subscription #" . $subscription->id);

                $payment = $subscription->getOrCreatePayment($template);

                if (!$payment->notificationEmailSent) {
                    $payment->sendNotificationEmail();
                }
            }
        }
    }

    public function sendSubscriptionNotificationEmails()
    {
        if (!$this->plugin->isFeatureEnabled("subscription_notification") || !$this->plugin->getOption("subscription_notification_enable", false)) {
            return;
        }

        if (!$this->plugin->getOption("subscription_notification_template", null)) {
            return;
        }

        $advance = max(0, (int) $this->plugin->getOption("subscription_notification_advance", 0));

        $minDateTo = Utils::today()->subDays(7);
        $maxDateTo = Utils::today()->addDays($advance);

        $subscriptions = Models\Subscription::paid()->forTrainings()->where("notification_email_sent", false)
            ->where("date_to", ">=", $minDateTo)
            ->where("date_to", "<=", $maxDateTo)->get();

        foreach ($subscriptions as $subscription) {
            if (!$subscription->object)
                continue;

            $this->log("About to subscription notification for subscription #" . $subscription->id);

            $subscription->sendNotificationEmail();
        }
    }

    public function migrateModels()
    {
        $subscribers = Models\Subscriber::where("hash", null)->get();

        foreach ($subscribers as $subscriber) {
            $subscriber->hash = Utils::createHash();
            $subscriber->save();
        }

        $subscriptions = Models\Subscription::where("hash", null)->get();

        foreach ($subscriptions as $subscription) {
            $subscription->hash = Utils::createHash();
            $subscription->save();
        }
    }

    public function convertPayments()
    {
        $subscriptions = Models\Subscription::forTrainings()->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->transaction_id === null) {
                continue;
            }

            $initialPayment = $subscription->getInitialPayment();

            if (!$initialPayment) {
                $this->log("No initial payment for subscription #" . $subscription->subscription_id);
                continue;
            }

            if ($subscription->paid && $subscription->payment_amount > 0) {
                $initialPayment->amount = $subscription->payment_amount;
                $initialPayment->save();
            }

            if ($subscription->paid) {
                $initialPayment->paid                    = true;
                $initialPayment->paid_amount             = $initialPayment->amount;
                $initialPayment->confirmation_email_sent = $subscription->email_sent;
                $initialPayment->save();

                $subscription->paid_amount = $initialPayment->amount;
                $subscription->save();
            }

            $initialPayment->transactions()->create([
                "gopay_transaction_id" => $subscription->transaction_id,
                "amount"               => $initialPayment->amount,
                "paid"                 => $subscription->paid,
                "created_at"           => $subscription->created_at,
                "paid_at"              => $subscription->paid_at,
            ]);
        }
    }

    public function convertGyms()
    {
        $gyms = Models\Gym::all();

        foreach ($gyms as $gym) {
            $this->log("Processing gym " . $gym->name);

            $trainings               = $gym->trainings();
            $ageGroupToTrainingGroup = [];

            $this->log("> Loaded " . $trainings->count() . " trainings.");

            foreach ($trainings as $training) {
                $ageGroupId = $training->ageGroup;
                $tgroup     = $training->trainingGroup();

                if ($tgroup) {
                    if (isset($ageGroupToTrainingGroup[$ageGroupId]) && $ageGroupToTrainingGroup[$ageGroupId]->id !== $tgroup->id) {
                        $this->log("  > Multiple training groups found matching single age group, using only the first one.");
                        continue;
                    }

                    $ageGroupToTrainingGroup[$ageGroupId] = $tgroup;
                }
            }

            $enabledAgeGroups = [];
            foreach (Local\AgeGroup::all() as $ageGroup) {
                if ($gym->getPaymentAmount($ageGroup, "biannual", 1) <= 0 && $gym->getPaymentAmount($ageGroup, "monthly", 1) <= 0) {
                    continue;
                }

                $enabledAgeGroups[$ageGroup["id"]] = $ageGroup;
            }

            $subscriptions = $gym->subscriptions()->withTrashed()->get();

            foreach ($subscriptions as $subscription) {
                if ($subscription->trashed()) {
                    continue;
                }

                $ageGroup = $subscription->ageGroup;

                $enabledAgeGroups[$ageGroup["id"]] = $ageGroup;
            }

            foreach ($trainings as $training) {
                $ageGroupId = $training->ageGroup;

                $enabledAgeGroups[$ageGroupId] = Local\AgeGroup::find($ageGroupId);
            }

            foreach ($enabledAgeGroups as $ageGroup) {
                $this->log("> Enabled age group: " . Utils::getAgeGroupPath($ageGroup));

                $created = false;

                if (isset($ageGroupToTrainingGroup[$ageGroup["id"]])) {
                    $tgroup = $ageGroupToTrainingGroup[$ageGroup["id"]];
                    $this->log("  > Found training group for this age group: " . $tgroup->name);
                } else {
                    $tgroupName = $gym->name . " (" . Utils::getAgeGroupPath($ageGroup) . ")";

                    $this->log("  > Training group not found, creating " . $tgroupName);

                    $tgroup = Models\TrainingGroup::where("name", $tgroupName)->first();

                    if ($tgroup) {
                        $this->log("    > Found preexisting training group with the same name, using it instead.");
                    } else {
                        $res = wp_insert_term($tgroupName, Taxonomies\TrainingGroup::NAME);
                        if (!is_array($res)) {
                            wp_die($res);
                            exit;
                        }

                        $tgroup  = Models\TrainingGroup::find($res['term_id']);
                        $created = true;
                    }

                    $ageGroupToTrainingGroup[$ageGroup["id"]] = $tgroup;
                }

                if (isset($_GET['force']) || $created) {
                    $this->log("  > Copying over payment templates...");

                    $paymentTemplates = collect($gym->getPaymentTemplatesFiltered($ageGroup))->map(function ($template) {
                        unset($template["age_group"]);
                        return $template;
                    })->all();

                    $tgroup->setPrefixedMeta("payment_templates", $paymentTemplates);

                    $this->log("  > Copying over other data...");

                    $enabledSubscriptionTypes = [];
                    if ($gym->biannualEnabled) {
                        $enabledSubscriptionTypes[] = SubscriptionType::BIANNUAL;
                    }

                    if ($gym->monthlyEnabled) {
                        $enabledSubscriptionTypes[] = SubscriptionType::MONTHLY;
                    }

                    if (isset($gym->priceSingle[$ageGroup["id"]])) {
                        $priceSingle = $gym->priceSingle[$ageGroup["id"]];
                    } else {
                        $priceSingle = 0;
                    }

                    $tgroup->setPrefixedMetaBulk([
                        "price_single"               => $priceSingle,
                        "password"                   => $gym->password,
                        "enabled_subscription_types" => $enabledSubscriptionTypes,
                        "capacity"                   => $gym->capacity,
                        "term_periods"               => $gym->termPeriods,
                        "attachment_sets"            => $gym->attachmentSets,
                    ]);
                }
            }

            $this->log("> Updating training associations...");

            foreach ($trainings as $training) {
                $ageGroupId = $training->ageGroup;
                $tgroup     = $training->trainingGroup();

                $correctTgroup = $ageGroupToTrainingGroup[$ageGroupId];

                if ($tgroup && $correctTgroup->id !== $tgroup->id) {
                    $this->log("  > Skipping " . $training->title . " because it is associated to different training group (current: " . $tgroup->name . ", correct: " . $correctTgroup->name . ")");
                    continue;
                }

                wp_set_object_terms($training->id, $correctTgroup->id, Taxonomies\TrainingGroup::NAME);
                $this->log("  > Associated " . $training->title . " (age group: " . Utils::getAgeGroupPath($ageGroupId) . ") with " . $correctTgroup->name);
            }

            $this->log("> Updating subscriptions...");

            foreach ($subscriptions as $subscription) {
                $tgroup = $subscription->trainingGroup;

                if (!$subscription->ageGroup) {
                    $this->log("  > Invalid age group: #" . $ageGroupId . ", skipping subscription #" . $subscription->id);
                    continue;
                }

                $ageGroupId = $subscription->ageGroup["id"];

                if (!isset($ageGroupToTrainingGroup[$ageGroupId])) {
                    $this->log("  > Age group not enabled: " . Utils::getAgeGroupPath($subscription->ageGroup) . ", skipping subscription #" . $subscription->id);
                    continue;
                }

                $correctTgroup = $ageGroupToTrainingGroup[$ageGroupId];

                if ($tgroup && $correctTgroup->id !== $tgroup->id) {
                    $this->log("  > Skipping #" . $subscription->id . " because it is associated to different training group (current: " . $tgroup->name . ", correct: " . $correctTgroup->name . ")");
                    continue;
                }

                $subscription->tgroup_id = $correctTgroup->id;
                $subscription->save();
                $this->log("  > Associated #" . $subscription->id . " (age group: " . Utils::getAgeGroupPath($ageGroupId) . ") with " . $correctTgroup->name);
            }

            $this->log(" ");
        }

        $this->log("Conversion done.");

        // $trainings = Models\Training::all();

        // foreach ($trainings as $training) {
        //     if ($training->trainingGroup()) {
        //         continue;
        //     }

        //     $gym = $training->gym();

        //     $priceSingle = $gym->priceSingle;
        //     $ageGroup    = Local\AgeGroup::find($training->ageGroup);

        //     $res = wp_insert_term($gym->name . " (" . Utils::getAgeGroupPath($ageGroup) . ")", Taxonomies\TrainingGroup::NAME);
        //     if (!is_array($res)) {
        //         wp_die($res);
        //         exit;
        //     }

        //     $tgroup = Models\TrainingGroup::find($res['term_id']);

        //     $enabledSubscriptionTypes = [];
        //     if ($gym->biannualEnabled) {
        //         $enabledSubscriptionTypes[] = SubscriptionType::BIANNUAL;
        //     }

        //     if ($gym->monthlyEnabled) {
        //         $enabledSubscriptionTypes[] = SubscriptionType::MONTHLY;
        //     }

        //     if (isset($gym->priceSingle[$ageGroup["id"]])) {
        //         $priceSingle = $gym->priceSingle[$ageGroup["id"]];
        //     } else {
        //         $priceSingle = 0;
        //     }

        //     $tgroup->setPrefixedMetaBulk([
        //         "price_single"               => $priceSingle,
        //         "password"                   => $gym->password,
        //         "enabled_subscription_types" => $enabledSubscriptionTypes,
        //         "capacity"                   => $gym->capacity,
        //         "term_periods"               => $gym->termPeriods,
        //         "attachment_sets"            => $gym->attachmentSets,
        //     ]);

        //     $paymentTemplates = collect($gym->getPaymentTemplatesFiltered($ageGroup))->map(function ($template) {
        //         unset($template["age_group"]);
        //         return $template;
        //     })->all();

        //     $tgroup->setPrefixedMeta("payment_templates", $paymentTemplates);

        //     foreach ($gym->trainings() as $t) {
        //         if ($t->ageGroup === $ageGroup["id"]) {
        //             wp_set_object_terms($training->id, $tgroup->term_id, Taxonomies\TrainingGroup::NAME);
        //         }
        //     }
        // }
    }
}
