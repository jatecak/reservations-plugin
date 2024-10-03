<?php

namespace Reservations\Pages\Events;

use Reservations;
use Reservations\Models;
use Reservations\Utils;

trait EventsBase
{
    protected $eventType;

    protected $subscription;
    protected $event;

    public function setEventType($eventType)
    {
        $this->eventType = $eventType;
        $this->parentId  = $this->getParentPageId();
    }

    public function getParentPage()
    {
        if ($this->plugin->isFeatureEnabled("unified_events")) {
            return $this->getRouter()->eventsPage;
        }

        return $this->getRouter()->eventTypePages[$this->eventType["id"]];
    }

    public function getParentPageId()
    {
        return $this->getParentPage()->ID;
    }

    private function eventsAssets()
    {

    }

    private function eventsPrepare()
    {

    }

    private function terminate()
    {
        $this->redirectRelative();
    }

    private function redirectRelative($url = "")
    {
        $this->redirect($this->permalink . $url);
    }

    private function loadEvent($checkEventType = true)
    {
        if (!$this->subscription) {
            $this->terminate();
        }

        $this->event = $this->subscription->event()->first();

        if (!$this->event) {
            $this->terminate();
        }

        if (!$this->plugin->isFeatureEnabled("unified_events") && $checkEventType && $this->event->eventType !== $this->eventType["id"]) {
            $this->terminate();
        }
    }
}
