<?php

namespace Tests\Feature;

use App\Models\ChoreChart;
use App\Models\MagicLoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class ChoreChartAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_the_editor_toggle_and_anonymous_save_prompt(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Chore Charts')
            ->assertSee('Editor')
            ->assertSee('Print View')
            ->assertSee('Want to save this chart?')
            ->assertSee('Send Link')
            ->assertSee('Privacy');
    }

    public function test_privacy_policy_page_explains_saved_chart_data_and_magic_links(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('email address')
            ->assertSee('chore chart data')
            ->assertSee('magic sign-in links');
    }

    public function test_guest_can_request_a_magic_link_that_captures_current_chart_data(): void
    {
        Livewire::test('chart-builder')
            ->set('email', 'parent@example.com')
            ->set('chart.children.0.childName', 'Molly')
            ->call('sendMagicLink')
            ->assertSet('notice', 'Magic link sent. In local development, check storage/logs/laravel.log.');

        $token = MagicLoginToken::query()->firstOrFail();

        $this->assertSame('parent@example.com', $token->email);
        $this->assertNotNull($token->token_hash);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertSame('Molly', $token->chart_data['children'][0]['childName']);
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

    public function test_magic_link_signs_user_in_and_saves_the_pending_chart(): void
    {
        $plainToken = 'known-test-token';

        MagicLoginToken::create([
            'email' => 'parent@example.com',
            'token_hash' => hash('sha256', $plainToken),
            'chart_data' => $this->chartData('Molly'),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->get(URL::temporarySignedRoute('magic.consume', now()->addMinutes(30), ['token' => $plainToken]))
            ->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'parent@example.com',
        ]);
        $this->assertDatabaseCount('chore_charts', 1);
        $this->assertSame('Molly', ChoreChart::firstOrFail()->data['children'][0]['childName']);
        $this->assertNotNull(MagicLoginToken::firstOrFail()->used_at);
    }

    public function test_magic_link_cannot_be_reused(): void
    {
        $plainToken = 'used-test-token';

        MagicLoginToken::create([
            'email' => 'parent@example.com',
            'token_hash' => hash('sha256', $plainToken),
            'chart_data' => $this->chartData('Molly'),
            'expires_at' => now()->addMinutes(30),
            'used_at' => now(),
        ]);

        $this->get(URL::temporarySignedRoute('magic.consume', now()->addMinutes(30), ['token' => $plainToken]))
            ->assertForbidden();

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('chore_charts', 0);
    }

    public function test_signed_in_user_can_save_chart_changes(): void
    {
        $user = User::factory()->create(['email' => 'parent@example.com']);

        $this->actingAs($user);

        Livewire::test('chart-builder')
            ->set('chart.children.0.childName', 'Molly')
            ->call('saveChart')
            ->assertSet('notice', 'Saved.');

        $this->assertDatabaseCount('chore_charts', 1);
        $this->assertSame('Molly', $user->choreChart()->firstOrFail()->data['children'][0]['childName']);
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
