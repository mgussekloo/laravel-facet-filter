<?php

namespace Mgussekloo\FacetFilter\Facades;

use Illuminate\Support\Facades\Facade;

class FacetCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'facetcache';
    }
}
