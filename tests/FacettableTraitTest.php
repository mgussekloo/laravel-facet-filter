<?php

namespace Mgussekloo\FacetFilter\Tests;

use Mgussekloo\FacetFilter\Builders\FacetQueryBuilder;
use Mgussekloo\FacetFilter\Collections\FacettableCollection;
use Mgussekloo\FacetFilter\FacetFilter;
use Mgussekloo\FacetFilter\Models\FacetRow;
use Mgussekloo\FacetFilter\Tests\Models\Article;
use Mgussekloo\FacetFilter\Tests\Models\Product;

class FacettableTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FacetFilter::$facets = [];
    }

    /** @test */
    public function integer_id_model_returns_facet_query_builder()
    {
        $builder = Product::query();

        $this->assertInstanceOf(FacetQueryBuilder::class, $builder);
    }

    /** @test */
    public function string_id_model_returns_facet_query_builder()
    {
        $builder = Article::query();

        $this->assertInstanceOf(FacetQueryBuilder::class, $builder);
    }

    /** @test */
    public function integer_id_model_returns_facettable_collection()
    {
        Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);

        $collection = Product::all();

        $this->assertInstanceOf(FacettableCollection::class, $collection);
    }

    /** @test */
    public function string_id_model_returns_facettable_collection()
    {
        Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);

        $collection = Article::all();

        $this->assertInstanceOf(FacettableCollection::class, $collection);
    }

    /** @test */
    public function integer_id_model_has_facetrows_relationship()
    {
        $product = Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);

        Product::all()->buildIndex();

        $this->assertEquals(2, $product->facetrows()->count());
    }

    /** @test */
    public function string_id_model_has_facetrows_relationship()
    {
        $article = Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);

        Article::all()->buildIndex();

        $this->assertEquals(2, $article->facetrows()->count());
    }

    /** @test */
    public function facet_rows_reference_correct_string_id()
    {
        $article = Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);

        Article::all()->buildIndex();

        $rows = FacetRow::where('subject_id', $article->getKey())->get();

        $this->assertCount(2, $rows);
        $rows->each(function ($row) use ($article) {
            $this->assertEquals($article->getKey(), $row->subject_id);
        });
    }

    /** @test */
    public function facet_rows_reference_correct_integer_id()
    {
        $product = Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);

        Product::all()->buildIndex();

        $rows = FacetRow::where('subject_id', $product->getKey())->get();

        $this->assertCount(2, $rows);
    }

    /** @test */
    public function make_facets_works_for_string_id_model()
    {
        $facets = Article::makeFacets();

        $this->assertCount(2, $facets);

        $slugs = $facets->map->getSlug()->toArray();
        $expectedPrefix = Article::class;

        foreach ($slugs as $slug) {
            $this->assertStringStartsWith($expectedPrefix, $slug);
        }
    }

    /** @test */
    public function collection_build_index_works_for_string_ids()
    {
        Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']);

        Article::all()->buildIndex();

        $this->assertEquals(4, FacetRow::count());

        // All subject_ids should be 26-char ULIDs
        FacetRow::pluck('subject_id')->unique()->each(function ($id) {
            $this->assertEquals(26, strlen($id));
        });
    }

    /** @test */
    public function collection_reset_index_works_for_string_ids()
    {
        $article1 = Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        $article2 = Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']);

        Article::all()->buildIndex();

        $this->assertEquals(4, FacetRow::count());

        // Reset just article1
        collect([$article1])->each(function ($a) {
            // Need a FacettableCollection
        });
        (new FacettableCollection([$article1]))->resetIndex();

        $this->assertEquals(2, FacetRow::count());

        $remainingIds = FacetRow::pluck('subject_id')->unique()->toArray();
        $this->assertEquals([$article2->getKey()], $remainingIds);
    }

    /** @test */
    public function forget_cache_works()
    {
        // This should not throw any errors
        Product::forgetCache();
        Article::forgetCache();
        Article::forgetCache('custom-tag');

        $this->assertTrue(true);
    }
}