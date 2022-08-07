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

For all models that should support facet filtering, add a Facettable trait and
a defineFacets() method. This method should return a title and model property
from which to pull the value(s) for each facet.

``` php
use Illuminate\Database\Eloquent\Model;
use Mgussekloo\FacetFilter\Traits\Facettable;

class Product extends Model
{
    use Facettable;

    public function defineFacets()
    {
        return [
            [
                'Main color',
                'color'
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

For very large datasets you might want to build it in chunks, e.g. in a scheduled command.

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

### Get the filter

The filter array is a key-value array for all the facets of a particular model, keyed by the slug of the facet title.

``` php
/* Get the filter from the query parameter (default: "filter").
The key is the facet title, e.g. /?filter[main-color][0]=green will result in:
[ 'main-color' => [ 'green' ], 'size' => [ ] ] */
$filter = Product::getFilterFromParam();

/* Get the filter from an array... e.g. /?main-color=green&size=[s,m] */
$filter = Product::getFilterFromArr(request()->all());
```

### Apply facet filtering to a query

A local scope on the Facettable trait, facetsMatchFilter, applies the current filter to a query.

``` php
/* Apply the filter to a query. */
$products = Product::facetsMatchFilter($filter)->get();

/* Or ... */
$products = Product::where('discounted', true)->facetsMatchFilter($filter)->pluck('id');

/* Calling getFacets() after facetsMatchFilter() takes the last query
into account automagically, so that the facet options will show the correct count
for the current results. */
$facets = Product::getFacets($filter);
```

### Displaying the facets

This package doesn't include a frontend. You are free to set it up how you like.

The getFacets() method takes a $filter argument and returns a collection of facets.
Each facet has a title and a getOptions() method which returns all the possible options for this facet.
Each option has these properties: value, slug, selected (whether it's included in the filter), total (total occurences within current results).

``` php
/* Get the facets for a model. */
$facets = Product::getFacets($filter);

/* Since the method returns a collection, you can iterate or find the one you need easily. */
$singleFacet = $facets->firstWhere('fieldname', 'color');

/* Get the options for a facet */
$options = $singleFacet->getOptions();

/* Example value:
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

To let the user select facets you will have to update the correct query parameter(s).
You could use something like a form submit or AJAX request. The example below uses Laravel Livewire's wire:model directive which makes it very easy.

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

