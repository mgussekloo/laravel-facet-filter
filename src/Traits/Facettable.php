<?php

namespace Mgussekloo\FacetFilter\Traits;

use Illuminate\Support\Collection;
use Mgussekloo\FacetFilter\Builders\FacetQueryBuilder;
use Mgussekloo\FacetFilter\Facades\FacetFilter;
use Mgussekloo\FacetFilter\Models\Facet;
use Mgussekloo\FacetFilter\Models\FacetRow;

trait Facettable
{
    abstract public static function defineFacets();

    // make the facet models, based on the facet definition.
    // you can overwrite this function
    public static function makeFacets()
    {
        $definitions = self::defineFacets();
        if (is_array($definitions)) {
            $definitions = collect($definitions);
        }
        $definitions = $definitions->map(function ($definition) {
            if (! isset($definition['fieldname'])) {
                throw new \Exception('Missing key `fieldname` in facet definition '.json_encode($definition).'!');
            }

            return array_merge([
                'title' => $definition['fieldname'],
                'subject_type' => self::class,
                'facet_class' => Facet::class,
            ], $definition);
        })->filter();

        // Instantiate models
        $facets = [];
        foreach ($definitions as $definition) {
            $facets[] = new $definition['facet_class']($definition);
        }

        return collect($facets);
    }

    public function facetrows()
    {
        return $this->hasMany(FacetRow::class, 'subject_id');
    }

    public static function getFacets($filter = null, $load = true): Collection
    {
        return FacetFilter::getFacets(self::class, $filter, $load);
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
