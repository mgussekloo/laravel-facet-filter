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

        // Try to optimize the query
        $this->removeFilterWhenAllFacetValuesSelected();

        // Constrain the query
        $this->constrainQueryWithFilter();

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

        if (isset($this->filter[$paramName])) {
            $this->filter[$paramName] = [];
        }

        $this->removeFilterWhenAllFacetValuesSelected();

        $result = FacetFilter::cacheIdsInFilteredQuery($this->subjectType, $this->filter);

        if ($result === false) {
            $this->constrainQueryWithFilter();
            $result = parent::get()->pluck('id')->toArray();
            FacetFilter::cacheIdsInFilteredQuery($this->subjectType, $this->filter, $result);
        }

        return $result;
    }

    // Constrain the query with the facets and filter
    public function constrainQueryWithFilter()
    {
        $facets = FacetFilter::getFacets($this->subjectType);

        foreach ($facets as $facet) {
            $key = $facet->getParamName();

            if (isset($this->filter[$key])) {
                $values = (array) $this->filter[$key];

                if (! empty($values)) {
                    $this->whereHas('facetrows', function ($query) use ($values, $facet): void {
                        $query->select('id')->where('facet_slug', $facet->getSlug())->whereIn('value', $values);
                    });
                }
            }
        }
    }

    // If we selected ALL facet values, we do not need to filter at all
    public function removeFilterWhenAllFacetValuesSelected()
    {
        $facets = FacetFilter::getFacets($this->subjectType);

        foreach ($facets as $facet) {
            $key = $facet->getParamName();

            $allValues = $facet->getRows()->pluck('value')->filter()->unique()->values();
            $selectedValues = collect($this->filter[$key])->values();
            if ($allValues->diff($selectedValues)->isEmpty()) {
                $this->filter[$key] = [];
            }
        }
    }
}
