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

	public function constrainQueryWithFilter($subjectType, $query, $filter)
	{
		$facets = self::getFacets($subjectType);

		foreach ($facets as $facet) {
            $key = $facet->getParamName();

            if (isset($filter[$key])) {
                $values = (array)$filter[$key];

                if (!empty($values)) {
                    $query->whereHas('facetrows', function($query) use ($values, $facet) {
                        $query->select('id')->where('facet_slug', $facet->getSlug())->whereIn('value', $values);
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
		// array_multisort($filter);
		$cacheKey = implode('_', [$subjectType, md5(json_encode($filter))]);

		if (!isset(self::$idsInFilteredQuery[$cacheKey])) {
			$query = FacetFilter::constrainQueryWithFilter($subjectType, $query, $filter);
	        self::$idsInFilteredQuery[$cacheKey] = $query->select('id')->get()->pluck('id')->toArray();
		}

		return self::$idsInFilteredQuery[$cacheKey];
	}

}