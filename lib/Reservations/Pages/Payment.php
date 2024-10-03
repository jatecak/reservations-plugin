<?php

namespace Reservations\Pages;

use Reservations;
use Reservations\Base;
use Reservations\Models\Local\SubscriptionType;
use Reservations\Pages;
use Reservations\Pages\Utils as PagesUtils;
use Reservations\Utils;

class Payment extends Base\Page
{
    use PagesUtils\ObjectLoader;

    protected $paymentHash;

    protected $error;
    protected $paymentStatus;
    protected $paymentExists = false;
    protected $showNotice    = false;

    public function setPaymentHash($hash)
    {
        $this->paymentHash = $hash;
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

        $this->loadObjectsByPaymentHash($this->paymentHash);

        if (!$this->payment || !$this->subscription) {
            $this->error = __('Failed to get subscription info.', 'reservations');
            return;
        }

        $this->payment->updatePaidStatus();

        if ($this->payment->paid) {
            $transaction = $this->payment->transactions()->paid()->orderBy("transaction_id", "desc")->first();

            $this->redirect($this->permalink . _x('thank-you', 'url slug', 'reservations') . "/?id=" . $transaction->gopayTransactionId);
        }

        $lastTransaction = $this->payment->transactions()->orderBy("transaction_id", "desc")->first();

        if (!$lastTransaction) {
            $this->paymentStatus = __('Payment hasn\'t been created yet.', 'reservations');
        } else {
            $status = $lastTransaction->getStatus();

            if ($status->refunded) {
                $this->paymentStatus = __('Payment was refunded.', 'reservations');
            } else if ($status->waiting) {
                $this->paymentStatus = __('Payment was created. Waiting for transaction clearance.', 'reservations');
            } else if ($status->failed) {
                $this->paymentStatus = __('Payment was cancelled.', 'reservations');
            } else {
                $this->paymentStatus = __('Unknown status.', 'reservations');
            }
        }

        if (isset($_GET['create'])) {
            $this->createPayment();
        }
    }

    protected function createPayment()
    {
        if ($this->event) {
            $url = $this->permalink . "/";
        } else if ($this->tgroup) {
            $url = $this->tgroup->subscribeLink;
        }

        list($transaction, $response) = $this->payment->createTransaction([
            "order_description" => __('L.E.A.D. Parkour subscription', 'reservations'),
            "item_description"  => $this->subscription->description,
            "item_url"          => $url,

            "return_url"        => $this->permalink . _x('thank-you', 'url slug', 'reservations') . "/",
            "notification_url"  => $this->permalink . _x('ajax', 'url slug', 'reservations') . "/?gopay",
        ]);

        if ($transaction) {
            $this->redirect($response->json['gw_url']);
        }

        $this->errors[] = __('An error occured during payment.', 'reservations');
    }

    public function render()
    {
        $error = $this->error;

        if (!$this->error) {
            $isTrainingGroup = (bool) $this->tgroup;
            $tgroup          = $this->tgroup;
            $isEvent         = (bool) $this->event;
            $event           = $this->event;

            $subscriber = $this->subscriber;

            $totalAmountFormatted        = Utils::formatNumber($this->subscription->paymentAmount);
            $paidAmountFormatted         = Utils::formatNumber($this->subscription->paidAmount);
            $paymentToPayAmountFormatted = Utils::formatNumber($this->payment->toPayAmount);

            $initial       = $this->payment->initial;
            $paymentStatus = $this->paymentStatus;

            $createPaymentUrl = $this->permalink . _x('payment', 'url slug', 'reservations') . "/?id=" . $this->paymentHash . "&create";
        }

        include Reservations::ABSPATH . "/public/payment.php";
    }
}
