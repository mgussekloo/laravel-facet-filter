# Laravel Facet Filter

This package provides simple facet filtering (sometimes called Faceted Search or Faceted Navigation) in Laravel projects. It helps narrow down query results based on the attributes of your models.

- Free, no dependencies
- Easy, no complex queries to write
- Includes everything to get started
- Flexible

![Demo](https://raw.githubusercontent.com/mgussekloo/laravel-facet-filter/master/demo.gif)

### Contributing

Feel free to contribute to this package, either by creating a pull request or reporting an issue.

### Installation

This package can be installed through Composer.

``` bash
composer require mgussekloo/laravel-facet-filter
```

## Prepare your project

### Publish and run the migrations

``` bash
php artisan vendor:publish --tag="facet-filter-migrations"
php artisan migrate
```

### Update your models

For all models that should support facet filtering, add a Facettable trait and
a defineFacets() method. This method returns an array containing one or more facet definitions. A definition is a title and a model property
from which to pull the value(s) for each facet.

``` php
use Illuminate\Database\Eloquent\Model;
use Mgussekloo\FacetFilter\Traits\Facettable;

class Product extends Model
{
    use Facettable;

    public static function defineFacets()
    {
        return [
            [
                'Main color', /* Title of the facet */
                'color' /* Model property from which to get values */
            ],
            [
                'Size',
                'sizes.name' /* Use dot notation to get the value from related models. */
            ]
    }

    ...

```

### Build the index

You have to build the index before you can start filtering. You can, for example, create a command and run it every time you update the data.

``` php

/* Minimal example */
$products = Product::with(['sizes'])->get();
$indexer = new Mgussekloo\FacetFilter\Indexer($products);
$indexer->resetIndex(); // clears the index
$indexer->buildIndex(); // process all supplied models

```

## Start filtering

### Apply facet filtering to a query

Use the facetsMatchFilter($filter) method.

``` php
/* Get filter from any array, such as the request parameters. E.g. /?main-color=green&size=[s,m] becomes [ 'main-color' => [ 'green' ], 'size' => [ 's', 'm' ] ] */

$filter = Product::getFilterFromArr(request()->all());
$products = Product::facetsMatchFilter($filter)->get();
```

### Display the output

There is no frontend included but there are helper methods to make displaying easy.

``` php
/* Get info about the facets. The facets have the correct option counts for the last queried results. */
$facets = Product::getFacets();

/* You can filter and sort like any regular Laravel collection. */
$singleFacet = $facets->firstWhere('fieldname', 'color');

/* A facet has a title, param name, and options. */
$title = $singleFacet->title; // "Main color"
$paramName = $singleFacet->getParamName(); // "main-color"
$options = $singleFacet->getOptions();

/*
Example of options:
[
    (object)[
        'value' => 'Red'
        'selected' => false,
        'total' => 3
        'slug' => 'color_red'
    ],
    (object)[
        'value' => 'Green'
        'selected' => true
        'total' => 2
        'slug' => 'color_green'
    ]
*/

```

This is how it could look using Laravel Livewire to communicate a selected option to the backend. You could use any AJAX request, form submit or whatever you like.

``` html
<h2>{{ $facet->title }}</h2>
@foreach ($facet->getOptions() as $option)
    <div class="facet-checkbox-pill">
        <input
            wire:model="filter.{{ $facet->getParamName() }}"
            type="checkbox"
            id="{{ $option->slug }}"
            value="{{ $option->value }}"
        />
        <label for="{{ $option->slug }}" class="{{ $option->selected ? 'selected' : '' }}">
            {{ $option->value }} ({{ $option->total }})
        </label>
    </div>
@endforeach
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

