<?php

namespace Mgussekloo\FacetFilter\Traits;

use Mgussekloo\FacetFilter\Models\FacetRow;

use FacetFilter;

trait Facettable {

    public function facetrows()
    {
        return $this->hasMany(FacetRow::class, 'subject_id');
    }

    public function scopeFacetsMatchFilter($query, $filter)
    {
        $query->whereHas('facetrows', function($query) use ($filter) {
            // $queryOr = false;
            foreach (FacetFilter::getFacets(self::class) as $facet) {
                $key = $facet->getParamName();
                $filterArr = array_filter($filter[$key]);
                if (!empty($filterArr)) {
                    $values = (array)$filter[$key];

                    $query->where(function($query) use ($facet, $values) {
                        $query->where('facet_id', $facet->id)->whereIn('value', $values);
                    });
                }
            }
        });
    }

}