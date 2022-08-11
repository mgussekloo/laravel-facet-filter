<?php

namespace Mgussekloo\FacetFilter\Models;

use Illuminate\Database\Eloquent\Model;

use Mgussekloo\FacetFilter\Facades\FacetFilter;

use DB;
use Str;

class Facet extends Model
{
    protected $fillable = [
        'title',
        'fieldname',
        'subject_type',
    ];

    public $options = null;
    public $lastQuery = null;
    public $filter = null;

    public function getOptions()
    {
        if (is_null($this->options)) {
            $facetName = $this->getParamName();
            $subjectType = $this->subject_type;

            $facetrows = DB::table('facetrows')
            ->select('subject_id', 'value')
            ->where('facet_slug', $this->getSlug())
            ->where('value', '<>', '')
            ->get();

            // find out totals of the values in this facet
            // *within* the current query / filter operation.
            // in short: apply all the filters EXCEPT the one involving this facet.
            // https://stackoverflow.com/questions/27550841/calculating-product-counts-efficiently-in-faceted-search-with-php-mysql

            $idsInFilteredQuery = [];

            if (!is_null($this->lastQuery)) {
                list($query, $filter) = $this->lastQuery;

                if (isset($filter[$facetName])) {
                    $filter[$facetName] = [];
                }

                $idsInFilteredQuery = FacetFilter::getIdsInFilteredQuery($subjectType, $query, $filter);
            }

            $values = [];
            foreach ($facetrows as $row) {
                if (!isset($values[$row->value])) {
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
                $options->push((object)[
                    'value' => $value,
                    'selected' => in_array($value, $selectedValues),
                    'total' => $total,
                    'slug' =>  sprintf('%s_%s', Str::of($this->fieldname)->slug('-'), Str::of($value)->slug('-'))
                ]);
            }

            $options = $options;

            $this->options = $options;
        }
        return $this->options;
    }

    public function getNonMissingOptions()
    {
        return $this->getOptions()->filter(function($value) {
            return $value->total > 0;
        });
    }

    // public function hasOptions()
    // {
    //     return $this->getOptions()->isNotEmpty();
    // }

    public function getParamName()
    {
        return Str::slug($this->title);
    }

    public function getSlug()
    {
        return implode('.', [$this->subject_type, $this->fieldname]);
    }

    public function setLastQuery($query, $filter)
    {
        $this->lastQuery = [clone $query, $filter];
        $this->options = null;

        return $this;
    }

    public function setFilter($filter) {
        $this->filter = $filter;

        return $this;
    }
}
