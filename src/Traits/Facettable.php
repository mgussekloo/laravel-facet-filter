<?php

namespace Mgussekloo\FacetFilter\Traits;

use Mgussekloo\FacetFilter\Models\Facet;
use Mgussekloo\FacetFilter\Models\FacetRow;

use Mgussekloo\FacetFilter\Facades\FacetFilter;

trait Facettable {

    public function facetrows()
    {
        return $this->hasMany(FacetRow::class, 'subject_id');
    }

    public function scopeFacetsMatchFilter($query, $filter)
    {
        self::getFacets()->map->setLastQuery($query, $filter);

        FacetFilter::resetIdsInFilteredQuery();
        FacetFilter::constrainQueryWithFilter(self::class, $query, $filter);

        return $query;
    }

    public static function getFacets($filter = null)
    {
        return FacetFilter::getFacets(self::class, $filter);
    }

    public static function getFilterFromParam($paramName = 'filter')
    {
        $arr = (array)request()->query($paramName);
        return FacetFilter::getFilterFromArr(self::class, $arr);
    }

    public static function getFilterFromArr($arr = [])
    {
        return FacetFilter::getFilterFromArr(self::class, $arr);
    }

    public static function getEmptyFilter()
    {
        return FacetFilter::getEmptyFilter(self::class);
    }

    abstract public function defineFacets();

}