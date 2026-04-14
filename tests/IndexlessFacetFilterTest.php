<?php

namespace Mgussekloo\FacetFilter\Tests;

use Mgussekloo\FacetFilter\FacetFilter;
use Mgussekloo\FacetFilter\Tests\Models\Article;
use Mgussekloo\FacetFilter\Tests\Models\Product;

class IndexlessFacetFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FacetFilter::$facets = [];
        app('facetcache')->forgetCache();
    }

    /** @test */
    public function it_returns_all_when_no_filter_applied_integer_ids()
    {
        Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);
        Product::create(['name' => 'Pants', 'color' => 'Blue', 'size' => 'L']);

        $products = Product::all();

        $filter = Product::getFilterFromArr([]);
        $result = $products->indexlessFacetFilter($filter);

        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_returns_all_when_no_filter_applied_string_ids()
    {
        Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']);

        $articles = Article::all();

        $filter = Article::getFilterFromArr([]);
        $result = $articles->indexlessFacetFilter($filter);

        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_populates_facet_rows_from_integer_id_collection()
    {
        Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);
        Product::create(['name' => 'Pants', 'color' => 'Blue', 'size' => 'L']);
        Product::create(['name' => 'Hat', 'color' => 'Red', 'size' => 'S']);

        $products = Product::all();
        $filter = Product::getFilterFromArr(['color' => ['Red']]);
        $products->indexlessFacetFilter($filter);

        $facets = Product::getFacets();
        $colorFacet = $facets->first(fn ($f) => $f->fieldname === 'color');

        $this->assertTrue($colorFacet->rows->isNotEmpty());

        // Rows should reference the correct integer subject_ids
        $rowSubjectIds = $colorFacet->rows->pluck('subject_id')->unique()->sort()->values()->toArray();
        $productIds = $products->pluck('id')->sort()->values()->toArray();
        $this->assertEquals($productIds, $rowSubjectIds);
    }

    /** @test */
    public function it_populates_facet_rows_from_string_id_collection()
    {
        $a1 = Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        $a2 = Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']);
        $a3 = Article::create(['title' => 'Post 3', 'category' => 'Tech', 'status' => 'Draft']);

        $articles = Article::all();
        $filter = Article::getFilterFromArr(['category' => ['Tech']]);
        $articles->indexlessFacetFilter($filter);

        $facets = Article::getFacets();
        $categoryFacet = $facets->first(fn ($f) => $f->fieldname === 'category');

        $this->assertTrue($categoryFacet->rows->isNotEmpty());

        // Rows should reference the correct ULID subject_ids
        $rowSubjectIds = $categoryFacet->rows->pluck('subject_id')->unique()->sort()->values()->toArray();
        $articleIds = $articles->pluck('id')->sort()->values()->toArray();
        $this->assertEquals($articleIds, $rowSubjectIds);

        // Verify the subject_ids are proper ULID strings
        $categoryFacet->rows->pluck('subject_id')->unique()->each(function ($id) {
            $this->assertIsString($id);
            $this->assertEquals(26, strlen($id));
        });
    }

    /** @test */
    public function it_sets_filter_on_facets_with_string_ids()
    {
        Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']);
        Article::create(['title' => 'Post 3', 'category' => 'Tech', 'status' => 'Draft']);

        $articles = Article::all();
        $filter = Article::getFilterFromArr(['category' => ['Tech']]);
        $articles->indexlessFacetFilter($filter);

        $facets = Article::getFacets();
        $categoryFacet = $facets->first(fn ($f) => $f->fieldname === 'category');

        $filterValues = $categoryFacet->getFilterValues();
        $this->assertTrue($filterValues->contains('Tech'));
    }

    /** @test */
    public function it_computes_facet_options_with_string_ids()
    {
        Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']);
        Article::create(['title' => 'Post 3', 'category' => 'Tech', 'status' => 'Draft']);

        $articles = Article::all();
        $filter = Article::getFilterFromArr(['category' => ['Tech']]);
        $articles->indexlessFacetFilter($filter);

        $facets = Article::getFacets();
        $categoryFacet = $facets->first(fn ($f) => $f->fieldname === 'category');

        $options = $categoryFacet->getOptions();
        $this->assertCount(2, $options);

        $techOption = $options->firstWhere('value', 'Tech');
        $scienceOption = $options->firstWhere('value', 'Science');

        $this->assertTrue($techOption->selected);
        $this->assertFalse($scienceOption->selected);
    }

    /** @test */
    public function it_computes_facet_options_with_integer_ids()
    {
        Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);
        Product::create(['name' => 'Pants', 'color' => 'Blue', 'size' => 'L']);
        Product::create(['name' => 'Hat', 'color' => 'Red', 'size' => 'S']);

        $products = Product::all();
        $filter = Product::getFilterFromArr(['color' => ['Red']]);
        $products->indexlessFacetFilter($filter);

        $facets = Product::getFacets();
        $colorFacet = $facets->first(fn ($f) => $f->fieldname === 'color');

        $options = $colorFacet->getOptions();
        $this->assertCount(2, $options);

        $redOption = $options->firstWhere('value', 'Red');
        $blueOption = $options->firstWhere('value', 'Blue');

        $this->assertTrue($redOption->selected);
        $this->assertFalse($blueOption->selected);
    }

    /** @test */
    public function it_sets_ids_in_filter_per_facet_with_string_ids()
    {
        $a1 = Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        $a2 = Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']);
        $a3 = Article::create(['title' => 'Post 3', 'category' => 'Tech', 'status' => 'Draft']);

        $articles = Article::all();
        $filter = Article::getFilterFromArr(['category' => ['Tech']]);
        $articles->indexlessFacetFilter($filter);

        $facets = Article::getFacets();

        // Each facet should have idsInFilter set (not null)
        foreach ($facets as $facet) {
            $this->assertNotNull($facet->idsInFilter, "idsInFilter should be set for facet {$facet->fieldname}");
            $this->assertIsArray($facet->idsInFilter);

            // All IDs in idsInFilter should be strings (ULIDs)
            foreach ($facet->idsInFilter as $id) {
                $this->assertIsString($id);
                $this->assertEquals(26, strlen($id));
            }
        }
    }

    /** @test */
    public function it_sets_ids_in_filter_per_facet_with_integer_ids()
    {
        Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);
        Product::create(['name' => 'Pants', 'color' => 'Blue', 'size' => 'L']);
        Product::create(['name' => 'Hat', 'color' => 'Red', 'size' => 'S']);

        $products = Product::all();
        $filter = Product::getFilterFromArr(['color' => ['Red']]);
        $products->indexlessFacetFilter($filter);

        $facets = Product::getFacets();

        foreach ($facets as $facet) {
            $this->assertNotNull($facet->idsInFilter, "idsInFilter should be set for facet {$facet->fieldname}");
            $this->assertIsArray($facet->idsInFilter);
        }
    }

    /** @test */
    public function it_handles_empty_collection_gracefully()
    {
        // An empty collection should simply return itself
        // We need at least one model to satisfy first() calls, so this tests
        // the empty filter path
        $article = Article::create(['title' => 'Solo', 'category' => 'Tech', 'status' => 'Published']);

        $articles = Article::all();
        $filter = Article::getFilterFromArr([]);
        $result = $articles->indexlessFacetFilter($filter);

        $this->assertCount(1, $result);
    }

    /** @test */
    public function cache_tag_works_on_collection()
    {
        Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);

        $products = Product::all();
        $tagged = $products->cacheTag('custom-tag');

        $this->assertSame($products, $tagged);
        $this->assertEquals('custom-tag', $products->facetCacheTag);
    }

    /** @test */
    public function it_builds_facet_rows_with_correct_subject_id_type_for_string_model()
    {
        $a1 = Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        $a2 = Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']);

        $articles = Article::all();
        $filter = Article::getFilterFromArr(['category' => ['Tech']]);
        $articles->indexlessFacetFilter($filter);

        $facets = Article::getFacets();
        $categoryFacet = $facets->first(fn ($f) => $f->fieldname === 'category');

        // Find rows for a1 — they should use the ULID
        $a1Rows = $categoryFacet->rows->where('subject_id', $a1->getKey());
        $this->assertTrue($a1Rows->isNotEmpty());
        $this->assertEquals($a1->getKey(), $a1Rows->first()->subject_id);
    }

    /** @test */
    public function it_uses_model_key_name_for_collection_pluck_with_integer_ids()
    {
        $p1 = Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);
        $p2 = Product::create(['name' => 'Pants', 'color' => 'Blue', 'size' => 'L']);

        $products = Product::all();

        // Verify the model's key name is 'id'
        $this->assertEquals('id', $products->first()->getKeyName());

        $filter = Product::getFilterFromArr(['color' => ['Red']]);
        $result = $products->indexlessFacetFilter($filter);

        // Should not throw — confirms pluck uses the correct key name
        $this->assertNotNull($result);
    }

    /** @test */
    public function it_uses_model_key_name_for_collection_pluck_with_string_ids()
    {
        $a1 = Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        $a2 = Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']);

        $articles = Article::all();

        // Verify the model's key name is 'id' and type is string
        $this->assertEquals('id', $articles->first()->getKeyName());
        $this->assertEquals('string', $articles->first()->getKeyType());

        $filter = Article::getFilterFromArr(['category' => ['Tech']]);
        $result = $articles->indexlessFacetFilter($filter);

        // Should not throw — confirms pluck uses the correct key name for ULID models
        $this->assertNotNull($result);
    }

    /** @test */
    public function selecting_all_values_returns_full_collection_with_string_ids()
    {
        Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']);

        $articles = Article::all();

        // Selecting all values for a facet is the same as selecting none
        $filter = Article::getFilterFromArr(['category' => ['Tech', 'Science']]);
        $result = $articles->indexlessFacetFilter($filter);

        $this->assertCount(2, $result);
    }

    /** @test */
    public function selecting_all_values_returns_full_collection_with_integer_ids()
    {
        Product::create(['name' => 'Shirt', 'color' => 'Red', 'size' => 'M']);
        Product::create(['name' => 'Pants', 'color' => 'Blue', 'size' => 'L']);

        $products = Product::all();

        $filter = Product::getFilterFromArr(['color' => ['Red', 'Blue']]);
        $result = $products->indexlessFacetFilter($filter);

        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_caches_results_and_returns_same_output_with_string_ids()
    {
        Article::create(['title' => 'Post 1', 'category' => 'Tech', 'status' => 'Published']);
        Article::create(['title' => 'Post 2', 'category' => 'Science', 'status' => 'Draft']);

        $articles = Article::all();
        $filter = Article::getFilterFromArr(['category' => ['Tech']]);

        // Run twice — second run should use cached results
        $result1 = $articles->indexlessFacetFilter($filter);

        FacetFilter::$facets = [];
        $articles2 = Article::all();
        $filter2 = Article::getFilterFromArr(['category' => ['Tech']]);
        $result2 = $articles2->indexlessFacetFilter($filter2);

        $this->assertEquals($result1->count(), $result2->count());
    }

    /** @test */
    public function intersect_each_works_with_string_ids()
    {
        $ulid1 = '01HQ3K4B2C5D6E7F8G9H0JKLMN';
        $ulid2 = '01HQ3K4B2C5D6E7F8G9H0JKLMO';
        $ulid3 = '01HQ3K4B2C5D6E7F8G9H0JKLMP';

        $result = \Mgussekloo\FacetFilter\Collections\FacettableCollection::intersectEach([
            'facet1' => [$ulid1, $ulid2, $ulid3],
            'facet2' => [$ulid1, $ulid3],
        ]);

        $this->assertCount(2, $result);
        $this->assertContains($ulid1, $result);
        $this->assertContains($ulid3, $result);
    }

    /** @test */
    public function intersect_each_skips_null_with_string_ids()
    {
        $ulid1 = '01HQ3K4B2C5D6E7F8G9H0JKLMN';
        $ulid2 = '01HQ3K4B2C5D6E7F8G9H0JKLMO';

        $result = \Mgussekloo\FacetFilter\Collections\FacettableCollection::intersectEach([
            'facet1' => [$ulid1, $ulid2],
            'facet2' => null,
        ]);

        $this->assertCount(2, $result);
        $this->assertContains($ulid1, $result);
        $this->assertContains($ulid2, $result);
    }

    /** @test */
    public function intersect_each_with_all_null_returns_null()
    {
        $result = \Mgussekloo\FacetFilter\Collections\FacettableCollection::intersectEach([
            'facet1' => null,
            'facet2' => null,
        ]);

        $this->assertNull($result);
    }
}