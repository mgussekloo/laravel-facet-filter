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
		if (is_null($filter)) {
			$filter = self::getFilterFromParam($subjectType);
		}

		if (!isset(self::$facets[$subjectType])) {
			self::$facets[$subjectType] = Facet::where('subject_type', $subjectType)->get();
		}

		self::$facets[$subjectType]->map->setFilter($filter);

		return self::$facets[$subjectType];
	}

	/*
	Returns an array with the current filter, based on all the available facets for this model,
	and the specified (optional) GET parameter (default is "filter"). A facet's title is
	its key in the GET parameter.

	e.g. /?filter[main-color][0]=green will result in:
	[ 'main-color' => [ 'green' ], 'size' => [ ] ]
	*/
	public function getFilterFromParam($subjectType, $paramName = 'filter')
	{
		$arr = (array)request()->input($paramName);
		return $this->getFilterFromArr($subjectType, $arr);
	}

	public function getFilterFromArr($subjectType, $arr)
	{
		$emptyFilter = $this->getFacets($subjectType)->mapWithKeys(function($facet) {
			return [$facet->getParamName() => [ ]];
		})->toArray();

		return array_replace($emptyFilter, array_filter($arr));
	}

	public function getEmptyFilter($subjectType)
	{
		return $this->getFilterFromArr($subjectType, []);
	}

	public function constrainQueryWithFilter($subjectType, $query, $filter)
	{
		$facets = self::getFacets($subjectType);

		foreach ($facets as $facet) {
            $key = $facet->getParamName();

            if (isset($filter[$key])) {
                $values = (array)$filter[$key];
                if (!empty($values)) {
                    $query->whereHas('facetrows', function($query) use ($values, $facet) {
                        $query->where('facet_id', $facet->id)->whereIn('value', $values);
                    });
                }
            }
        }

        return $query;
	}

	public function resetIdsInFilteredQuery()
	{
		self::$idsInFilteredQuery = [];
	}

	public function getIdsInFilteredQuery($subjectType, $query, $filter)
	{
		$cacheKey = implode('_', [$subjectType, md5(serialize($filter))]);

		if (!isset(self::$idsInFilteredQuery[$cacheKey])) {
			$query = FacetFilter::constrainQueryWithFilter($subjectType, $query, $filter);
	        self::$idsInFilteredQuery[$cacheKey] = $query->select('id')->get()->pluck('id')->toArray();
		}

		return self::$idsInFilteredQuery[$cacheKey];
	}

}