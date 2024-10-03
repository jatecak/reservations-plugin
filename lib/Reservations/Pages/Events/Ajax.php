<?php

namespace Reservations\Pages\Events;

use Reservations\Pages;

class Ajax extends Pages\Ajax
{
    use EventsBase;

    public function prepare()
    {
        $this->eventsPrepare();

        parent::prepare();
    }
}
