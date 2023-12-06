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

    /**
     * Remember the filter and the subject type (model class) and wait
     * until we need the results (get) before performing the query.
     */
    public function facetsMatchFilter($filter = [])
    {
        $this->filter = $filter;
        $this->subjectType = $this->model::class;

		FacetFilter::getFacets($this->subjectType, $filter);

        return $this;
    }

    /**
     * Get the results, but first constrain the query with matching facets.
     * We save the base query, to use it later to calculate the results in each facet.
     */
    public function get($columns = ['*'])
    {
        // If we're not doing any facet filtering, just bail.
        if (is_null($this->filter)) {
            return parent::get($columns);
        }

        // Save the unconstrained query
        FacetFilter::setLastQuery($this->subjectType, $this);

        // Constrain the query
        $this->constrainQueryWithFilter($this->filter);

        // Get the result
        $result = parent::get($columns);

        // Save the ID's within the result in the cache
        FacetFilter::cacheIdsInFilteredQuery($this->subjectType, $this->filter, $result->pluck('id')->toArray());

        return $result;
    }

    /**
     * Perform a query of the same filter, without a particular facet
     * and return the id's in the results. Cache the results, to avoid
     * running the same query twice.
     */
    public function getIdsInQueryWithoutFacet($facet): array
    {
        $paramName = $facet->getParamName();

        $filter = array_merge($this->filter, [$paramName => []]);

        // $this->removeFilterWhenAllFacetValuesSelected();

        $idsArr = FacetFilter::cacheIdsInFilteredQuery($this->subjectType, $filter);

        if ($idsArr === false) {
            $this->constrainQueryWithFilter($filter);
            $idsArr = parent::pluck('id')->toArray();
            FacetFilter::cacheIdsInFilteredQuery($this->subjectType, $filter, $idsArr);
        }

        return $idsArr;
    }

    // Constrain the query with the facets and filter
    public function constrainQueryWithFilter($filter)
    {
    	if (empty(array_filter($filter))) {
    		return;
    	}

        $facets = FacetFilter::getFacets($this->subjectType);

        foreach ($facets as $facet) {
    		$facet->constrainQueryWithFilter($this);
        }
    }
}
