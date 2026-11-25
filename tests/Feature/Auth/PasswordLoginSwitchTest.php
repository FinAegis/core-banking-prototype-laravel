<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
| auth.password_login_enabled (AUTH_PASSWORD_LOGIN_ENABLED) turns off every
| way to sign in or reset with a password. Everyone signs in through Privy
| instead: the mobile app with POST /api/v1/auth/privy-login, the web with
| the email code. While it is off, a password sign-in answers 403
| ERR_AUTH_008 to JSON clients, and a web form goes back to the login page.
*/

uses(RefreshDatabase::class);

const PLS_PASSWORD = 'Corr3ct-Horse-Battery!pls';

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    Cache::store('array')->flush();
    // Password::uncompromised() asks the Pwned Passwords range API.
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
});

function plsUser(string $email = 'pls-user@example.com'): User
{
    return User::factory()->create([
        'email'    => $email,
        'password' => Hash::make(PLS_PASSWORD),
    ]);
}

describe('API password login', function (): void {
    it('refuses POST /api/auth/login and /api/v2/auth/login while the switch is off', function (string $uri): void {
        config(['auth.password_login_enabled' => false]);
        plsUser();

        $this->postJson($uri, ['email' => 'pls-user@example.com', 'password' => PLS_PASSWORD])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ERR_AUTH_008')
            ->assertJsonPath('error.reason', 'PASSWORD_LOGIN_DISABLED')
            ->assertJsonMissingPath('data.access_token');

        expect(Laravel\Sanctum\PersonalAccessToken::query()->count())->toBe(0);
    })->with(['/api/auth/login', '/api/v2/auth/login']);

    it('accepts the password while the switch is on', function (string $uri): void {
        config(['auth.password_login_enabled' => true]);
        plsUser();

        $this->postJson($uri, ['email' => 'pls-user@example.com', 'password' => PLS_PASSWORD])
            ->assertOk()
            ->assertJsonPath('success', true);
    })->with(['/api/auth/login', '/api/v2/auth/login']);
});

describe('API password reset', function (): void {
    it('refuses the reset request and sends no email while the switch is off', function (): void {
        config(['auth.password_login_enabled' => false]);
        Notification::fake();
        $user = plsUser();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ERR_AUTH_008')
            ->assertJsonPath('error.reason', 'PASSWORD_RESET_DISABLED');

        Notification::assertNothingSent();
    });

    it('sends the reset email while the switch is on', function (): void {
        config(['auth.password_login_enabled' => true]);
        Notification::fake();
        $user = plsUser();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    });

    it('refuses the reset itself while the switch is off', function (): void {
        config(['auth.password_login_enabled' => false]);
        $user = plsUser();
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'N3w-Strong-Passw0rd!pls',
            'password_confirmation' => 'N3w-Strong-Passw0rd!pls',
        ])->assertStatus(403)->assertJsonPath('error.code', 'ERR_AUTH_008');

        expect(Hash::check(PLS_PASSWORD, (string) $user->fresh()?->password))->toBeTrue();
    });
});

