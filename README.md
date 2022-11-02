# Laravel Facet Filter

This package provides simple facet filtering (sometimes called Faceted Search or Faceted Navigation) in Laravel projects. It helps narrow down query results, based on the attributes of your models. I wanted to provide something that is free, relatively easy to setup, and doesn't have any dependencies.

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

### Update your model

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
namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Product;
use Mgussekloo\FacetFilter\Indexer;

class IndexFacets extends Command
{

    public function handle()
    {
        /* Build the whole index in one go */
        $products = Product::with(['sizes'])->get();
        $indexer = new Indexer($products);

        $indexer->resetIndex(); // clears the index
        $indexer->buildIndex(); // process all supplied models
    }
}
```

Alternatively, for very large datasets you might want to process models in chunks.

```php
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

A local scope on the Facettable trait, facetsMatchFilter(), applies the filter to the query.

``` php
/* Get the filter from the request, e.g. /?main-color=green&size=[s,m] becomes [ 'main-color' => [ 'green' ], 'size' => [ 's', 'm' ] ] */
$arr = request()->all();
$filter = Product::getFilterFromArr($arr);

/* Or from a single query parameter, e.g. /?filter[main-color][0]=green becomes [ 'main-color' => [ 'green' ], 'size' => [ ] ] */
$arr = request()->query('filter');
$filter = Product::getFilterFromArr($arr)

/* Build your query!*/
$products = Product::facetsMatchFilter($filter)->get(); // You can also apply the facets to the subsection of models, e.g. Product::where('discounted', true)->facetsMatchFilter($filter)->get()
```

### Display the facets

This package doesn't have any opinions about the frontend you should use. This is how you can render the facets:

``` php
/* Get the facets to display them in your frontend. Calling getFacets() after you've called facetsMatchFilter() lets the facets have the correct option counts for the queried results. */
$facets = Product::getFacets($filter);

/* It returns a Laravel collection! */
$singleFacet = $facets->firstWhere('fieldname', 'color');

/* A getOptions() method helps you grab the info you need to render the facet. */
$options = $singleFacet->getOptions();

/* Options have these properties: value, slug, selected (whether it's selected in the $filter), total (total occurrences within current results).
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

/* There's some other info you can get from a facet, such as the title and the identifying key for the filter. */

$title = $singleFacet->title; // "Main color"
$paramName = $singleFacet->getParamName(); // "main-color"

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

The above example uses Laravel Livewire's wire:model directive to communicate a selected option to the backend, but you could use an AJAX request, form submit or whatever you like. Just make sure to build a correct $filter so you can apply the facetsMatchFilter() scope to refine your query!

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

