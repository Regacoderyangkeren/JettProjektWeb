<?php

namespace Tests\Feature;

use App\Exceptions\AuthenticatedProfileUnavailable;
use App\Services\JettAuthService;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth\SignIn\FailedToSignIn;
use Kreait\Firebase\Exception\Auth\EmailExists;
use Mockery;
use RuntimeException;
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

    public function test_login_explains_when_auth_succeeds_but_profile_is_unavailable(): void
    {
        Log::spy();

        $auth = Mockery::mock(JettAuthService::class);
        $auth->shouldReceive('login')->once()->andThrow(
            new AuthenticatedProfileUnavailable(new RuntimeException('Firestore unavailable.'))
        );
        $this->app->instance(JettAuthService::class, $auth);

        $response = $this->post('/login', [
            'email' => 'member@example.test',
            'password' => 'Password123',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Password diterima, tetapi profil belum bisa dimuat. Coba lagi sesaat.',
        ]);
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
