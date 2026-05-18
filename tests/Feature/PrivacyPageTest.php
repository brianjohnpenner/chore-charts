<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_page_renders(): void
    {
        $this->get('/privacy')
            ->assertStatus(200)
            ->assertSee('Privacy Policy')
            ->assertSee('What we collect')
            ->assertSee('emailed magic link', false);
    }

    public function test_homepage_links_to_privacy_page(): void
    {
        $this->get('/')
            ->assertStatus(200)
            ->assertSee(route('privacy'), false);
    }
}
