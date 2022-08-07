<?php

namespace Mgussekloo\FacetFilter;

use Mgussekloo\FacetFilter\Facades\FacetFilter;

use Mgussekloo\FacetFilter\Models\Facet;
use Mgussekloo\FacetFilter\Models\FacetRow;

use Illuminate\Http\Request;

use DB;

class Indexer
{

	public $models = null;

	public function __construct($models)
	{
		$this->models = $models;
	}

	public function resetIndex()
	{
        DB::table('facetrows')->truncate();
        return $this;
	}

	public function buildIndex()
	{
		if (!is_null($this->models) && $this->models->isNotEmpty()) {
			$subjectType = get_class($this->models->first());

			$facets = FacetFilter::getFacets($subjectType);

	        foreach ($this->models as $model) {
	            foreach ($facets as $facet) {
	                $values = [];

	                $fields = explode('.', $facet->fieldname);

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
	                    FacetRow::create([
	                        'facet_slug' => $facet->getSlug(),
	                        'subject_id' => $model->id,
	                        'value' => $value
	                    ]);
	                }
	            }
	        }
		}
	}

}