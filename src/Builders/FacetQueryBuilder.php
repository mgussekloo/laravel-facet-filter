<?php

namespace Mgussekloo\FacetFilter\Builders;

use Illuminate\Database\Eloquent\Builder;
use Mgussekloo\FacetFilter\Facades\FacetFilter;

class FacetQueryBuilder extends Builder
{
    public $facetSubjectType = null;

    public $facetFilter = null;

    public $facetMainQuery = false;

    /**
     * Remember the filter and the subject type (model class) and wait
     * until we need the results (get) before performing the query.
     */
    public function facetsMatchFilter($filter = [])
    {
        $this->facetFilter = $filter;
        $this->facetSubjectType = $this->model::class;
        $this->facetMainQuery = true;

        return $this;
    }

    /**
     * Get the results, but first constrain the query with matching facets.
     * We save the base query, to use it later to calculate the results in each facet.
     */
    public function get($columns = ['*'])
    {
        // If we're not doing any facet filtering, just bail.
        if (is_null($this->facetFilter)) {
            return parent::get($columns);
        }

        // Save the unconstrained query
        FacetFilter::setLastQuery($this->facetSubjectType, $this);

        // Constrain the query
        $this->constrainQueryWithFilter($this->facetFilter);

        // Get the result
        $result = parent::get($columns);

        // Save the ID's within the result in the cache
        FacetFilter::cacheIdsInFilteredQuery($this->facetSubjectType, $this->facetFilter, $result->pluck('id')->toArray());

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

        $filter = array_merge($this->facetFilter, [$paramName => []]);

        $idsArr = FacetFilter::cacheIdsInFilteredQuery($this->facetSubjectType, $filter);

        if (false === $idsArr) {
            $this->constrainQueryWithFilter($filter);
            $idsArr = parent::pluck('id')->toArray();
            FacetFilter::cacheIdsInFilteredQuery($this->facetSubjectType, $filter, $idsArr);
        }

        return $idsArr;
    }

    // Constrain the query with the facets and filter
    public function constrainQueryWithFilter($filter)
    {
        $shouldApplyFilter = ($this->facetMainQuery) ? $filter : false;
        $facets = FacetFilter::getFacets($this->facetSubjectType, $shouldApplyFilter);

        foreach ($facets as $facet) {
            $facet->constrainQueryWithFilter($this, $filter);
        }
    }
}
