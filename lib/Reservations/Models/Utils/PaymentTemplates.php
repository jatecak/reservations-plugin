<?php

namespace Reservations\Models\Utils;

use Reservations\Utils;

trait PaymentTemplates
{
    public function getPaymentTemplatesFiltered($ageGroup = null, $subscriptionType = null, $numMonths = null)
    {
        return Utils::filterPaymentTemplates($this->paymentTemplates, $ageGroup, $subscriptionType, $numMonths);
    }

    public function getPaymentAmount($ageGroup = null, $subscriptionType = null, $numMonths = null)
    {
        return Utils::sumPaymentTemplates($this->getPaymentTemplatesFiltered($ageGroup, $subscriptionType, $numMonths));
    }

    public function getInitialPaymentTemplate($ageGroup = null, $subscriptionType = null, $numMonths = null)
    {
        return Utils::getInitialPaymentTemplate($this->getPaymentTemplatesFiltered($ageGroup, $subscriptionType, $numMonths));
    }

    public function getInitialPaymentAmount($ageGroup = null, $subscriptionType = null, $numMonths = null)
    {
        $initialTemplate = $this->getInitialPaymentTemplate($ageGroup, $subscriptionType, $numMonths);

        return $initialTemplate ? $initialTemplate["amount"] : 0;
    }
}
