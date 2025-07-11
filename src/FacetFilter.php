<?php

namespace Mgussekloo\FacetFilter;

use DB;
use Log;

use Illuminate\Support\Collection;
use Mgussekloo\FacetFilter\Facades\FacetCache;

class FacetFilter
{
    public static $facets = [];
    public static $lastQueries = [];

    /**
     * Get the facets for a subjecttype, optionally setting the filter
     */
    public function getFacets(string $subjectType, $filter = null): Collection
    {
        if (! isset(self::$facets[$subjectType])) {
            self::$facets[$subjectType] = $subjectType::makeFacets();
        }

        $facets = self::$facets[$subjectType];

        if (is_array($filter)) {
            $filter = $subjectType::getFilterFromArr($filter);
            $facets->map->setFilter($filter);
        }

        return self::$facets[$subjectType];
    }

     /**
     * Get a filter array that has the facet parameters for a certain subject (model) as keys
     * merged with $arr.
     */
    public function getFilterFromArr($subjectType, $arr = []): array
    {
        $emptyFilter = self::getEmptyFilter($subjectType);

        $arr = array_map(function ($item): array {
            if (! is_array($item)) {
                return [$item];
            }

            return $item;
        }, (array) $arr);

        $filter = array_replace($emptyFilter, array_intersect_key(array_filter($arr), $emptyFilter));

        return $filter;
    }

 	/**
     * Empty filter
     */
    public function getEmptyFilter(string $subjectType): array
    {
    	return self::getFacets($subjectType)->mapWithKeys(fn ($facet) => [$facet->getParamName() => []])->toArray();
    }

    /**
     * Get all the rows for a number of facets. This is an expensive operation,
     * because we may load 1000's of rows for each facet.
     */
    public function loadRows($facets, $rowQuery=null)
    {
        if (method_exists($facets, 'getSlug')) {
        	$facets = collect([$facets]);
        }

    	if (is_null($rowQuery)) {
    		$rowQuery = self::getRowQuery($facets);
    	}

    	$rows = $rowQuery->get()->groupBy('facet_slug');
		if (count($rows) == 0) {
			Log::warning(sprintf('No facet rows for %s! Did you forget to build an index?', $subjectType));
		}

        foreach ($facets as $facet) {
            $slug = $facet->getSlug();
            if (isset($rows[$slug])) {
                $facet->setRows($rows[$slug]);
            }
        }

        return $rows;
    }

    public function getRowQuery($facets) {
        $facetRowsTable = config('facet-filter.table_names.facetrows');

        if (method_exists($facets, 'getSlug')) {
        	$facets = collect([$facets]);
        }

		$query = DB::table($facetRowsTable)->select('facet_slug', 'subject_id', 'value');
		if ($facets->count() == 1) {
			$query->where('facet_slug', $facets->first()->getSlug());
		} else {
			$query->whereIn('facet_slug', $facets->map->getSlug());
		}

		return $query;
    }

    /**
     * Remember which model id's were in a filtered query for the combination
     * "model class" and "filter". This should avoid running the same query
     * twice when calculating the number of results for each facet.
     */
    public function cacheIdsInFilter($cacheSubkey, $filter, $ids = null)
    {
    	asort($filter);
    	ksort($filter);
    	if (is_array($cacheSubkey)) {
    		$cacheSubkey = implode('.', $cacheSubkey);
    	}
        $cacheSubkey = implode('.', [$cacheSubkey, json_encode($filter, JSON_THROW_ON_ERROR)]);
        return FacetCache::cache('idsInFilteredQuery', $cacheSubkey, $ids);
    }

}