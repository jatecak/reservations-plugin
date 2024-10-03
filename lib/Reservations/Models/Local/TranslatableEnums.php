<?php

namespace Reservations\Models\Local;

use Nette\MemberAccessException;
use Nette\Utils\Strings;
use Reservations;
use Reservations\Utils;

class TranslatableEnums
{
    public static function levels()
    {
        return [
            "beginner"     => _x('beginner', 'preferred level', 'reservations'),
            "intermediate" => _x('intermediate', 'preferred level', 'reservations'),
            "advanced"     => _x('advanced', 'preferred level', 'reservations'),
        ];
    }

    public static function subscriptionTypes()
    {
        return [
            "annual"   => __('annual', 'reservations'),
            "biannual" => __('biannual', 'reservations'),
            "monthly"  => __('monthly', 'reservations'),
            "single"   => __('one time', 'reservations'),
        ];
    }

    public static function subscriptionTypesTable()
    {
        return [
            "annual"   => __('annual subscription', 'reservations'),
            "biannual" => __('biannual subscription', 'reservations'),
            "monthly"  => __('monthly subscription', 'reservations'),
            "single"   => __('one time subscription', 'reservations'),
        ];
    }

    public static function yesNo()
    {
        return [
            "1" => __('Yes', 'reservations'),
            "0" => __('No', 'reservations'),
        ];
    }

    public static function campTypes()
    {
        return [
            "trip"     => __('trip', 'reservations'),
            "suburban" => __('suburban', 'reservations'),
        ];
    }

    public static function shirtSizes()
    {
        return [
            "122" => __('122 (6-7 years)', 'reservations'),
            "134" => __('134 (8-9 years)', 'reservations'),
            "146" => __('146 (10-11 years)', 'reservations'),
            "158" => __('158 (12-13 years)', 'reservations'),
            "XS"  => __('XS', 'reservations'),
            "S"   => __('S', 'reservations'),
            "M"   => __('M', 'reservations'),
            "L"   => __('L', 'reservations'),
            "XL"  => __('XL', 'reservations'),
            "XXL" => __('XXL', 'reservations'),
        ];
    }

    public static function shirtSizesLead()
    {
        return [
            "148" => __('children 148', 'reservations'),
            "156" => __('children 156', 'reservations'),
            "XS"  => __('XS', 'reservations'),
            "S"   => __('S', 'reservations'),
            "M"   => __('M', 'reservations'),
            "L"   => __('L', 'reservations'),
            "XL"  => __('XL', 'reservations'),
        ];
    }

    public static function referrers()
    {
        return [
            "training" => _x('training', 'referrer', 'reservations'),
            "web"      => _x('web', 'referrer', 'reservations'),
            "facebook" => _x('facebook', 'referrer', 'reservations'),
            "email"    => _x('email', 'referrer', 'reservations'),
            "other"    => _x('other', 'referrer', 'reservations'),
        ];
    }

    public static function runReasons()
    {
        return [
            "date"          => _x('date', 'event', 'reservations'),
            "location"      => __('location', 'reservations'),
            "accessibility" => __('better accessibility in case of need', 'reservations'),
            "new"           => __('wanted to try new location', 'reservations'),
            "other"         => __('other', 'reservations'),
        ];
    }

    public static function workshopReasons()
    {
        return [
            "skills"      => __('improve my skills', 'reservations'),
            "techniques"  => __('new techniques', 'reservations'),
            "condition"   => __('improve physical condition', 'reservations'),
            "friends"     => __('friends and community', 'reservations'),
            "information" => __('new information', 'reservations'),
            "limits"      => __('overcome my limits', 'reservations'),
            "meet"        => __('meet experienced people', 'reservations'),
            "fun"         => __('fun', 'reservations'),
        ];
    }

    public static function catering()
    {
        return [
            "0" => __('without catering', 'reservations'),
            "1" => __('complete catering (breakfast, lunch, dinner, snack, second dinner) and fluid intake', 'reservations'),
        ];
    }

    public static function carpool()
    {
        return [
            "none"    => __('no carpool', 'reservations'),
            "offer"   => __('I want to offer carpool', 'reservations'),
            "request" => __('I want to request carpool', 'reservations'),
        ];
    }

    public static function carpoolApplicationForm()
    {
        return [
            "none"    => _x('no carpool', 'application form', 'reservations'),
            "offer"   => _x('I want to offer carpool', 'application form', 'reservations'),
            "request" => _x('I want to request carpool', 'application form', 'reservations'),
        ];
    }

    public static function mealOptions()
    {
        return [
            __('meal option 1 (chicken)', 'reservations'),
            __('meal option 2 (fried cheese)', 'reservations'),
        ];
    }

    public static function weekdays()
    {
        return [
            __('sunday', 'reservations'),
            __('monday', 'reservations'),
            __('tuesday', 'reservations'),
            __('wednesday', 'reservations'),
            __('thursday', 'reservations'),
            __('friday', 'reservations'),
            __('saturday', 'reservations'),
        ];
    }

    public static function __callStatic($name, $arguments)
    {
        if (Strings::endsWith($name, "UcFirst")) {
            $replacedName = Strings::replace($name, '/UcFirst$/', "");

            return Utils\Arrays::ucFirst(static::{$replacedName}());
        }

        throw new MemberAccessException("Call to undefined static method ${self::class}::$name().");
    }
}
