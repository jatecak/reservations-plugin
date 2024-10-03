<?php

namespace Reservations\Pages\Utils;

use DateTime;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;
use Reservations;
use Reservations\Mail;
use Reservations\Models;
use Reservations\Models\Local;
use Reservations\Models\Local\ObjectType;
use Reservations\PostTypes;
use Reservations\Taxonomies;
use Reservations\Utils;

trait SubscriberAccount
{
    protected $sr_errors = [];
    protected $sr_subscriber;

    protected function sr_getObject()
    {
        return $this->tgroup ?? $this->event ?? null;
    }

    protected function sr_getSubscriber()
    {
        $user = Models\User::current();

        $subscriber = null;

        if ($user && isset($_POST['load_subscriber'])) {
            $subscriber = $user->subscribers()->whereKey((int) sanitize_text_field($_POST['load_subscriber']))->first();
        }

        return $subscriber;
    }

    protected function sr_isEnabled()
    {
        return $this->plugin->isFeatureEnabled($this->getObjectType() . "_subscriber_account");
    }

    protected function handleSubscriberAccount()
    {
        if (!$this->sr_isEnabled()) {
            return;
        }

        $this->handleLoginForm();

        $loadedSubscriber = $this->sr_getSubscriber();
        $user             = Models\User::current();

        if (isset($_POST['do']) && $_POST['do'] === "load_subscriber") {
            if (isset($_POST['load_subscriber'])) {
                $this->oldValues["load_subscriber"] = $_POST['load_subscriber'];
            }

            $this->oldValues["save_details"] = "1";

            if ($loadedSubscriber) {
                foreach ($loadedSubscriber->getFillable() as $key) {
                    $val = $loadedSubscriber->{$key};

                    if ($val instanceof DateTime) {
                        $val = $val->format("Y-m-d");
                    }

                    $this->oldValues[$key] = $val;
                }

                return;
            }
        } else if (isset($_POST['do']) && $_POST['do'] === "delete_subscriber") {
            if ($loadedSubscriber) {
                $loadedSubscriber->user_id = null;
                $loadedSubscriber->save();

                $loadedSubscriber = null;
            } else {
                $this->sr_errors[] = __('Please select a subscriber to delete.', 'reservations');
            }
        }

        if ($user) {
            $subscriber = $loadedSubscriber ?? $user->subscribers()->orderBy("subscriber_id", "DESC")->first();

            if (!$subscriber) {
                return;
            }

            foreach ($subscriber->getFillable() as $key) {
                if (!Strings::startsWith($key, "rep_") && !Strings::startsWith($key, "contact_")) {
                    continue;
                }

                $val = $subscriber->{$key};

                if ($val instanceof DateTime) {
                    $val = $val->format("Y-m-d");
                }

                if (!isset($this->oldValues[$key])) {
                    $this->oldValues[$key] = $val;
                }
            }
        }
    }

    protected function handleLoginForm()
    {
        if (!isset($_POST['do']) || $_POST['do'] !== "login") {
            return;
        }

        $credentials = [
            "user_login"    => $_POST['user_login'] ?? "",
            "user_password" => $_POST['user_password'] ?? "",
            "remember"      => false,
        ];

        unset($_POST['user_login']);
        unset($_POST['user_password']);

        $this->oldValues = $_POST;

        $user = wp_signon($credentials);

        if (is_wp_error($user)) {
            $this->sr_errors = array_merge($this->sr_errors, $user->get_error_messages());
        } else {
            wp_set_current_user($user->ID);
        }
    }

