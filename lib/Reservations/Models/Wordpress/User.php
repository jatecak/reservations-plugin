<?php

namespace Reservations\Models\Wordpress;

use Sofa\Eloquence;
use \Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use Eloquence\Eloquence, Eloquence\Mappable, Utils\Metable;

    protected $table      = 'users';
    protected $primaryKey = 'ID';
    public $timestamps    = false;

    protected $maps = [
        "id"          => "ID",
        "login"       => "user_login",
        "email"       => "user_email",
        "displayName" => "display_name",
    ];

    protected $fillable = [];

    /* Relationships */

    public function meta()
    {
        return $this->hasMany(User\Meta::class, "user_id");
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'post_author');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /* Attributes */

    public function getFirstNameAttribute()
    {
        return $this->getMeta("first_name", "");
    }

    public function getLastNameAttribute()
    {
        return $this->getMeta("last_name", "");
    }

    /* Methods */

    public function can($capability)
    {
        return user_can($this->id, $capability);
    }
}
