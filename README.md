# Laravel Facet Filter

This package provides simple facet filtering (sometimes called Faceted Search or Faceted Navigation) in Laravel projects. It helps narrow down query results based on the attributes of your models.

- Free, no dependencies
- Easy to use in any project
- Easy to customize
- There's a [demo project](https://github.com/mgussekloo/Facet-Demo) to get you started

![Demo](https://raw.githubusercontent.com/mgussekloo/laravel-facet-filter/master/demo.gif)

### Contributing

Please contribute to this package, either by creating a pull request or reporting an issue.

### Installation

This package can be installed through [Composer](https://packagist.org/packages/mgussekloo/laravel-facet-filter).

``` bash
composer require mgussekloo/laravel-facet-filter
```

## Prepare your project

### Update your models

Add a Facettable trait and a facetDefinitions() method to models that should support facet filtering.

``` php
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Mgussekloo\FacetFilter\Traits\Facettable;

class Product extends Model
{
	use HasFactory;
	use Facettable;

	public static function facetDefinitions()
	{
		// Return an array of definitions
		return [
			[
				'title' => 'Main color', // The title will be used for the parameter.
				'fieldname' => 'color' // Model property from which to get the values.
			],
			[
				'title' => 'Sizes',
				'fieldname' => 'sizes.name' // Use dot notation to get the value from related models.
			]
		];
	}
}


```

### Publish and run the migrations

For larger datasets you must build an index of all facets beforehand. If you're absolutely certain you don't need an index, skip to [filtering collections](#filtering-collections).

``` bash
php artisan vendor:publish --tag="facet-filter-migrations"
php artisan migrate
```

### Build the index

Now you can start building the index. There's a simple Indexer included, you just need to configure it to run once, periodically or whenever a relevant part of your data changes.

``` php
use Mgussekloo\FacetFilter\Indexer;

$products = Product::with(['sizes'])->get(); // get some products

$indexer = new Indexer();

$indexer->resetIndex(); // clear the entire index or...
$indexer->resetRows($products); // clear only the models that you know have changed

$indexer->buildIndex($products); // process the models
```

## Get results

### Apply the facet filter to a query

``` php
$filter = request()->all(); // use the request parameters
$filter = ['main-color' => ['green']]; // (or provide your own array)

$products = Product::facetFilter($filter)->get();
```

## Build the frontend

``` php
$facets = Product::getFacets();

/* You can filter and sort like any regular Laravel collection. */
$singleFacet = $facets->firstWhere('fieldname', 'color');

/* Find out stuff about the facet. */
$paramName = $singleFacet->getParamName(); // "main-color"
$options = $singleFacet->getOptions();

/*
Options look like this:
(object)[
	'value' => 'Red',
	'selected' => false,
	'total' => 3,
	'slug' => 'color_red',
	'http_query' => 'main-color%5B1%5D=red&sizes%5B0%5D=small'
]
*/
```

### Basic frontend example

Here's a simple [demo project](https://github.com/mgussekloo/Facet-Demo) that demonstrates a basic frontend.

``` html
<div class="flex">
	<div class="w-1/4 flex-0">
		@foreach ($facets as $facet)
			<p>
				<h3>{{ $facet->title }}</h3>

				@foreach ($facet->getOptions() as $option)
					<a href="?{{ $option->http_query }}" class="{{ $option->selected ? 'underline' : '' }}">{{ $option->value }} ({{ $option->total }}) </a><br />
				@endforeach
			</p><br />
		@endforeach
	</div>
	<div class="w-3/4">
		@foreach ($products as $product)
			<p>
				<h1>{{ $product->name }} ({{ $product->sizes->pluck('name')->join(', ') }})</h1>
				{{ $product->color }}<br /><br />
			</p>
		@endforeach
	</div>
</div>
```

### Livewire example

This is how it could look like with Livewire.

``` html
<h2>Colors</h2>
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

## Customization

### Advanced indexing

Extend the [Indexer](src/Indexer.php) to customize behavior, e.g. to save a "range bracket" value instead of a "individual price" value to the index.

``` php
class MyCustomIndexer extends \Mgussekloo\FacetFilter\Indexer {
	public function buildValues($facet, $model) {
		$values = parent::buildValues($facet, $model);

		if ($facet->fieldname == 'price') {

			if ($model->price > 1000) {
				return 'Expensive';
			}
			if ($model->price > 500) {
				return '500 - 1000';
			}
			if ($model->price > 250) {
				return '250 - 500';
			}
			return '0 - 250';
		}

		return $values;
	}
}
```

### Incremental indexing for large datasets

``` php
$perPage = 1000; $currentPage = Cache::get('facetIndexingPage', 1);

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

### Filtering collections

It's possible to get your models first, and apply facet filtering afterwards. This method builds the index "on the fly". This is much slower than using an index, so it's not recommended for most use cases.

``` php
$products = Product::all(); // returns a "FacettableCollection"
$products = $products->indexlessFacetFilter($filter);

// the second (optional) parameter lets you specify which indexer to use when indexing values from models

$indexer = new App\MyCustomIndexer();
$products = Product::all()->indexlessFacetFilter($filter, $indexer);
```

### Custom facets

Provide custom attributes and an optional custom [Facet class](src/Models/Facet.php) in the facet definitions.

``` php
public static function facetDefinitions()
{
	return [
		[
			'title' => 'Main color',
			'description' => 'The main color.', // optional custom attribute, you could use $facet->description when creating the frontend...
            'related_id' => 23, // ... or use $facet->related_id with your custom indexer 
			'fieldname' => 'color',
			'facet_class' => CustomFacet::class // optional Facet class with custom logic
		]
	];
}
```

## Notes on caching

By default Facet Filter uses the non-persistent 'array' cache driver, with queries and calculations happening every request.
You can configure the cache driver (as well as the expiration time and cachekey prefix) through config/facet-filter.php

If you decide to use a persistent cache driver, please note the following:
- Facet rows for a facet are cached for the duration of the expiration time.
- If you rebuild the index, you need to clear the cache. The default Indexer does this automatically.

```php
	FacetFilter::forgetCache(); // all caches, result counts for all facets, and facet rows
```

- Calls to facetFilter() or indexlessFacetFilter() always clear cached result counts to prevent running into problems with re-used queries, complex state (being logged in / out), etc. You can opt-out of this behaviour.

```php
	// using index-based facet filtering
	Product::withCache()->facetFilter($filter)->get();

	// using collection-based facet filtering
	Projects::all()->withCache()->indexlessFacetFilter($filter);
```

## Config

``` php
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
```


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

