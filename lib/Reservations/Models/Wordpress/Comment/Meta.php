<?php

namespace Reservations\Models\Wordpress\Comment;

use Reservations\Models\Wordpress;

class Meta extends Wordpress\Base\Meta
{
    protected $table      = 'commentmeta';
    protected $primaryKey = 'meta_id';

    public function comment()
    {
        return $this->belongsTo(Wordpress\Comment::class);
    }
}
