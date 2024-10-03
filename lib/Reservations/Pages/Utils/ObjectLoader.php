<?php

namespace Reservations\Pages\Utils;

use Reservations\Models;
use Reservations\Models\Local\ObjectType;

trait ObjectLoader
{
    protected $transaction;
    protected $payment;
    protected $subscription;
    protected $subscriber;
    protected $event;
    protected $tgroup;
    // protected $gym;

    protected function loadObjectsByTransactionId($transactionId)
    {
        $this->transaction = Models\Transaction::transactionId($transactionId)->first();

        if ($this->transaction) {
            $this->payment      = $this->transaction->payment;
            $this->subscription = $this->payment->subscription;
            $this->subscriber   = $this->subscription->subscriber;

            switch ($this->subscription->objectType) {
                case ObjectType::EVENT:
                    $this->event = $this->subscription->event;
                    break;

                case ObjectType::TRAININGS:
                    // $this->gym    = $this->subscription->gym;
                    $this->tgroup = $this->subscription->trainingGroup;
                    break;
            }
        }
    }

    protected function loadObjectsByPaymentHash($paymentHash)
    {
        $this->payment = Models\Payment::hash($paymentHash)->first();

        if ($this->payment) {
            $this->subscription = $this->payment->subscription;
            $this->subscriber   = $this->subscription->subscriber;

            switch ($this->subscription->objectType) {
                case ObjectType::EVENT:
                    $this->event = $this->subscription->event;
                    break;

                case ObjectType::TRAININGS:
                    // $this->gym = $this->subscription->gym;
                    $this->tgroup = $this->subscription->trainingGroup;
                    break;
            }
        }
    }
}
