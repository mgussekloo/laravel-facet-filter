<?php

namespace Mgussekloo\FacetFilter\Tests;

use Mgussekloo\FacetFilter\Indexer;
use Mgussekloo\FacetFilter\Models\FacetRow;
use Mgussekloo\FacetFilter\Tests\Models\Article;
use Mgussekloo\FacetFilter\Tests\Models\Product;

class IndexerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset static facets cache between tests
        \Mgussekloo\FacetFilter\FacetFilter::$facets = [];
    }

    /** @test */
    public function it_builds_rows_with_integer_subject_id()
    {
        $product = Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);
        $facets = Product::getFacets();
        $indexer = new Indexer();

        $row = $indexer->buildRow($facets->first(), $product, 'Red');

        $this->assertSame($product->getKey(), $row['subject_id']);
        $this->assertIsInt($row['subject_id']);
        $this->assertEquals('Red', $row['value']);
    }

    /** @test */
    public function it_builds_rows_with_string_subject_id()
    {
        $article = Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        $facets = Article::getFacets();
        $indexer = new Indexer();

        $row = $indexer->buildRow($facets->first(), $article, 'Tech');

        $this->assertSame($article->getKey(), $row['subject_id']);
        $this->assertIsString($row['subject_id']);
        $this->assertEquals(26, strlen($row['subject_id']));
        $this->assertEquals('Tech', $row['value']);
    }

    /** @test */
    public function it_builds_index_for_integer_id_models()
    {
        $products = collect([
            Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']),
            Product::create(['name' => 'Pants', 'color' => 'Blue', 'size' => 'L']),
            Product::create(['name' => 'Hat', 'color' => 'Red', 'size' => 'S']),
        ]);

        $indexer = new Indexer();
        $indexer->buildIndex(Product::all());

        // 3 products x 2 facets (color + size) = 6 rows
        $this->assertEquals(6, FacetRow::count());

        // Verify subject_ids are integers stored as expected
        $subjectIds = FacetRow::pluck('subject_id')->unique()->sort()->values();
        $this->assertCount(3, $subjectIds);
    }

    /** @test */
    public function it_builds_index_for_string_id_models()
    {
        $articles = collect([
            Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']),
            Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']),
            Article::create(['title' => 'Post 3', 'category' => 'Tech', 'status' => 'Draft']),
        ]);

        $indexer = new Indexer();
        $indexer->buildIndex(Article::all());

        // 3 articles x 2 facets (category + status) = 6 rows
        $this->assertEquals(6, FacetRow::count());

        // Verify subject_ids are ULIDs (26 chars)
        $subjectIds = FacetRow::pluck('subject_id')->unique();
        $this->assertCount(3, $subjectIds);
        $subjectIds->each(function ($id) {
            $this->assertIsString($id);
            $this->assertEquals(26, strlen($id));
        });
    }

    /** @test */
    public function it_builds_correct_values_for_facets()
    {
        $product = Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);
        $facets = Product::getFacets();
        $indexer = new Indexer();

        $colorFacet = $facets->first(fn ($f) => $f->fieldname === 'color');
        $values = $indexer->buildValues($colorFacet, $product);

        $this->assertEquals(['Red'], $values);
    }

    /** @test */
    public function it_resets_rows_for_integer_id_models()
    {
        $products = collect([
            Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']),
            Product::create(['name' => 'Pants', 'color' => 'Blue', 'size' => 'L']),
        ]);

        $indexer = new Indexer();
        $indexer->buildIndex(Product::all());

        $this->assertEquals(4, FacetRow::count());

        // Reset rows for just the first product
        $indexer->resetRows(Product::where('id', $products[0]->id)->get());

        $this->assertEquals(2, FacetRow::count());

        // Only rows for the second product should remain
        $remainingIds = FacetRow::pluck('subject_id')->unique()->toArray();
        $this->assertEquals([(string) $products[1]->id], $remainingIds);
    }

    /** @test */
    public function it_resets_rows_for_string_id_models()
    {
        $articles = collect([
            Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']),
            Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']),
        ]);

        // Need fresh facets for Article
        \Mgussekloo\FacetFilter\FacetFilter::$facets = [];

        $indexer = new Indexer();
        $indexer->buildIndex(Article::all());

        $this->assertEquals(4, FacetRow::count());

        // Reset rows for just the first article
        $indexer->resetRows(Article::where('id', $articles[0]->id)->get());

        $this->assertEquals(2, FacetRow::count());

        // Only rows for the second article should remain
        $remainingIds = FacetRow::pluck('subject_id')->unique()->toArray();
        $this->assertEquals([$articles[1]->id], $remainingIds);
    }

    /** @test */
    public function it_resets_entire_index()
    {
        Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);
        $indexer = new Indexer();
        $indexer->buildIndex(Product::all());

        $this->assertGreaterThan(0, FacetRow::count());

        $indexer->resetIndex();

        $this->assertEquals(0, FacetRow::count());
    }

    /** @test */
    public function it_skips_null_values_during_indexing()
    {
        Product::create(['name' => 'Shirt', 'color' => null, 'size' => 'M']);

        $indexer = new Indexer();
        $indexer->buildIndex(Product::all());

        // Only the size row should exist, color is null and skipped
        $this->assertEquals(1, FacetRow::count());
        $this->assertEquals('M', FacetRow::first()->value);
    }

    /** @test */
    public function it_deduplicates_rows_by_unique_key()
    {
        $product = Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);

        $indexer = new Indexer();
        // Build the index twice for the same models - the unique key deduplication
        // within a single buildIndex call means rows with identical slug+id+value
        // are only added once
        $indexer->buildIndex(collect([$product, $product]));

        // Should have 2 unique rows (color=Red, size=M), not 4
        $this->assertEquals(2, FacetRow::count());
    }
}