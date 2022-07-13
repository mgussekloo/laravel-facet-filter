<?php

namespace Mgussekloo\FacetFilter\Collections;

use Illuminate\Database\Eloquent\Collection;

class FacetCollection extends Collection
{
    public function limitToSubjectIds($subjectIds)
    {
        return $this->map->limitToSubjectIds($subjectIds);
    }
}