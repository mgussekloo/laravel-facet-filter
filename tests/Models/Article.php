<?php

namespace Mgussekloo\FacetFilter\Tests\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Mgussekloo\FacetFilter\Traits\Facettable;

class Article extends Model
{
    use Facettable;
    use HasUlids;

    protected $table = 'articles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['title', 'category', 'status'];

    public static function facetDefinitions(): array
    {
        return [
            ['title' => 'Category', 'fieldname' => 'category'],
            ['title' => 'Status', 'fieldname' => 'status'],
        ];
    }
}