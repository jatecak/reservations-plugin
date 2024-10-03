<?php

namespace Reservations\Pages\Events;

use Carbon\Carbon;
use Reservations;
use Reservations\Models;
use Reservations\Pages;
use Reservations\Utils;

class ThankYou extends Pages\ThankYou
{
    use EventsBase;

    public function prepare()
    {
        $this->eventsPrepare();

        parent::prepare();
    }
}
