<?php

namespace Mgussekloo\FacetFilter\Builders;

use Illuminate\Database\Eloquent\Builder;

use Mgussekloo\FacetFilter\Facades\FacetFilter;

class FacetQueryBuilder extends Builder
{
    public $useFacetCache = true;

    public $facetSubjectType = null;

    public $facetFilter = null;

    public $appliedConstraint = false;

    /**
     * Remember the filter and the subject type (model class) and wait
     * until we need the results (get) before performing the query.
     */
    public function facetFilter($filter = [])
    {
        $this->facetSubjectType = $this->model::class;
        $this->facetFilter = $this->facetSubjectType::getFilterFromArr($filter);

        return $this;
    }

    // Alias of facetfilter
    public function facetsMatchFilter($filter = [])
    {
    	return $this->facetFilter($filter);
    }

    /**
     * We cache relevant models for a query based on the filter, not the query specifics, because we assume that you do only one facet filtering query for any model.
     * If this is not the case, be sure to use the ->withoutCache() method when querying.
     */
    public function withCache($cache=true) {
    	$this->useFacetCache=$cache;
    	return $this;
    }

    public function withoutCache($cache=true) {
    	$this->useFacetCache=!$cache;
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
        if (!$this->appliedConstraint) {
        	FacetFilter::setLastQuery($this->facetSubjectType, $this);

        	if (!$this->useFacetCache) {
        		FacetFilter::forgetCache('idsInFilteredQuery', $this->facetSubjectType);
        	}
        }

        // Constrain the query
        $this->constrainQueryWithFilter($this->facetFilter);

        // Get the result
        $result = parent::get($columns);

        return $result;
    }

    // Constrain the query with the facets and filter
    public function constrainQueryWithFilter($filter, $shouldApplyFilter=true)
    {
        if ($this->appliedConstraint) {
        	return;
        }

        $shouldApplyFilter = ($shouldApplyFilter) ? $filter : false;
        $facets = FacetFilter::getFacets($this->facetSubjectType, $shouldApplyFilter);

        foreach ($facets as $facet) {
            $facet->constrainQueryWithFilter($this, $filter);
        }

        $this->appliedConstraint = true;
    }

    // paginate
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
    	$total = $this->getCountForPagination();
        return parent::paginate($perPage, $columns, $pageName, $page, $total);
    }

	public function getCountForPagination($columns = ['*']) {
		$count = FacetFilter::cache('countForPagination', $this->facetSubjectType);
		if ($count === false) {
	    	$tempQuery = FacetFilter::cloneBaseQuery($this);
	        $tempQuery->constrainQueryWithFilter($this->facetFilter);
	        $count = FacetFilter::cache('countForPagination', $this->facetSubjectType, $tempQuery->count());
		}
		return $count;
    }
}
