<?php

namespace Mgussekloo\FacetFilter\Builders;

use Illuminate\Database\Eloquent\Builder;

use Mgussekloo\FacetFilter\Facades\FacetFilter;
use Mgussekloo\FacetFilter\Facades\FacetCache;

class FacetQueryBuilder extends Builder
{
	public $facetFilter = null;
	public $appliedConstraint = false;
	public $facetCacheTag = '';

	/**
	 * Remember the filter, wait until we need the results (get) before performing the query.
	 */
	public function facetFilter($filter = null)
	{
		$this->facetFilter = $filter;

		return $this;
	}

	// Alias of facetfilter
	public function facetsMatchFilter($filter = null)
	{
		return $this->facetFilter($filter);
	}

	// Manual cache postfix to differentiate queries with the same model class
	public function cacheTag($str) {
		$this->facetCacheTag = $str;
		return $this;
	}

	/**
	 * Get the results, but first constrain the query with matching facets.
	 * We save the base query, to use it later to calculate the results in each facet.
	 */
	public function get($columns = ['*'])
	{
		if (!$this->appliedConstraint) {
			// Constrain the query
			$this->constrainQueryWithFilter($this->facetFilter);
		}

		// Get the result
		return parent::get($columns);
	}

	// Constrain the query with the facets and filter
	public function constrainQueryWithFilter($filter)
	{
		if ($this->appliedConstraint) {
			return;
		}

		$this->appliedConstraint = true;

		$facetSubjectType = $this->model::class;
		$filter = $facetSubjectType::getFilterFromArr($filter);

		$cacheSubkey = [$facetSubjectType, $this->facetCacheTag];
		$facets = FacetFilter::getFacets($facetSubjectType, $filter);

		// ===

		$tempQuery = self::cloneBaseQuery($this);
		$idsInQuery = $tempQuery->get()->pluck('id')->toArray();

		$allRows = FacetCache::cache('facetRows', $cacheSubkey);
   		if ($allRows === false) {
			$rowQuery = FacetFilter::getRowQuery($facets)->whereIntegerInRaw('subject_id', $idsInQuery);
			$allRows = $rowQuery->get()->groupBy('facet_slug');
		    FacetCache::cache('facetRows', $cacheSubkey, $allRows);
		}

		foreach ($facets as $facet) {
			$facetSlug = $facet->getSlug();
			$facet->setRows($allRows[$facetSlug]);
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
					->whereIn('subject_id', $idsInQuery)
					->pluck('subject_id')->toArray();

					// $idsByFacet[$facetSlug] = FacetFilter::getRowQuery($facet)
					// ->whereIntegerInRaw('value', $facet->getFilterValues())
					// ->whereIntegerInRaw('subject_id', $idsInQuery)
					// ->pluck('subject_id')->toArray();
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
					$idsByFacet[$facetSlug] = $idsInQuery;
				}
			}

			FacetFilter::cacheIdsInFilter($cacheSubkey, $filter, $idsByFacet);
		}

		// ===

		foreach ($facets as $facet) {
			$facetSlug = $facet->getSlug();
			$facet->setIdsInFilter($idsByFacet[$facetSlug]);
		}

		// ===

		$mustFilter = collect($idsByFacet)->some(function($ids) {
			return !is_null($ids);
		});

		if ($mustFilter) {
			$ids = self::intersectEach($idsByFacet);
	    	$this->whereIntegerInRaw('id', $ids);
	    }
	}

	// Support pagination
	public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
	{
		$total = $this->getCountForPagination();
		return parent::paginate($perPage, $columns, $pageName, $page, $total);
	}

	public function getCountForPagination($columns = ['*']) {
		$facetSubjectType = $this->model::class;
		$cacheSubkey = [$facetSubjectType, $this->facetCacheTag];

		$count = FacetCache::cache('countForPagination', $cacheSubkey);
		if ($count === false) {
			$tempQuery = self::cloneBaseQuery($this);

			$_query = $tempQuery->getQuery();
	        if ($_query->limit > 0) {
	        	$tempQuery->limit(null);
	        	$_query->offset = null;
	        }

			$tempQuery->constrainQueryWithFilter($this->facetFilter);
			$count = FacetCache::cache('countForPagination', $cacheSubkey, $tempQuery->count());
		}
		return $count;
	}

 	public static function cloneBaseQuery($query)
    {
		$newQuery = clone $query;
        $newQuery->withOnly([]);

        return $newQuery;
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
