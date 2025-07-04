<?php

namespace Mgussekloo\FacetFilter;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mgussekloo\FacetFilter\Facades\FacetCache;

class FacetFilter
{
	protected static array $facets = [];
	protected static array $lastQueries = [];

	/**
	 * Retrieve facets for a subject type, optionally applying a filter and preloading options.
	 */
	public function getFacets(string $subjectType, $filter = null, bool $load = true): Collection
	{
		if (!isset(self::$facets[$subjectType])) {
			$facets = $subjectType::makeFacets();

			if ($load) {
				$this->loadRows($subjectType, $facets);
			}

			self::$facets[$subjectType] = $facets;
		}

		if (is_array($filter)) {
			$parsedFilter = $subjectType::getFilterFromArr($filter);
			self::$facets[$subjectType]->each->setFilter($parsedFilter);
		}

		return self::$facets[$subjectType];
	}

	/**
	 * Load facet rows for the provided facets and subject type.
	 */
	public function loadRows(string $subjectType, Collection $facets): void
	{
		$rows = FacetCache::cache('facetRows', $subjectType);

		if ($rows === false) {
			$rows = DB::table('facetrows')
				->whereIn('facet_slug', $facets->map->getSlug())
				->select('facet_slug', 'subject_id', 'value')
				->get()
				->groupBy('facet_slug');

			if ($rows->isEmpty()) {
				Log::warning("No facet rows for {$subjectType}! Did you forget to build an index?");
			}

			FacetCache::cache('facetRows', $subjectType, $rows);
		}

		foreach ($facets as $facet) {
			if (isset($rows[$facet->getSlug()])) {
				$facet->setRows($rows[$facet->getSlug()]);
			}
		}
	}

	/**
	 * Clone a base query while removing eager loads and limits.
	 */
	public static function cloneBaseQuery($query)
	{
		$cloned = clone $query;
		$cloned->withOnly([]);

		$underlyingQuery = $cloned->getQuery();

		if ($underlyingQuery->limit > 0) {
			$cloned->limit(null);
			$underlyingQuery->offset = null;
		}

		return $cloned;
	}

	/**
	 * Store a query without eager loads to be reused for facet filtering.
	 */
	public function setLastQuery(string $subjectType, $query): void
	{
		self::$lastQueries[$subjectType] = self::cloneBaseQuery($query);
	}

	/**
	 * Retrieve the last saved query for a subject type.
	 */
	public function getLastQuery(string $subjectType)
	{
		return self::$lastQueries[$subjectType] ?? false;
	}

	/**
	 * Get IDs from last query with a specific facet filter removed.
	 */
	public function getIdsInLastQueryWithoutFacet($facet)
	{
		$param = $facet->getParamName();
		$filter = array_merge($facet->filter, [$param => []]);

		$cachedIds = $this->cacheIdsInFilteredQuery($facet->subject_type, $filter);

		if ($cachedIds === false) {
			$lastQuery = $this->getLastQuery($facet->subject_type);

			if ($lastQuery) {
				$lastQuery->constrainQueryWithFilter($filter, false);
				$ids = $lastQuery->pluck('id')->toArray();
				$this->cacheIdsInFilteredQuery($facet->subject_type, $filter, $ids);
				return $ids;
			}
		}

		return $cachedIds;
	}

	/**
	 * Generate a complete filter array with empty defaults, merged with input.
	 */
	public function getFilterFromArr(string $subjectType, array $arr = []): array
	{
		$defaultFilter = $this->getEmptyFilter($subjectType);

		$normalized = array_map(fn($v) => (array)$v, $arr);
		$filtered = array_intersect_key(array_filter($normalized), $defaultFilter);

		return array_replace($defaultFilter, $filtered);
	}

	/**
	 * Generate a base filter array with empty values.
	 */
	public function getEmptyFilter(string $subjectType): array
	{
		return $subjectType::getFacets()
			->mapWithKeys(fn($facet) => [$facet->getParamName() => []])
			->toArray();
	}

	/**
	 * Cache or retrieve IDs for a specific subjectType + filter combo.
	 */
	public function cacheIdsInFilteredQuery(string $subjectType, array $filter, ?array $ids = null)
	{
		asort($filter);
		ksort($filter);
		$cacheKey = $subjectType . '.' . json_encode($filter, JSON_THROW_ON_ERROR);

		return FacetCache::cache('idsInFilteredQuery', $cacheKey, $ids);
	}

	/**
	 * Clear cached ID queries for a subjectType.
	 */
	public function resetIdsInFilteredQuery(string $subjectType): void
	{
		FacetCache::forgetCache('idsInFilteredQuery', $subjectType);
	}

	// Convenience methods
	public function cache(string $key, string $subkey, $value = null)
	{
		return FacetCache::cache($key, $subkey, $value);
	}

	public function forgetCache(?string $key = null, ?string $subkey = null)
	{
		return FacetCache::forgetCache($key, $subkey);
	}
}
