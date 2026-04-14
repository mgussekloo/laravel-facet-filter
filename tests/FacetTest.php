<?php

namespace Mgussekloo\FacetFilter\Tests;

use Mgussekloo\FacetFilter\Models\Facet;

class FacetTest extends TestCase
{
    /** @test */
    public function it_computes_value_totals_with_integer_ids_in_filter()
    {
        $facet = new Facet([
            'title' => 'Color',
            'fieldname' => 'color',
            'subject_type' => 'App\\Models\\Product',
        ]);

        $facet->setRows(collect([
            (object) ['facet_slug' => 'color', 'subject_id' => 1, 'value' => 'Red'],
            (object) ['facet_slug' => 'color', 'subject_id' => 2, 'value' => 'Red'],
            (object) ['facet_slug' => 'color', 'subject_id' => 3, 'value' => 'Blue'],
        ]));

        $facet->setIdsInFilter([1, 2, 3]);

        $totals = $facet->getValueTotals();

        $this->assertEquals(2, $totals['Red']);
        $this->assertEquals(1, $totals['Blue']);
    }

    /** @test */
    public function it_computes_value_totals_with_string_ids_in_filter()
    {
        $facet = new Facet([
            'title' => 'Category',
            'fieldname' => 'category',
            'subject_type' => 'App\\Models\\Article',
        ]);

        $ulid1 = '01HQ3K4B2C5D6E7F8G9H0JKLMN';
        $ulid2 = '01HQ3K4B2C5D6E7F8G9H0JKLMO';
        $ulid3 = '01HQ3K4B2C5D6E7F8G9H0JKLMP';

        $facet->setRows(collect([
            (object) ['facet_slug' => 'category', 'subject_id' => $ulid1, 'value' => 'Tech'],
            (object) ['facet_slug' => 'category', 'subject_id' => $ulid2, 'value' => 'Tech'],
            (object) ['facet_slug' => 'category', 'subject_id' => $ulid3, 'value' => 'Science'],
        ]));

        $facet->setIdsInFilter([$ulid1, $ulid2, $ulid3]);

        $totals = $facet->getValueTotals();

        $this->assertEquals(2, $totals['Tech']);
        $this->assertEquals(1, $totals['Science']);
    }

    /** @test */
    public function it_counts_all_rows_when_ids_in_filter_is_not_set()
    {
        $facet = new Facet([
            'title' => 'Color',
            'fieldname' => 'color',
            'subject_type' => 'App\\Models\\Product',
        ]);

        $facet->setRows(collect([
            (object) ['facet_slug' => 'color', 'subject_id' => 'abc123', 'value' => 'Red'],
            (object) ['facet_slug' => 'color', 'subject_id' => 'def456', 'value' => 'Blue'],
        ]));

        // idsInFilter is null by default — should count all
        $totals = $facet->getValueTotals();

        $this->assertEquals(1, $totals['Red']);
        $this->assertEquals(1, $totals['Blue']);
    }

    /** @test */
    public function it_excludes_ids_not_in_filter_with_string_ids()
    {
        $facet = new Facet([
            'title' => 'Category',
            'fieldname' => 'category',
            'subject_type' => 'App\\Models\\Article',
        ]);

        $ulid1 = '01HQ3K4B2C5D6E7F8G9H0JKLMN';
        $ulid2 = '01HQ3K4B2C5D6E7F8G9H0JKLMO';
        $ulid3 = '01HQ3K4B2C5D6E7F8G9H0JKLMP';

        $facet->setRows(collect([
            (object) ['facet_slug' => 'category', 'subject_id' => $ulid1, 'value' => 'Tech'],
            (object) ['facet_slug' => 'category', 'subject_id' => $ulid2, 'value' => 'Tech'],
            (object) ['facet_slug' => 'category', 'subject_id' => $ulid3, 'value' => 'Science'],
        ]));

        // Only ulid1 is in the filter
        $facet->setIdsInFilter([$ulid1]);

        $totals = $facet->getValueTotals();

        $this->assertEquals(1, $totals['Tech']);
        $this->assertEquals(0, $totals['Science']);
    }

