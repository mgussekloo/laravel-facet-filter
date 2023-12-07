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
                $rows = DB::table('facetrows')->select('facet_slug', 'subject_id', 'value')->get()->groupBy('facet_slug');

                foreach ($facets as $facet) {
                    $slug = $facet->getSlug();
                    if (isset($rows[$slug])) {
                        $facet->setRows($rows[$slug]);
                    }
                }
            }

            self::$facets[$subjectType] = $facets;
        }

        if (is_array($filter)) {
            $filter = $subjectType::getFilterFromArr($filter);
            self::$facets[$subjectType]->map->setFilter($filter);
        }

        return self::$facets[$subjectType];
    }

    /**
     * Get the facets for a subjecttype, optionally setting the filter
     * and preloading the available options.
     */
    public function makeFacetsWithDefinitions(string $subjectType, $definitions): Collection
    {
        if (is_array($definitions)) {
            $definitions = collect($definitions);
        }

        $definitions = $definitions->map(function ($definition) use ($subjectType) {
            if (! isset($definition['fieldname'])) {
                throw new \Exception('Missing key `fieldname` in facet definition '.json_encode($definition).'!');
            }

            return array_merge([
                'subject_type' => $subjectType,
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

    /**
     * Remember the last query for a model class, without eager loaded relations.
     * We use this query as basis to run queries for each facet, calculating the number
     * of results the facet options would have.
     */
    public function setLastQuery(string $subjectType, $query): void
    {
        $newQuery = clone $query;
        $newQuery->withOnly([]);
        $newQuery->facetMainQuery = false;
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
        $emptyFilter = $subjectType::getFacets()->mapWithKeys(fn ($facet) => [$facet->getParamName() => []])->toArray();

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
        return $subjectType::getFilterFromArr($subjectType, []);
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

        // $filter = $subjectType::getFilterFromArr($subjectType, $filter);

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
        return implode('_', [$subjectType, json_encode($filter, JSON_THROW_ON_ERROR)]);
    }
}
