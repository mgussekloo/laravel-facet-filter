<?php

namespace Mgussekloo\FacetFilter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacetRow extends Model
{
	protected $table = 'facetrows';

    protected $fillable = [
        'facet_slug',
        'subject_id',
        'value',
    ];

    public function facet(): BelongsTo
    {
        return $this->belongsTo(Facet::class);
    }
}
