# Laravel Facet Filter

This package intends to make it easy to implement facet filtering in a Laravel project.
Since I couldn't find many alternatives, I decided to release this package as a way to help others.

### Is this better than using Algolia / Meilisearch / whatever?

Probably not.

### Todo

There is lots of room for improvement in this package. Please feel free to help out.

### Installation

This package can be installed through Composer.

``` bash
composer require mgussekloo/laravel-facet-filter
```

## Usage - prepare your project

### Publish and run the migrations

``` bash
php artisan vendor:publish --provider="mgussekloo\laravel-facet-filter"
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
		Master::defineFacet(
			'Main color',
			'color'
		);

		/* You can use dot notation to get a value from related models. */
		Master::defineFacet(
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

        if ($currentPage == 0) {
            $indexer->resetIndex();
        }

        $indexer->buildIndex();
        if ($products->hasMorePages()) {}
        	$currentPage = $currentPage + 1;
        	// next iteration
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

		/* $facets is a regular laravel collectio, so it's easy to iterate all of them, or find the one you need.
		Each facet has a method to get a Laravel collection of option objects, to help you build your frontend. */
		$singleFacet = $facets->firstWhere('fieldname', 'color');

		return view('home')->with([
			'facets' => $facets,
			'facet' => $singleFacet
		]);
	}
}

```

### Frontend example

You'll have to build the frontend yourself. Here's an example of how this may work.
Each facet has a getOptions() method that returns all the possible options for that particular facet.

In order to select a facet, send the correct GET-param, e.g. "?filter[main-color][0]=green"
back.

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
	/* Returns an array with the current filter, based on all the available facets for this model,
	and the specified (optional) GET parameter (default is "filter"). A facet's title is
	its key in the GET parameter.
	e.g. /?filter[main-color][0]=green will result in:
	[ 'main-color' => [ 'green' ], 'size' => [ ] ]
	*/
	$filter = Product::getFilterFromParam();

	/* Maybe you want to use this notation instead...
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

