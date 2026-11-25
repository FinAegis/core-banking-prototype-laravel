<?php

declare(strict_types=1);

use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\Auth\Services\PrivyJwtVerifier;
use App\Models\ApiKey;
use App\Models\User;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

/*
| Production users linked to Privy may still have email_verified_at NULL
| (linked before the linker set it, or created by older code). Privy has
| verified their address, so the verified-email check lets them in, every
| Privy login fills the column, and users:backfill-privy-verified fills it
| for everyone at once.
*/

beforeEach(function (): void {
    Cache::flush();
    config(['cache.default' => 'array', 'auth.verified_email_required' => true]);
});

/**
 * @return array{privatePem: string, kid: string, jwks: array<string, mixed>}
 */
function ppvKeys(): array
{
    /** @var array{privatePem: string, kid: string, jwks: array<string, mixed>}|null $keys */
    static $keys = null;

    if ($keys !== null) {
        return $keys;
    }

    $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if ($resource === false) {
        throw new RuntimeException('Failed to generate RSA keypair');
    }
    $privatePem = '';
    openssl_pkey_export($resource, $privatePem);
    $details = openssl_pkey_get_details($resource);
    if ($details === false) {
        throw new RuntimeException('Failed to read RSA key details');
    }

    $b64 = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

    $keys = [
        'privatePem' => $privatePem,
        'kid'        => 'ppv-kid',
        'jwks'       => ['keys' => [['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => 'ppv-kid', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]]],
    ];

    return $keys;
}

function ppvMobileSetup(): void
{
    config([
        'privy.app_id'                 => 'ppv-app-id',
        'privy.app_secret'             => 'ppv-secret',
        'privy.jwks_url'               => 'https://privy.example/.well-known/jwks.json',
        'privy.issuer'                 => 'privy.io',
        'privy.jwks_cache_ttl_seconds' => 3600,
    ]);

    $jwks = ppvKeys()['jwks'];

    /** @var ClientInterface&MockInterface $http */
    $http = Mockery::mock(ClientInterface::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $http->shouldReceive('request');
    $expectation->andReturnUsing(
        fn (string $method, string $url) => $url === 'https://privy.example/.well-known/jwks.json'
            ? new PsrResponse(200, [], (string) json_encode($jwks))
            : new PsrResponse(404, [], '{}')
    );
    app()->bind(PrivyJwtVerifier::class, fn () => new PrivyJwtVerifier($http));
}

function ppvToken(string $did, string $email): string
{
    $keys = ppvKeys();

    return JWT::encode([
        'sub'             => $did,
        'iss'             => 'privy.io',
        'aud'             => 'ppv-app-id',
        'iat'             => time() - 60,
        'exp'             => time() + 3600,
        'linked_accounts' => [['type' => 'email', 'address' => $email]],
    ], $keys['privatePem'], 'RS256', $keys['kid']);
}

function ppvWebCode(string $did, string $email): void
{
    config([
        'privy.app_id'                     => 'ppv-app-id',
        'privy.app_secret'                 => 'ppv-secret',
        'privy.web_login_enabled'          => true,
        'privy.web_verify_min_duration_ms' => 0,
    ]);

    /** @var PrivyEmailOtpClient&MockInterface $otp */
    $otp = Mockery::mock(PrivyEmailOtpClient::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $otp->shouldReceive('loginWithCode');
    $expectation->andReturn(['id' => $did, 'email' => $email]);
    app()->instance(PrivyEmailOtpClient::class, $otp);
}

function ppvPrivyUnverified(string $did, string $email = 'linked@example.com'): User
{
    $user = User::factory()->create(['email' => $email, 'email_verified_at' => null]);
    $user->forceFill(['privy_user_id' => $did, 'privy_linked_at' => now()])->save();

    return $user->refresh();
}

function ppvVerifiedAt(User $user): mixed
{
    return User::query()->whereKey($user->getKey())->value('email_verified_at');
}

describe('verified-email check for Privy-linked users', function (): void {
    it('lets a Privy-linked user with a NULL email_verified_at open web pages', function (): void {
        $this->actingAs(ppvPrivyUnverified('did:privy:ppvweb'));

        $this->get('/dashboard')->assertOk();
    });

    it('lets a Privy-linked user with a NULL email_verified_at use a real API token', function (): void {
        $token = ppvPrivyUnverified('did:privy:ppvtoken')->createToken('app', ['read', 'write', 'delete'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me')
            ->assertOk();
    });

    it('lets a Privy-linked user with a NULL email_verified_at use an API key', function (): void {
        $plain = ApiKey::createForUser(ppvPrivyUnverified('did:privy:ppvkey'), ['name' => 'key', 'permissions' => ['read']])['plain_key'];

        $this->withHeader('Authorization', 'Bearer ' . $plain)
            ->getJson('/api/v2/profile')
            ->assertOk();
    });

    it('still refuses a user without Privy and without a verified email', function (): void {
        $user = User::factory()->create(['email_verified_at' => null]);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->getJson('/api/auth/me')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_003');

        $plain = ApiKey::createForUser($user, ['name' => 'key', 'permissions' => ['read']])['plain_key'];
        $this->withHeader('Authorization', 'Bearer ' . $plain)
            ->getJson('/api/v2/profile')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_003');
    });

    it('does not treat an empty privy_user_id as Privy-linked', function (): void {
        $user = User::factory()->create(['email_verified_at' => null]);
        $user->forceFill(['privy_user_id' => ''])->save();
        Sanctum::actingAs($user->refresh(), ['read', 'write', 'delete']);

        $this->getJson('/api/auth/me')->assertForbidden();
    });
});

describe('Privy login fills email_verified_at', function (): void {
    it('sets it on the mobile Privy login of a returning user', function (): void {
        ppvMobileSetup();
        $user = ppvPrivyUnverified('did:privy:ppvmobile', 'mobile@example.com');

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => ppvToken('did:privy:ppvmobile', 'mobile@example.com')])
            ->assertOk();

        expect(ppvVerifiedAt($user))->not->toBeNull();
    });

    it('sets it on the web email-code login of a returning user', function (): void {
        ppvWebCode('did:privy:ppvwebcode', 'webcode@example.com');
        $user = ppvPrivyUnverified('did:privy:ppvwebcode', 'webcode@example.com');

        $this->post('/login/privy/verify', ['email' => 'webcode@example.com', 'code' => '123456'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
        expect(ppvVerifiedAt($user))->not->toBeNull();
    });

    it('keeps an earlier verification time', function (): void {
        ppvMobileSetup();
        $user = User::factory()->create(['email' => 'kept@example.com', 'email_verified_at' => '2026-01-02 03:04:05']);
        $user->forceFill(['privy_user_id' => 'did:privy:ppvkept', 'privy_linked_at' => now()])->save();

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => ppvToken('did:privy:ppvkept', 'kept@example.com')])
            ->assertOk();

        expect((string) ppvVerifiedAt($user))->toStartWith('2026-01-02 03:04:05');
    });
});

describe('users:backfill-privy-verified', function (): void {
    it('only counts in a dry run and changes nothing', function (): void {
        $first = ppvPrivyUnverified('did:privy:ppvdry1', 'dry1@example.com');
        $second = ppvPrivyUnverified('did:privy:ppvdry2', 'dry2@example.com');
        $noPrivy = User::factory()->create(['email_verified_at' => null]);

        $this->artisan('users:backfill-privy-verified', ['--dry-run' => true])
            ->expectsOutputToContain('2 Privy-linked users have no verified email. Dry run: nothing was changed.')
            ->assertSuccessful();

        expect(ppvVerifiedAt($first))->toBeNull()
            ->and(ppvVerifiedAt($second))->toBeNull()
            ->and(ppvVerifiedAt($noPrivy))->toBeNull();
    });

    it('sets the column for Privy-linked users only and is idempotent', function (): void {
        $first = ppvPrivyUnverified('did:privy:ppvrun1', 'run1@example.com');
        $second = ppvPrivyUnverified('did:privy:ppvrun2', 'run2@example.com');
        $noPrivy = User::factory()->create(['email_verified_at' => null]);
        $verified = User::factory()->create(['email_verified_at' => '2026-01-02 03:04:05']);
        $verified->forceFill(['privy_user_id' => 'did:privy:ppvrunok'])->save();

        $this->artisan('users:backfill-privy-verified')
            ->expectsOutputToContain('Marked 2 Privy-linked users as verified.')
            ->assertSuccessful();

        expect(ppvVerifiedAt($first))->not->toBeNull()
            ->and(ppvVerifiedAt($second))->not->toBeNull()
            ->and(ppvVerifiedAt($noPrivy))->toBeNull()
            ->and((string) ppvVerifiedAt($verified))->toStartWith('2026-01-02 03:04:05');

        $firstValue = ppvVerifiedAt($first);

        $this->artisan('users:backfill-privy-verified')
            ->expectsOutputToContain('Marked 0 Privy-linked users as verified.')
            ->assertSuccessful();

        expect(ppvVerifiedAt($first))->toEqual($firstValue);
    });
});
