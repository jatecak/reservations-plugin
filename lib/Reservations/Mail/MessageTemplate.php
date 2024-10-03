<?php

namespace Reservations\Mail;

use Reservations\Utils;

class MessageTemplate
{
    public $bodyTemplate;
    public $subjectTemplate;

    public $bodyTemplateFile;
    public $subjectTemplateFile;

    public $attachments = [];

    public $model;

    public static function fromModel($model)
    {
        $template = new MessageTemplate();

        $template->bodyTemplate    = $model["body"];
        $template->subjectTemplate = $model["subject"];

        $attachments = Utils::resolveAttachmentIds($model["attachments"], false);

        foreach ($attachments as $id => $path) {
            $template->attachments[] = [
                "type"     => "file",
                "path"     => $path,
                "filename" => basename($path),
            ];
        }

        $template->model = $model;

        return $template;
    }

    protected function renderTemplate($template, $templateFile, $variables)
    {
        if (!is_null($template)) {
            $trans = [];
            foreach ($variables as $key => $value) {
                $trans["{{" . $key . "}}"]        = $value;
                $trans["http://{{" . $key . "}}"] = $value; // TinyMCE fix
            }

            return strtr($template, $trans);
        } else if (!is_null($templateFile)) {
            extract($variables);

            ob_start();
            include $templateFile;
            return ob_get_clean();
        } else {
            return "";
        }
    }

    protected function renderBody($variables)
    {
        return $this->renderTemplate($this->bodyTemplate, $this->bodyTemplateFile, $variables);
    }

    protected function renderSubject($variables)
    {
        return $this->renderTemplate($this->subjectTemplate, $this->subjectTemplateFile, $variables);
    }

    public function createMessage($variables = [])
    {
        $message = new Message();

        $message->body    = $this->renderBody($variables);
        $message->subject = $this->renderSubject($variables);

        foreach ($this->attachments as $attachment) {
            $message->attachments[] = $attachment;
        }

        return $message;
    }
}
