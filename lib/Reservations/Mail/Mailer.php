<?php

namespace Reservations\Mail;

use Reservations;
use Reservations\Base\Service;
use Reservations\Utils;

class Mailer extends Service
{
    protected $message;
    protected $sending;

    public function send($message)
    {
        $this->message = $message;

        $this->sending = true;

        $ok = true;
        foreach ($message->getRecipients() as $to) {
            $ok = wp_mail($to, $message->subject, $message->body, [
                "Content-Type: text/html; charset=utf-8",
            ]) && $ok;
        }

        $this->sending = false;

        return $ok;
    }

    /** @action(phpmailer_init) */
    public function setupEmailAttachments($phpmailer)
    {
        if (!$this->sending) {
            return;
        }

        foreach ($this->message->attachments as $attachment) {
            switch ($attachment["type"]) {
                case "file":
                    $phpmailer->addAttachment($attachment["path"], $attachment["filename"]);
                    break;
                case "url":
                    $phpmailer->addStringAttachment(file_get_contents($attachment["url"], null, $attachment["context"]), $attachment["filename"]);
                    break;
                case "string":
                    $phpmailer->addStringAttachment($attachment["data"], $attachment["filename"]);
                    break;
            }
        }
    }

    /** @filter(wp_mail_from) */
    public function setEmailFrom($fromEmail)
    {
        if (!$this->sending) {
            return $fromEmail;
        }

        $customFromEmail = Reservations::instance()->getOption("email_from");

        return $customFromEmail ?: $fromEmail;
    }

    /** @filter(wp_mail_from_name) */
    public function setEmailFromName($fromName)
    {
        if (!$this->sending) {
            return $fromName;
        }

        $customFromName = Reservations::instance()->getOption("email_from_name");

        return $customFromName ?: $fromName;
    }
}
