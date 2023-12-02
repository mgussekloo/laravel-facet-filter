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

For all models that should support facet filtering, add a Facettable trait and
a defineFacets() method. It returns an array containing one or more facet definitions.

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
				'fieldname' => 'color' /* Model property from which to get values */
			],
			[
				'fieldname' => 'sizes.name' /* Use dot notation to get the value from related models. */
			]
		]
	}

	...

```

### Build the index

You must build the index before you can start filtering. How you build the index is up to you.
You could run the included indexer in a scheduled command.

``` php

$products = Product::with(['sizes'])->get(); // get some products

$indexer = new Mgussekloo\FacetFilter\Indexer();
$indexer->resetIndex(); // clear the index
$indexer->buildIndex($products); // process all supplied models

```

## Start filtering

### Apply facet filter to the query

``` php
// A facet filter is an associative, two-dimensional array ([facet_name => [values]]).
$products = Product::facetsMatchFilter(['main-color' => ['green']]->get();

// You can use the getFilterFromArr helper function to build the array.
$filter = Product::getFilterFromArr(request()->all()); // /?main-color=green&size=[s,m] becomes [ 'main-color' => [ 'green' ], 'size' => [ 's', 'm' ] ]
$products = Product::facetsMatchFilter($filter)->get();
```

### Display the output

There is no frontend included, but it should be straight forward.

``` php
/* Get info about the facets. The facets have the correct option counts for the last queried results. */
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

## Next steps

The defineFacets() method on the Facettable model can return more than the fieldname. Each key will be added as a property on the Facet.

``` php
public static function defineFacets()
{
	return [
		[
			'title' => 'Color',
			'description' => 'The main color.',
			'fieldname' => 'color',
			'facet_class' => CustomFacet::class
		]
	];
}

echo $singleFacet->title; // Color
echo $singleFacet->description; // The main color.
```

The 'facet_class' key will be used when instantiating Facets.

``` php
class CustomFacet extends Mgussekloo\FacetFilter\Models\Facet
{
	...
}
```

You can extend the Indexer to customize the facet values. For example, save a "range bracket" value instead of a "individual price" value.

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
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

