<?php

namespace Mgussekloo\FacetFilter\Tests;

use Mgussekloo\FacetFilter\Models\FacetRow;

class FacetRowTest extends TestCase
{
    /** @test */
    public function it_stores_integer_subject_id()
    {
        $row = FacetRow::create([
            'facet_slug' => 'test.color',
            'subject_id' => 42,
            'value' => 'Red',
        ]);

        $this->assertEquals('42', $row->fresh()->subject_id);
        $this->assertEquals('Red', $row->fresh()->value);
    }

    /** @test */
    public function it_stores_string_subject_id()
    {
        $ulid = '01HQ3K4B2C5D6E7F8G9H0JKLMN';

        $row = FacetRow::create([
            'facet_slug' => 'test.category',
            'subject_id' => $ulid,
            'value' => 'Tech',
        ]);

        $this->assertEquals($ulid, $row->fresh()->subject_id);
    }

    /** @test */
    public function it_queries_by_string_subject_id()
    {
        $ulid1 = '01HQ3K4B2C5D6E7F8G9H0JKLMN';
        $ulid2 = '01HQ3K4B2C5D6E7F8G9H0JKLMO';

        FacetRow::create(['facet_slug' => 'test.category', 'subject_id' => $ulid1, 'value' => 'Tech']);
        FacetRow::create(['facet_slug' => 'test.category', 'subject_id' => $ulid2, 'value' => 'Science']);

        $rows = FacetRow::where('subject_id', $ulid1)->get();

        $this->assertCount(1, $rows);
        $this->assertEquals('Tech', $rows->first()->value);
    }

    /** @test */
    public function it_queries_with_where_in_for_string_subject_ids()
    {
        $ulid1 = '01HQ3K4B2C5D6E7F8G9H0JKLMN';
        $ulid2 = '01HQ3K4B2C5D6E7F8G9H0JKLMO';
        $ulid3 = '01HQ3K4B2C5D6E7F8G9H0JKLMP';

        FacetRow::create(['facet_slug' => 'test.category', 'subject_id' => $ulid1, 'value' => 'Tech']);
        FacetRow::create(['facet_slug' => 'test.category', 'subject_id' => $ulid2, 'value' => 'Science']);
        FacetRow::create(['facet_slug' => 'test.category', 'subject_id' => $ulid3, 'value' => 'Art']);

        $rows = FacetRow::whereIn('subject_id', [$ulid1, $ulid3])->get();

        $this->assertCount(2, $rows);
        $values = $rows->pluck('value')->sort()->values()->toArray();
        $this->assertEquals(['Art', 'Tech'], $values);
    }

    /** @test */
    public function it_stores_multiple_rows_for_same_string_subject()
    {
        $ulid = '01HQ3K4B2C5D6E7F8G9H0JKLMN';

        FacetRow::create(['facet_slug' => 'test.category', 'subject_id' => $ulid, 'value' => 'Tech']);
        FacetRow::create(['facet_slug' => 'test.status', 'subject_id' => $ulid, 'value' => 'Published']);

        $rows = FacetRow::where('subject_id', $ulid)->get();

        $this->assertCount(2, $rows);
        $slugs = $rows->pluck('facet_slug')->sort()->values()->toArray();
        $this->assertEquals(['test.category', 'test.status'], $slugs);
    }

    /** @test */
    public function it_handles_null_value()
    {
        $ulid = '01HQ3K4B2C5D6E7F8G9H0JKLMN';

        $row = FacetRow::create([
            'facet_slug' => 'test.category',
            'subject_id' => $ulid,
            'value' => null,
        ]);

        $this->assertNull($row->fresh()->value);
    }

    /** @test */
    public function it_uses_correct_table_name()
    {
        $row = new FacetRow();

        $this->assertEquals('facetrows', $row->getTable());
    }

    /** @test */
    public function subject_id_is_fillable()
    {
        $row = new FacetRow();

        $this->assertContains('subject_id', $row->getFillable());
    }

    /** @test */
    public function it_indexes_correctly_for_facet_slug_value_subject_id()
    {
        $ulid1 = '01HQ3K4B2C5D6E7F8G9H0JKLMN';
        $ulid2 = '01HQ3K4B2C5D6E7F8G9H0JKLMO';

        FacetRow::create(['facet_slug' => 'test.color', 'subject_id' => $ulid1, 'value' => 'Red']);
        FacetRow::create(['facet_slug' => 'test.color', 'subject_id' => $ulid2, 'value' => 'Red']);
        FacetRow::create(['facet_slug' => 'test.color', 'subject_id' => $ulid1, 'value' => 'Blue']);

        // Query using the composite index pattern
        $rows = FacetRow::where('facet_slug', 'test.color')
            ->where('value', 'Red')
            ->whereIn('subject_id', [$ulid1, $ulid2])
            ->get();

        $this->assertCount(2, $rows);
    }
}