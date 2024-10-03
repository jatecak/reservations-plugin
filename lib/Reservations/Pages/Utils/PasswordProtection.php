<?php

namespace Reservations\Pages\Utils;

use Reservations;

trait PasswordProtection
{
    protected $pp_error;

    protected function pp_init()
    {
        if (!session_id()) {
            session_start();
        }
    }

    protected function pp_getObject()
    {
        return $this->tgroup ?? $this->event ?? null;
    }

    protected function isUnlocked()
    {
        $this->pp_init();

        $object = $this->pp_getObject();

        if (!$object || empty($object->password)) {
            return true;
        }

        if (!isset($_SESSION['pp_unlocked'])) {
            return false;
        }

        $unlockedIds = (array) $_SESSION['pp_unlocked'];

        return in_array($object->id, $unlockedIds);
    }

    protected function handleUnlockForm()
    {
        $this->pp_init();

        $object = $this->pp_getObject();

        if (!isset($_POST['password']) || !$object) {
            return;
        }

        if ($_POST['password'] === $object->password) {
            if (!isset($_SESSION['pp_unlocked']) || !is_array($_SESSION['pp_unlocked'])) {
                $_SESSION['pp_unlocked'] = [];
            }

            $_SESSION['pp_unlocked'][] = $object->id;
        } else {
            $this->pp_error = __('Incorrect password', 'reservations');
        }
    }

    protected function renderUnlockForm()
    {
        $this->pp_init();

        if ($this->isUnlocked()) {
            return false;
        }

        $isTrainingGroup = (bool) $this->tgroup;
        $tgroup          = $this->tgroup;
        $isEvent         = (bool) $this->event;
        $event           = $this->event;
        $error           = $this->pp_error;

        include Reservations::ABSPATH . "/public/password-form.php";

        return true;
    }
}
