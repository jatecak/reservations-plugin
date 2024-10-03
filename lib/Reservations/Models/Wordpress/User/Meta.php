<?php

namespace Reservations\Models\Wordpress\User;

use Reservations\Models\Wordpress;

class Meta extends Wordpress\Base\Meta
{
    protected $table      = 'usermeta';
    protected $primaryKey = 'umeta_id';

    public function user()
    {
        return $this->belongsTo(Wordpress\User::class);
    }
}
