# Laravel Facet Filter

This package provides simple facet filtering (sometimes called Faceted Search or Faceted Navigation) in Laravel projects. It helps narrow down query results based on the attributes of your models.

- Free to use, no dependencies
- Easy to set up
- Includes everything to get started, no need to write complex queries yourself
- Flexible

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

The included simple indexer iterates over models, populating the facetrows table based on the facet definitions.

``` php
use App\Models\Product;
use Mgussekloo\FacetFilter\Indexer;

/* Example one: Iterate over all the models in one go. */
$products = Product::with(['sizes'])->get();
$indexer = new Indexer($products);

$indexer->resetIndex(); // clears the index
$indexer->buildIndex(); // process all supplied models

/* Example two: Process models in chunks. */

$perPage = 1000; $currentPage = ...;

$products = Product::with(['sizes'])->paginate($perPage, ['*'], 'page', $currentPage);
$indexer = new Indexer($products);

if ($currentPage == 1) {
    $indexer->resetIndex();
}

$indexer->buildIndex();

if ($products->hasMorePages()) {}
    // next iteration, increase currentPage with one
}
```

## Usage

### Apply facet filtering to a query

The filter needs to be an array like: `[ 'main-color' => [ 'green' ], 'size' => [ 's', 'm' ] ]`. A helper method to build the filter is provided.

``` php
/* Example one: Get filter from the request, e.g. /?main-color=green&size=[s,m] becomes  [ 'main-color' => [ 'green' ], 'size' => [ 's', 'm' ] ]*/
$filter = Product::getFilterFromArr(request()->all());
$products = Product::facetsMatchFilter($filter)->get();

/* Example two: Make yourself */
$filter = Product::getFilterFromArr(['main-color' => 'green']);
$products = Product::where('discounted', true)->facetsMatchFilter($filter)->get();
```

### Display the facets

There is no frontend included, but there are helper methods to make building a frontend easy.

``` php
/* Get the facets to display them in your frontend. Calling getFacets() after you've called facetsMatchFilter() lets the facets have the correct option counts for the queried results. */
$facets = Product::getFacets();

/* It returns a Laravel collection! */
$singleFacet = $facets->firstWhere('fieldname', 'color');

/* A facet has a title, param name, and options. */
$title = $singleFacet->title; // "Main color"
$paramName = $singleFacet->getParamName(); // "main-color"

/* The getOptions() method provides an array of options, with useful info such as the total result count within the current query. */
$options = $singleFacet->getOptions();

/*
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

This is how it could look:

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

The above example uses Laravel Livewire's wire:model directive to communicate a selected option to the backend. You could use an AJAX request, form submit or whatever you like.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

