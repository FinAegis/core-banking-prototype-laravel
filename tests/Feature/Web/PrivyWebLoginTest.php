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
    // The route throttles count per IP in the cache store, which persists
    // between tests when it is Redis.
    Illuminate\Support\Facades\Cache::flush();

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

it('provisions a personal team for a brand-new Privy web signup', function (): void {
    mock_privy_otp_client(function (MockInterface $m): void {
        $m->shouldReceive('loginWithCode')
            ->once()
            ->with('teamless@example.com', '123456')
            ->andReturn(['id' => 'did:privy:needsteam', 'email' => 'teamless@example.com']);
    });

    $this->post(route('login.privy.verify'), [
        'email' => 'teamless@example.com',
        'code'  => '123456',
    ])->assertRedirect();

    // A teamless user 500s on the first team-aware Blade view — the dashboard
    // navigation dereferences Auth::user()->currentTeam->name.
    // personalTeam() is defined as ownedTeams->where('personal_team', true)
    // ->first() — a non-null result proves a personal team was provisioned.
    $user = User::where('privy_user_id', 'did:privy:needsteam')->firstOrFail();
    expect($user->personalTeam())->not->toBeNull();
    expect($user->currentTeam)->not->toBeNull();
});

it('lands a brand-new Privy signup on a working dashboard without a 500', function (): void {
    mock_privy_otp_client(function (MockInterface $m): void {
        $m->shouldReceive('loginWithCode')
            ->once()
            ->with('dashok@example.com', '123456')
            ->andReturn(['id' => 'did:privy:dashboardok', 'email' => 'dashok@example.com']);
    });

    // followingRedirects() chases the 302 into GET /dashboard — the exact
    // request that 500'd in production when the new user had no team.
    $response = $this->followingRedirects()->post(route('login.privy.verify'), [
        'email' => 'dashok@example.com',
        'code'  => '123456',
    ]);

    $response->assertOk();
    $this->assertAuthenticated();
});

it('heals a returning Privy user that was left without a personal team', function (): void {
    // A user created before team provisioning existed — or by a pre-fix web
    // signup that 500'd *after* the User row was inserted. A retry lands on
    // the returning-user branch and would 500 on the dashboard forever.
    User::factory()->create([
        'email'           => 'healme@example.com',
        'privy_user_id'   => 'did:privy:healme',
        'privy_linked_at' => now(),
    ]);

    mock_privy_otp_client(function (MockInterface $m): void {
        $m->shouldReceive('loginWithCode')
            ->once()
            ->with('healme@example.com', '222222')
            ->andReturn(['id' => 'did:privy:healme', 'email' => 'healme@example.com']);
    });

    $response = $this->followingRedirects()->post(route('login.privy.verify'), [
        'email' => 'healme@example.com',
        'code'  => '222222',
    ]);

    $response->assertOk();
    $healed = User::where('privy_user_id', 'did:privy:healme')->firstOrFail();
    expect($healed->personalTeam())->not->toBeNull();
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

it('links a non-Privy account that already owns the email and signs it in', function (): void {
    // Privy proved inbox control, so the account created before Privy is
    // linked instead of refused (tests/Feature/Auth/PrivyAccountLinkTest.php
    // covers the unverified-email case).
    $existing = User::factory()->create([
        'email'         => 'legacy@example.com',
        'privy_user_id' => null,
    ]);

    mock_privy_otp_client(function (MockInterface $m): void {
        $m->shouldReceive('loginWithCode')
            ->once()
            ->andReturn(['id' => 'did:privy:legacyowner', 'email' => 'legacy@example.com']);
    });

    $this->post(route('login.privy.verify'), [
        'email' => 'legacy@example.com',
        'code'  => '654321',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($existing);
    expect($existing->fresh()?->privy_user_id)->toBe('did:privy:legacyowner')
        ->and(User::where('email', 'legacy@example.com')->count())->toBe(1);
});

it('refuses verifyCode when the email belongs to an account linked to a different Privy identity', function (): void {
    User::factory()->create([
        'email'           => 'taken@example.com',
        'privy_user_id'   => 'did:privy:firstowner',
        'privy_linked_at' => now(),
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
    expect(User::where('privy_user_id', 'did:privy:tryingtotakeover')->count())->toBe(0)
        ->and(User::where('email', 'taken@example.com')->value('privy_user_id'))->toBe('did:privy:firstowner');
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
        ->assertSeeText('You sign in with a code that we send to your email.');
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
