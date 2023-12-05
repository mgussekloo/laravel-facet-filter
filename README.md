# Laravel Facet Filter

This package provides simple facet filtering (sometimes called Faceted Search or Faceted Navigation) in Laravel projects. It helps narrow down query results based on the attributes of your models.

- Free, no dependencies
- No complex queries to write
- Easy to extend

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

Add a Facettable trait and a defineFacets() method to all the models that should support facet filtering.

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

	...
```

### Build the index

Before you can start filtering you will have to build an index. You can use the
Indexer provided with this package.

``` php

use Mgussekloo\FacetFilter\Indexer;

$products = Product::with(['sizes'])->get(); // get some products

$indexer = new Indexer();
$indexer->resetIndex(); // clears the index
$indexer->buildIndex($products); // process the models
```

## Get results

### Apply the facet filter to a query

``` php
$filter = ['main-color' => ['green']];
$products = Product::facetsMatchFilter($filter)->get();
```

## Build the frontend

You can get all the info about the facets you need from the getFacets() method on the Facettable model.

``` php
/* Get info about the facets. */
$facets = Product::getFacets();

/* You can filter and sort like any regular Laravel collection. */
$singleFacet = $facets->firstWhere('fieldname', 'color');

/* Find out stuff about the facet. */
$paramName = $singleFacet->getParamName(); // "main-color"
$options = $singleFacet->getOptions();

/*
Options look like this:
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
### Livewire example

This is how it could look using Laravel Livewire to communicate a selected option to the backend. You could use any AJAX request, form submit or whatever you like.

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

## Further reading

### Advanced indexing

You can extend the Indexer when you want to save a "range bracket" value instead of a "individual price" value to the index.

``` php

class CustomIndexer extends Mgussekloo\FacetFilter\Indexer
{
	public function buildRow($facet, $model, $value) {
		$row = parent::buildRow($facet, $model, $value);

		if ($facet->getSlug() == 'App\Models\Product.price') {
			if ($row['value'] > 0 && $row['value'] < 100) {
				$row['value'] = '0-100';
			}
		}

		return $row;
	}
}
```

Process the models in chunks when you deal with very large datasets.

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

### Custom facets

You can define a custom Facet class (and other custom attributes) for your facets.

``` php
public static function facetDefinitions()
{
	return [
		[
			'title' => 'Main color',
			'description' => 'The main color.',
			'fieldname' => 'color',
			'facet_class' => CustomFacet::class
		]
	];
}

class CustomFacet extends Mgussekloo\FacetFilter\Models\Facet
{
	// return the option objects for this facet
	public function getOptions(): Collection { ... }
	// return the options objects, but remove the ones leading to zero results
	public function getNonMissingOptions(): Collection { ... }
	// get this facet's parameter name
	public function getParamName(): string { ... }
	// get this facet's unique slug (used for indexing)
	public function getSlug(): string { ... }
	// set the filter for this facet (used when getting the options)
	public function setFilter($filter) { ... }
	// set the facetrows for this facet
	public function setRows($rows) { ... }
}
```



## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

