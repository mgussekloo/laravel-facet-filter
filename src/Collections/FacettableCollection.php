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
    	return $this->first()->getFacets($filter, $load);
    }

	// Manual cache postfix to differentiate queries with the same model class
	public function facetCache($str) {
		$this->facetCachePostfix = $str;
		return $this;
	}

	/**
     * Indexing
     */

    // add rows to the index table for these models
    public function buildIndex() {
    	$this->first()->indexer()->buildIndex($this);
    }

    // reset the rows in the index for these models
    public function resetIndex() {
    	$this->first()->indexer()->resetRows($this);
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

		$indexer = $this->first()->indexer();

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

		$idsByFacet = FacetFilter::cacheIdsInFilter($cacheSubkey, $filter);
		if ($idsByFacet === false) {
			$idsByFacet = $_idsByFacet = [];

			foreach ($facets as $facet) {
				$facetSlug = $facet->getSlug();
				$facetName = $facet->getParamName();

				$_idsByFacet[$facetSlug] = null;

				if ($facet->getFilterValues()->isNotEmpty()) {
					$_idsByFacet[$facetSlug] = $facet->rows
					->whereIn('value', $facet->getFilterValues())
					->whereIn('subject_id', $this->pluck('id')->toArray())
					->pluck('subject_id')->toArray();
				}
			}

			foreach ($facets as $facet) {
				$facetSlug = $facet->getSlug();
				$idsWithoutFacet = array_merge($_idsByFacet, [$facetSlug => null]);

				$mustFilter = collect($idsWithoutFacet)->some(function($ids) {
					return !is_null($ids);
				});

				if ($mustFilter) {
					$idsByFacet[$facetSlug] = self::intersectEach($idsWithoutFacet);
				} else {
					$idsByFacet[$facetSlug] = $this->pluck('id')->toArray();
				}
			}

			FacetFilter::cacheIdsInFilter($cacheSubkey, $filter, $idsByFacet);
		}

		foreach ($facets as $facet) {
			$facetSlug = $facet->getSlug();
			if (isset($idsByFacet[$facetSlug])) {
				$facet->setIdsInFilter($idsByFacet[$facetSlug]);
			}
		}

		// ===

		$mustFilter = collect($idsByFacet)->some(function($ids) {
			return !is_null($ids);
		});

		if ($mustFilter) {
			$ids = self::intersectEach($idsByFacet);
	    	return $this->whereIn('id', $ids);
	    }

		return $this;
    }

	public static function intersectEach($arr) {
    	$values = array_values($arr);
    	$intersect = null;
    	foreach ($values as $value) {
    		if (is_null($value)) {
    			continue;
    		}
    		$intersect = is_null($intersect) ? $value : array_intersect($intersect, $value);
    	}
    	return $intersect;
    }
}