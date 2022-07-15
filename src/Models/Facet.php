<?php

namespace Mgussekloo\FacetFilter\Models;

use Illuminate\Database\Eloquent\Model;

use DB;
use Str;
use Cache;
use FacetFilter;

class Facet extends Model
{
    protected $fillable = [
        'title',
        'fieldname',
        'facet_type',
        'subject_type',
    ];

    public $currentQuery = null;
    public $options = null;

    public function getOptions()
    {

        if (is_null($this->options)) {
            $values = Cache::remember('facet_' . $this->id, 60, function() {
                $query = DB::table('facetrows')
                ->select('value',  DB::raw('0 as total'))
                ->where('facet_id', $this->id)
                ->where('value', '<>', '')
                ->groupBy('value');

                return $query->get()->pluck('total', 'value')->toArray();
            });

            // now, find out totals of the values in this facet
            // but *within* the current query / filter operation.
            // we need to apply all the filters EXCEPT the one involving this facet.
            $subjectIds = null;
            if (!is_null($this->currentQuery)) {
                list($query, $facets, $filter) = $this->currentQuery;
                $key = $this->getParamName();
                if (isset($filter[$key]) && !empty($filter[$key])) {
                    $filter[$key] = [];
                    $query = FacetFilter::constrainQueryWithFacetFilter($query, $facets, $filter);
                    $subjectIds = $query->select('id')->get()->pluck('id')->toArray();
                }
            }

            // now get the facet counts
            $query = DB::table('facetrows')
            ->select('value',  DB::raw('count(*) as total'))
            ->where('facet_id', $this->id)
            ->where('value', '<>', '')
            ->groupBy('value')
            ->when(is_array($subjectIds), function($query) use ($subjectIds) {
                $query->whereIn('subject_id', $subjectIds);
            });

            $updatedValues = $query->get()->pluck('total', 'value')->toArray();
            $values = array_replace($values, $updatedValues);

            $options = collect([]);

            if (is_null($this->filter)) {
                $this->filter = FacetFilter::getFilterFromParam($this->subject_type);
            }

            $filteredValues = $this->filter[$this->getParamName()];

            foreach ($values as $value => $total) {
                $options->push((object)[
                    'value' => $value,
                    'selected' => in_array($value, $filteredValues),
                    'total' => $total,
                    'slug' =>  sprintf('%s_%s', Str::of($this->fieldname)->slug('-'), Str::of($value)->slug('-'))
                ]);
            }

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

    public function hasOptions()
    {
        return $this->getOptions()->isNotEmpty();
    }

    public function setCurrentQuery($query, $facets, $filter)
    {
        $this->currentQuery = [$query, $facets, $filter];
        return $this;
    }

    public function getParamName()
    {
        return Str::slug($this->title);
    }
}
