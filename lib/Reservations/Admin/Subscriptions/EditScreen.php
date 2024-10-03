<?php

namespace Reservations\Admin\Subscriptions;

use Reservations;
use Reservations\Models;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\Utils;

class EditScreen
{
    public $subscription;
    public $plugin;

    protected $isEvent;
    protected $isReplacement;

    public function __construct($plugin, $subscription)
    {
        $this->plugin       = $plugin;
        $this->subscription = $subscription;

        $this->isEvent       = $subscription->objectType === ObjectType::EVENT;
        $this->isReplacement = $subscription->isReplacement;
    }

    public function render()
    {
        $subscription = $this->subscription;

        if ($this->isEvent) {
            $event = $subscription->event;
            $city  = $event->city();
        } else {
            $tgroup = $subscription->trainingGroup;
            $cities = $tgroup->cities;
            if (count($cities) === 1) {
                $city = Utils\Arrays::getFirstElement($cities);
            } else {
                $city = null;
            }

            $ageGroups = $tgroup->ageGroups;

            if (!count($ageGroups)) {
                $ageGroups = Models\Local\AgeGroup::all();
            }

            $ageGroups = collect($ageGroups)->map(function ($ageGroup) {
                $ageGroup["label"] = Utils::getAgeGroupPath($ageGroup);
                return $ageGroup;
            });

            $ageGroupSelect = Utils\Html::getAgeGroupSelect($ageGroups, $subscription->ageGroup["id"]);

            $accessibleTrainingGroups = Models\TrainingGroup::accessible()->get();
            if (!$accessibleTrainingGroups->contains(function ($tg) use ($tgroup) {
                // this is there just in case, shouldn't be necessary
                return $tg->id === $tgroup->id;
            })) {
                $accessibleTrainingGroups->push($tgroup);
            }

            $trainingGroupSelect = Utils\Html::getTrainingGroupSelect($accessibleTrainingGroups, $tgroup->id);

            $subscriptionType = TranslatableEnums::subscriptionTypesTableUcFirst()[$subscription->subscriptionType] ?? "";
            $monthsLeft       = max(0, Utils::today()->diffInMonths($subscription->dateTo));
            $monthsLeft       = sprintf(_n('%d month left', '%d months left', $monthsLeft, 'reservations'), $monthsLeft);
        }

        // templates -> payments
        $payments = collect($subscription->paymentTemplates)->map(function ($template) use ($subscription) {
            $payment = [
                "templateHash" => $template["hash"],
                "template"     => $template,
                "canSendNotificationEmail" => !$template["initial"],
            ];

            $p = $subscription->getPayment($template);

            if ($p) {
                // template with payment
                $payment["p"] = $p;
                $payment["canSendNotificationEmail"] = $payment["canSendNotificationEmail"] && !$p->notificationEmailSent;
            } else {
                // template without payment
                $payment["paid"]           = false;
                $payment["paidAmount"]     = 0;
                $payment["amount"]         = $payment["toPayAmount"]         = Utils::formatNumber($template["amount"]);
                $payment["canUnmark"]      = false;
                $payment["transactionIds"] = "";
            }

            return $payment;
        })->values();

        // payments -> templates
        $subscription->payments()->get()->each(function ($p) use (&$payments) {
            $found = $payments->contains(function ($p2) use ($p) {
                return isset($p2["p"]) && $p2["p"]->id === $p->id;
            });

            if ($found) {
                return;
            }

            // payment without template
            $payment = [
                "template" => null,
                "p"        => $p,
                "canSendNotificationEmail" => false,
            ];

            $payments[] = $payment;
        });

        $payments = $payments->map(function ($payment) {
            if (!isset($payment["p"])) {
                return $payment;
            }

            $p = $payment["p"];

            $payment["payment_id"] = $p->id;

            $payment["paid"]        = $p->paid;
            $payment["amount"]      = Utils::formatNumber($p->amount);
            $payment["paidAmount"]  = Utils::formatNumber($p->paidAmount);
            $payment["toPayAmount"] = Utils::formatNumber($p->toPayAmount);
            $payment["canUnmark"]   = $p->transactions()->manual()->exists();

            if ($p->paid) {
                $payment["transactionIds"] = $p->transactions()->paid()->get()->filter(function ($transaction) {
                    return !$transaction->manual;
                })->implode("gopayTransactionId", ", ");
            } else {
                $payment["transactionIds"] = $p->transactions()->get()->filter(function ($transaction) {
                    return !$transaction->manual;
                })->implode("gopayTransactionId", ", ");
            }

            return $payment;
        });

        $hasUnpaid = $payments->contains(function ($payment) {
            return !$payment["paid"];
        });

        $hasUnmarkable = $payments->contains(function ($payment) {
            return $payment["canUnmark"];
        });

        $hasUnassigned = $payments->contains(function ($payment) {
            return !$payment["template"];
        });

?>
        <div class="wrap" id="res-subscriptions">
            <?php if ($this->isReplacement) : ?>
                <h1 class="wp-heading-inline"><?php _e('Edit Replacement', 'reservations'); ?></h1>
            <?php elseif ($this->isEvent) : ?>
                <h1 class="wp-heading-inline"><?php _ex('Edit Subscription', 'event', 'reservations'); ?></h1>
            <?php else : ?>
                <h1 class="wp-heading-inline"><?php _e('Edit Subscription', 'reservations'); ?></h1>
            <?php endif; ?>
            <hr class="wp-header-end">
            <form method="post">
                <input type="hidden" name="action" value="update" />
                <?php wp_nonce_field("subscription_edit"); ?>

                <table class="form-table">
                    <?php if ($this->isEvent) : ?>
                        <tr class="form-field subscription-gym-id-wrap">
                            <th scope="row"><?php _e('Event Name', 'reservations'); ?></th>
                            <td><strong><a href="<?= $subscription->event->editLink ?>"><?= esc_html($event->title) ?></a></strong>
                                (<?= esc_html($city->name) ?>)</td>
                        </tr>
                    <?php else : ?>
                        <tr class="form-field subscription-gym-id-wrap">
                            <th scope="row"><?php _e('Subscription Type', 'reservations'); ?></th>
                            <td>
                                <p style="font-weight: bold;margin-bottom:3px"><?= esc_html($subscriptionType) ?></p>
                                <?= esc_html($subscription->dateFrom->format("j. n. Y")) ?> &ndash; <?= esc_html($subscription->dateTo->format("j. n. Y")) ?> (<?= esc_html($monthsLeft) ?>)
                            </td>
                        </tr>

                        <?php if ($this->isReplacement) : ?>
                            <tr class="form-field subscription-tgroup-id-wrap">
                                <th scope="row"><?php _e('Training Group', 'reservations'); ?></th>
                                <td><strong><?= esc_html($tgroup->name) ?></strong>
                                    <?php if ($city) : ?><br><?= esc_html($city->name) ?><?php endif; ?></td>
                            </tr>
                        <?php else : ?>
                            <tr class="form-field subscription-tgroup-id-wrap">
                                <th scope="row"><label for="subscription-tgroup-id"><?php _e('Training Group', 'reservations'); ?></label></th>
                                <td><select name="tgroup_id" id="subscription-tgroup-id">
                                        <option value=""><?php _e('&mdash; Select &mdash;', 'reservations'); ?>
                                            <?= $trainingGroupSelect ?>
                                    </select></td>
                            </tr>
                            <tr class="form-field subscription-age-group-wrap">
                                <th scope="row"><label for="subscription-age-group"><?php _e('Age Group', 'reservations'); ?></label></th>
                                <td><select name="age_group" id="subscription-age-group">
                                        <?= $ageGroupSelect ?>
                                    </select></td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!$this->isReplacement) : ?>
                        <tr class="form-field subscription-payments-wrap">
                            <th scope="row"><?php _e('Payments', 'reservations'); ?></th>
                            <td>
                                <table class="res-payments">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Payment #', 'reservations'); ?></th>
                                            <th><?php _e('Total Amount', 'reservations'); ?></th>
                                            <th><?php _e('Paid Amount', 'reservations'); ?></th>
                                            <th><?php _e('To Pay Amount', 'reservations'); ?></th>
                                            <th><?php _e('Transaction ID', 'reservations'); ?></th>
                                            <th></th>
                                        </tr>
                                    </thead>

