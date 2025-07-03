<?php

namespace Mgussekloo\FacetFilter;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FacetFilterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-facet-filter')
            ->hasConfigFile()
            ->hasMigrations(['create_facetrows_table']);
    }

    public function registeringPackage(): void
    {
        $this->app->bind('facetfilter', fn () => new FacetFilter());
        $this->app->bind('facetcache', fn () => new FacetCache());
    }
}
