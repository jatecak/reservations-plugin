<?php

namespace Reservations\Models\Wordpress\Term;

use Sofa\Eloquence;
use \Illuminate\Database\Eloquent\Model;
use \Reservations\Models\Wordpress;

class Taxonomy extends Model
{
    use Eloquence\Eloquence, Eloquence\Mappable;

    protected $table      = 'term_taxonomy';
    protected $primaryKey = 'term_taxonomy_id';
    public $timestamps    = false;

    protected $fillable = [
        'taxonomy', 'description', 'parent', 'count',
    ];

    /* Relationships */

    public function term()
    {
        return $this->belongsTo(Wordpress\Term::class);
    }

    public function posts()
    {
        return $this->belongsToMany(Wordpress\Post::class, 'term_relationships', 'term_taxonomy_id', 'object_id')->withPivot('term_order');
    }

    /* Scopes */

    /* Attributes */
}
