<?php

namespace Mgussekloo\FacetFilter;

use Mgussekloo\FacetFilter\Models\Facet;
use Illuminate\Http\Request;

class FacetFilter
{

	public static $facets = [];
	public $filterParam = 'filter';

	public function getFacets($subjectType)
	{

		if (!isset(self::$facets[$subjectType])) {
			self::$facets[$subjectType] = Facet::where('subject_type', $subjectType)->get();
		}

		return self::$facets[$subjectType];
	}

	public function getFilterFromRequest($subjectType)
	{
		return array_merge($this->getEmptyFilter($subjectType), (array)request()->input($this->filterParam));
	}

	public function getEmptyFilter($subjectType)
	{
		return $this->getFacets($subjectType)->mapWithKeys(function($facet) {
            return [$facet->getParamName() => []];
        })->toArray();
	}

}