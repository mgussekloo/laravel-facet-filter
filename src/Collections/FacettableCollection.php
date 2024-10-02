<?php

namespace Mgussekloo\FacetFilter\Collections;

use Illuminate\Support\Collection;

use Mgussekloo\FacetFilter\Indexer;

use Mgussekloo\FacetFilter\Models\FacetRow;
use Mgussekloo\FacetFilter\Facades\FacetFilter;

class FacettableCollection extends Collection
{
	/**
     * Experimental: Filter a collection, bypassing the database index entirely
     */
    public function indexlessFacetFilter($filter, $indexer=null)
    {
		$subjectType = $this->first()::class;

		$facets = FacetFilter::getFacets($subjectType, $filter, false);

		if ($facets->isEmpty()) {
			return $this;
		}

		if (is_null($indexer)) {
    		$indexer = new Indexer();
    	}

	    $filter = $facets->first()->filter;

		$all_ids = $this->pluck('id')->toArray();

    	foreach ($facets as $facet) {
    		// build the facetrows
    		$rows = collect([]);
	    	foreach ($this as $model) {
	    		$values = $indexer->buildValues($facet, $model);
                if (!is_array($values)) {
                	$values = [$values];
                }

	    		foreach ($values as $value) {
	    			$arr = (object)$indexer->buildRow($facet, $model, $value);
	    			$rows->push($arr);
	    		}
	    	}
	    	$facet->setRows($rows);

	    	// now start filtering
        	$facetName = $facet->getParamName();

	        $selectedValues = (isset($filter[$facetName]))
	            ? collect($filter[$facetName])->values()
	            : collect([]);

	        // if you have selected ALL, it is the same as selecting none
			if ($selectedValues->isNotEmpty()) {
		        $allValues = $rows->pluck('value')->filter()->unique()->values();
		        if ($allValues->diff($selectedValues)->isEmpty()) {
		            $selectedValues = collect([]);
		        }
		    }

	        // if you must filter
	        if ($selectedValues->isNotEmpty()) {
	        	$facet->included_ids = $facet->rows->whereIn('value', $selectedValues)->pluck('subject_id')->toArray();
	        } else {
	        	$facet->included_ids = $all_ids;
	        }
	    }

	    // all facets are done, prepare the last query caches and correct option counts

		// FacetFilter::resetIdsInFilteredQuery($subjectType);

	    // if we have no filter, we are done early
	    if (empty(array_filter(array_values($filter)))) {
	    	FacetFilter::cacheIdsInFilteredQuery($subjectType, $filter, $all_ids);
	    	return $this;
	    }

	    // otherwise...
	    $included_ids = [];
	    foreach ($facets as $facet) {
	    	$facetName = $facet->getParamName();

	    	if (isset($filter[$facetName])) {
	    		$filterWithoutFacet = array_merge($filter, [$facetName => []]);
	    		if (false === FacetFilter::cacheIdsInFilteredQuery($subjectType, $filterWithoutFacet)) {

					$otherFacets = $facets->reject(function($f) use ($facetName) {
			    		return $facetName == $f->getParamName();
			    	});

			    	$ids = [];
			    	foreach ($otherFacets as $f) {
		    			$ids = (!empty($ids)) ? array_intersect($ids, $f->included_ids) : $f->included_ids;
			    	};

		    		FacetFilter::cacheIdsInFilteredQuery($subjectType, array_merge($filter, [$facet->getParamName() => []]), $ids);
	    		}
	    	}

			$included_ids = (!empty($included_ids)) ? array_intersect($included_ids, $facet->included_ids) : $facet->included_ids;
	    }

    	FacetFilter::cacheIdsInFilteredQuery($subjectType, $filter, $included_ids);

	    return $this->whereIn('id', $included_ids);
    }
}