                                    <?php foreach ($payments as $i => $payment) : ?>
                                        <tr<?= ($payment["paid"] ? ' class="res-paid"' : '') ?>>
                                            <td><?= $i + 1 ?>.</td>
                                            <td><?= esc_html($payment["amount"]) ?> <?php _e('US$', 'reservations'); ?></td>
                                            <td><?= esc_html($payment["paidAmount"]) ?> <?php _e('US$', 'reservations'); ?></td>
                                            <td><?= esc_html($payment["toPayAmount"]) ?> <?php _e('US$', 'reservations'); ?></td>
                                            <td><?= esc_html($payment["transactionIds"]) ?></td>
                                            <td>
                                                <?php if ($payment["template"]) : ?>
                                                    <?php if (!$payment["paid"]) : ?><button type="submit" class="button" name="mark" value="<?= esc_attr($payment["templateHash"]) ?>"><?php _e('Mark as paid', 'reservations'); ?></button><?php endif; ?>
                                                    <?php if ($payment["canUnmark"]) : ?><button type="submit" class="button" name="unmark" value="<?= esc_attr($payment["templateHash"]) ?>"><?php _e('Unmark', 'reservations'); ?></button><?php endif; ?>
                                                    <?php if ($payment["canSendNotificationEmail"]) : ?><button type="submit" class="button" name="send_notification_email" value="<?= esc_attr($payment["templateHash"]) ?>"><?php _e('Send Notification Email', 'reservations'); ?></button><?php endif; ?>
                                                <?php else : ?>
                                                    <strong>(<?php _e('unknown payment &ndash; template not found', 'reservations'); ?>)</strong>
                                                <?php endif; ?>
                                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php if ($hasUnpaid) : ?>
                    <button type="submit" class="button" name="mark_all"><?php _e('Mark all as paid', 'reservations'); ?></button>
                <?php endif; ?>

