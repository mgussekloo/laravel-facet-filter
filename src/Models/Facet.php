<?php

namespace Mgussekloo\FacetFilter\Models;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as EloquentCollection;
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
        'title',
        'fieldname',
        'subject_type',
    ];

    public $rows = null;

    public $options = null;

    public $filter = null;

    public function getFacetRowsFromDB(): EloquentCollection
    {
        return $this->rows = DB::table('facetrows')
        ->select('subject_id', 'value')
        ->where('facet_slug', $this->getSlug())
        ->get();
    }

    public function getRows(): EloquentCollection
    {
        if (is_null($this->rows)) {
            // FacetFilter::fillFacetRows($this->subject_type);
            $this->rows = $this->getFacetRowsFromDB();
        }

        return $this->rows;
    }

    public function getOptions(): Collection
    {
        if (is_null($this->options)) {
            $facetName = $this->getParamName();
            $subjectType = $this->subject_type;

            // find out totals of the values in this facet
            // *within* the current query / filter operation.
            // in short: apply all the filters EXCEPT the one involving this facet.

            // https://stackoverflow.com/questions/27550841/calculating-product-counts-efficiently-in-faceted-search-with-php-mysql

            $idsInFilteredQuery = [];
            if ($lastQuery = FacetFilter::getLastQuery($this->subject_type)) {
                $idsInFilteredQuery = $lastQuery->getIdsInQueryWithoutFacet($this);
            }

            $values = [];
            foreach ($this->getRows() as $row) {
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
                ]);
            }

            $this->options = $options;
        }

        return $this->options;
    }

    public function getNonMissingOptions(): Collection
    {
        return $this->getOptions()->filter(fn ($value) => $value->total > 0);
    }

    public function getParamName(): string
    {
        return Str::slug($this->title);
    }

    public function getSlug(): string
    {
        return implode('.', [$this->subject_type, $this->fieldname]);
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;

        return $this;
    }
}
