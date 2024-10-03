<?php

namespace Reservations\Pages;

use Reservations;
use Reservations\Base;
use Reservations\Pages\Utils as PagesUtils;

class Ajax extends Base\Page
{
    use PagesUtils\ObjectLoader;

    public function prepare()
    {
        $this->action = isset($_GET['form']) ? "form" : (isset($_GET['gopay']) ? "gopay" : null);

        $this->handleAction();
    }

    protected function handleAction()
    {
        switch ($this->action) {
            case "form":
                $this->actionForm();
                break;

            case "gopay":
                $this->actionGopay();
                break;

            default:
                $this->redirect($this->permalink);
        }

        exit;
    }

    protected function loadObjects()
    {
        if (!isset($_GET['id'])) {
            $this->redirect($this->permalink);
        }

        $this->loadObjectsByTransactionId((int) $_GET['id']);

        if (!$this->subscription) {
            $this->redirect($this->permalink);
        }
    }

    protected function actionForm()
    {
        $this->loadObjects();

        if (!$this->subscription->applicationFormFilename) {
            $this->redirect($this->permalink);
        }

        $context = $this->subscription->getApplicationFormStreamContext();

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . _x('application_form.pdf', 'downloaded file name', 'reservations') . '"');

        readfile($this->plugin->getOption("form_filler_url"), false, $context);
    }

    protected function actionGopay()
    {
        $this->loadObjects();

        $status = $this->transaction->updatePaidStatus();

        if ($status->error) {
            $this->sendResponse("Failed to get payment info.", 500);
        }
    }
}
