# Laravel Facet Filter

This package intends to make it easy to implement facet filtering in a Laravel project.
Since I couldn't find many alternatives I provide this package as a way to help others.

### Todo

There is certainly room for improvement in this package. Feel free to contribute!

### Installation

This package can be installed through Composer.

``` bash
composer require mgussekloo/laravel-facet-filter
```

## Usage - prepare your project

### Publish and run the migrations

``` bash
php artisan vendor:publish --tag="facet-filter-migrations"
php artisan migrate
```

### Update your model

Add the trait to your model.

``` php
use Illuminate\Database\Eloquent\Model;
use Mgussekloo\FacetFilter\Traits\Facettable;

class Product extends Model
{
    use Facettable;

    protected $fillable = ['name', 'color'];

    public function sizes() {
        return $this->hasMany('sizes');
    }
}
```

### Define the facets

Insert the facet definitions into the "facets" table. Remember that you only need to insert the definitions once.
You can insert the rows any way you want, but the Facettable trait includes a handy defineFacet method.

``` php
namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Product;

class DefineFacets extends Command
{

    public function handle()
    {
        /* Creates a row in the "facets" table. Takes the title and the field on the model
        that contains the value to index. The title will be visible as the key in the GET parameter. */
        Product::defineFacet(
            'Main color',
            'color'
        );

        /* You can use dot notation to get a value from related models. */
        Product::defineFacet(
            'Size',
            'sizes.name'
        );
    }
}
```

### Build the index

Build an index for the facets, using the "facetrows" table. The indexer provided takes a Laravel collection of models and iterates over each facet, and each model.
You can do this once, or come up with a solution that does this periodically.

``` php
namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Product;
use Mgussekloo\FacetFilter\Indexer;

class IndexFacets extends Command
{

    public function handle()
    {
        /* Build the whole index once */
        $products = Product::with(['sizes'])->get();

        $indexer = new Indexer($products);

        $indexer->resetIndex(); // clears the index
        $indexer->buildIndex(); // process all supplied models

        /* Or come up with way to build it in chunks, e.g. in a scheduled command.
        Each iteration, do this: */

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

## Usage - using the facets

### Get the facets

Get a list of facets, so you can show them in a frontend as a facet filter.

``` php
namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

use App\Models\Product;

class HomeController extends BaseController
{

    public function home()
    {
        /* Returns a Laravel collection of the facets for this model. */
        $facets = Product::getFacets();

        /* Since it's a Laravel collection, you can iterate or find the one you need easily.
        Each facet has a method to get a Laravel collection of the available options, to help you build your frontend. */
        $singleFacet = $facets->firstWhere('fieldname', 'color');

        return view('home')->with([
            'facets' => $facets,
            'facet' => $singleFacet
        ]);
    }
}
```

### Frontend example

You'll have to build the frontend yourself, this is just an example. You're
responsible for setting the correct GET-parameter when a user toggles a facet option.
This example uses Laravel Livewire's wire:model directive.

``` html
@php
    $paramName = $facet->getParamName();
@endphp

<h2>{{ $facet->title }}</h2>
@foreach ($facet->getOptions() as $option)
    <div class="facet-checkbox-pill">
        <input
            wire:model="filter.{{ $paramName }}"
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

### Use facet filtering in a query

``` php
/* Get the empty filter array for this model, and fill it with the GET parameter (default is "filter").
A facet's title is its key in the GET parameter.
e.g. /?filter[main-color][0]=green will result in:
[ 'main-color' => [ 'green' ], 'size' => [ ] ]
*/
$filter = Product::getFilterFromParam();

/* You can also use another array to fill the filter array.
e.g. /?main-color=[green]&size=[s,m]
*/
$anotherFilter = Product::getFilterFromArr(request()->all());

/* Apply the filter to a query using the facetsMatchFilter() scope on the model. */
$products = Product::where('discounted', true)->facetsMatchFilter($filter);

/* After running a query with facetsMatchFilter(), grabbing the facets will take the applied
filter into account automagically. */
$facets = Product::getFacets();
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

