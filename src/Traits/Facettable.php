<?php

namespace Mgussekloo\FacetFilter\Traits;

use Mgussekloo\FacetFilter\Models\Facet;
use Mgussekloo\FacetFilter\Models\FacetRow;

use FacetFilter;

trait Facettable {

    public static $facets = null;

    public function facetrows()
    {
        return $this->hasMany(FacetRow::class, 'subject_id');
    }

    public function scopeFacetsMatchFilter($query, $filter)
    {
        $queryCopy = clone $query;

        $facets = self::getFacets();
        FacetFilter::constrainQueryWithFacetFilter($query, $facets, $filter);

        $facets->map->setCurrentQuery($queryCopy, $facets, $filter);

        return $query;
    }

    public static function getFacets()
    {
        if (is_null(self::$facets)) {
            self::$facets = FacetFilter::getFacets(self::class);
        }

        return self::$facets;
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