<?php

namespace Mgussekloo\FacetFilter;

use Illuminate\Support\Facades\Facade;

class FacetFilterFacade extends Facade
{

    protected static function getFacadeAccessor() { return 'facetfilter'; }

}