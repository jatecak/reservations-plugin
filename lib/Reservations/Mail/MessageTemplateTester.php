<?php

namespace Reservations\Mail;

use Carbon\Carbon;
use Reservations;
use Reservations\Models;
use Reservations\Models\Local\EventType;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Local\SubscriptionType;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\Utils;

class MessageTemplateTester
{
    public static function createMockSubscription($subscriptionType, $objectType, $object)
    {
        $subscriptionData = [
            "type"                      => $subscriptionType,
            "is_replacement"            => true,
            "application_form_received" => false,
            "paid"                      => false,
            "created_at"                => Utils::now(),
        ];

        if ($subscriptionType !== SubscriptionType::SINGLE) {
            $subscriptionData["date_from"]  = Utils::today();
            $subscriptionData["date_to"]    = Utils::today()->addMonths(1);
            $subscriptionData["num_months"] = $subscriptionType === SubscriptionType::MONTHLY ? 1 : null;
        }

        $subscription = new Models\Subscription($subscriptionData);

        $subscriberData = [
            "hash"                  => Utils::createHash(),

            "first_name"            => _x('John', 'mock first name', 'reservations'),
            "last_name"             => _x('Doe', 'mock last name', 'reservations'), "",
            "facebook"              => _x('FB John Doe', 'mock fb', 'reservations'), "",
            "address"               => _x('1632 Henery Street, Andover', 'mock addres', 'reservations'),
            "date_of_birth"         => Carbon::createFromDate(2002, 11, 20, Utils::getTimezone()),
            "personal_number"       => _x('021110/1234', 'mock personal number', 'reservations'),
            "health_restrictions"   => _x('no health restrictions', 'mock health restrictions', 'reservations'),
            "used_medicine"         => _x('no medicaments', 'mock used medicine', 'reservations'),
            "health_insurance_code" => _x('100', 'mock health insurance code', 'reservations'),

            "rep_first_name"        => _x('Jane', 'mock rep first name', 'reservations'),
            "rep_last_name"         => _x('Doe', 'mock rep last name', 'reservations'),
            "rep_address"           => _x('1632 Henery Street, Andover', 'mock rep address', 'reservations'),
            "rep_date_of_birth"     => Carbon::createFromDate(1975, 2, 15, Utils::getTimezone()),
            "rep_personal_number"   => _x('123456/1234', 'mock rep personal number', 'reservations'),

            "contact_email"         => _x('jane.doe@example.com', 'mock email', 'reservations'),
            "contact_phone"         => _x('785-550-8837', 'mock phone', 'reservations'),
            "contact_phone_2"       => _x('509-470-8891', 'mock phone 2', 'reservations'),

            "referrer"              => "",
            "referrer_other"        => "",
            "reason"                => "",
            "reason_other"          => "",
            "carpool"               => "none",
            "carpool_seats"         => null,
            "carpool_contact"       => null,
            "catering"              => false,
            "meal"                  => null,
            "swimmer"               => false,
            "shirt_size"            => "S",
        ];

        $subscriber = new Models\Subscriber($subscriberData);

        $subscription->setRelation("subscriber", $subscriber);

        if ($objectType === ObjectType::TRAININGS) {
            $subscription->tgroup_id = 1;
            $subscription->setRelation("trainingGroup", $object);
        } else if ($objectType === ObjectType::EVENT) {
            $subscription->event_id = 1;
            $subscription->setRelation("event", $object);
        }

        return $subscription;
    }

    public static function sendTestMessage($recipient, $messageTemplateModel, $subscription)
    {
        $messageTemplate = MessageTemplate::fromModel($messageTemplateModel);

        $variables = $subscription->getEmailVariables();

        $variables["username"] = $subscription->subscriber->generateUsername();
        $variables["password"] = wp_generate_password(12, false);

        $amount        = max(100, $subscription->paymentAmount);
        $initialAmount = max(50, $subscription->initialPaymentAmount);

        $variables["amount"]            = Utils::formatNumber($initialAmount);
        $variables["paymentPaidAmount"] = Utils::formatNumber(0);
        $variables["paidAmount"]        = Utils::formatNumber(0);
        $variables["toPayAmount"]       = Utils::formatNumber($amount);
        $variables["paymentAmount"]     = $variables["totalAmount"]     = Utils::formatNumber($amount);
        $variables["payUrl"]            = _x('http://example.com/', 'mock pay url', 'reservations');

        $message = $messageTemplate->createMessage($variables);

        $message->texturizeBody();
        $message->addTo($recipient);

        if ($messageTemplateModel["attach_application_form"] && $subscription->applicationFormFilename) {
            $message->addUrlAttachment(Reservations::instance()->getOption("form_filler_url"), _x('application_form.pdf', 'attachment file name', 'reservations'), $subscription->getApplicationFormStreamContext());
        }

        return Reservations::instance()->mailer->send($message);
    }
}
