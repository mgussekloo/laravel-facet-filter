<?php

namespace Mgussekloo\FacetFilter\Collections;

use Mgussekloo\FacetFilter\Facades\FacetFilter;
use Mgussekloo\FacetFilter\Facades\FacetCache;

use Illuminate\Database\Eloquent\Collection;

class FacettableCollection extends Collection
{
	public $facetCachePostfix = '';

	// get the facets for this collection's model
    public function getFacets($filter = null, $load = true)
    {
    	$subjectType = $this->first()::class;
    	return FacetFilter::getFacets($subjectType, $filter, $load);
    }

	// Manual cache postfix to differentiate queries with the same model class
	public function withCacheKey($str) {
		$this->facetCachePostfix = $str;
		return $this;
	}

	/**
     * Indexing
     */

    // add rows to the index table for these models
    public function buildIndex($reset = true) {
    	$indexer = $this->getIndexer();
    	if ($reset) {
    		$indexer->resetRows($this);
    	}
    	$indexer->buildIndex($this);
    }

    // reset the rows in the index for these models
    public function resetIndex() {
    	$indexer = $this->getIndexer();
    	$indexer->resetRows($this);
    }

    // get the indexer class that is specified for the models in this collection
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
		$filter = FacetFilter::getFilterFromArr($subjectType, $filter);

		// get the facets but do not load the options
		$facets = $this->getFacets($filter, false);
		$cacheSubkey = [$subjectType, $this->facetCachePostfix];


		if ($facets->isEmpty()) {
			return $this;
		}

		$indexer = $this->getIndexer();

		// build the facet rows
   		$allRows = FacetCache::cache('facetRows', $subjectType);

   		if ($allRows === false) {
    		$allRows = [];

	    	foreach ($facets as $facet) {
				$facetSlug = $facet->getSlug();

	    		$_rows = [];

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

		    	$allRows[$facetSlug] = collect($_rows);
		    }

		    FacetCache::cache('facetRows', $subjectType, $allRows);
		}

		$ids = FacetFilter::cacheIdsInFilter($cacheSubkey, $filter);

		if ($ids === false) {
			$ids = [];

			foreach ($facets as $facet) {
				$facetSlug = $facet->getSlug();

				// add the rows
		    	$rows = $allRows[$facetSlug]->sortBy('value');
	    		$facet->setRows($rows);

				// by default, you don't have to filter this
				$ids[$facetSlug] = null;

				// unless you've selected any values in the filter for this facet
				$selectedValues = $facet->getSelectedValues();
				if ($selectedValues->isNotEmpty()) {
					$ids[$facetSlug] =  $facet->rows->whereIn('value', $selectedValues)->pluck('subject_id')->toArray();
				}
			}

			FacetFilter::cacheIdsInFilter($cacheSubkey, $filter, $ids);
		}

		// load the facets with the ids
		foreach ($facets as $facet) {
			$facetSlug = $facet->getSlug();

			$idsWithoutFacet = collect(array_merge($ids, [$facetSlug => null]));

			// if there is something to filter
			$mustFilter = $idsWithoutFacet->some(function($ids) {
				return !is_null($ids);
			});

			if ($mustFilter) {
				$idsWithoutFacet = collect($idsWithoutFacet)->flatten()->filter()->unique()->toArray();
				$facet->setIdsInFilter($idsWithoutFacet);
			}
		}

		// if there is something to filter
		$mustFilter = collect($ids)->some(function($ids) {
			return !is_null($ids);
		});

		if ($mustFilter) {
			$ids = collect($ids)->flatten()->filter()->unique()->toArray();
		    return $this->whereIn('id', $ids);
		}

		return $this;
    }
}