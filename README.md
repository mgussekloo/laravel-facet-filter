# Laravel Facet Filter

This package provides simple facet filtering in Laravel projects.

### Contributing

Feel free to contribute to this package, either by creating a pull request
or reporting an issue.

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

Add the Facettable trait to the model(s) for which you want to enable facet filtering.

``` php
use Illuminate\Database\Eloquent\Model;
use Mgussekloo\FacetFilter\Traits\Facettable;

class Product extends Model
{
    use Facettable;

    ...

```

### Define the facets

Define the facets for each model. For each facet, provide a title and the property on the model
from which to pull the values.

``` php
namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Product;

class DefineFacets extends Command
{

    public function handle()
    {
        /* Creates a row in the "facets" table, defining the facet title and the
        property that contains the value to index. */
        Product::defineFacet(
            'Main color',
            'color'
        );

        /* You can use dot notation to get the value from related models. */
        Product::defineFacet(
            'Size',
            'sizes.name'
        );
    }
}
```

### Build the index

This package includes a simple indexer that iterates over a number of models, populating the facetrows table based on
the facet definitions.

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

        /* For large datasets, you might want to build it in chunks,
        e.g. in a scheduled command. Each iteration, do this: */

        $perPage = 1000;
        $currentPage = ...;

        $products = Product::with(['sizes'])->paginate($perPage, ['*'], 'page', $currentPage);

        $indexer = new Indexer($products);

        if ($currentPage == 1) {
            $indexer->resetIndex();
        }

        $indexer->buildIndex();

        if ($products->hasMorePages()) {}
            // next iteration, increase currentPage with one
        } else {
            // stop iterating
        }
    }
}
```

## Usage

### Get the filter

The filter array is a key-value array for all the facets of a particular model, keyed by the slug of the facet title.

``` php
/* Get the filter from the query parameter (default: "filter").
The key is the facet title, e.g. /?filter[main-color][0]=green will result in:
[ 'main-color' => [ 'green' ], 'size' => [ ] ] */
$filter = Product::getFilterFromParam();

/* Or build the filter from an array... e.g. /?main-color=[green]&size=[s,m] */
$anotherFilter = Product::getFilterFromArr(request()->all());
```

### Apply facet filtering to a query

A local scope on the Facettable trait, facetsMatchFilter, can be used to
apply the current filter to any query.

``` php
/* Apply the filter to a query. */
$products = Product::facetsMatchFilter($filter);

/* Apply the filter to a query. */
$products = Product::where('discounted', true)->facetsMatchFilter($filter)->get();

/* Afterwards, grabbing the facets will take your query and filter into account automagically so
the correct totals show in your frontend. */
$facets = Product::getFacets();
```

### Displaying facets

The getFacets method returns a Laravel collection of Facets you can use to build a frontend.

``` php
/* Returns a Laravel collection of the facets for this model. */
$facets = Product::getFacets();

/* If you want to show which options were selected, pass the current filter to the method. */
$facets = Product::getFacets($filter);

/* Since it's a Laravel collection, you can iterate or find the one you need easily. */
$singleFacet = $facets->firstWhere('fieldname', 'color');

```

### Frontend

This package doesn't include any frontend, so you are free to set it up how you like.

The facets include the basic information you need, and each facet has a getOptions method
which returns a Laravel collection of the possible options for this facet.

Each option has these properties: value, slug, selected (whether it's included in the filter), total (total occurences within current results).

Make sure you update the query parameters yourself, so that you can build the correct filter.
This example uses Laravel Livewire's wire:model directive, which makes it easy.

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

