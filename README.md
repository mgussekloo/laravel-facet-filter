# Laravel Facet Filter

This package provides simple facet filtering (sometimes called Faceted Search or Faceted Navigation) in Laravel projects. It helps narrow down query results based on the attributes of your models.

- Free, no dependencies
- Easy to use in any project
- Easy to customize
- There's a [demo project](https://github.com/mgussekloo/Facet-Demo) to get you started
- Ongoing support (last update: july 2025)

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

Add a Facettable trait and a facetDefinitions() method to models you'd like to filter:

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

### Build an index

In most cases, you'll want to create an index so you can filter large datasets efficiently. (Don't want to build an index? Skip to [filtering collections](#filtering-collections).)

First, run the migrations:

``` bash
php artisan vendor:publish --tag="facet-filter-migrations"
php artisan migrate
```

To build the index, create an Artisan command that queries your Facettable models. You can run it periodically

``` php
use Mgussekloo\FacetFilter\Indexer;

$products = Product::with(['sizes'])->get(); // get some products
$products->buildIndex(); // build the index
```

## Get results

Within a controller, apply the facet filter to a query

``` php
$filter = request()->all(); // the filter looks like ['main-color' => ['green']]
$products = Product::facetFilter($filter)->get();
```

### Basic frontend example

Here's a simple [demo project](https://github.com/mgussekloo/Facet-Demo) that demonstrates a basic frontend. To see an example of a Livewire implementation, [see this gist](https://gist.github.com/mgussekloo/85f1901baceb8e0e244c4860c37dae1f).

``` html
<div class="flex">
	<div class="w-1/4 flex-0">
		@foreach ($products->getFacets() as $facet)
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

## Facet details

``` php
$facets = $products->getFacets();

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

Overwrite the indexer() method on your Facettable model to make it use your custom indexer.

```php
use App\MyCustomIndexer;

class Product extends Model
{
	use HasFactory;
	use Facettable;

	public static indexer() {
		return MyCustomIndexer::class;
	}
}
```

Example: Update a larger index over time

```php
$indexer = new Indexer();

$perPage = 1000; $currentPage = Cache::get('facetIndexingPage', 1);

$products = Product::with(['sizes'])->paginate($perPage, ['*'], 'page', $currentPage);

if ($currentPage == 1) {
	$indexer->resetIndex(); // clear entire index
}

$indexer->buildIndex($products);

if ($products->hasMorePages()) {}
	// next iteration, increase currentPage with one
}
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

### Filtering collections

It's possible to apply facet filtering to a collection without building an index. Facettable models return a FacettableCollection, which has an indexlessFacetFilter() method.

``` php
$products = Product::all(); // returns a "FacettableCollection"
$products = $products->indexlessFacetFilter($filter);
```

### Pagination

Example:

```php
$products = Product::facetFilter($filter)->paginate(10);
$pagination = $products->appends(request()->input())->links();
```

## Notes on caching

By default Facet Filter caches some heavy operations through the non-persistent 'array' cache driver. You can configure a peristent cache driver through `config/facet-filter.php`. If you not only want to cache facet retrieval from the db, but also the actual models being retrieved for a particular filter, use the withCache() method.

```php
	// do not clear the result count cache before facet filtering (only useful if using a persistent caching driver)
	Product::withCache()->facetFilter($filter)->get();

	// using collection-based facet filtering
	Projects::all()->withCache()->indexlessFacetFilter($filter);
```

The default Indexer clears the cache automatically when rebuilding the index. To do it manually:

```php
	FacetFilter::forgetCache(); // clears all result counts for all facets, and all facet rows
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

