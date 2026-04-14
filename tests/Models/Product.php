<?php

namespace Mgussekloo\FacetFilter\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Mgussekloo\FacetFilter\Traits\Facettable;

class Product extends Model
{
    use Facettable;

    protected $table = 'products';

    protected $fillable = ['name', 'color', 'size'];

    public static function facetDefinitions(): array
    {
        return [
            ['title' => 'Color', 'fieldname' => 'color'],
            ['title' => 'Size', 'fieldname' => 'size'],
        ];
    }
}