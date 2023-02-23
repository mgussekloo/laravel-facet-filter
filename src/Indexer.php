<?php

namespace Mgussekloo\FacetFilter;

use DB;
use Mgussekloo\FacetFilter\Facades\FacetFilter;
use Mgussekloo\FacetFilter\Models\FacetRow;

class Indexer
{
    public function __construct(public $models)
    {
    }

    public function resetIndex()
    {
        DB::table('facetrows')->truncate();

        return $this;
    }

    public function buildIndex()
    {
        if (! is_null($this->models) && $this->models->isNotEmpty()) {
            $subjectType = $this->models->first()::class;

            $facets = FacetFilter::getFacets($subjectType);

            $rows = [];
            $now = now();
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
                        $uniqueKey = implode('.', [$facet->getSlug(), $model->id, $value]);
                        $rows[$uniqueKey] = [
                            'facet_slug' => $facet->getSlug(),
                            'subject_id' => $model->id,
                            'value' => $value,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            FacetRow::insert(array_values($rows));
        }

        return $this;
    }
}
