<?php

namespace Tests\Feature;

use App\Services\JettAuthService;
use Kreait\Firebase\Auth\SignIn\FailedToSignIn;
use Kreait\Firebase\Exception\Auth\EmailExists;
use Mockery;
use Tests\TestCase;

class AuthenticationFeedbackTest extends TestCase
{
    public function test_registration_reports_when_email_is_already_registered(): void
    {
        $auth = Mockery::mock(JettAuthService::class);
        $auth->shouldReceive('register')->once()->andThrow(new EmailExists('Already used.'));
        $this->app->instance(JettAuthService::class, $auth);

        $response = $this->post('/register', $this->registrationData());

        $response->assertSessionHasErrors([
            'email' => 'Email ini sudah terdaftar. Silakan masuk.',
        ]);
    }

    public function test_login_reports_invalid_credentials_only_for_firebase_rejection(): void
    {
        $auth = Mockery::mock(JettAuthService::class);
        $auth->shouldReceive('login')->once()->andThrow(new FailedToSignIn('INVALID_LOGIN_CREDENTIALS'));
        $this->app->instance(JettAuthService::class, $auth);

        $response = $this->post('/login', [
            'email' => 'member@example.test',
            'password' => 'Password123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Email atau password belum cocok.',
        ]);
    }

    public function test_login_opens_session_with_profile_returned_by_auth_service(): void
    {
        $auth = Mockery::mock(JettAuthService::class);
        $auth->shouldReceive('login')->once()->andReturn([
            'uid' => 'user_1',
            'profile' => [
                'email' => 'member@example.test',
                'alias' => 'member',
            ],
        ]);
        $this->app->instance(JettAuthService::class, $auth);

        $response = $this->post('/login', [
            'email' => 'member@example.test',
            'password' => 'Password123',
        ]);

        $response->assertRedirect('/dashboard')
            ->assertSessionHas('firebase.uid', 'user_1')
            ->assertSessionHas('firebase.profile.alias', 'member');
    }

    public function test_api_returns_conflict_for_existing_registration(): void
    {
        $auth = Mockery::mock(JettAuthService::class);
        $auth->shouldReceive('register')->once()->andThrow(new EmailExists('Already used.'));
        $this->app->instance(JettAuthService::class, $auth);

        $response = $this->postJson('/api/auth/register', $this->registrationData());

        $response->assertStatus(409)
            ->assertJsonPath('message', 'This email is already registered.');
    }

    private function registrationData(): array
    {
        return [
            'firstName' => 'Jett',
            'lastName' => 'Member',
            'alias' => 'jett_member',
            'email' => 'member@example.test',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ];
    }
}
