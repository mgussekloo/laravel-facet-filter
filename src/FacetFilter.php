<?php

namespace Mgussekloo\FacetFilter;

use Mgussekloo\FacetFilter\Models\Facet;

use Illuminate\Support\Collection;

use DB;

class FacetFilter
{
    public static $facets = [];

    public static $lastQueries = [];

    public static $idsInFilteredQuery = [];

    /**
     * Get the facets for a subjecttype, optionally setting the filter
     * and preloading the available options in an efficient query
     *
     * @return Collection
     */
    public function getFacets($subjectType, $filter = null, $load = true): Collection
    {
        if (! isset(self::$facets[$subjectType])) {

        	// Get the definition from the model's static method
            $definitions = collect($subjectType::defineFacets())
            ->map(fn ($arr) => [
                'title' => $arr[0],
                'fieldname' => $arr[1],
                'subject_type' => $subjectType,
            ]);

            // Instantiate models
            $facets = $definitions->mapInto(Facet::class);

            // Should we preload the options?
            if ($load) {
                $rows = DB::table('facetrows')
                ->select('facet_slug', 'subject_id', 'value')
                ->get()
                ->groupBy('facet_slug');

                foreach ($facets as $facet) {
                    $slug = $facet->getSlug();
                    if (isset($rows[$slug])) {
                        $facet->rows = $rows[$slug];
                    }
                }
            }

            self::$facets[$subjectType] = $facets;
        }

        // should we set the filter on all the facets?
        if (! is_null($filter)) {
            self::$facets[$subjectType]->map->setFilter($filter);
        }

        return self::$facets[$subjectType];
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
     * Get a filter array that has the facet parameters for a certain subject (model) as keys
     * merged with $arr.
     */
    public function getFilterFromArr($subjectType, $arr = []): array
    {
        $emptyFilter = $this->getFacets($subjectType)->mapWithKeys(fn ($facet) => [$facet->getParamName() => []])->toArray();

        $arr = array_map(function ($item): array {
            if (! is_array($item)) {
                return [$item];
            }

            return $item;
        }, $arr);

        return array_replace($emptyFilter, array_intersect_key(array_filter($arr), $emptyFilter));
    }

    /**
     * Same as above, but the values are empty.
     */
    public function getEmptyFilter($subjectType): array
    {
        return $this->getFilterFromArr($subjectType, []);
    }

    /**
     * Remember which model id's were in a filtered query for the combination
     * "model class" and "filter". This avoid running the same query
     * twice when calculating the number of results for each facet.
     */
    public function cacheIdsInFilteredQuery($subjectType, $filter, $ids = null)
    {
        if (! isset(self::$idsInFilteredQuery[$subjectType])) {
            self::$idsInFilteredQuery[$subjectType] = [];
        }

        $cacheKey = (new FacetFilter())->getCacheKey($subjectType, $filter);
        if (! isset(self::$idsInFilteredQuery[$subjectType][$cacheKey]) && ! is_null($ids)) {
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
    public function resetIdsInFilteredQuery($subjectType): void
    {
        if (isset(self::$idsInFilteredQuery[$subjectType])) {
            unset(self::$idsInFilteredQuery[$subjectType]);
        }
    }

    /**
     * Build a cache key for the combination model class + filter
     */
    public function getCacheKey($subjectType, $filter): string
    {
        return implode('_', [$subjectType, md5(json_encode($filter, JSON_THROW_ON_ERROR))]);
    }
}
