<?php

namespace Mgussekloo\FacetFilter;

use Mgussekloo\FacetFilter\Models\Facet;
use Illuminate\Http\Request;

use DB;

class FacetFilter
{

	public static $facets = [];
	public static $lastQueries = [];
	public static $idsInFilteredQuery = [];

	/*
	Returns a Laravel collection of the available facets.
	*/
	public function getFacets($subjectType, $filter = null, $load = true)
	{
		if (!isset(self::$facets[$subjectType])) {
			$definitions = collect($subjectType::defineFacets())
			->map(function($arr) use ($subjectType) {
				return [
					'title' => $arr[0],
					'fieldname' => $arr[1],
					'subject_type' => $subjectType,
				];
			});

			$facets = $definitions->mapInto(Facet::class);

        	self::$facets[$subjectType] = $facets;
		}

		// set the filter
		if (!is_null($filter)) {
			self::$facets[$subjectType]->map->setFilter($filter);
		}

		// should we preload the options?
		if ($load) {
			$this->fillFacetRows($subjectType);
			// self::$facets[$subjectType]->map->getOptions();
		}

		return self::$facets[$subjectType];
	}

	public function fillFacetRows($subjectType)
	{
		$rows = DB::table('facetrows')
        ->select('facet_slug', 'subject_id', 'value')
        ->get()
        ->groupBy('facet_slug');

        $facets = self::getFacets($subjectType);

    	foreach ($facets as $index => $facet) {
    		$slug = $facet->getSlug();
    		if (isset($rows[$slug])) {
    			$facet->rows = $rows[$slug];
    		}
    	}
	}

	public function setLastQuery($subjectType, $query)
	{
		$newQuery = clone $query;
		$newQuery->withOnly([]);
		self::$lastQueries[$subjectType] = $newQuery;
	}

	public function getLastQuery($subjectType)
	{
		if (isset(self::$lastQueries[$subjectType])) {
			return clone self::$lastQueries[$subjectType];
		}
		return false;
	}

	public function getFilterFromArr($subjectType, $arr = [])
	{
		$emptyFilter = $this->getFacets($subjectType)->mapWithKeys(function($facet) {
			return [$facet->getParamName() => [ ]];
		})->toArray();

		$arr = array_map(function($item) {
			if (!is_array($item)) {
				return [ $item ];
			}
			return $item;
		}, $arr);

		return array_replace($emptyFilter, array_intersect_key(array_filter($arr), $emptyFilter));
	}

	public function getEmptyFilter($subjectType)
	{
		return $this->getFilterFromArr($subjectType, []);
	}

	public function resetIdsInFilteredQuery($subjectType)
	{
		if (isset(self::$idsInFilteredQuery[$subjectType])) {
			unset(self::$idsInFilteredQuery[$subjectType]);
		}
	}

	public function cacheIdsInFilteredQuery($subjectType, $filter, $ids = null)
	{
		if (!isset(self::$idsInFilteredQuery[$subjectType])) {
			self::$idsInFilteredQuery[$subjectType] = [];
		}

		$cacheKey = FacetFilter::getCacheKey($subjectType, $filter);
		if (!isset(self::$idsInFilteredQuery[$subjectType][$cacheKey]) && !is_null($ids)) {
			self::$idsInFilteredQuery[$subjectType][$cacheKey] = $ids;
			return $ids;
		}

		if (isset(self::$idsInFilteredQuery[$subjectType][$cacheKey])) {
			return self::$idsInFilteredQuery[$subjectType][$cacheKey];
		}

		return false;
	}

	public function getCacheKey($subjectType, $filter)
	{
		return implode('_', [$subjectType, md5(json_encode($filter))]);
	}

}