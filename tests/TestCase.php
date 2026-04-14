<?php

namespace Mgussekloo\FacetFilter\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mgussekloo\FacetFilter\FacetFilterServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            FacetFilterServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('facet-filter.classes.facet', \Mgussekloo\FacetFilter\Models\Facet::class);
        $app['config']->set('facet-filter.classes.facetrow', \Mgussekloo\FacetFilter\Models\FacetRow::class);
        $app['config']->set('facet-filter.table_names.facetrows', 'facetrows');
        $app['config']->set('facet-filter.cache.store', 'array');
        $app['config']->set('facet-filter.cache.key', 'mgussekloo.facetfilter.cache');
        $app['config']->set('facet-filter.cache.expiration_time', \DateInterval::createFromDateString('24 hours'));
    }

    protected function setUpDatabase(): void
    {
        // Create the facetrows table (matches the published migration but with string subject_id)
        Schema::create('facetrows', function (Blueprint $table) {
            $table->id();
            $table->string('facet_slug');
            $table->string('subject_id', 26);
            $table->string('value')->nullable();
            $table->timestamps();
            $table->index(['facet_slug', 'value', 'subject_id']);
        });

        // Products table with auto-incrementing integer ID
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->timestamps();
        });

        // Articles table with ULID string primary key
        Schema::create('articles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->string('category')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }
}