describe('web password login (Fortify)', function (): void {
    it('sends a POST /login back to the login page with the email-code message while the switch is off', function (): void {
        config(['auth.password_login_enabled' => false]);
        plsUser();

        $this->from('/login')
            ->post('/login', ['email' => 'pls-user@example.com', 'password' => PLS_PASSWORD])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Signing in with a password is turned off. Sign in with the code we send to your email.']);

        $this->assertGuest();
    });

    it('answers a JSON POST /login with 403 while the switch is off', function (): void {
        config(['auth.password_login_enabled' => false]);
        plsUser();

        $this->postJson('/login', ['email' => 'pls-user@example.com', 'password' => PLS_PASSWORD])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ERR_AUTH_008');

        $this->assertGuest();
    });

    it('signs in with the password while the switch is on', function (): void {
        config(['auth.password_login_enabled' => true]);
        $user = plsUser();

        $this->post('/login', ['email' => 'pls-user@example.com', 'password' => PLS_PASSWORD])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    });

    it('refuses the two-factor challenge while the switch is off', function (): void {
        config(['auth.password_login_enabled' => false]);
        $user = plsUser();

        $this->withSession(['login.id' => $user->id, 'login.remember' => false])
            ->post('/two-factor-challenge', ['code' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('refuses the web reset request and sends no email while the switch is off', function (): void {
        config(['auth.password_login_enabled' => false]);
        Notification::fake();
        $user = plsUser();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Password reset is turned off. Sign in with the code we send to your email.']);

        Notification::assertNothingSent();
    });

    it('sends the web reset email while the switch is on', function (): void {
        config(['auth.password_login_enabled' => true]);
        Notification::fake();
        $user = plsUser();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    });

    it('refuses the web reset while the switch is off', function (): void {
        config(['auth.password_login_enabled' => false]);
        $user = plsUser();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'N3w-Strong-Passw0rd!pls',
            'password_confirmation' => 'N3w-Strong-Passw0rd!pls',
        ])->assertRedirect(route('login'));

        expect(Hash::check(PLS_PASSWORD, (string) $user->fresh()?->password))->toBeTrue();
    });

    it('sends the reset pages to the login page while the switch is off', function (string $uri): void {
        config(['auth.password_login_enabled' => false]);

        $this->get($uri)->assertRedirect(route('login'));
    })->with(['/forgot-password', '/reset-password/some-token']);

    it('serves the reset pages while the switch is on', function (): void {
        config(['auth.password_login_enabled' => true]);

        $this->get('/forgot-password')->assertOk();
    });
});

describe('OAuth password grant', function (): void {
    it('does not offer the Passport password grant at all', function (bool $enabled): void {
        config(['auth.password_login_enabled' => $enabled]);
        plsUser();

        // The token endpoint needs a key pair to start; the test suite has none.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        assert($key !== false);
        $privatePem = '';
        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);
        assert($details !== false);
        config(['passport.private_key' => $privatePem, 'passport.public_key' => $details['key']]);

        $this->postJson('/oauth/token', [
            'grant_type' => 'password',
            'client_id'  => '1',
            'username'   => 'pls-user@example.com',
            'password'   => PLS_PASSWORD,
        ])->assertStatus(400)->assertJsonPath('error', 'unsupported_grant_type');
    })->with(['switch on' => [true], 'switch off' => [false]]);
});

describe('web login page', function (): void {
    it('shows no password field when Privy web login is on', function (): void {
        config(['auth.password_login_enabled' => false, 'privy.web_login_enabled' => true]);

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('name="password"', false)
            ->assertSee(route('login.privy.send'), false);
    });
});

describe('config default', function (): void {
    it('is on only for local, testing and demo unless AUTH_PASSWORD_LOGIN_ENABLED is set', function (string $appEnv, ?string $explicit, bool $expected): void {
        $config = plsAuthConfigUnderEnv(['APP_ENV' => $appEnv, 'AUTH_PASSWORD_LOGIN_ENABLED' => $explicit]);

        expect($config['password_login_enabled'])->toBe($expected);
    })->with([
        ['production', null, false],
        ['staging', null, false],
        ['local', null, true],
        ['testing', null, true],
        ['demo', null, true],
        ['production', 'true', true],
        ['demo', 'false', false],
    ]);
});

/**
 * Load config/auth.php under the given environment variables (null unsets
 * one), then restore them.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function plsAuthConfigUnderEnv(array $env): array
{
    $saved = [];
    foreach ($env as $key => $value) {
        $saved[$key] = [array_key_exists($key, $_SERVER), $_SERVER[$key] ?? null, array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];

        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        } else {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        /** @var array<string, mixed> $config */
        $config = require base_path('config/auth.php');

        return $config;
    } finally {
        foreach ($saved as $key => [$hasServer, $server, $hasEnv, $envValue, $getenv]) {
            if ($hasServer) {
                $_SERVER[$key] = $server;
            } else {
                unset($_SERVER[$key]);
            }
            if ($hasEnv) {
                $_ENV[$key] = $envValue;
            } else {
                unset($_ENV[$key]);
            }
            if ($getenv === false) {
                putenv($key);
            } else {
                putenv("{$key}={$getenv}");
            }
        }
    }
}
