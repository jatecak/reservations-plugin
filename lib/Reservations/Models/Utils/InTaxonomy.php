<?php

namespace Reservations\Models\Utils;

trait InTaxonomy
{
    public function scopeInTaxonomy($builder, $taxonomy, $termId)
    {
        return $builder->whereHas("termTaxonomies", function ($builder) use ($taxonomy, $termId) {
            $builder->where("taxonomy", $taxonomy);

            if (is_array($termId)) {
                $builder->whereIn("term_id", $termId);
            } else {
                $builder->where("term_id", $termId);
            }
        });
    }
}
