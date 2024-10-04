<?php

return [

    'classes' => [
        'facet' => Mgussekloo\FacetFilter\Models\Facet::class,
        'facetrow' => Mgussekloo\FacetFilter\Models\FacetRow::class,
    ],

    'table_names' => [
		'facetrows' => 'facetrows',
	],

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'mgussekloo.facetfilter.cache',
        'store' => 'array',
    ],

];