<?php

namespace Mgussekloo\FacetFilter\Builders;

use Illuminate\Database\Eloquent\Builder;

use Mgussekloo\FacetFilter\Facades\FacetFilter;
use Mgussekloo\FacetFilter\Facades\FacetCache;

class FacetQueryBuilder extends Builder
{
	public $facetSubjectType = null;
	public $facetFilter = null;
	public $appliedConstraint = false;
	public $facetCachePostfix = '';

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

	// Manual cache postfix to differentiate queries with the same model class
	public function facetCache($str) {
		$this->facetCachePostfix = $str;
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

		if (!$this->appliedConstraint) {
			// Constrain the query
			$this->constrainQueryWithFilter($this->facetFilter);
		}

		// Get the result
		$result = parent::get($columns);

		return $result;
	}

	// Constrain the query with the facets and filter
	public function constrainQueryWithFilter($filter)
	{
		if ($this->appliedConstraint) {
			return;
		}

		$this->appliedConstraint = true;

		$cacheSubkey = [$this->facetSubjectType, $this->facetCachePostfix];
		$facets = FacetFilter::getFacets($this->facetSubjectType, $filter);

		// get the ids of the models included in this filter
		$idsByFacet = FacetFilter::cacheIdsInFilter($cacheSubkey, $filter);

		if ($idsByFacet === false) {
			$idsByFacet = [];

			foreach ($facets as $facet) {
				$facetSlug = $facet->getSlug();

				// by default, you don't have to filter this
				$idsByFacet[$facetSlug] = null;

				// unless you've selected any values in the filter for this facet
				$selectedValues = $facet->getSelectedValues();
				if ($selectedValues->isNotEmpty()) {
					$idsByFacet[$facetSlug] = FacetFilter::getRowQuery($this->facetSubjectType, $facet)->whereIn('value', $selectedValues)->pluck('subject_id')->toArray();
				}
			}

			FacetFilter::cacheIdsInFilter($cacheSubkey, $filter, $idsByFacet);
		}

		// if there is something to filter
		$mustFilter = collect($idsByFacet)->some(function($ids) {
			return !is_null($ids);
		});

		if ($mustFilter) {
			$ids = collect($idsByFacet)->filter()->reduce(function($c, $v, $i) {
				return ($c === false) ? collect($v) : $c->intersect($v);
			}, false)
			->flatten()->toArray();

		    $this->whereIntegerInRaw('id', $ids);
		}

		// load the facets, set the ids
		$tempQuery = self::cloneBaseQuery($this);
		$idsInQuery = $tempQuery->pluck('id')->toArray();

		foreach ($facets as $facet) {
			$facetSlug = $facet->getSlug();

			$idsWithoutFacet = collect(array_merge($idsByFacet, [$facetSlug => null]));

			// if there is something to filter
			$mustFilter = $idsWithoutFacet->some(function($ids) {
				return !is_null($ids);
			});

			if ($mustFilter) {
				$idsInQuery = collect($idsWithoutFacet)->filter()->reduce(function($c, $v, $i) {
					return ($c === false) ? collect($v) : $c->intersect($v);
				}, false)
				->flatten()
				->intersect($idsInQuery)
				->toArray();
			}

			$facet->setIdsInFilter($idsInQuery);

		}
	}

	// Support pagination
	public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
	{
		$total = $this->getCountForPagination();
		return parent::paginate($perPage, $columns, $pageName, $page, $total);
	}

	public function getCountForPagination($columns = ['*']) {
		$cacheSubkey = [$this->facetSubjectType, $this->facetCachePostfix];

		$count = FacetCache::cache('countForPagination', $cacheSubkey);
		if ($count === false) {
			$tempQuery = self::cloneBaseQuery($this);
			$tempQuery->constrainQueryWithFilter($this->facetFilter);
			$count = FacetCache::cache('countForPagination', $cacheSubkey, $tempQuery->count());
		}
		return $count;
	}

 	public static function cloneBaseQuery($query)
    {
		$newQuery = clone $query;
        $newQuery->withOnly([]);

        $query = $newQuery->getQuery();
        if ($query->limit > 0) {
        	$newQuery->limit(null);
        	$query->offset = null;
        }

        return $newQuery;
    }
}
