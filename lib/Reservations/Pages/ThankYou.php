<?php

namespace Reservations\Pages;

use Carbon\Carbon;
use Reservations;
use Reservations\Base;
use Reservations\GoPay\PaymentStatus;
use Reservations\Models;
use Reservations\Pages\Utils as PagesUtils;
use Reservations\Utils;

class ThankYou extends Base\Page
{
    use PagesUtils\ObjectLoader;

    protected $transactionId;
    protected $isReplacement;

    protected $status;
    protected $subscription;
    protected $subscriber;
    protected $tgroup;
    protected $event;

    protected $error;

    public function setTransactionId($id)
    {
        $this->transactionId = (int) $id;
    }

    public function setIsReplacement($bool)
    {
        $this->isReplacement = (bool) $bool;
    }

    public function assets()
    {
        wp_enqueue_style("res-css", $this->plugin->url("public/style.css"));

        if (Reservations::MODE === "lead") {
            wp_enqueue_style("res-lead-css", $this->plugin->url("public/style-lead.css"), ["res-css"]);
        }
    }

    public function prepare()
    {
        global $wp_query;

        if ($this->isReplacement) {
            return;
        }

        $this->loadObjectsByTransactionId($this->transactionId);

        if (!$this->transaction || !$this->subscription) {
            $this->error = __('Failed to get subscription info.', 'reservations');
            return;
        }

        $status = $this->transaction->updatePaidStatus();

        if ($status->error) {
            $this->error = __('Failed to get payment info.', 'reservations');
            return;
        }

        $this->status = $status->json;

        if ($status->refunded) {
            $this->error = __('Payment was refunded.', 'reservations');
        } else if ($status->failed) {
            $this->error = __('Payment was cancelled or timed out.', 'reservations');
        } else if ($status->state === PaymentStatus::CREATED) {
            $this->error = __('Payment method hasn\'t been chosen yet.', 'reservations');
        } else if (!$status->paid && !$status->waiting) {
            $this->error = __('Payment hasn\'t been completed.', 'reservations');
        }

        $payment = $this->payment->fresh();
        if ($payment->paid && !$payment->confirmationEmailSent) {
            $payment->sendConfirmationEmail();
        }
    }

    public function render()
    {
        if ($this->isReplacement) {
            $error       = false;
            $paid        = false;
            $replacement = true;
        } else {
            $tgroup       = $this->tgroup;
            $event        = $this->event;
            $subscription = $this->subscription;
            $subscriber   = $this->subscriber;

            $error       = $this->error;
            $paid        = $this->status["state"] === "PAID";
            $replacement = false;

            $formUrl = $this->subscription->applicationFormFilename ? $this->permalink . _x('ajax', 'url slug', 'reservations') . '/?form&id=' . $this->transactionId : null;
        }

        if (Reservations::MODE === "lead") {
            include Reservations::ABSPATH . "/public/thank-you-lead.php";
        } else {
            include Reservations::ABSPATH . "/public/thank-you.php";
        }
    }
}
