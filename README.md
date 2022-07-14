# Laravel Facet Filter

---

This package intends to make it easy to implement facet filtering in a Laravel project.
Since I couldn't find many alternatives, I decided to release this package as a way to help others.

## Is this better than using Algolia / Meilisearch / whatever?

Probably not.

## Todo

There is lots of room for improvement in this package. Please feel free to help out.

## Installation

This package can be installed through Composer.

``` bash
composer require mgussekloo/laravel-facet-filter
```

## Prepare your project

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

Insert the facet definitions into the "facets" table. You can use a helper method on your facettable model. As an example, consider this custom command.
Remember that you only need to insert the definitions once.

``` php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Product;

class DefineFacets extends Command
{

    public function handle()
    {
    	Master::defineFacet(
    		'Main color',
    		'color'
    	);
    	/* Creates an entry in the "facets" table. Takes the title and the field on the model
    	that contains the value to index.
    	The title will be visible as the key in the GET parameter. */

    	Master::defineFacet(
    		'Size',
    		'sizes.name'
    	);
    	/* You can use dot notation to get a field from related models. */
    }
}

```

### Build the index

Build an index for the facets, using the "facetrows" table. The indexer provided takes a Laravel collection of models and iterates over each facet, and each model.
You could write a scheduled command that initially resets the index, and then processes chunks of models only when necessary.

``` php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Product;
use Mgussekloo\FacetFilter\Indexer;

class IndexFacets extends Command
{

    public function handle()
    {
    	$products = Product::with(['sizes'])->get();

        $indexer = new Indexer($products);

        $indexer->resetIndex(); // clears the index
        $indexer->buildIndex(); // process all supplied models
    }
}

```
## Getting the facets

### Use the available facets, and the applied filter, to build a facet filter in your frontend.

``` php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

use App\Models\Product;

class HomeController extends BaseController
{

	public function home()
	{
		$filter = Product::getFilterFromParam();
		/* Returns an array with the current filter, based on all the available facets for this model,
		and the specified (optional) GET parameter (default is "filter"). A facet's title is
		its key in the GET parameter.

		e.g. /?filter[main-color][0]=green will result in:
		[ 'main-color' => [ 'green' ], 'size' => [ ] ]
		*/

		$facets = Product::getFacets();
		/* Returns a Laravel collection of the available facets. */

		$facetsInQueryResult = $facets->limitToSubjectIds([1,2,3]);
		/* Sometimes you want the facet information for a subset of models
		(e.g. if you're refining results with facet filtering).
		You can use limitToSubjectIds(), which takes an array of model ID's. */

		$singleFacet = $facets->firstWhere('fieldname', 'color');
		/* $facets is a regular laravel collectio, so it's easy to iterate all of them, or find the one you need.
		Each facet has a method to get a Laravel collection of option objects, to help you build your frontend. */

		return view('home')->with([
			'filter' => $filter,
			'facets' => $facets,
			'facet' => $singleFacet
		]);
	}
}

```

### Frontend example

You'll have to build the frontend yourself. Here's an example of how a frontend may look.
Make sure you set the correct GET-param, e.g. "?filter[main-color][0]=green",
so that the package can keep track of selected facets.

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
            {{ $option->total == 0 ? 'disabled' : '' }}
        />
        <label for="{{ $option->slug }}" class="{{ $option->selected ? 'selected' : '' }}">
            {{ $option->value }} ({{ $option->total }})
        </label>
    </div>
@endforeach
```

### Use facets in a query

``` php
	$filter = Product::getFilterFromParam();
	/* You can grab the filter from the request GET param or build it yourself.
	It is a nested array with facet titles for keys.
	e.g. [ 'main-color' => [ 'green', 'red' ], 'size' => [ ] ]
	*/

	$products = Product::where('discounted', true)->facetsMatchFilter($filter);
	/* Apply the filter to a query using the facetsMatchFilter() scope on the model */

	$subjectIds = $products->pluck('id');
	$facets = Product::getFacets()->limitToSubjectIds($subjectIds);
	/* Sometimes you want the facet information for a subset of models
	(e.g. if you're refining results with facet filtering).
	You can use limitToSubjectIds(), which takes an array of model ID's. */

```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

