<?php

namespace Mgussekloo\FacetFilter\Traits;

use Mgussekloo\FacetFilter\Models\FacetRow;

trait hasFacetRows {

    public function facetrows()
    {
        return $this->hasMany(FacetRow::class, 'subject_id');
    }

}