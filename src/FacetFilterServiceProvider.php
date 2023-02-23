<?php

namespace Mgussekloo\FacetFilter;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use Mgussekloo\FacetFilter\FacetFilter;

class FacetFilterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-facet-filter')
            ->hasConfigFile()
            ->hasMigrations(['create_facetrows_table']);
    }

    public function registeringPackage()
    {
        $this->app->bind('facetfilter', fn() => new FacetFilter());
    }
}
