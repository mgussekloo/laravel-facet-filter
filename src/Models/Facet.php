<?php

namespace Mgussekloo\FacetFilter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mgussekloo\FacetFilter\Builders\FacetQueryBuilder;
use Mgussekloo\FacetFilter\Facades\FacetFilter;
use Str;

/**
 * @property string $title
 * @property string $fieldname
 * @property string $subject_type
 */
class Facet extends Model
{
    protected $fillable = [
        'fieldname',
        'subject_type',
    ];

    public $rows = null;
    public $options = null;
    public $filter = null;
    public $idsInFilter = null;

    public function __construct($definition)
    {
    	$this->filter = [];
    	$this->rows = collect([]);

        foreach ($definition as $key => $value) {
            $this->$key = $value;
        }
    }

    // return the option objects for this facet
    public function getOptions(): Collection
    {
        if (is_null($this->options)) {
            $slugBase = Str::slug($this->fieldname ?? $this->title);
            $facetName = $this->getParamName();

            $valueTotals = $filterValues = collect([]);
            if ($this->rows->isNotEmpty()) {
				$valueTotals = $this->getValueTotals();
				$filterValues = $this->getFilterValues();
			}

            $options = $valueTotals->map(function($total, $value) use ($filterValues, $slugBase) {
                return (object)[
                    'value' => $value,
                    'selected' => $filterValues ? $filterValues->contains($value) : false,
                    'total' => $total,
                    'slug' => sprintf( '%s_%s', $slugBase, Str::slug($value) ),
                    'http_query' => $this->getHttpQuery($value),
                ];
            });

            $this->options = $options;
        }

        return $this->options;
    }


    // return the options objects, but remove the ones leading to zero results
    public function getNonMissingOptions(): Collection
    {
        return $this->getOptions()->filter(fn ($value) => $value->total);
    }

    // get all values
	public function getValueTotals() {
    	$valueTotals = [];

		foreach ($this->rows as $row) {
			if (!isset($valueTotals[$row->value])) {
				$valueTotals[$row->value] = 0;
			}

			if (!is_array($this->idsInFilter) || in_array($row->subject_id, $this->idsInFilter)) {
				$valueTotals[$row->value]++;
			}
		}

		$valueTotals = collect($valueTotals);

    	return $valueTotals;
    }

    // get selected values
    public function getFilterValues()
    {
        $facetName = $this->getParamName();

        $filterValues = (isset($this->filter[$facetName]))
            ? collect($this->filter[$facetName])->values()
            : collect([]);

    	if ($filterValues->isNotEmpty()) {
        	$allValues = $this->rows->pluck('value');

	        // if you have selected ALL, it is the same as selecting none
	        if ($allValues->diff($filterValues)->isEmpty()) {
	            $filterValues = collect([]);
	        }
	    }

	    return $filterValues;
	}

    public function getHttpQuery($value): string
    {
        $facetName = $this->getParamName();

        $arr = $this->filter;
        if (isset($arr[$facetName])) {
        	if (empty($arr[$facetName])) {
        		$arr[$facetName][] = $value;
        	} elseif (in_array($value, $arr[$facetName])) {
        		$arr[$facetName] = array_diff($arr[$facetName], [$value]);
        	} else {
        		$arr[$facetName][] = $value;
        	}
        }

        $arr = array_filter($arr);
        return http_build_query($arr, '', '&', PHP_QUERY_RFC3986);
    }

    // return the title (or fieldname) to use for the http query param
    public function getParamName(): string
    {
        $param = $this->title ?? $this->fieldname;
        return Str::slug($param);
    }

    // get this facet's unique slug (used for indexing)
    public function getSlug(): string
    {
        return implode('.', [$this->subject_type, $this->fieldname ?? strtolower($this->title) ]);
    }

    // set the filter for this facet
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    public function setIdsInFilter($arr) {
    	$this->idsInFilter = $arr;
    }

    // set the facetrows for this facet
    public function setRows($rows)
    {
        $this->rows = $rows;
    }


}