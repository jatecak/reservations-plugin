<?php

namespace Reservations\Models\Wordpress\Post;

use Reservations\Models\Wordpress;

class Meta extends Wordpress\Base\Meta
{
    protected $table      = 'postmeta';
    protected $primaryKey = 'meta_id';

    public function post()
    {
        return $this->belongsTo(Wordpress\Post::class);
    }
}
