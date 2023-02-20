<?php

namespace Mgussekloo\FacetFilter;

use Mgussekloo\FacetFilter\Models\Facet;
use Illuminate\Http\Request;

class FacetFilter
{

	public static $facets = [];
	public static $idsInFilteredQuery = [];

	/*
	Returns a Laravel collection of the available facets.
	*/
	public function getFacets($subjectType, $filter = null)
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

			self::$facets[$subjectType] = $definitions->mapInto(Facet::class);
		}

		if (!is_null($filter)) {
			self::$facets[$subjectType]->map->setFilter($filter);
		}

		return self::$facets[$subjectType];
	}

	public function setLastQuery($subjectType, $query)
	{
        self::getFacets($subjectType)->map->setLastQuery($query);
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

	public function resetIdsInFilteredQuery()
	{
		self::$idsInFilteredQuery = [];
	}

	public function cacheIdsInFilteredQuery($subjectType, $filter, $ids = null)
	{
		$cacheKey = FacetFilter::getCacheKey($subjectType, $filter);
		if (!isset(self::$idsInFilteredQuery[$cacheKey]) && !is_null($ids)) {
			self::$idsInFilteredQuery[$cacheKey] = $ids;
		}
		if (isset(self::$idsInFilteredQuery[$cacheKey])) {
			return self::$idsInFilteredQuery[$cacheKey];
		}
		return false;
	}

	public function getCacheKey($subjectType, $filter)
	{
		ksort($filter);
		return implode('_', [$subjectType, md5(json_encode($filter))]);
	}

}