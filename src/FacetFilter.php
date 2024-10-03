<?php

namespace Mgussekloo\FacetFilter;

use DB;
use Illuminate\Support\Collection;
use Mgussekloo\FacetFilter\Models\Facet;

class FacetFilter
{
    public static $facets = [];

    public static $lastQueries = [];

    public static $idsInFilteredQuery = [];

    /**
     * Get the facets for a subjecttype, optionally setting the filter
     * and preloading the available options.
     */
    public function getFacets(string $subjectType, $filter = null, $load = true): Collection
    {
        if (! isset(self::$facets[$subjectType])) {
            $facets = $subjectType::makeFacets();

            // Should we preload the options?
            if ($load) {
                self::loadOptions($facets);
            }

            self::$facets[$subjectType] = $facets;
        }

        if (is_array($filter)) {
            $filter = $subjectType::getFilterFromArr($filter);
            self::$facets[$subjectType]->map->setFilter($filter);
        }

        return self::$facets[$subjectType];
    }

    public function loadOptions($facets)
    {
		$rows = DB::table('facetrows')
        ->whereIn('facet_slug', $facets->map->getSlug())
		->select('facet_slug', 'subject_id', 'value')
		->get()->groupBy('facet_slug');

        foreach ($facets as $facet) {
            $slug = $facet->getSlug();
            if (isset($rows[$slug])) {
                $facet->setRows($rows[$slug]);
            }
        }
    }

    /**
     * Remember the last query for a model class, without eager loaded relations.
     * We use this query as basis to run queries for each facet, calculating the number
     * of results the facet options would have.
     */
    public function setLastQuery(string $subjectType, $query): void
    {
        $newQuery = clone $query;
        $newQuery->withOnly([]);

        $query = $newQuery->getQuery();
        if ($query->limit > 0) {
        	$newQuery->limit(null);
        	$query->offset = null;

        }

        // dd($query);
		// $newQuery->skip(false);
        self::$lastQueries[$subjectType] = $newQuery;
        self::resetIdsInFilteredQuery($subjectType);
    }

    /**
     * Retrieve the last query for a model class or return false.
     */
    public function getLastQuery(string $subjectType)
    {
        if (isset(self::$lastQueries[$subjectType])) {
            return clone self::$lastQueries[$subjectType];
        }

        return false;
    }

	/**
     * Retrieve the ids in the last query for a model class, without filter set for a single facet
     */
    public function getIdsInLastQueryWithoutFacet($facet)
    {
    	$facetName = $facet->getParamName();
		$filterWithoutFacet = array_merge($facet->filter, [$facetName => []]);

    	$ids = self::cacheIdsInFilteredQuery($facet->subject_type, $filterWithoutFacet);

    	if ($ids === false) {
    		if ($lastQuery = self::getLastQuery($facet->subject_type)) {
    			$ids = $lastQuery->constrainQueryWithFilter($filterWithoutFacet, false, true);
    		}
    	}

    	return $ids;
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
     * Same as above, but the values are empty.
     */
    public function getEmptyFilter(string $subjectType): array
    {
    	return $subjectType::getFacets()->mapWithKeys(fn ($facet) => [$facet->getParamName() => []])->toArray();
    }

    /**
     * Remember which model id's were in a filtered query for the combination
     * "model class" and "filter". This should avoid running the same query
     * twice when calculating the number of results for each facet.
     */
    public function cacheIdsInFilteredQuery(string $subjectType, $filter, $ids = null)
    {
        if (! isset(self::$idsInFilteredQuery[$subjectType])) {
            self::$idsInFilteredQuery[$subjectType] = [];
        }

        $cacheKey = self::getCacheKey($subjectType, $filter);

        if (! is_null($ids)) {
            self::$idsInFilteredQuery[$subjectType][$cacheKey] = $ids;
            return $ids;
        }

        if (isset(self::$idsInFilteredQuery[$subjectType][$cacheKey])) {
            return self::$idsInFilteredQuery[$subjectType][$cacheKey];
        }

        return false;
    }

    /**
     * Forget the model id's that were in a filtered query, so we can
     * start fresh.
     */
    public function resetIdsInFilteredQuery(string $subjectType): void
    {
        unset(self::$idsInFilteredQuery[$subjectType]);
    }

    /**
     * Build a cache key for the combination model class + filter
     */
    public static function getCacheKey(string $subjectType, $filter): string
    {
        // return $subjectType . implode('_', array_values(array_filter(array_values($filter))));
        return implode('_', [$subjectType, json_encode($filter, JSON_THROW_ON_ERROR)]);
    }
}
