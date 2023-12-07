<?php

namespace Mgussekloo\FacetFilter\Traits;

use Illuminate\Support\Collection;
use Mgussekloo\FacetFilter\Builders\FacetQueryBuilder;
use Mgussekloo\FacetFilter\Facades\FacetFilter;
use Mgussekloo\FacetFilter\Models\FacetRow;

trait Facettable
{
    abstract public static function facetDefinitions();

    // make the facet models, based on the facet definition.
    // you can overwrite this function
    public static function makeFacets(): Collection
    {
        return FacetFilter::makeFacetsWithDefinitions(self::class, self::facetDefinitions());
    }

    // get the facet models
    public static function getFacets($filter = null, $load = true): Collection
    {
        return FacetFilter::getFacets(self::class, $filter, $load);
    }

    public function facetrows()
    {
        return $this->hasMany(FacetRow::class, 'subject_id');
    }

    public function newEloquentBuilder($query): FacetQueryBuilder
    {
        return new FacetQueryBuilder($query);
    }

    public static function getFilterFromArr($arr = [])
    {
        return FacetFilter::getFilterFromArr(self::class, $arr);
    }
}