                <?php if ($hasUnmarkable) : ?>
                    <button type="submit" class="button" name="unmark_all"><?php _e('Unmark all', 'reservations'); ?></button>
                <?php endif; ?>

                <?php if ($hasUnassigned) : ?>
                    <button type="submit" class="button" name="auto_assign"><?php _e('Autoassign unknown payments (by amount)', 'reservations'); ?></button>
                <?php endif; ?>
                </td>
                </tr>
            <?php endif; ?>
            </table>

            <?php if (Reservations::MODE === "lead") : ?>
                <div class="res-sub-info">
                    <?php $this->renderSubscriptionInfo(); ?>
                </div>
            <?php endif; ?>

            <?php if (!$this->isReplacement) : ?>
                <div class="edit-subscription-actions">
                    <input type="submit" class="button button-primary" value="Aktualizovat" />
                </div>
            <?php endif; ?>

            </form>
        </div>
    <?php
    }

    public function handleSubmit()
    {
        $subscription = $this->subscription;

        if ($subscription->isReplacement) {
            $this->redirect([
                "edit" => false,
            ]);
        }

        if (!$this->isEvent) {
            if (!Utils::allSet($_POST, [
                "tgroup_id", "age_group",
            ])) {
                return $this->redirect([
                    "editok" => false,
                ]);
            }

            $tgroup = Models\TrainingGroup::find((int) $_POST['tgroup_id']);

            if ($tgroup) {
                $subscription->tgroup_id = $tgroup->id;
            }

            $ageGroup = Models\Local\AgeGroup::find((int) $_POST['age_group']);

            if ($ageGroup) {
                $subscription->ageGroup = $ageGroup["id"];
            }

            $ageGroups = collect($tgroup->ageGroups);

            if (!$ageGroups->contains(function ($g) use ($subscription) {
                return $g["id"] === $subscription->ageGroup["id"];
            })) {
                $subscription->ageGroup = $ageGroups->count() ? $ageGroups->first()["id"] : null;
            }

            $subscription->save();
        }

        if (isset($_POST["mark"]) || isset($_POST["unmark"]) || isset($_POST["mark_all"]) || isset($_POST["unmark_all"])) {
            $templateHash = $_POST["mark"] ?? $_POST["unmark"];
            $mark         = isset($_POST["mark"]) || isset($_POST["mark_all"]);

            $templates = $subscription->paymentTemplates;

            if (isset($_POST["mark"]) || isset($_POST["unmark"])) {
                $templates = collect($templates)->where("hash", $templateHash)->take(1)->all();
            }

            foreach ($templates as $template) {
                $payment = $mark ? $subscription->getOrCreatePayment($template) : $subscription->getPayment($template);

                if ($mark) {
                    $payment->markPaidManually();
                } else if ($payment) {
                    $payment->transactions()->manual()->delete();

                    $toPayAmount = $payment->amount - $payment->transactions()->get()->pluck("paidAmount")->sum();

                    if ($payment->paid && $toPayAmount > 0) {
                        $payment->paid       = false;
                        $payment->paidAmount = 0;

                        $payment->save();
                    }

                    $toPayAmount = $subscription->paymentAmount - $subscription->payments()->get()->pluck("paidAmount")->sum();

                    if ($subscription->paid && $toPayAmount > 0) {
                        $subscription->paid       = false;
                        $subscription->paidAmount = 0;

                        $subscription->save();
                    }
                }
            }

            $this->redirect([
                "editok" => 1,
            ]);
        } else if (isset($_POST["send_notification_email"])) {
            $templateHash = $_POST["send_notification_email"];
            $templates = collect($subscription->paymentTemplates)->where("hash", $templateHash)->take(1)->all();

            foreach ($templates as $template) {
                $payment = $subscription->getOrCreatePayment($template);

                if (!$payment->notificationEmailSent) {
                    $payment->sendNotificationEmail();
                }
            }

            $this->redirect([
                "editok" => 1,
            ]);
        } else if (isset($_POST['auto_assign'])) {
            $templates = collect($subscription->paymentTemplates);
            $payments  = $subscription->payments()->get();

            $templatePaymentMap = [];
            $paymentTemplateMap = [];

            foreach ($payments as $payment) {
                $template = $templates->first(function ($template) use ($payment) {
                    return $template["hash"] === $payment->templateHash;
                });

                if ($template) {
                    if (isset($templatePaymentMap[$template["hash"]])) // this shouldn't happen
                    {
                        continue;
                    }

                    $templatePaymentMap[$template["hash"]] = $payment;
                    $paymentTemplateMap[$payment->hash]    = $template;
                }
            }

            // stage 1: uncreated payments
            foreach ($payments as $payment) {
                if (isset($paymentTemplateMap[$payment->hash])) // payment has existing template
                {
                    continue;
                }

                // find templates with the same amount without created payment
                $newTemplate = $templates->first(function ($template) use ($payment, &$templatePaymentMap) {
                    return !isset($templatePaymentMap[$template["hash"]]) && $template["amount"] === $payment->amount;
                });

                if ($newTemplate) {
                    // assign $newTemplate to $payment

                    $templatePaymentMap[$newTemplate["hash"]] = $payment;
                    $paymentTemplateMap[$payment->hash]       = $newTemplate;

                    $payment->templateHash = $newTemplate["hash"];
                    $payment->save();
                }
            }

            // stage 2: unpaid payments
            foreach ($payments as $payment) {
                if (isset($paymentTemplateMap[$payment->hash])) // payment has existing template
                {
                    continue;
                }

                $newTemplate = $templates->first(function ($template) use ($payment, &$templatePaymentMap) {
                    if (!isset($templatePaymentMap[$template["hash"]])) {
                        return false;
                    }

                    $currentPayment = $templatePaymentMap[$template["hash"]];

                    return $template["amount"] === $payment->amount
                        && $currentPayment->amount === $payment->amount
                        && $currentPayment->paidAmount === 0;
                });

                if ($newTemplate) {
                    $newPayment = $templatePaymentMap[$newTemplate["hash"]];

                    // move all transactions from $payment to $newPayment
                    $payment->transactions()->update([
                        "payment_id" => $newPayment->id,
                    ]);

                    if ($payment->notificationEmailSent) {
                        $newPayment->notificationEmailSent = true;
                    }

                    if ($payment->confirmationEmailSent) {
                        $newPayment->confirmationEmailSent = true;
                    }

                    $newPayment->save();
                    $newPayment->updatePaidStatus(false);

                    $payment->delete();
                }
            }

            $this->redirect([
                "editok" => 1,
            ]);
        }

        $this->redirect([
            "edit"   => false,
            "editok" => 1,
        ]);
    }

    public function renderSubscriptionInfo()
    {
        $subscription = $this->subscription;
        $subscriber   = $subscription->subscriber;
        $event        = $subscription->event;

        $preferredLevel          = TranslatableEnums::levelsUcFirst()[$subscriber->preferredLevel] ?? $subscriber->preferredLevel;
        $healthRestrictions      = Utils::texturize(esc_html($subscriber->healthRestrictions));
        $usedMedicine            = Utils::texturize(esc_html($subscriber->usedMedicine));
        $ageGroup                = $subscription->ageGroup ? Utils::getAgeGroupPath($subscription->ageGroup) : "";
        $independentLeave        = TranslatableEnums::yesNoUcFirst()[(int) $subscriber->independentLeave] ?? "";
        $swimmer                 = TranslatableEnums::yesNoUcFirst()[$subscriber->swimmer] ?? "";
        $applicationFormReceived = TranslatableEnums::yesNoUcFirst()[$subscription->applicationFormReceived] ?? "";
        $catering                = TranslatableEnums::cateringUcFirst()[$subscriber->catering] ?? "";
        $carpool                 = TranslatableEnums::carpoolUcFirst()[$subscriber->carpool] ?? "";

        if (Reservations::MODE === "lead") {
            $shirtSize = TranslatableEnums::shirtSizesLead()[$subscriber->shirtSize] ?? "";
        } else {
            $shirtSize = TranslatableEnums::shirtSizes()[$subscriber->shirtSize] ?? "";
        }

        $isEvent     = $subscription->objectType === ObjectType::EVENT;
        $isTrainings = $subscription->objectType === ObjectType::TRAININGS;
        $isLubo      = Reservations::MODE === "lubo";
        $isLead      = Reservations::MODE === "lead";
        $isWorkshop  = $isEvent && $subscription->event->eventType["id"] === "workshop";
        $isCamp      = $isEvent && $subscription->event->eventType["id"] === "camp";

        if ($subscriber->reason === "other") {
            $referrer = $subscriber->referrerOther;
        } else {
            $referrer = TranslatableEnums::referrersUcFirst()[$subscriber->referrer] ?? $subscriber->referrer;
        }

        if ($subscriber->reason === "other") {
            $reason = $subscriber->reasonOther;
        } else if ($isWorkshop) {
            $reason = TranslatableEnums::workshopReasonsUcFirst()[$subscriber->reason] ?? $subscriber->reason;
        } else if ($isCamp) {
            $reason = TranslatableEnums::runReasonsUcFirst()[$subscriber->reason] ?? $subscriber->reason;
        }

        if ($isTrainings) {
            $subscribedTrainings = Utils::formatSubscribedTrainings($subscription->trainingGroup->trainings());
        }

    ?>
        <div class="res-pull-left">
            <h2><?php _e('Subscriber Details', 'reservations'); ?></h2>

            <table class="res-sub-info-table">
                <tr>
                    <th><?php _e('First Name', 'reservations'); ?></th>
                    <td><?= esc_html($subscriber->firstName) ?></td>
                </tr>
                <tr>
                    <th><?php _e('Last Name', 'reservations'); ?></th>
                    <td><?= esc_html($subscriber->lastName) ?></td>
                </tr>
                <tr>
                    <th><?php _e('Date of Birth', 'reservations'); ?></th>
                    <td><?= esc_html($subscriber->dateOfBirth->format("j. n. Y")) ?></td>
                </tr>

                <?php if ($isLubo || !$isEvent) : ?>
                    <tr>
                        <th><?php _e('Address', 'reservations'); ?></th>
                        <td><?= esc_html($subscriber->address) ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($isLubo) : ?>
                    <tr>
                        <th><?php _e('Personal Number', 'reservations'); ?></th>
                        <td><?= esc_html($subscriber->personalNumber) ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($isEvent) : ?>
                    <tr>
                        <th><?php _e('Health Insurance Company Code', 'reservations'); ?></th>
                        <td><?= esc_html($subscriber->healthInsuranceCode) ?></td>
                    </tr>
                <?php endif; ?>

                <tr>
                    <th><?php _e('Health Restrictions', 'reservations'); ?></th>
                    <td><?= $healthRestrictions ?></td>
                </tr>

                <?php if ($isEvent && ($isLead || $isCamp)) : ?>
                    <tr>
                        <th><?php _e('Used Medicine', 'reservations'); ?></th>
                        <td><?= $usedMedicine ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Swimmer', 'reservations'); ?></th>
                        <td><?= esc_html($swimmer) ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Shirt Size', 'reservations'); ?></th>
                        <td><?= esc_html($shirtSize) ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($isTrainings) : ?>
                    <tr>
                        <th><?php _e('Age Group', 'reservations'); ?></th>
                        <td><?= esc_html($ageGroup) ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($isLead && $isTrainings) : ?>
                    <tr>
                        <th><?php _e('Independent Leave', 'reservations'); ?></th>
                        <td><?= esc_html($independentLeave) ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($isLubo && $isTrainings) : ?>
                    <tr>
                        <th><?php _e('Facebook', 'reservations'); ?></th>
                        <td><?= esc_html($subscriber->facebook) ?></td>
                    </tr>

                    <tr>
                        <th><?php _e('Preferred Level', 'reservations'); ?></th>
                        <td><?= esc_html($preferredLevel) ?></td>
                    </tr>
                <?php endif; ?>
            </table>

            <?php if ($isLubo && $isWorkshop && $subscriber->catering) : ?>
                <h2><?php _e('Catering', 'reservations'); ?></h2>

                <table class="res-sub-info-table">
                    <tr>
                        <th><?php _e('Selected Meal', 'reservations'); ?></th>
                        <td><?= esc_html($subscriber->meal) ?></td>
                    </tr>
                </table>
            <?php endif; ?>

            <?php if ($isLubo && $isWorkshop && $subscriber->carpool && $subscriber->carpool !== "none") : ?>
                <h2><?php _e('Carpool', 'reservations'); ?></h2>

                <table class="res-sub-info-table">
                    <tr>
                        <th><?php _e('Carpool', 'reservations'); ?></th>
                        <td><?= esc_html($carpool) ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Number of Requested/Offered Seats', 'reservations'); ?></th>
                        <td><?= esc_html($subscriber->carpoolSeats) ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Contact Phone', 'reservations'); ?></th>
                        <td><?= esc_html($subscriber->carpoolContact) ?></td>
                    </tr>
                </table>
            <?php endif; ?>

            <h2><?php _e('Other', 'reservations'); ?></h2>

            <table class="res-sub-info-table">
                <?php if ($isLubo && $isEvent) : ?>
                    <tr>
                        <th><?php _e('Referrer', 'reservations'); ?></th>
                        <td><?= esc_html($referrer) ?></td>
                    </tr>
                    <tr>
                        <?php if ($isCamp) : ?>
                            <th><?php _ex('Reason', 'camp', 'reservations'); ?></th>
                        <?php else : ?>
                            <th><?php _e('Reason', 'reservations'); ?></th>
                        <?php endif; ?>
                        <td><?= esc_html($reason) ?></td>
                    </tr>
                <?php endif; ?>

                <tr>
                    <th><?php _e('Application Form Received', 'reservations'); ?></th>
                    <td><?= esc_html($applicationFormReceived) ?></td>
                </tr>

                <tr>
                    <th><?php _e('Registration Date', 'reservations'); ?></th>
                    <td><?= esc_html($subscription->createdAt->format("j. n. Y H:i:s")) ?></td>
                </tr>
            </table>
        </div>

        <div class="res-pull-left">
            <h2><?php _e('Subscriber Representative Details', 'reservations'); ?></h2>

            <table class="res-sub-info-table">
                <tr>
                    <th><?php _e('First Name', 'reservations'); ?></th>
                    <td><?= esc_html($subscriber->repFirstName) ?></td>
                </tr>
                <tr>
                    <th><?php _e('Last Name', 'reservations'); ?></th>
                    <td><?= esc_html($subscriber->repLastName) ?></td>
                </tr>

                <?php if ($isLubo) : ?>
                    <tr>
                        <th><?php _e('Date of Birth', 'reservations'); ?></th>
                        <td><?= esc_html($subscriber->repDateOfBirth->format("j. n. Y")) ?></td>
                    </tr>
                <?php endif; ?>

                <tr>
                    <th><?php _e('Address', 'reservations'); ?></th>
                    <td><?= esc_html($subscriber->repAddress) ?></td>
                </tr>
            </table>

            <h2><?php _e('Contact Info', 'reservations'); ?></h2>

            <table class="res-sub-info-table">
                <tr>
                    <th><?php _e('Email', 'reservations'); ?></th>
                    <td><?= esc_html($subscriber->contactEmail) ?></td>
                </tr>

                <?php if ($isTrainings || $isLead) : ?>
                    <tr>
                        <th><?php _e('Phone', 'reservations'); ?></th>
                        <td><?= esc_html($subscriber->contactPhone) ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($isLubo && $isEvent) : ?>
                    <tr>
                        <th><?php _e('Phone (mother)', 'reservations'); ?></th>
                        <td><?= esc_html($subscriber->contactPhone) ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Phone (father)', 'reservations'); ?></th>
                        <td><?= esc_html($subscriber->contactPhone2) ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="res-clear"></div>

        <?php if ($isTrainings) : ?>
            <h2><?php _e('List of Trainings', 'reservations'); ?></h2>

            <?php if (count($subscribedTrainings)) : ?>
                <p><?php _e('This subscription applies to the following trainings:', 'reservations'); ?></p>

                <ul class="res-trainings-list">
                    <?php foreach ($subscribedTrainings as $gymName => $trainings) : ?>
                        <li>
                            <div class="res-tl-gym-name"><?= esc_html($gymName) ?></div>
                            <ul>
                                <?php foreach ($trainings as $training) : ?>
                                    <li><a href="<?= esc_url($training->editLink) ?>"><?= esc_html($training->title) ?></a> &ndash; <?php _e('age group:', 'reservations') ?> <strong><?= $training->ageGroupLabel ?></strong> &ndash; <?= $training->timeText ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php _e('There are no trainings in this training group.', 'reservations'); ?></p>
            <?php endif; ?>
        <?php endif; ?>

<?php
    }

    protected function redirect($args)
    {
        wp_redirect(add_query_arg($args));
        exit;
    }
}
