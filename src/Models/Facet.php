<?php

namespace Mgussekloo\FacetFilter\Models;

use Illuminate\Database\Eloquent\Model;

use Mgussekloo\FacetFilter\Collections\FacetCollection;

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

    public $subjectIds = [];
    public $options = null;

    public function getOptions()
    {
        if (is_null($this->options)) {
            $query = DB::table('facetrows')
            ->select('value',  DB::raw('0 as total'))
            ->where('facet_id', $this->id)
            ->where('value', '<>', '')
            ->groupBy('value');

            $values = $query->get()->pluck('total', 'value')->toArray();

            $query = DB::table('facetrows')
            ->select('value',  DB::raw('count(*) as total'))
            ->where('facet_id', $this->id)
            ->where('value', '<>', '')
            ->groupBy('value')
            ->when(!empty($this->subjectIds), function($query) {
                $query->whereIn('subject_id', $this->subjectIds);
            });

            $values = array_replace(
                $values,
                $query->get()->pluck('total', 'value')->toArray()
            );

            $options = collect([]);

            $filter = FacetFilter::getFilterFromRequest($this->subject_type);
            $filteredValues = $filter[$this->getParamName()];

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

    public function limitToSubjectIds($subjectIds = [])
    {
        $this->subjectIds = $subjectIds;
        return $this;
    }

    public function getParamName()
    {
        return Str::slug($this->title);
    }

    public function newCollection(array $models = [])
    {
        return new FacetCollection($models);
    }
}
