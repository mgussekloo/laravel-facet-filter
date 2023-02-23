<?php

namespace Mgussekloo\FacetFilter\Traits;

use Mgussekloo\FacetFilter\Models\Facet;
use Mgussekloo\FacetFilter\Models\FacetRow;

use Mgussekloo\FacetFilter\Facades\FacetFilter;

use Mgussekloo\FacetFilter\Builders\FacetQueryBuilder;

use Illuminate\Support\Collection;

trait Facettable {

    abstract public static function defineFacets();

    public function facetrows()
    {
        return $this->hasMany(FacetRow::class, 'subject_id');
    }

    public static function getFacets($filter = null, $load = true): Collection
    {
        return FacetFilter::getFacets(self::class, $filter, $load);
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

 	public function newEloquentBuilder($query): FacetQueryBuilder
    {
        return new FacetQueryBuilder($query);
    }

}