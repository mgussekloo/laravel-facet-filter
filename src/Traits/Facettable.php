<?php

namespace Mgussekloo\FacetFilter\Traits;

use Mgussekloo\FacetFilter\Models\Facet;
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
            foreach (self::getFacets() as $facet) {
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

    public static function getFilterFromRequest($paramName = 'filter')
    {
        return FacetFilter::getFilterFromRequest(self::class, $paramName);
    }

    public static function getFacets()
    {
        return FacetFilter::getFacets(self::class);
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