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

    public function __construct($definition)
    {
        foreach ($definition as $key => $value) {
            $this->$key = $value;
        }
    }

    // return the option objects for this facet
    public function getOptions(): Collection
    {
        if (is_null($this->options)) {
            $facetName = $this->getParamName();
            $subjectType = $this->subject_type;

            if (is_null($this->rows)) {
                throw new \Exception('This facet `'.$facetName.'` has no rows!');
            }

            // find out totals of the values in this facet
            // *within* the current query / filter operation.
            // in short: apply all the filters EXCEPT the one involving this facet.

            // https://stackoverflow.com/questions/27550841/calculating-product-counts-efficiently-in-faceted-search-with-php-mysql

            $idsInFilteredQuery = [];
            if ($lastQuery = FacetFilter::getLastQuery($this->subject_type)) {
                $idsInFilteredQuery = $lastQuery->getIdsInQueryWithoutFacet($this);
            }

            $values = [];
            foreach ($this->rows as $row) {
                if ($row->value == '') {
                    continue;
                }

                if (! isset($values[$row->value])) {
                    $values[$row->value] = 0;
                }

                if (in_array($row->subject_id, $idsInFilteredQuery)) {
                    $values[$row->value] = $values[$row->value] + 1;
                }
            }

            $selectedValues = [];
            if (is_array($this->filter) && isset($this->filter[$facetName])) {
                $selectedValues = $this->filter[$facetName];
            }

            $options = collect([]);

            foreach ($values as $value => $total) {
                $options->push((object) [
                    'value' => $value,
                    'selected' => in_array($value, $selectedValues),
                    'total' => $total,
                    'slug' => sprintf('%s_%s', Str::of($this->fieldname)->slug('-'), Str::of($value)->slug('-')),
                    'http_query' => $this->getHttpQuery($value),
                ]);
            }

            $this->options = $options;
        }

        return $this->options;
    }

    // return the options objects, but remove the ones leading to zero results
    public function getNonMissingOptions(): Collection
    {
        return $this->getOptions()->filter(fn ($value) => $value->total);
    }

    // constrain the given query to this facet's filtered values
    public function constrainQueryWithFilter($query, $filter): FacetQueryBuilder
    {
        $facetName = $this->getParamName();

        $selectedValues = (isset($filter[$facetName]))
            ? collect($filter[$facetName])->values()
            : collect([]);

        $allValues = $this->rows->pluck('value')->filter()->unique()->values();

        if ($allValues->diff($selectedValues)->isEmpty()) {
            $selectedValues = collect([]);
        }

        if ($selectedValues->isNotEmpty()) {
            $query->whereHas('facetrows', function ($query) use ($selectedValues): void {
                $query->select('id')->where('facet_slug', $this->getSlug())->whereIn('value', $selectedValues->toArray());
            });
        }

        return $query;
    }

    public function getHttpQuery($value): string
    {
        $facetName = $this->getParamName();

        if (isset($this->filter[$facetName])) {
            $collection = collect($this->filter[$facetName]);
            if ($collection->contains($value)) {
                $collection->pull($collection->search($value));
            } else {
                $collection->push($value);
            }

            $arr = array_merge($this->filter, [$facetName => $collection->toArray()]);

            return http_build_query($arr, '', '&', PHP_QUERY_RFC3986);
        }

        return '';
    }

    // return the title (or fieldname) to use for the http query param
    public function getParamName(): string
    {
        $param = isset($this->title) ? $this->title : $this->fieldname;

        return Str::slug($param);
    }

    // get this facet's unique slug (used for indexing)
    public function getSlug(): string
    {
        return implode('.', [$this->subject_type, $this->fieldname]);
    }

    // set the filter for this facet
    public function setFilter($filter)
    {
        $this->filter = $filter;
    }

    // set the facetrows for this facet
    public function setRows($rows)
    {
        $this->rows = $rows;
    }
}
