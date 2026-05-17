<?php

namespace Tests\Feature;

use App\Mail\MagicLink;
use App\Models\Chart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_creates_a_chart_and_stores_id_in_session_for_anonymous_user(): void
    {
        $this->assertSame(0, Chart::count());

        $response = $this->get('/');

        $response->assertStatus(200);
        $this->assertSame(1, Chart::count());
        $chart = Chart::first();
        $this->assertNull($chart->user_id);
        $this->assertSame($chart->id, session('chart_id'));
    }

    public function test_homepage_reuses_session_chart_on_subsequent_visits(): void
    {
        $this->get('/');
        $chartId = session('chart_id');

        $this->withSession(['chart_id' => $chartId])->get('/');

        $this->assertSame(1, Chart::count());
        $this->assertSame($chartId, session('chart_id'));
    }

    public function test_homepage_for_logged_in_user_loads_their_latest_chart(): void
    {
        $user = User::create(['email' => 'a@b.test']);
        $chart = Chart::createDefault($user->id);

        $this->actingAs($user)->get('/')->assertStatus(200)->assertSee('Chore Charts');

        $this->assertSame(1, $user->charts()->count());
        $this->assertSame($chart->id, $user->charts()->first()->id);
    }

    public function test_send_magic_link_via_livewire_creates_user_and_sends_signed_email(): void
    {
        Mail::fake();

        Livewire::test('chart-editor', ['chartId' => Chart::createDefault()->id])
            ->set('email', 'newbie@example.com')
            ->call('sendMagicLink')
            ->assertHasNoErrors()
            ->assertSet('email', '');

        $user = User::where('email', 'newbie@example.com')->first();
        $this->assertNotNull($user);

        Mail::assertSent(MagicLink::class, function (MagicLink $mail) use ($user) {
            return str_contains($mail->url, "/magic/login/{$user->id}")
                && str_contains($mail->url, 'signature=')
                && $mail->hasTo('newbie@example.com');
        });
    }

    public function test_send_magic_link_rejects_invalid_email(): void
    {
        Mail::fake();

        Livewire::test('chart-editor', ['chartId' => Chart::createDefault()->id])
            ->set('email', 'not-an-email')
            ->call('sendMagicLink')
            ->assertHasErrors(['email']);

        $this->assertSame(0, User::count());
        Mail::assertNothingSent();
    }

    public function test_signed_magic_link_logs_user_in_and_claims_session_chart(): void
    {
        $user = User::create(['email' => 'claimer@example.com']);
        $chart = Chart::createDefault();
        $this->assertNull($chart->user_id);

        $url = URL::temporarySignedRoute('magic.login', now()->addHour(), ['user' => $user->id]);

        $response = $this->withSession(['chart_id' => $chart->id])->get($url);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->id, $chart->fresh()->user_id);
        $this->assertNull(session('chart_id'));
    }

    public function test_signed_magic_link_does_not_steal_a_chart_already_owned_by_someone_else(): void
    {
        $owner = User::create(['email' => 'owner@example.com']);
        $intruder = User::create(['email' => 'intruder@example.com']);
        $chart = Chart::createDefault($owner->id);

        $url = URL::temporarySignedRoute('magic.login', now()->addHour(), ['user' => $intruder->id]);
        $this->withSession(['chart_id' => $chart->id])->get($url);

        $this->assertSame($owner->id, $chart->fresh()->user_id);
    }

    public function test_magic_link_with_invalid_signature_is_rejected(): void
    {
        $user = User::create(['email' => 'noaccess@example.com']);

        $response = $this->get(route('magic.login', ['user' => $user->id]).'?signature=tampered');

        $response->assertStatus(403);
        $this->assertGuest();
    }

    public function test_logout_clears_authentication(): void
    {
        $user = User::create(['email' => 'bye@example.com']);

        $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
