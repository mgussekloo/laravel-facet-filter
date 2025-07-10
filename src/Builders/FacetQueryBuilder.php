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
		$facets = FacetFilter::getFacets($this->facetSubjectType, $filter, true);

		// ===

		$idsByFacet = FacetFilter::cacheIdsInFilter($cacheSubkey, $filter);
		if ($idsByFacet === false) {
			$tempQuery = self::cloneBaseQuery($this);
			$idsInQuery = $tempQuery->pluck('id')->toArray();

			$idsByFacet = [];

			// 1. load the facet rows
			// $rowQuery = FacetFilter::getRowQuery($facets);
			// FacetFilter::loadRows($facets, $rowQuery);

			// foreach ($facets as $facet) {
			// 	$facetSlug = $facet->getSlug();

			// 	// by default, you don't have to filter this
			// 	$idsByFacet[$facetSlug] = null;

			// 	// unless you've selected any values in the filter for this facet
			// 	if ($facet->getSelectedValues()->isNotEmpty()) {
			// 		$idsByFacet[$facetSlug] = FacetFilter::getRowQuery($facet)
			// 			->whereIn('subject_id', $idsInQuery)
			// 			->pluck('subject_id');
			// 	}
			// }

			// 2. calculcate the selected, per facet
			$tempIdsPerFacet = [];

			foreach ($facets as $facet) {
				$facetName = $facet->getParamName();
				$facetSlug = $facet->getSlug();

				$tempIdsPerFacet[$facetSlug] = null;

				$query = FacetFilter::getRowQuery($facet)->whereIn('subject_id', $idsInQuery);

				if (!empty($facet->filter[$facetName])) {
					$query->whereIn('value', $facet->filter[$facetName]);
					$tempIdsPerFacet[$facetSlug] = $query->pluck('subject_id')->toArray();
				}
			}

			foreach ($facets as $facet) {
				$facetSlug = $facet->getSlug();
				$idsWithoutFacet = array_merge($tempIdsPerFacet, [$facetSlug => null]);

				$mustFilter = collect($idsWithoutFacet)->some(function($ids) {
					return !is_null($ids);
				});

				if ($mustFilter) {
					$ids = collect($idsWithoutFacet)->filter()->reduce(function($c, $v, $i) {
						return (is_null($c)) ? collect($v) : $c->intersect($v);
					}, null)->toArray();

					$facet->setIdsInFilter($ids);
				} else {
					$facet->setIdsInFilter($idsInQuery);
				}
			}


			// // 3. limit the main query by ids as dictated by the facets
			// $mustFilter = collect($idsByFacet)->some(function($ids) {
			// 	return !is_null($ids);
			// });

			// $idsByFacet = $idsInQuery;

			// if ($mustFilter) {
			// 	$idsByFacet = collect($idsByFacet)->filter()->reduce(function($c, $v, $i) {
			// 		return ($c === false) ? collect($v) : $c->intersect($v);
			// 	}, false)
			// 	->flatten()->toArray();
			// }

			FacetFilter::cacheIdsInFilter($cacheSubkey, $filter, $idsByFacet);
		}



		// ===

		$ids = $facets->reduce(function($c, $v, $i) {
			return ($c === false) ? collect($v->idsInFilter) : $c->intersect($v->idsInFilter);
		}, false)
		->flatten()->toArray();
    	$this->whereIntegerInRaw('id', $ids);

		// if ($idsInFilter['__main__']) {

		// }
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
}