    protected function getAccountVariables()
    {
        $vars = new ArrayHash;

        if (!$this->sr_isEnabled()) {
            return $vars;
        }

        $object           = $this->sr_getObject();
        $loadedSubscriber = $this->sr_getSubscriber();

        $currentUrl = $this->link($object->slug . "/" . _x('subscribe', 'url slug', 'reservations'), true);
        $user       = Models\User::current();

        $vars->user            = $user;
        $vars->lostPasswordUrl = wp_lostpassword_url($currentUrl);

        $oldValues = $this->oldValues;
        $val       = function ($key) use ($oldValues) {
            return $oldValues[$key] ?? "";
        };

        if ($user) {
            $vars->logoutUrl = wp_logout_url($currentUrl);
            $subscribers     = $user->subscribers()->get();

            $fullName       = trim($user->firstName . " " . $user->lastName);
            $vars->fullName = $fullName ? $fullName . " (" . $user->email . ")" : $user->email . " (" . $user->displayName . ")";

            $vars->subscriberSelect = Utils\Html::getSelect(Utils\Arrays::makePairs($subscribers, "id", "fullName"), $val("load_subscriber"));
            $vars->subscriberLoaded = (bool) $loadedSubscriber;
        }

        if ($this->sr_errors) {
            $vars->userErrors = $this->sr_errors;
        } else {
            $vars->userErrors = [];
        }

        return $vars;
    }

    protected function sr_sendNewAccountEmail($subscriber, $user, $password)
    {
        $templateId = Reservations::instance()->getOption("new_account_template", null);

        if (!$templateId) {
            return;
        }

        $messageTemplateModel = Local\MessageTemplate::find($templateId);

        if (!$messageTemplateModel || $messageTemplateModel["body"] === "") {
            return;
        }

        $messageTemplate = Mail\MessageTemplate::fromModel($messageTemplateModel);

        $variables = $subscriber->getEmailVariables();

        $variables["username"] = $user->user_login;
        $variables["password"] = $password;

        $message = $messageTemplate->createMessage($variables);

        $message->texturizeBody();
        $message->addTo($subscriber->contactEmail);

        return Reservations::instance()->mailer->send($message);
    }

    protected function sr_createAccount($subscriber)
    {
        $username = $subscriber->generateUsername();
        $password = wp_generate_password(12, false);

        $userId = wp_insert_user([
            "user_login" => $username,
            "user_pass"  => $password,
            "user_email" => $subscriber->contact_email,
            "first_name" => $subscriber->rep_first_name,
            "last_name"  => $subscriber->rep_last_name,
        ]);

        if (is_wp_error($userId)) {
            return null;
        }

        $user = Models\User::find($userId);

        $this->sr_sendNewAccountEmail($subscriber, $user, $password);

        return $user;
    }

    protected function saveSubscriberToAccount($subscriber)
    {
        if (!$this->sr_isEnabled()) {
            return;
        }

        if (!isset($_POST['save_details']) || !$_POST['save_details'] === "1") {
            return;
        }

        $user = Models\User::current();

        if (!$user) {
            $user = $this->sr_createAccount($subscriber);
        }

        if (!$user) {
            return;
        }

        $loadedSubscriber = $this->sr_getSubscriber();

        if ($loadedSubscriber) {
            if ($loadedSubscriber->subscriptions()->count()) {
                // clone subscriber if it is attached to a subscription
                $tmp = $loadedSubscriber->replicate();
                $tmp->setRelations([]);

                $loadedSubscriber->user_id = null;
                $loadedSubscriber->save();

                $loadedSubscriber = $tmp;
            }

            foreach ($subscriber->getFillable() as $key) {
                $val = $subscriber->{$key} ?? null;

                if (!is_null($val)) {
                    $loadedSubscriber->{$key} = $val;
                }
            }

            $loadedSubscriber->save();
        } else {
            $newSubscriber = $subscriber->replicate();
            $newSubscriber->setRelations([]);

            $newSubscriber->user_id = $user->id;
            $newSubscriber->save();
        }

        // if ($loadedSubscriber) {
        //     $loadedSubscriber->user_id = null;
        //     $loadedSubscriber->save();
        // }

        // $subscriber->user_id = $user->id;
        // $subscriber->save();
    }
}
