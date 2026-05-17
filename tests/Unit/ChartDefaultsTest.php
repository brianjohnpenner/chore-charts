<?php

namespace Tests\Unit;

use App\Support\ChartDefaults;
use PHPUnit\Framework\TestCase;

class ChartDefaultsTest extends TestCase
{
    public function test_default_chart_has_jack_with_three_sections_and_weekly_chores(): void
    {
        $chart = ChartDefaults::defaultChart();

        $this->assertSame('jack', $chart['activeChildId']);
        $this->assertCount(1, $chart['children']);

        $child = $chart['children'][0];
        $this->assertSame('Jack', $child['childName']);
        $this->assertSame('landscape', $child['orientation']);
        $this->assertCount(7, $child['days']);
        $this->assertSame(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], array_column($child['days'], 'label'));
        $this->assertCount(3, $child['sections']);
        $this->assertSame(['Morning', 'Daytime', 'Before Bed'], array_column($child['sections'], 'name'));
        $this->assertCount(5, $child['weeklyChores']['rows']);
    }

    public function test_morning_section_has_one_regular_row_for_feeding_the_cat(): void
    {
        $section = ChartDefaults::defaultSection('morning', 'Morning');

        $regular = array_values(array_filter($section['rows'], fn ($r) => $r['type'] === 'regular'));
        $this->assertCount(1, $regular);
        $this->assertSame('Feed cat', $regular[0]['label']);
    }

    public function test_default_child_disambiguates_existing_ids(): void
    {
        $first = ChartDefaults::defaultChild('Sam');
        $second = ChartDefaults::defaultChild('Sam', [$first['id']]);
        $third = ChartDefaults::defaultChild('Sam', [$first['id'], $second['id']]);

        $this->assertSame('sam', $first['id']);
        $this->assertSame('sam-2', $second['id']);
        $this->assertSame('sam-3', $third['id']);
    }

    public function test_chore_row_defaults_to_all_days_selected(): void
    {
        $row = ChartDefaults::choreRow('regular', 'Test');

        $this->assertSame(
            ['sun' => true, 'mon' => true, 'tue' => true, 'wed' => true, 'thu' => true, 'fri' => true, 'sat' => true],
            $row['days']
        );
        $this->assertFalse($row['paid']);
    }

    public function test_chore_row_falls_back_to_room_icon_for_unknown_icon(): void
    {
        $row = ChartDefaults::choreRow('icon', '', 'totally-fake-icon');

        $this->assertSame('room', $row['icon']);
    }

    public function test_slugify_handles_empty_and_punctuated_input(): void
    {
        $this->assertSame('child', ChartDefaults::slugify(''));
        $this->assertSame('jane-doe', ChartDefaults::slugify('Jane  Doe!'));
    }
}
