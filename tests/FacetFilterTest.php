<?php

namespace Mgussekloo\FacetFilter\Tests;

use Mgussekloo\FacetFilter\FacetFilter;
use Mgussekloo\FacetFilter\Tests\Models\Article;
use Mgussekloo\FacetFilter\Tests\Models\Product;

class FacetFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FacetFilter::$facets = [];
    }

    /** @test */
    public function it_gets_facets_for_integer_id_model()
    {
        $facets = Product::getFacets();

        $this->assertCount(2, $facets);
        $this->assertEquals('color', $facets[0]->fieldname);
        $this->assertEquals('size', $facets[1]->fieldname);
    }

    /** @test */
    public function it_gets_facets_for_string_id_model()
    {
        $facets = Article::getFacets();

        $this->assertCount(2, $facets);
        $this->assertEquals('category', $facets[0]->fieldname);
        $this->assertEquals('status', $facets[1]->fieldname);
    }

    /** @test */
    public function it_builds_empty_filter_for_integer_id_model()
    {
        $filter = Product::getFilterFromArr();

        $this->assertIsArray($filter);
        $this->assertArrayHasKey('color', $filter);
        $this->assertArrayHasKey('size', $filter);
        $this->assertEmpty($filter['color']);
        $this->assertEmpty($filter['size']);
    }

    /** @test */
    public function it_builds_empty_filter_for_string_id_model()
    {
        $filter = Article::getFilterFromArr();

        $this->assertIsArray($filter);
        $this->assertArrayHasKey('category', $filter);
        $this->assertArrayHasKey('status', $filter);
        $this->assertEmpty($filter['category']);
        $this->assertEmpty($filter['status']);
    }

    /** @test */
    public function it_merges_filter_values()
    {
        $filter = Product::getFilterFromArr(['color' => ['Red', 'Blue']]);

        $this->assertEquals(['Red', 'Blue'], $filter['color']);
        $this->assertEmpty($filter['size']);
    }

    /** @test */
    public function it_ignores_unknown_filter_keys()
    {
        $filter = Product::getFilterFromArr(['unknown' => ['value']]);

        $this->assertArrayNotHasKey('unknown', $filter);
        $this->assertEmpty($filter['color']);
        $this->assertEmpty($filter['size']);
    }

    /** @test */
    public function it_wraps_scalar_filter_values_in_array()
    {
        $filter = Product::getFilterFromArr(['color' => 'Red']);

        $this->assertEquals(['Red'], $filter['color']);
    }

    /** @test */
    public function it_sets_filter_on_facets()
    {
        $facets = Product::getFacets(['color' => ['Red']]);

        $colorFacet = $facets->first(fn ($f) => $f->fieldname === 'color');

        $this->assertEquals(['Red'], $colorFacet->filter['color']);
    }

    /** @test */
    public function it_generates_row_query_for_single_facet()
    {
        $facetFilter = app('facetfilter');
        $facets = Article::getFacets();
        $singleFacet = $facets->first();

        $query = $facetFilter->getRowQuery($singleFacet);

        $this->assertStringContainsString('facet_slug', $query->toSql());
        $this->assertStringContainsString('subject_id', $query->toSql());
        $this->assertStringContainsString('value', $query->toSql());
    }

    /** @test */
    public function it_generates_row_query_for_multiple_facets()
    {
        $facetFilter = app('facetfilter');
        $facets = Product::getFacets();

        $query = $facetFilter->getRowQuery($facets);

        $sql = $query->toSql();
        $this->assertStringContainsString('facet_slug', $sql);
    }
}