<?php

namespace Mgussekloo\FacetFilter\Models;

use Illuminate\Database\Eloquent\Model;

class FacetRow extends Model
{
	protected $table = 'facetrows';

    protected $fillable = [
        'facet_slug',
        'subject_id',
        'value',
    ];

    public function facet()
    {
        return $this->belongsTo(Facet::class);
    }
}
