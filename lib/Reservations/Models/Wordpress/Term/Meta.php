<?php

namespace Reservations\Models\Wordpress\Term;

use Reservations\Models\Wordpress;

class Meta extends Wordpress\Base\Meta
{
    protected $table      = 'termmeta';
    protected $primaryKey = 'meta_id';

    public function term()
    {
        return $this->belongsTo(Wordpress\Term::class);
    }
}
