<?php

namespace Reservations\Models\Wordpress;

use \Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence;

class Comment extends Model
{
    use Eloquence\Eloquence, Eloquence\Mappable, Utils\Metable;

    protected $table      = 'comments';
    protected $primaryKey = 'comment_ID';
    public $timestamps    = false;

    protected $fillable = [];

    public function meta()
    {
        return $this->hasMany(Comment\Meta::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'comment_post_ID');
    }
}
