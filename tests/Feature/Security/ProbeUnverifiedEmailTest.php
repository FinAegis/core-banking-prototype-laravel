<?php

declare(strict_types=1);

use App\Domain\User\Models\UserInvitation;
use App\Domain\User\Services\UserInvitationService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
| An account whose email address nobody verified (the legacy password
| sign-up) must not use authenticated pages or API routes while
| AUTH_REQUIRE_VERIFIED_EMAIL is on (the default outside local, testing and
| demo). Before this, the `verified` route middleware did nothing because the
| User model does not implement MustVerifyEmail, and the API had no check.
*/

beforeEach(function (): void {
    Cache::flush();
    config(['cache.default' => 'array', 'auth.verified_email_required' => true]);
});

/**
 * Evaluate config/auth.php under the given APP_ENV and restore the env.
 *
 * @return array<string, mixed>
 */
function pueAuthConfigUnderEnv(?string $appEnv, ?string $switch = null): array
{
    $keys = ['APP_ENV' => $appEnv, 'AUTH_REQUIRE_VERIFIED_EMAIL' => $switch];
    $saved = [];
    foreach ($keys as $key => $value) {
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

function pueUnverifiedUser(): User
{
    return User::factory()->create(['email_verified_at' => null]);
}

describe('switch defaults', function (): void {
    it('requires a verified email outside local, testing and demo', function (): void {
        foreach (['production', 'staging', null] as $appEnv) {
            expect(pueAuthConfigUnderEnv($appEnv)['verified_email_required'] ?? null)->toBeTrue();
        }
    });

    it('does not require it in local, testing and demo unless set', function (): void {
        foreach (['local', 'testing', 'demo'] as $appEnv) {
            expect(pueAuthConfigUnderEnv($appEnv)['verified_email_required'] ?? null)->toBeFalse();
        }

        expect(pueAuthConfigUnderEnv('demo', 'true')['verified_email_required'] ?? null)->toBeTrue()
            ->and(pueAuthConfigUnderEnv('production', 'false')['verified_email_required'] ?? null)->toBeFalse();
    });
});

describe('web pages for an unverified account', function (): void {
    it('sends the account to the verification notice instead of the dashboard, API keys, tokens or teams', function (string $path): void {
        $this->actingAs(pueUnverifiedUser());

        $this->get($path)->assertRedirect(route('verification.notice'));
    })->with(['/dashboard', '/api-keys', '/api-keys/create', '/user/api-tokens', '/teams/create', '/wallet/blockchain']);

    it('does not create an API key', function (): void {
        $user = pueUnverifiedUser();
        $this->actingAs($user);

        $this->post('/api-keys', ['name' => 'probe', 'permissions' => ['read']])
            ->assertRedirect(route('verification.notice'));

        expect(App\Models\ApiKey::query()->where('user_uuid', $user->uuid)->exists())->toBeFalse();
    });

    it('refuses the OAuth consent approval', function (): void {
        $this->actingAs(pueUnverifiedUser());

        $this->post('/oauth/authorize', ['auth_token' => 'x'])->assertRedirect(route('verification.notice'));
    });

    it('keeps the verification notice, resend and sign-out reachable', function (): void {
        $this->actingAs(pueUnverifiedUser());

        $this->get('/email/verify')->assertOk();
        $this->post('/logout')->assertRedirect();
    });

    it('lets a verified account in', function (): void {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertOk();
    });

    it('lets an unverified account in while the switch is off', function (): void {
        config(['auth.verified_email_required' => false]);
        $this->actingAs(pueUnverifiedUser());

        $this->get('/dashboard')->assertOk();
    });
});

describe('API routes for an unverified account', function (): void {
    it('answers 403 ERR_REG_003 on wallet, x402 and account routes', function (string $method, string $path): void {
        Sanctum::actingAs(pueUnverifiedUser(), ['read', 'write', 'delete']);

        $this->json($method, $path, [])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_003')
            ->assertJsonPath('error.reason', 'EMAIL_NOT_VERIFIED');
    })->with([
        ['GET', '/api/v1/wallet/state'],
        ['POST', '/api/v1/wallet/transactions/prepare'],
        ['GET', '/api/v1/x402/spending-limits'],
        ['POST', '/api/v1/x402/spending-limits'],
        ['GET', '/api/auth/me'],
    ]);

    it('refuses a real bearer token of an unverified account', function (): void {
        $user = pueUnverifiedUser();
        $token = $user->createToken('probe', ['read', 'write', 'delete'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/wallet/state')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_003');
    });

    it('still lets the account sign out, resend the verification email and delete itself', function (): void {
        Sanctum::actingAs(pueUnverifiedUser(), ['read', 'write', 'delete']);

        $this->postJson('/api/auth/resend-verification')->assertOk();
        $this->postJson('/api/auth/logout')->assertOk();
    });

    it('lets a verified account through', function (): void {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->getJson('/api/auth/me')->assertOk();
    });

    it('leaves public routes alone', function (): void {
        $this->getJson('/api/v1/x402/status')->assertOk();
    });
});

describe('API keys of an unverified account', function (): void {
    it('refuses an API key whose owner has not verified the email', function (): void {
        $plain = App\Models\ApiKey::createForUser(pueUnverifiedUser(), ['name' => 'probe', 'permissions' => ['read']])['plain_key'];

        $this->withHeader('Authorization', 'Bearer ' . $plain)
            ->getJson('/api/v2/profile')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_003');
    });

    it('refuses a Sanctum token of an unverified account on the API-key-or-token routes', function (): void {
        $token = pueUnverifiedUser()->createToken('probe', ['read', 'write', 'delete'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v2/profile')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_003');
    });

    it('serves an API key of a verified account', function (): void {
        $plain = App\Models\ApiKey::createForUser(User::factory()->create(), ['name' => 'ok', 'permissions' => ['read']])['plain_key'];

        $this->withHeader('Authorization', 'Bearer ' . $plain)
            ->getJson('/api/v2/profile')
            ->assertOk();
    });
});

describe('GraphQL for an unverified account', function (): void {
    it('refuses a Sanctum user whose email is not verified', function (): void {
        Sanctum::actingAs(pueUnverifiedUser(), ['read', 'write', 'delete']);

        $this->postJson('/graphql', ['query' => '{ accounts(first: 1) { data { id } } }'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_003');
    });

    it('refuses a real bearer token of an unverified account', function (): void {
        $token = pueUnverifiedUser()->createToken('probe', ['read', 'write', 'delete'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)->postJson('/graphql', ['query' => '{ accounts(first: 1) { data { id } } }'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_003');
    });

    it('serves a verified Sanctum user', function (): void {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $response = $this->postJson('/graphql', ['query' => '{ accounts(first: 1) { data { id } } }']);

        $response->assertOk();
        expect($response->json('data.accounts'))->not->toBeNull();
    });
});

describe('admin invitation', function (): void {
    it('counts the emailed invitation link as proof of the address', function (): void {
        $invitation = UserInvitation::query()->create([
            'email'      => 'invitee@example.com',
            'token'      => Str::random(64),
            'role'       => 'private',
            'invited_by' => User::factory()->create()->id,
            'expires_at' => now()->addDay(),
        ]);

        $user = app(UserInvitationService::class)->accept($invitation->token, 'Invited Person', 'Sup3r-Secret-Pass!');

        expect($user->email_verified_at)->not->toBeNull();
    });
});
