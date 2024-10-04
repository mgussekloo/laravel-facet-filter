<?php

namespace Mgussekloo\FacetFilter;

use DB;

use Mgussekloo\FacetFilter\Facades\FacetFilter;

class Indexer
{
    public $models;
    public $facetClass;
    public $facetRowClass;

    public function __construct($models = null)
    {
        $this->models = $models;
        $this->facetClass = config('facet-filter.classes.facet');
        $this->facetRowClass = config('facet-filter.classes.facetrow');

    }

    public function buildRow($facet, $model, $value)
    {
        return [
            'facet_slug' => $facet->getSlug(),
            'subject_id' => $model->id,
            'value' => $value,
        ];
    }

    public function buildValues($facet, $model)
    {
		$values = [];

    	if (isset($facet->fieldname)) {
	    	$fields = explode('.', (string) $facet->fieldname);

	        if (count($fields) == 1) {
	            $values = collect([$model->{$fields[0]}]);
	        } else {
	            $last_key = array_key_last($fields);

	            $values = collect([$model]);
	            foreach ($fields as $key => $field) {
	                $values = $values->pluck($field);
	                if ($key !== $last_key) {
	                    $values = $values->flatten(1);
	                }
	            }
	        }

	        return $values->toArray();
	    }

        return $values;
    }

    public function insertRows($rows)
    {
    	$chunks = array_chunk($rows, 1000);
    	foreach ($chunks as $chunk) {
        	$this->facetRowClass::insert(array_values($chunk));
        }
    }

    public function resetRows($models = null): self
    {
        if (is_null($models) || $models->isEmpty()) {
            return $this->resetIndex();
        }

        foreach ($models as $model) {
            FacetFilter::getFacets($model::class, false, false)->each(function ($facet) use ($model) {
                $this->facetRowClass::where('subject_id', $model->id)
                ->where('facet_slug', $facet->getSlug())
                ->delete();
            });
        }

		FacetFilter::forgetCache();

        return $this;
    }

    public function resetIndex()
    {
        $this->facetRowClass::truncate();

		FacetFilter::forgetCache();

        return $this;
    }

    public function buildIndex($models = null)
    {
        if (!is_null($models)) {
        	$this->models = $models;
        }

        if (! is_null($this->models) && $this->models->isNotEmpty()) {
            $subjectType = $this->models->first()::class;

            $facets = FacetFilter::getFacets($subjectType, false, false);

            $now = now();
            $rows = [];
            foreach ($this->models as $model) {
                foreach ($facets as $facet) {
                    $values = $this->buildValues($facet, $model);

                    if (!is_array($values)) {
                    	$values = [$values];
                    }

                    foreach ($values as $value) {
                        if (is_null($value)) {
                            continue;
                        }

                        $uniqueKey = implode('.', [$facet->getSlug(), $model->id, $value]);
                        $row = $this->buildRow($facet, $model, $value);
                        $row = array_merge([
                            'created_at' => $now,
                            // 'updated_at' => null,
                        ], $row);
                        $rows[$uniqueKey] = $row;
                    }
                }
            }

            $this->insertRows($rows);
        }

        return $this;
    }
}
