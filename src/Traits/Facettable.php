<?php

namespace Mgussekloo\FacetFilter\Traits;

use Mgussekloo\FacetFilter\Facades\FacetFilter;
use Mgussekloo\FacetFilter\Facades\FacetCache;
use Mgussekloo\FacetFilter\Indexer;

use Mgussekloo\FacetFilter\Builders\FacetQueryBuilder;
// use Mgussekloo\FacetFilter\Builders\BaseFacetQueryBuilder;

use Mgussekloo\FacetFilter\Collections\FacettableCollection;
use Illuminate\Support\Collection;


trait Facettable
{
    abstract public static function facetDefinitions();

    public static function indexer() {
    	return new Indexer();
    }

	public function newCollection(array $models = [])
    {
        return new FacettableCollection($models);
    }

    /**
     * Make the facets
     */
    public static function makeFacets($subjectType=null,$definitions=null): Collection
    {
    	if (is_null($subjectType)) {
    		$subjectType = self::class;
    	}

    	if (is_null($definitions)) {
    		$definitions = self::facetDefinitions();
    	}

        if (is_array($definitions)) {
            $definitions = collect($definitions);
        }

        $facetClass = config('facet-filter.classes.facet');

        $definitions = $definitions->map(function ($definition) use ($subjectType, $facetClass) {
            return array_merge([
                'subject_type' => $subjectType,
                'facet_class' => $facetClass,
            ], $definition);
        })->filter();

        // Instantiate models
        $facets = [];
        foreach ($definitions as $definition) {
            $facets[] = new $definition['facet_class']($definition);
        }

        return collect($facets);
    }

    // get the facet models
    public static function getFacets($filter = null): Collection
    {
        return FacetFilter::getFacets(self::class, $filter);
    }

    public function facetrows()
    {
        $facetRowClass = config('facet-filter.classes.facetrow');
        return $this->hasMany($facetRowClass, 'subject_id');
    }

    // protected function newBaseQueryBuilder()
    // {
    // 	$connection = $this->getConnection();
    //     return new BaseFacetQueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
    // }

    public function newEloquentBuilder($query): FacetQueryBuilder
    {
        return new FacetQueryBuilder($query);
    }

    public static function getFilterFromArr($arr = [])
    {
        return FacetFilter::getFilterFromArr(self::class, $arr);
    }

	public static function filterCollection($models, $filter, $indexer=null)
    {
    	return FacetFilter::filterCollection($models, $filter, $indexer);
    }

    public static function forgetCache($cachePostfix = '')
    {
		FacetCache::forgetCache(null, [self::class, $cachePostfix]);
    }

}
