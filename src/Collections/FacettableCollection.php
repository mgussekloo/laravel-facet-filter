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
    public function facetFilter($filter, $indexer=null)
    {
    	if (is_null($indexer)) {
    		$indexer = new Indexer();
    	}

		$subjectType = $this->first()::class;
	    $filter = $subjectType::getFilterFromArr($filter);

		$facets = FacetFilter::getFacets($subjectType, $filter, false);

    	foreach ($facets as $facet) {
    		// build the facetrows
    		$rows = [];
	    	foreach ($this as $model) {
	    		foreach ($indexer->buildValues($facet, $model) as $value) {
	    			$arr = $indexer->buildRow($facet, $model, $value);
	    			$rows[] = new FacetRow($arr);
	    		}
	    	}
	    	$facet->setRows(collect($rows));

	    	// now start filtering
        	$facetName = $facet->getParamName();

	        $selectedValues = (isset($filter[$facetName]))
	            ? collect($filter[$facetName])->values()
	            : collect([]);

	        // if you have selected ALL, it is the same as selecting none
	        $allValues = $facet->rows->pluck('value')->filter()->unique()->values();
	        if ($allValues->diff($selectedValues)->isEmpty()) {
	            $selectedValues = collect([]);
	        }

	        // if you must filter
	        if ($selectedValues->isNotEmpty()) {
	        	$ids = [];
	        	foreach ($this as $model) {
	            	$values = $indexer->buildValues($facet, $model);
		            if ($values->intersect($selectedValues)->isNotEmpty()) {
		            	$ids[] = $model->id;
		            }
		        }
		        $facet->included_ids = $ids;
	        } else {
	        	$facet->included_ids = $this->pluck('id')->toArray();
	        }
	    }

	    // all facets are done
		FacetFilter::resetIdsInFilteredQuery($subjectType);

	    $included_ids = [];
	    foreach ($facets as $facet) {
	    	$otherFacets = $facets->reject(function($f) use ($facet) {
	    		return $facet->getParamName() == $f->getParamName();
	    	});

	    	$ids = [];
	    	foreach ($otherFacets as $f) {
    			$ids = (!empty($ids)) ? array_intersect($ids, $f->included_ids) : $f->included_ids;
	    	};

	    	FacetFilter::cacheIdsInFilteredQuery($subjectType, array_merge($filter, [$facet->getParamName() => []]), $ids);

			$included_ids = (!empty($included_ids)) ? array_intersect($included_ids, $facet->included_ids) : $facet->included_ids;
	    }

    	FacetFilter::cacheIdsInFilteredQuery($subjectType, $filter, $included_ids);

	    return $this->whereIn('id', $included_ids);
    }
}