<?php

namespace Reservations\GoPay;

use Nette;

/**
 * @property-read stdClass $json
 * @property-read string $state
 * @property-read bool $error
 * @property-read bool $failed
 * @property-read bool $refunded
 * @property-read bool $waiting
 * @property-read bool $paid
 */
class PaymentStatus
{
    use Nette\SmartObject;

    const CREATED         = "CREATED",
    PAYMENT_METHOD_CHOSEN = "PAYMENT_METHOD_CHOSEN",
    PAID                  = "PAID",
    AUTHORIZED            = "AUTHORIZED",
    CANCELED              = "CANCELED",
    TIMEOUTED             = "TIMEOUTED",
    REFUNDED              = "REFUNDED",
    PARTIALLY_REFUNDED    = "PARTIALLY_REFUNDED";

    public $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public static function getStatus($goPay, $transactionId)
    {
        return new self($goPay->getStatus($transactionId));
    }

    public function getJson()
    {
        return $this->response->json;
    }

    public function isError()
    {
        return !$this->response->hasSucceed();
    }

    public function getState()
    {
        return !$this->error ? $this->json["state"] : null;
    }

    public function isFailed()
    {
        return $this->error || in_array($this->state, [self::TIMEOUTED, self::CANCELED]) || $this->refunded;
    }

    public function isRefunded()
    {
        return !$this->error && in_array($this->state, [self::REFUNDED, self::PARTIALLY_REFUNDED]);
    }

    public function isWaiting()
    {
        return !$this->error && in_array($this->state, [self::CREATED, self::PAYMENT_METHOD_CHOSEN, self::AUTHORIZED]);
    }

    public function isPaid()
    {
        return !$this->error && $this->state === self::PAID;
    }
}
