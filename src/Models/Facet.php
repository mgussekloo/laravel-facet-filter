<?php

namespace Mgussekloo\FacetFilter\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\DB;
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

    public $subjectIds;

    public function getValues()
    {
        $cacheKey = implode('_', ['facet', $this->id, md5(serialize($this->subjectIds))]);

        $values = Cache::remember($cacheKey, 3600, function() {
            $query = DB::table('facetrows')
            ->select('value',  DB::raw('0 as total'))
            ->where('facet_id', $this->id)
            ->where('value', '<>', '')
            ->groupBy('value');

            $values = $query->get()->pluck('total', 'value')->toArray();

            if (is_array($this->subjectIds)) {
                $query = DB::table('facetrows')
                ->select('value',  DB::raw('count(*) as total'))
                ->where('facet_id', $this->id)
                ->where('value', '<>', '')
                ->groupBy('value')
                ->whereIn('subject_id', $this->subjectIds);

                $values = array_replace(
                    $values,
                    $query->get()->pluck('total', 'value')->toArray()
                );
            }

            return $values;
        });

        $result = collect([]);

        $filter = FacetFilter::getFilterFromRequest($this->subject_type);
        $filteredValues = $filter[$this->getParamName()];

        foreach ($values as $value => $total) {
            $result->push((object)[
                'value' => $value,
                'selected' => in_array($value, $filteredValues),
                'total' => $total,
                'slug' =>  sprintf('%s_%s', Str::of($this->fieldname)->slug('-'), Str::of($value)->slug('-'))
            ]);
        }

        return $result;
    }

    public function getNonMissingValues()
    {
        return $this->getValues()->filter(function($value) {
            return $value->total > 0;
        });
    }

    public function hasValues()
    {
        return $this->getValues()->isNotEmpty();
    }

    public function limitToIds($ids = [])
    {
        $this->subjectIds = $ids;
        return $this;
    }

    public function getParamName()
    {
        return last(explode('.', $this->fieldname));
    }
}