    /** @test */
    public function it_returns_correct_options_with_string_ids()
    {
        $facet = new Facet([
            'title' => 'Status',
            'fieldname' => 'status',
            'subject_type' => 'App\\Models\\Article',
        ]);

        $ulid1 = '01HQ3K4B2C5D6E7F8G9H0JKLMN';
        $ulid2 = '01HQ3K4B2C5D6E7F8G9H0JKLMO';

        $facet->setRows(collect([
            (object) ['facet_slug' => 'status', 'subject_id' => $ulid1, 'value' => 'Published'],
            (object) ['facet_slug' => 'status', 'subject_id' => $ulid2, 'value' => 'Draft'],
        ]));

        $facet->setFilter(['status' => []]);
        $facet->setIdsInFilter([$ulid1, $ulid2]);

        $options = $facet->getOptions();

        $this->assertCount(2, $options);
        $optionValues = $options->pluck('value')->toArray();
        $this->assertContains('Published', $optionValues);
        $this->assertContains('Draft', $optionValues);
    }

    /** @test */
    public function it_marks_selected_options_correctly()
    {
        $facet = new Facet([
            'title' => 'Color',
            'fieldname' => 'color',
            'subject_type' => 'App\\Models\\Product',
        ]);

        $facet->setRows(collect([
            (object) ['facet_slug' => 'color', 'subject_id' => '1', 'value' => 'Red'],
            (object) ['facet_slug' => 'color', 'subject_id' => '2', 'value' => 'Blue'],
            (object) ['facet_slug' => 'color', 'subject_id' => '3', 'value' => 'Green'],
        ]));

        $facet->setFilter(['color' => ['Red']]);
        $facet->setIdsInFilter(['1', '2', '3']);

        $options = $facet->getOptions();

        $redOption = $options->firstWhere('value', 'Red');
        $blueOption = $options->firstWhere('value', 'Blue');

        $this->assertTrue($redOption->selected);
        $this->assertFalse($blueOption->selected);
    }

    /** @test */
    public function get_non_missing_options_filters_zero_totals()
    {
        $facet = new Facet([
            'title' => 'Color',
            'fieldname' => 'color',
            'subject_type' => 'App\\Models\\Product',
        ]);

        $facet->setRows(collect([
            (object) ['facet_slug' => 'color', 'subject_id' => '1', 'value' => 'Red'],
            (object) ['facet_slug' => 'color', 'subject_id' => '2', 'value' => 'Blue'],
        ]));

        // Only id 1 is in filter, so Blue will have 0 total
        $facet->setFilter(['color' => []]);
        $facet->setIdsInFilter(['1']);

        $nonMissing = $facet->getNonMissingOptions();

        $this->assertCount(1, $nonMissing);
        $this->assertEquals('Red', $nonMissing->first()->value);
    }

    /** @test */
    public function it_generates_slug_correctly()
    {
        $facet = new Facet([
            'title' => 'Color',
            'fieldname' => 'color',
            'subject_type' => 'App\\Models\\Product',
        ]);

        $this->assertEquals('App\\Models\\Product.color', $facet->getSlug());
    }

    /** @test */
    public function it_generates_param_name_from_title()
    {
        $facet = new Facet([
            'title' => 'My Color',
            'fieldname' => 'color',
            'subject_type' => 'App\\Models\\Product',
        ]);

        $this->assertEquals('my-color', $facet->getParamName());
    }

    /** @test */
    public function selecting_all_values_is_same_as_selecting_none()
    {
        $facet = new Facet([
            'title' => 'Color',
            'fieldname' => 'color',
            'subject_type' => 'App\\Models\\Product',
        ]);

        $facet->setRows(collect([
            (object) ['facet_slug' => 'color', 'subject_id' => '1', 'value' => 'Red'],
            (object) ['facet_slug' => 'color', 'subject_id' => '2', 'value' => 'Blue'],
        ]));

        // Select all values
        $facet->setFilter(['color' => ['Red', 'Blue']]);

        $filterValues = $facet->getFilterValues();

        $this->assertTrue($filterValues->isEmpty());
    }
}