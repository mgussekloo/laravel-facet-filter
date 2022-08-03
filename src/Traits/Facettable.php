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

    public static function getFacets()
    {
        return FacetFilter::getFacets(self::class);
    }

    public static function getFilterFromParam($paramName = 'filter')
    {
        return FacetFilter::getFilterFromParam(self::class, $paramName);
    }

    public static function getFilterFromArr($arr)
    {
        return FacetFilter::getFilterFromArr(self::class, $arr);
    }

    public static function getEmptyFilter()
    {
        return FacetFilter::getEmptyFilter(self::class);
    }

    public static function defineFacet($title, $fieldName, $facetType = 'simple')
    {
        Facet::create([
            'subject_type' => self::class,
            'facet_type' => $facetType,
            'title' => $title,
            'fieldname' => $fieldName
        ]);
    }

}