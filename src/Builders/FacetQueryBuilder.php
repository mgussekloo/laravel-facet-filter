<?php

namespace Mgussekloo\FacetFilter\Builders;

use Illuminate\Database\Eloquent\Builder;

use Mgussekloo\FacetFilter\Facades\FacetFilter;

class FacetQueryBuilder extends Builder
{
    public $useFacetCache = false;

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
     * By default, we perform new calculations to get facet row counts for every query.
     * But, if you KNOW you're doing the same query anyway, you may override this.
     */
    public function withCache($cache=true) {
    	$this->useFacetCache=$cache;
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

    // paginator
    protected function paginator($items, $total, $perPage, $currentPage, $options)
    {
    	$total = $this->getCountForPagination();
    	return parent::paginator($items, $total, $perPage, $currentPage, $options);
    }

	public function getCountForPagination($columns = ['*']) {
    	$tempQuery = FacetFilter::cloneBaseQuery($this);
        $tempQuery->constrainQueryWithFilter($this->facetFilter);
        return $tempQuery->count();
    }
}
