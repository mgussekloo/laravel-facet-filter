<?php

namespace Mgussekloo\FacetFilter;

use Mgussekloo\FacetFilter\Models\Facet;
use Illuminate\Http\Request;

class FacetFilter
{

	public static $facets = [];

	/*
	Returns a Laravel collection of the available facets.
	*/
	public function getFacets($subjectType)
	{

		if (!isset(self::$facets[$subjectType])) {
			self::$facets[$subjectType] = Facet::where('subject_type', $subjectType)->get();
		}

		return self::$facets[$subjectType];
	}

	/*
	Returns an array with the current filter, based on all the available facets for this model,
	and the specified (optional) GET parameter (default is "filter"). A facet's title is
	its key in the GET parameter.

	e.g. /?filter[main-color][0]=green will result in:
	[ 'main-color' => [ 'green' ], 'size' => [ ] ]
	*/
	public function getFilterFromRequest($subjectType, $paramName = 'filter')
	{
		return array_replace($this->getEmptyFilter($subjectType), (array)request()->input($paramName));
	}

	public function getEmptyFilter($subjectType)
	{
		return $this->getFacets($subjectType)->mapWithKeys(function($facet) {
            return [$facet->getParamName() => []];
        })->toArray();
	}

}