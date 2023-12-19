<?php

namespace Mgussekloo\FacetFilter;

use DB;
use Mgussekloo\FacetFilter\Facades\FacetFilter;
use Mgussekloo\FacetFilter\Models\FacetRow;

class Indexer
{
    public $models;

    public function __construct($models = null)
    {
        $this->models = $models;
    }

    public function buildRow($facet, $model, $value)
    {
        return [
            'facet_slug' => $facet->getSlug(),
            'subject_id' => $model->id,
            'value' => $value,
        ];
    }

    public function insertRows($rows)
    {
        return FacetRow::insert(array_values($rows));
    }

    public function resetIndex()
    {
        DB::table('facetrows')->truncate();

        return $this;
    }

    public function buildIndex($models = null)
    {
        if (is_null($models)) {
        	$this->models = $models;
        }

        if (! is_null($this->models) && $this->models->isNotEmpty()) {
            $subjectType = $this->models->first()::class;

            $facets = FacetFilter::getFacets($subjectType);

            $now = now();
            $rows = [];
            foreach ($this->models as $model) {
                foreach ($facets as $facet) {
                    $values = [];

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
