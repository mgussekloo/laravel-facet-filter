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

            $rows = $this->rows;

            $allValues = $selectedValues = collect([]);
            if ($this->rows->isNotEmpty()) {
				$allValues = $this->getAllValues();
				$selectedValues = $this->getSelectedValues();
			}

            $options = $allValues->map(function($total, $value) use ($selectedValues, $slugBase) {
                return (object)[
                    'value' => $value,
                    'selected' => ($selectedValues) ? $selectedValues->contains($value) : false,
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
	public function getAllValues() {
		$rows = $this->rows;

		if (!is_null($this->idsInFilter)) {
			$rows = $rows->filter(function($row) {
				return in_array($row->subject_id, $this->idsInFilter);
			});
		}

		$allValues = $rows->pluck('value')->filter()->countBy();
    	return $allValues;
    }

    // get selected values
    public function getSelectedValues($allValues = null)
    {
        $facetName = $this->getParamName();

        $selectedValues = (isset($this->filter[$facetName]))
            ? collect($this->filter[$facetName])->values()
            : collect([]);

        // if you have selected ALL, it is the same as selecting none
        if ($selectedValues->isNotEmpty()) {
	        $allValues = $this->getAllValues()->keys();
	        if ($allValues->diff($selectedValues)->isEmpty()) {
	            $selectedValues = collect([]);
	        }
	    }

	    return $selectedValues;
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