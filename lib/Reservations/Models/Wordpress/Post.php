<?php

namespace Reservations\Models\Wordpress;

use Sofa\Eloquence;
use \Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Eloquence\Eloquence, Eloquence\Mappable, Utils\Metable;

    protected $table      = 'posts';
    protected $primaryKey = 'ID';

    const CREATED_AT = 'post_date';
    const UPDATED_AT = 'post_modified';

    protected $fillable = [];
    protected $maps     = [
        "title"   => "post_title",
        "id"      => "ID",
        "post_id" => "ID",
        "slug"    => "post_name",
        "status"  => "post_status",
    ];

    public function meta()
    {
        return $this->hasMany(Post\Meta::class, 'post_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'post_author');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'comment_post_id');
    }

    public function termTaxonomies()
    {
        return $this->belongsToMany(Term\Taxonomy::class, 'term_relationships', 'object_id', 'term_taxonomy_id')->withPivot('term_order');
    }

    public function scopeStatus($builder, $status)
    {
        return $builder->where("post_status", $status);
    }
}
