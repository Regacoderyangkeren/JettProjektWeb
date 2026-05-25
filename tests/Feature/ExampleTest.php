<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_guests_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_the_login_page_renders(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_forwarded_https_is_used_for_login_form_urls(): void
    {
        $response = $this
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://jettprojekt.test/login');

        $response->assertOk();
        $response->assertSee('action="https://jettprojekt.test/login"', false);
    }
}
