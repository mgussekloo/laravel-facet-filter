<?php

namespace Mgussekloo\FacetFilter\Collections;

use Mgussekloo\FacetFilter\Facades\FacetFilter;

use Illuminate\Database\Eloquent\Collection;

class FacettableCollection extends Collection
{
	public $useFacetCache = false;

	public function withCache($cache=true) {
    	$this->useFacetCache=$cache;
    	return $this;
    }

    public function getFacets($filter = null, $load = true)
    {
    	$subjectType = $this->first()::class;
    	return FacetFilter::getFacets($subjectType, $filter, $load);
    }

    public function buildIndex($reset = true) {
    	$indexer = $this->getIndexer();
    	if ($reset) {
    		$indexer->resetRows($this);
    	}
    	$indexer->buildIndex($this);
    }

    public function resetIndex() {
    	$indexer = $this->getIndexer();
    	$indexer->resetRows($this);
    }

	public function getIndexer() {
		$subjectType = $this->first()::class;
		$indexerClass = $subjectType::indexerClass();
		return new $indexerClass();
    }

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

	    $filter = $facets->first()->filter;
		$indexer = $this->getIndexer();

		if (!$this->useFacetCache) {
        	FacetFilter::resetIdsInFilteredQuery($subjectType);
        }

		// build the facet rows
   		$all_rows = FacetFilter::cache('facetRows', $subjectType);

   		if ($all_rows === false) {
    		$all_rows = [];

	    	foreach ($facets as $facet) {
	    		$_rows = [];
	    		// build the facetrows
		    	foreach ($this as $model) {
		    		$values = $indexer->buildValues($facet, $model);
	                if (!is_array($values)) {
	                	$values = [$values];
	                }

		    		foreach ($values as $value) {
		    			$arr = (object)$indexer->buildRow($facet, $model, $value);
		    			$_rows[] = $arr;
		    		}
		    	}
		    	$all_rows[$facet->getSlug()] = collect($_rows);
		    }

		    FacetFilter::cache('facetRows', $subjectType, $all_rows);
		}

		// load the rows
	    foreach ($facets as $facet) {
	    	$rows = $all_rows[$facet->getSlug()]->sortBy('value');
	    	$facet->setRows($rows);
	    }

		$all_ids = FacetFilter::cacheIdsInFilteredQuery($subjectType, $filter);
    	if ($all_ids === false) {
			$all_ids = $this->pluck('id')->toArray();
    		FacetFilter::cacheIdsInFilteredQuery($subjectType, $filter, $all_ids);
		}

	    if (empty(array_filter(array_values($filter)))) {
	    	return $this;
    	}

	    foreach ($facets as $facet) {
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

	    // all facets are done, prepare the last-query caches and correct option counts

	    $included_ids_known = FacetFilter::cacheIdsInFilteredQuery($subjectType, $filter);

	    $included_ids = null;
	    foreach ($facets as $facet) {
	    	$facetName = $facet->getParamName();

	    	if (isset($filter[$facetName])) {
	    		$filterWithoutFacet = array_merge($filter, [$facetName => []]);
	    		if (false === FacetFilter::cacheIdsInFilteredQuery($subjectType, $filterWithoutFacet)) {

					$otherFacets = $facets->reject(function($f) use ($facetName) {
			    		return $facetName == $f->getParamName();
			    	});

			    	$ids = null;
			    	foreach ($otherFacets as $f) {
		    			$ids = (!is_null($ids)) ? array_intersect($ids, $f->included_ids) : $f->included_ids;
			    	};

		    		FacetFilter::cacheIdsInFilteredQuery($subjectType, array_merge($filter, [$facet->getParamName() => []]), $ids);
	    		}
	    	}

	    	if ($included_ids_known === false) {
				$included_ids = (!is_null($included_ids)) ? array_intersect($included_ids, $facet->included_ids) : $facet->included_ids;
			}
	    }

	    if ($included_ids_known === false) {
    		FacetFilter::cacheIdsInFilteredQuery($subjectType, $filter, $included_ids);
    	} else {
    		$included_ids = $included_ids_known;
    	}

	    return $this->whereIn('id', $included_ids);
    }


}