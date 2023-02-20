<?php

namespace Mgussekloo\FacetFilter\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

use Mgussekloo\FacetFilter\Facades\FacetFilter;

class FacetQueryBuilder extends Builder
{

	public $subjectType = null;
	public $filter = null;

	public function __construct(QueryBuilder $query)
	{
		parent::__construct($query);
	}

    public function facetsMatchFilter($filter = [])
    {
    	$this->filter = $filter;
		$this->subjectType = get_class($this->model);
        return $this;
    }

	public function get($columns = ['*'], $saveLast = true)
    {
    	if (is_null($this->filter)) {
    		return parent::get($columns);
    	}

        if ($saveLast) {
        	FacetFilter::setLastQuery($this->subjectType, $this, $this->filter);
			FacetFilter::resetIdsInFilteredQuery();
        }

		$result = FacetFilter::cacheIdsInFilteredQuery($this->subjectType, $this->filter);

		if ($result === false) {
	        $facets = FacetFilter::getFacets($this->subjectType);

	        foreach ($facets as $facet) {
	            $key = $facet->getParamName();
	            if (isset($this->filter[$key])) {
	                $values = (array)$this->filter[$key];

	                if (!empty($values)) {
	                    $this->whereHas('facetrows', function($query) use ($values, $facet) {
	                        $query->select('id')->where('facet_slug', $facet->getSlug())->whereIn('value', $values);
	                    });
	                }
	            }
	        }

	        $result = parent::get($columns);
        	FacetFilter::cacheIdsInFilteredQuery($this->subjectType, $this->filter, $result->pluck('id')->toArray());
	    }

    	return $result;
    }

    public function getIdsInQueryWithoutFacet($facet) {
    	$facetName = $facet->getParamName();

    	if (isset($this->filter[$facetName])) {
    		$this->filter[$facetName] = [];
    	}

    	$result = FacetFilter::cacheIdsInFilteredQuery($this->subjectType, $this->filter);

    	if ($result === false) {
	    	$result = $this->get(['id'], false)->pluck('id')->toArray();
    	}

    	return $result;
    }
}
