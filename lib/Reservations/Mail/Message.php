<?php

namespace Reservations\Mail;

use Reservations;

class Message
{
    public $to;
    public $subject;
    public $body;
    public $attachments = [];

    public function addAttachment($path, $filename = null)
    {
        if (is_null($filename)) {
            $filename = basename($path);
        }

        $this->attachments[] = [
            "type"     => "file",
            "path"     => $path,
            "filename" => $filename,
        ];
    }

    public function addStringAttachment($data, $filename)
    {
        $this->attachments[] = [
            "type"     => "string",
            "data"     => $data,
            "filename" => $filename,
        ];
    }

    public function addUrlAttachment($url, $filename = null, $context = null)
    {
        if (is_null($filename)) {
            $filename = basename($url);
        }

        $this->attachments[] = [
            "type"     => "url",
            "url"      => $url,
            "filename" => $filename,
            "context"  => $context,
        ];
    }

    public function getRecipients()
    {
        return is_string($this->to) ? [$this->to] : (is_array($this->to) ? $this->to : []);
    }

    public function addTo($to)
    {
        if (is_string($this->to)) {
            $this->to = [$this->to, $to];
        } else if (is_array($this->to)) {
            $this->to[] = $to;
        } else {
            $this->to = $to;
        }
    }

    public function texturizeBody()
    {
        $body        = wpautop(wptexturize($this->body));
        $htmlSubject = wptexturize($this->subject);

        $style         = file_get_contents(Reservations::ABSPATH . "/public/style-email.css");
        $modeStyleFile = Reservations::ABSPATH . "/public/style-email-" . Reservations::MODE . ".css";
        if (file_exists($modeStyleFile)) {
            $style .= file_get_contents($modeStyleFile);
        }

        $this->body = "<!DOCTYPE html>\n<html>
        <head><meta charset=\"UTF-8\"><title>$htmlSubject</title><style type=\"text/css\">$style</style></head>
        <body>$body</body></html>";
    }
}
