<?php

namespace Tests\Feature;

use App\Models\Chart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChartEditorTest extends TestCase
{
    use RefreshDatabase;

    private function mountWithDefaultChart(): array
    {
        $chart = Chart::createDefault();
        $component = Livewire::test('chart-editor', ['chartId' => $chart->id]);
        return [$component, $chart];
    }

    public function test_mount_loads_chart_data_from_database(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();

        $component
            ->assertSet('chartId', $chart->id)
            ->assertSet('chart.activeChildId', 'jack')
            ->assertSet('viewMode', 'edit')
            ->assertSee('Morning')
            ->assertSee('Before Bed');
    }

    public function test_add_section_appends_to_active_child_and_persists(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();
        $startCount = count($chart->fresh()->data['children'][0]['sections']);

        $component->call('addSection');

        $sections = $chart->fresh()->data['children'][0]['sections'];
        $this->assertCount($startCount + 1, $sections);
        $this->assertSame('New Section', end($sections)['name']);
    }

    public function test_delete_section_removes_it_from_database(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();

        $component->call('deleteSection', 0);

        $sections = $chart->fresh()->data['children'][0]['sections'];
        $this->assertSame(['Daytime', 'Before Bed'], array_column($sections, 'name'));
    }

    public function test_move_section_reorders_and_persists(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();

        $component->call('moveSection', 0, 1);

        $sections = $chart->fresh()->data['children'][0]['sections'];
        $this->assertSame(['Daytime', 'Morning', 'Before Bed'], array_column($sections, 'name'));
    }

    public function test_move_section_out_of_bounds_is_a_no_op(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();
        $before = array_column($chart->data['children'][0]['sections'], 'name');

        $component->call('moveSection', 0, -1);

        $after = array_column($chart->fresh()->data['children'][0]['sections'], 'name');
        $this->assertSame($before, $after);
    }

    public function test_add_row_appends_chore_row_of_the_given_type(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();

        $component->call('addRow', 0, 'regular');

        $rows = $chart->fresh()->data['children'][0]['sections'][0]['rows'];
        $this->assertSame('regular', end($rows)['type']);
        $this->assertSame('New chore', end($rows)['label']);
    }

    public function test_change_row_type_to_empty_clears_label(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();
        $sectionIdx = 0;
        $rowIdx = 0;

        $component->call('changeRowType', $sectionIdx, $rowIdx, 'empty');

        $row = $chart->fresh()->data['children'][0]['sections'][$sectionIdx]['rows'][$rowIdx];
        $this->assertSame('empty', $row['type']);
        $this->assertSame('', $row['label']);
    }

    public function test_change_row_type_rejects_unknown_type(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();
        $original = $chart->data['children'][0]['sections'][0]['rows'][0]['type'];

        $component->call('changeRowType', 0, 0, 'banana');

        $after = $chart->fresh()->data['children'][0]['sections'][0]['rows'][0]['type'];
        $this->assertSame($original, $after);
    }

    public function test_add_child_makes_it_active(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();

        $component->call('addChild');

        $fresh = $chart->fresh()->data;
        $this->assertCount(2, $fresh['children']);
        $this->assertSame($fresh['children'][1]['id'], $fresh['activeChildId']);
    }

    public function test_delete_child_is_no_op_when_only_one_remains(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();

        $component->call('deleteChild');

        $this->assertCount(1, $chart->fresh()->data['children']);
    }

    public function test_duplicate_child_creates_unique_id_and_switches_to_copy(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();

        $component->call('duplicateChild');

        $fresh = $chart->fresh()->data;
        $this->assertCount(2, $fresh['children']);
        $this->assertSame('jack', $fresh['children'][0]['id']);
        $this->assertSame('jack-copy', $fresh['children'][1]['id']);
        $this->assertSame('Jack Copy', $fresh['children'][1]['childName']);
        $this->assertSame('jack-copy', $fresh['activeChildId']);
    }

    public function test_add_weekly_row_persists(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();
        $before = count($chart->data['children'][0]['weeklyChores']['rows']);

        $component->call('addWeeklyRow', 'regular');

        $rows = $chart->fresh()->data['children'][0]['weeklyChores']['rows'];
        $this->assertCount($before + 1, $rows);
        $this->assertSame('New weekly chore', end($rows)['label']);
    }

    public function test_set_orientation_normalizes_invalid_input_to_landscape(): void
    {
        [$component, $chart] = $this->mountWithDefaultChart();

        $component->call('setOrientation', 'sideways');

        $this->assertSame('landscape', $chart->fresh()->data['children'][0]['orientation']);
    }

    public function test_preview_mode_toggle(): void
    {
        [$component] = $this->mountWithDefaultChart();

        $component
            ->call('setPreview', 'preview')
            ->assertSet('viewMode', 'preview')
            ->assertSee('Responsibility Chart')
            ->call('setPreview', 'edit')
            ->assertSet('viewMode', 'edit');
    }
}
