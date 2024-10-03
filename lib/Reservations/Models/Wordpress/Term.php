<?php

namespace Reservations\Models\Wordpress;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations;
use Sofa\Eloquence;

class Term extends Model
{
    use Eloquence\Eloquence, Eloquence\Mappable, Utils\Metable;

    protected $table      = 'terms';
    protected $primaryKey = 'term_id';
    public $timestamps    = false;

    protected $fillable = [
        'name', 'slug', 'term_group',
    ];

    protected $maps = [
        "id" => "term_id",
    ];

    /* Relationships */

    public function termTaxonomy()
    {
        return $this->hasOne(Term\Taxonomy::class, "term_id");
    }

    public function meta()
    {
        return $this->hasMany(Term\Meta::class, "term_id");
    }

    public function posts()
    {
        return $this->termTaxonomy->posts();
    }

    /* Scopes */

    public function scopeOrderByName($builder)
    {
        $builder->orderBy("name");
    }

    public function scopeUsed($builder, $postType = null)
    {
        return $builder->with("termTaxonomy")->whereHas("termTaxonomy", function ($builder) use ($postType) {
            $builder->whereHas("posts", function ($builder) use ($postType) {
                if (!is_null($postType)) {
                    $builder->where("post_type", $postType);
                }

                $builder->where("post_status", "publish");
            });
        });
    }
}
