<?php

declare(strict_types=1);

use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/**
 * Bind a Mockery mock for PrivyEmailOtpClient into the container.
 *
 * Using app()->instance() instead of $this->mock() so PHPStan resolves
 * cleanly — Pest test files have no class declaration, so PHPStan sees
 * $this as the bare PHPUnit\Framework\TestCase rather than our
 * Tests\TestCase (where the mock() helper lives).
 *
 * @param  callable(MockInterface): void $configure
 */
function mock_privy_otp_client(callable $configure): void
{
    /** @var PrivyEmailOtpClient&MockInterface $mock */
    $mock = Mockery::mock(PrivyEmailOtpClient::class);
    $configure($mock);
    app()->instance(PrivyEmailOtpClient::class, $mock);
}

beforeEach(function (): void {
    config([
        'privy.app_id'            => 'test-app-id',
        'privy.app_secret'        => 'test-app-secret',
        'privy.api_base_url'      => 'https://auth.privy.io',
        'privy.web_login_enabled' => true,
        'cache.default'           => 'array',
    ]);
});

it('sends an OTP and redirects to the verify step on POST /login/privy/send', function (): void {
    mock_privy_otp_client(function (MockInterface $m): void {
        $m->shouldReceive('sendCode')->once()->with('jane@example.com');
    });

    $response = $this->from(route('login'))->post(route('login.privy.send'), [
        'email' => 'Jane@Example.COM',
    ]);

    $response->assertRedirect(route('login', ['email' => 'jane@example.com', 'step' => 'verify']))
        ->assertSessionHas('status');
});

it('rejects an invalid email with a validation error', function (): void {
    mock_privy_otp_client(function (MockInterface $m): void {
        $m->shouldReceive('sendCode')->never();
    });

    $response = $this->from(route('login'))->post(route('login.privy.send'), [
        'email' => 'not-an-email',
    ]);

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});

it('signs up a new user when verifyCode resolves a Privy DID with no existing record', function (): void {
    mock_privy_otp_client(function (MockInterface $m): void {
        $m->shouldReceive('loginWithCode')
            ->once()
            ->with('jane@example.com', '123456')
            ->andReturn(['id' => 'did:privy:newuser', 'email' => 'jane@example.com']);
    });

    $response = $this->post(route('login.privy.verify'), [
        'email' => 'jane@example.com',
        'code'  => '123456',
    ]);

    $response->assertRedirect(); // intended() falls back to /dashboard
    $this->assertAuthenticated();

    $user = User::where('privy_user_id', 'did:privy:newuser')->firstOrFail();
    expect($user->email)->toBe('jane@example.com')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->privy_linked_at)->not->toBeNull();
});

it('signs in an existing Privy user without creating a duplicate row', function (): void {
    $existing = User::factory()->create([
        'email'           => 'returning@example.com',
        'privy_user_id'   => 'did:privy:returning',
        'privy_linked_at' => now(),
    ]);

    mock_privy_otp_client(function (MockInterface $m): void {
        $m->shouldReceive('loginWithCode')
            ->once()
            ->with('returning@example.com', '999999')
            ->andReturn(['id' => 'did:privy:returning', 'email' => 'returning@example.com']);
    });

    $response = $this->post(route('login.privy.verify'), [
        'email' => 'returning@example.com',
        'code'  => '999999',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticated();
    expect(User::where('privy_user_id', 'did:privy:returning')->count())->toBe(1);
    expect(auth()->id())->toBe($existing->id);
});

it('refuses verifyCode when the email is already owned by a non-Privy account', function (): void {
    User::factory()->create([
        'email'         => 'taken@example.com',
        'privy_user_id' => null,
    ]);

    mock_privy_otp_client(function (MockInterface $m): void {
        $m->shouldReceive('loginWithCode')
            ->once()
            ->andReturn(['id' => 'did:privy:tryingtotakeover', 'email' => 'taken@example.com']);
    });

    $response = $this->post(route('login.privy.verify'), [
        'email' => 'taken@example.com',
        'code'  => '654321',
    ]);

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('email')
        // Mirror the mobile transport: error.code is parseable for support diagnosis.
        ->assertSessionHas('error_code', 'EMAIL_ALREADY_EXISTS');
    $this->assertGuest();
    expect(User::where('privy_user_id', 'did:privy:tryingtotakeover')->count())->toBe(0);
});

it('returns to verify step with an error when the OTP code is wrong', function (): void {
    mock_privy_otp_client(function (MockInterface $m): void {
        $m->shouldReceive('loginWithCode')
            ->once()
            ->andThrow(new App\Domain\Auth\Exceptions\PrivyEmailOtpException('Privy /api/v1/passwordless/authenticate returned 400: Invalid code'));
    });

    $response = $this->post(route('login.privy.verify'), [
        'email' => 'jane@example.com',
        'code'  => '000000',
    ]);

    $response->assertRedirect(route('login', ['email' => 'jane@example.com', 'step' => 'verify']))
        ->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('serves the Privy OTP login view at GET /login when the feature flag is on', function (): void {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('Sign in to')
        ->assertSee('Send code')
        ->assertSeeText('Email-only, passwordless');
});

it('preserves intended URL across the OTP flow (signup-during-OAuth)', function (): void {
    mock_privy_otp_client(function (MockInterface $m): void {
        $m->shouldReceive('loginWithCode')
            ->once()
            ->andReturn(['id' => 'did:privy:intended', 'email' => 'i@example.com']);
    });

    // Simulate the OAuth-authorize path setting an intended URL before login.
    $this->withSession(['url.intended' => 'https://zelta.test/oauth/authorize?client_id=abc']);

    $response = $this->post(route('login.privy.verify'), [
        'email' => 'i@example.com',
        'code'  => '111111',
    ]);

    $response->assertRedirect('https://zelta.test/oauth/authorize?client_id=abc');
    $this->assertAuthenticated();
});
