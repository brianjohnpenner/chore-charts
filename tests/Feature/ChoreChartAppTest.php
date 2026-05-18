<?php

namespace Tests\Feature;

use App\Models\ChoreChart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class ChoreChartAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_the_editor_toggle_and_save_prompt(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Chore Charts')
            ->assertSee('Editor')
            ->assertSee('Print View')
            ->assertSee('Want to save this chart?')
            ->assertSee('Save Chart')
            ->assertSee('Privacy');
    }

    public function test_privacy_policy_page_describes_saved_chart_data_and_share_links(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('chore chart data')
            ->assertSee('Shareable Chart Links');
    }

    public function test_view_mode_toggle_shows_only_editor_or_print_view(): void
    {
        $component = Livewire::test('chart-builder');

        $this->assertStringNotContainsString('editor no-print hidden-mode', $component->html());
        $this->assertStringContainsString('preview-wrap hidden-mode', $component->html());

        $component->set('viewMode', 'preview');

        $this->assertStringContainsString('editor no-print hidden-mode', $component->html());
        $this->assertStringNotContainsString('preview-wrap hidden-mode', $component->html());
    }

    public function test_preview_view_can_be_loaded_from_the_url(): void
    {
        $this->get('/?view=preview')
            ->assertOk()
            ->assertSee('editor no-print hidden-mode', false)
            ->assertDontSee('preview-wrap hidden-mode', false);
    }

    public function test_weekly_chores_render_inside_an_orientation_aware_grid(): void
    {
        $component = Livewire::test('chart-builder')
            ->set('viewMode', 'preview');

        $this->assertStringContainsString('weekly-chores-grid', $component->html());
        $this->assertStringContainsString('chart-preview landscape', $component->html());

        $component->set('chart.children.0.orientation', 'portrait');

        $this->assertStringContainsString('chart-preview portrait', $component->html());
        $this->assertStringContainsString('weekly-chores-grid', $component->html());
    }

    public function test_print_view_includes_an_icon_chore_legend(): void
    {
        $html = Livewire::test('chart-builder')
            ->set('viewMode', 'preview')
            ->html();

        $this->assertStringContainsString('icon-legend', $html);
        $this->assertStringContainsString('Icon Chores', $html);
        $this->assertStringContainsString('Make bed', $html);
        $this->assertStringContainsString('Brush teeth', $html);
        $this->assertStringContainsString('Laundry', $html);
    }

    public function test_empty_chore_boxes_render_without_write_in_lines(): void
    {
        $html = Livewire::test('chart-builder')
            ->set('viewMode', 'preview')
            ->html();

        $this->assertStringContainsString('write-cell', $html);
        $this->assertStringNotContainsString('write-line', $html);
    }

    public function test_editor_icon_picker_shows_icons_with_names(): void
    {
        $html = Livewire::test('chart-builder')->html();

        $this->assertStringContainsString('icon-picker', $html);
        $this->assertStringContainsString('icon-picker-symbol', $html);
        $this->assertStringContainsString('Toothbrush', $html);
        $this->assertStringContainsString('Cat', $html);
        $this->assertStringContainsString('Dog', $html);
        $this->assertStringContainsString('Broom', $html);
        $this->assertStringContainsString('Vacuum', $html);
        $this->assertStringContainsString('Dishwasher', $html);
    }

    public function test_saving_a_new_chart_creates_a_row_and_redirects_to_its_signed_url(): void
    {
        $component = Livewire::test('chart-builder')
            ->set('chart.children.0.childName', 'Molly')
            ->call('saveChart');

        $this->assertDatabaseCount('chore_charts', 1);
        $chart = ChoreChart::firstOrFail();

        $this->assertNotEmpty($chart->public_id);
        $this->assertSame('Molly', $chart->data['children'][0]['childName']);
        $this->assertNull($chart->email);

        $component->assertRedirect(URL::signedRoute('chart.show', ['chart' => $chart->public_id]));
    }

    public function test_saving_with_an_email_stores_it_on_the_chart(): void
    {
        Livewire::test('chart-builder')
            ->set('email', 'Parent@Example.com')
            ->call('saveChart');

        $chart = ChoreChart::firstOrFail();

        $this->assertSame('parent@example.com', $chart->email);
    }

    public function test_signed_chart_url_loads_the_chart_for_editing(): void
    {
        $chart = ChoreChart::create([
            'public_id' => 'abc123',
            'title' => 'Molly Chart',
            'data' => $this->chartData('Molly'),
        ]);

        $this->get(URL::signedRoute('chart.show', ['chart' => $chart->public_id]))
            ->assertOk()
            ->assertSee('Molly');
    }

    public function test_chart_url_without_a_valid_signature_is_forbidden(): void
    {
        $chart = ChoreChart::create([
            'public_id' => 'abc123',
            'title' => 'Molly Chart',
            'data' => $this->chartData('Molly'),
        ]);

        $this->get('/c/'.$chart->public_id)->assertForbidden();
    }

    public function test_editing_a_saved_chart_autosaves_on_change(): void
    {
        $chart = ChoreChart::create([
            'public_id' => 'abc123',
            'title' => 'Molly Chart',
            'data' => $this->chartData('Molly'),
        ]);

        Livewire::test('chart-builder', ['chart' => $chart])
            ->set('chart.children.0.childName', 'Sam');

        $this->assertSame('Sam', $chart->fresh()->data['children'][0]['childName']);
    }

    public function test_email_link_sends_a_mail_with_the_signed_url(): void
    {
        Mail::fake();

        $chart = ChoreChart::create([
            'public_id' => 'abc123',
            'title' => 'Molly Chart',
            'data' => $this->chartData('Molly'),
        ]);

        Livewire::test('chart-builder', ['chart' => $chart])
            ->set('email', 'parent@example.com')
            ->call('emailLink')
            ->assertSet('notice', 'Link emailed. In local development, check storage/logs/laravel.log.');

        Mail::assertSent(function ($mail): bool {
            return $mail->hasTo('parent@example.com');
        });

        $this->assertSame('parent@example.com', $chart->fresh()->email);
    }

    public function test_email_link_fails_when_chart_has_not_been_saved(): void
    {
        Mail::fake();

        Livewire::test('chart-builder')
            ->set('email', 'parent@example.com')
            ->call('emailLink')
            ->assertSet('error', 'Save the chart first, then email yourself the link.');

        Mail::assertNothingSent();
    }

    public function test_unsaved_drafts_do_not_persist_server_side(): void
    {
        Livewire::test('chart-builder')
            ->set('chart.children.0.childName', 'Molly');

        $this->assertNull(session('chart_draft'));
        $this->assertDatabaseCount('chore_charts', 0);

        $reopened = Livewire::test('chart-builder');
        $this->assertNotSame('Molly', $reopened->get('chart')['children'][0]['childName']);
    }

    private function chartData(string $childName): array
    {
        return [
            'version' => 3,
            'activeChildId' => strtolower($childName),
            'children' => [
                [
                    'id' => strtolower($childName),
                    'childName' => $childName,
                    'orientation' => 'landscape',
                    'days' => [
                        ['key' => 'sun', 'label' => 'Sun', 'color' => '#ded8ef'],
                        ['key' => 'mon', 'label' => 'Mon', 'color' => '#cfe0f8'],
                        ['key' => 'tue', 'label' => 'Tue', 'color' => '#fde6ca'],
                        ['key' => 'wed', 'label' => 'Wed', 'color' => '#f5c9cd'],
                        ['key' => 'thu', 'label' => 'Thu', 'color' => '#d2e2e6'],
                        ['key' => 'fri', 'label' => 'Fri', 'color' => '#dcefd7'],
                        ['key' => 'sat', 'label' => 'Sat', 'color' => '#fff2c7'],
                    ],
                    'sections' => [
                        [
                            'id' => 'morning',
                            'name' => 'Morning',
                            'rows' => [
                                [
                                    'id' => 'make-bed',
                                    'type' => 'regular',
                                    'label' => 'Make bed',
                                    'icon' => 'bed',
                                    'paid' => false,
                                    'days' => [
                                        'sun' => true,
                                        'mon' => true,
                                        'tue' => true,
                                        'wed' => true,
                                        'thu' => true,
                                        'fri' => true,
                                        'sat' => true,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'weeklyChores' => [
                        'title' => 'Weekly Chores',
                        'rows' => [],
                    ],
                ],
            ],
        ];
    }
}
