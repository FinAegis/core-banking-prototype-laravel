<?php

declare(strict_types=1);

use App\Domain\Auth\Services\PrivyAccountLinker;
use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\Auth\Services\PrivyJwtVerifier;
use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Mobile\Models\MobileDevice;
use App\Models\ApiKey;
use App\Models\User;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery\MockInterface;

/*
| Privy email sign-in links an account created before Privy that uses the
| same email address. If that address was never verified, every earlier way
| into the account ends. Both the mobile Privy login and the web email-code
| login go through PrivyAccountLinker.
*/

/**
 * @return array{privatePem: string, kid: string, jwks: array<string, mixed>}
 */
function palKeys(): array
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
    $kid = 'pal-kid';

    $keys = [
        'privatePem' => $privatePem,
        'kid'        => $kid,
        'jwks'       => ['keys' => [['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $kid, 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]]],
    ];

    return $keys;
}

function palMobileSetup(): void
{
    config([
        'privy.app_id'                 => 'pal-app-id',
        'privy.app_secret'             => 'pal-secret',
        'privy.jwks_url'               => 'https://privy.example/.well-known/jwks.json',
        'privy.issuer'                 => 'privy.io',
        'privy.jwks_cache_ttl_seconds' => 3600,
    ]);

    $jwks = palKeys()['jwks'];

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

function palToken(string $did, string $email): string
{
    $keys = palKeys();

    return JWT::encode([
        'sub'             => $did,
        'iss'             => 'privy.io',
        'aud'             => 'pal-app-id',
        'iat'             => time() - 60,
        'exp'             => time() + 3600,
        'linked_accounts' => [['type' => 'email', 'address' => $email]],
    ], $keys['privatePem'], 'RS256', $keys['kid']);
}

/**
 * Web email-code login, with Privy answering the code for this identity.
 */
function palWebCode(string $did, string $email): void
{
    config([
        'privy.app_id'                     => 'pal-app-id',
        'privy.app_secret'                 => 'pal-secret',
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

/**
 * @param  array<string, mixed>  $attributes
 */
function palLegacyUser(array $attributes = []): User
{
    return User::factory()
        ->state([
            'password'       => Hash::make('legacy-password'),
            'remember_token' => 'legacy-remember-token',
            'privy_user_id'  => null,
        ])
        ->create($attributes);
}

/**
 * A column exactly as stored, to show that a sign-in left it untouched.
 */
function palStored(User $user, string $column): mixed
{
    return DB::table('users')->where('id', $user->id)->value($column);
}

/**
 * Every way into the account that a pre-hijacker could have set up.
 *
 * @return array{token_id: int|string, device_id: string, api_key_id: int|string}
 */
function palEarlierAccess(User $user): array
{
    $token = $user->createToken('legacy', ['read', 'write', 'delete']);

    DB::table('oauth_access_tokens')->insert([
        'id'         => 'pal-grant-' . $user->id,
        'user_id'    => $user->id,
        'client_id'  => (string) Str::uuid(),
        'name'       => null,
        'scopes'     => '[]',
        'revoked'    => false,
        'created_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addDay(),
    ]);
    DB::table('oauth_refresh_tokens')->insert([
        'id'              => 'pal-grant-' . $user->id . '-refresh',
        'access_token_id' => 'pal-grant-' . $user->id,
        'revoked'         => false,
        'expires_at'      => now()->addMonth(),
    ]);

    DB::table((string) config('session.table', 'sessions'))->insert([
        'id'            => 'pal-session-' . $user->id,
        'user_id'       => $user->id,
        'ip_address'    => '203.0.113.9',
        'user_agent'    => 'Earlier browser',
        'payload'       => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    $device = MobileDevice::factory()->create([
        'user_id'               => $user->id,
        'passkey_enabled'       => true,
        'passkey_credential_id' => 'pal-credential',
        'passkey_public_key'    => 'pal-public-key',
    ]);

    $apiKey = ApiKey::createForUser($user, ['name' => 'Earlier key'])['api_key'];

    return [
        'token_id'   => $token->accessToken->getKey(),
        'device_id'  => (string) $device->getKey(),
        'api_key_id' => $apiKey->getKey(),
    ];
}

/**
 * @param  array{token_id: int|string, device_id: string, api_key_id: int|string}  $access
 */
function palEarlierAccessKept(User $user, array $access): bool
{
    return DB::table('personal_access_tokens')->where('id', $access['token_id'])->exists()
        && ! (bool) DB::table('oauth_access_tokens')->where('id', 'pal-grant-' . $user->id)->value('revoked')
        && ! (bool) DB::table('oauth_refresh_tokens')->where('id', 'pal-grant-' . $user->id . '-refresh')->value('revoked')
        && DB::table((string) config('session.table', 'sessions'))->where('id', 'pal-session-' . $user->id)->exists()
        && MobileDevice::query()->whereKey($access['device_id'])->exists()
        && (bool) ApiKey::query()->whereKey($access['api_key_id'])->value('is_active');
}

/**
 * @param  array{token_id: int|string, device_id: string, api_key_id: int|string}  $access
 */
function palEarlierAccessEnded(User $user, array $access): bool
{
    return ! DB::table('personal_access_tokens')->where('id', $access['token_id'])->exists()
        && (bool) DB::table('oauth_access_tokens')->where('id', 'pal-grant-' . $user->id)->value('revoked')
        && (bool) DB::table('oauth_refresh_tokens')->where('id', 'pal-grant-' . $user->id . '-refresh')->value('revoked')
        && ! DB::table((string) config('session.table', 'sessions'))->where('id', 'pal-session-' . $user->id)->exists()
        && ! MobileDevice::query()->whereKey($access['device_id'])->exists()
        && ! (bool) ApiKey::query()->whereKey($access['api_key_id'])->value('is_active');
}

function palLinkAudits(User $user): int
{
    return AuditLog::query()
        ->where('action', PrivyAccountLinker::AUDIT_ACTION)
        ->where('user_uuid', $user->uuid)
        ->count();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function palTwoFactorUser(array $attributes): User
{
    return palLegacyUser(array_merge([
        'two_factor_secret'         => encrypt('pal-totp-secret'),
        'two_factor_recovery_codes' => encrypt('[]'),
        'two_factor_confirmed_at'   => now()->subMonth(),
    ], $attributes));
}

/**
 * The account is exactly as it was before the refused sign-in.
 *
 * @param  array{token_id: int|string, device_id: string, api_key_id: int|string}  $access
 * @param  array<string, mixed>  $stored
 */
function palUntouched(User $user, array $access, array $stored): bool
{
    $now = (array) DB::table('users')->where('id', $user->id)->first();

    return array_intersect_key($now, $stored) == $stored
        && palEarlierAccessKept($user, $access)
        && DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count() === 1
        && DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->count() === 1
        && palLinkAudits($user) === 0;
}

/**
 * @return array<string, mixed>
 */
function palStoredRow(User $user): array
{
    $row = (array) DB::table('users')->where('id', $user->id)->first();

    return array_intersect_key($row, array_flip([
        'privy_user_id', 'privy_linked_at', 'password', 'remember_token', 'email_verified_at',
        'two_factor_secret', 'two_factor_confirmed_at', 'oauth_provider', 'oauth_id', 'updated_at',
    ]));
}

function palTwoFactorRefusal(User $user): ?AuditLog
{
    return AuditLog::query()
        ->where('action', PrivyAccountLinker::AUDIT_ACTION_REFUSED_TWO_FACTOR)
        ->where('user_uuid', $user->uuid)
        ->first();
}

beforeEach(function (): void {
    // Keep counters, rate limiters and IP blocks out of the Redis cache that
    // CI's parallel processes share.
    Tests\Traits\SharedCacheIsolation::isolate();
    config(['cache.default' => 'array', 'session.driver' => 'database']);
    Cache::store('array')->flush();
    Cache::flush();
});

describe('mobile Privy login links an existing account', function (): void {
    beforeEach(function (): void {
        palMobileSetup();
    });

    it('links a verified account and keeps its password, tokens and sessions', function (): void {
        $user = palLegacyUser(['email' => 'verified@example.com', 'email_verified_at' => now()->subYear()]);
        $access = palEarlierAccess($user);

        $response = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palverified', 'verified@example.com')]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.privy_user_id', 'did:privy:palverified')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.is_new_user', false);

        $fresh = $user->fresh();
        expect($fresh?->privy_user_id)->toBe('did:privy:palverified')
            ->and($fresh?->privy_linked_at)->not->toBeNull()
            ->and(Hash::check('legacy-password', (string) $fresh?->password))->toBeTrue()
            ->and($fresh?->remember_token)->toBe('legacy-remember-token')
            ->and(palEarlierAccessKept($user, $access))->toBeTrue()
            ->and(User::query()->where('email', 'verified@example.com')->count())->toBe(1);
    });

    it('answers a linked account exactly like a returning Privy login', function (): void {
        palLegacyUser(['email' => 'shape@example.com']);
        $token = palToken('did:privy:palshape', 'shape@example.com');

        $linked = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => $token])->assertOk()->json();
        $returning = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => $token])->assertOk()->json();

        expect(array_keys($linked))->toBe(array_keys($returning))
            ->and(array_keys($linked['data']))->toBe(array_keys($returning['data']))
            ->and(array_keys($linked['data']['user']))->toEqualCanonicalizing(array_keys($returning['data']['user']))
            ->and($linked['data']['is_new_user'])->toBeFalse()
            ->and($returning['data']['is_new_user'])->toBeFalse();
    });

    it('links an unverified account with an unconfirmed two-factor secret and ends every earlier way into it', function (): void {
        // A secret that was never confirmed is not enforced at sign-in
        // (Fortify confirm => true), so the normal rules apply.
        $user = palLegacyUser([
            'email'                     => 'unverified@example.com',
            'email_verified_at'         => null,
            'two_factor_secret'         => encrypt('pal-totp-secret'),
            'two_factor_recovery_codes' => encrypt('[]'),
            'two_factor_confirmed_at'   => null,
            'oauth_provider'            => 'github',
            'oauth_id'                  => 'pal-github-id',
        ]);
        $access = palEarlierAccess($user);

        $response = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palunverified', 'unverified@example.com')]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.is_new_user', false);

        $fresh = $user->fresh();
        expect($fresh?->privy_user_id)->toBe('did:privy:palunverified')
            ->and($fresh?->email_verified_at)->not->toBeNull()
            ->and(Hash::check('legacy-password', (string) $fresh?->password))->toBeFalse()
            ->and($fresh?->remember_token)->not->toBe('legacy-remember-token')
            ->and($fresh?->two_factor_secret)->toBeNull()
            ->and($fresh?->two_factor_confirmed_at)->toBeNull()
            ->and($fresh?->oauth_provider)->toBeNull()
            ->and($fresh?->oauth_id)->toBeNull()
            ->and(palEarlierAccessEnded($user, $access))->toBeTrue()
            // Only the token issued by this sign-in is left.
            ->and($fresh?->tokens()->count())->toBe(1)
            ->and($fresh?->tokens()->value('name'))->toBe('privy');
    });

    it('refuses an account with confirmed two-factor authentication and changes nothing', function (?string $verifiedAt): void {
        $user = palTwoFactorUser(['email' => 'two-factor@example.com', 'email_verified_at' => $verifiedAt]);
        $access = palEarlierAccess($user);
        $stored = palStoredRow($user);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:paltwofactor', 'two-factor@example.com')])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'EMAIL_ALREADY_EXISTS')
            ->assertJsonMissingPath('data.token');

        expect(palUntouched($user, $access, $stored))->toBeTrue()
            ->and(User::query()->where('privy_user_id', 'did:privy:paltwofactor')->exists())->toBeFalse();

        $refusal = palTwoFactorRefusal($user);
        expect($refusal)->not->toBeNull()
            ->and($refusal?->metadata)->toMatchArray([
                'attempted_privy_user_id' => 'did:privy:paltwofactor',
                'was_email_verified'      => $verifiedAt !== null,
                'flow'                    => 'mobile',
            ]);
    })->with([
        'verified email'   => ['2025-01-01 00:00:00'],
        'unverified email' => [null],
    ]);

    it('links a verified account whose two-factor secret was never confirmed', function (): void {
        $user = palTwoFactorUser([
            'email'                   => 'unconfirmed-2fa@example.com',
            'two_factor_confirmed_at' => null,
        ]);
        $access = palEarlierAccess($user);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palunconfirmed', 'unconfirmed-2fa@example.com')])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.is_new_user', false);

        expect($user->fresh()?->privy_user_id)->toBe('did:privy:palunconfirmed')
            ->and(Hash::check('legacy-password', (string) $user->fresh()?->password))->toBeTrue()
            ->and(palEarlierAccessKept($user, $access))->toBeTrue()
            ->and(palLinkAudits($user))->toBe(1)
            ->and(palTwoFactorRefusal($user))->toBeNull();
    });

    it('matches the email address whatever its letter case', function (): void {
        $user = palLegacyUser(['email' => 'Mixed.Case@Example.COM']);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palcase', 'mixed.case@example.com')])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.is_new_user', false);

        expect($user->fresh()?->privy_user_id)->toBe('did:privy:palcase')
            ->and($user->fresh()?->email)->toBe('Mixed.Case@Example.COM')
            ->and(User::query()->whereRaw('LOWER(email) = ?', ['mixed.case@example.com'])->count())->toBe(1);
    });

    it('answers 409 when the account is linked to a different Privy identity, and changes nothing', function (): void {
        $user = palLegacyUser([
            'email'             => 'other-identity@example.com',
            'email_verified_at' => null,
            'privy_user_id'     => 'did:privy:palfirstowner',
            'privy_linked_at'   => now()->subMonth(),
        ]);
        $access = palEarlierAccess($user);
        $linkedAt = palStored($user, 'privy_linked_at');

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palsecondowner', 'other-identity@example.com')])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'EMAIL_ALREADY_EXISTS');

        $fresh = $user->fresh();
        expect($fresh?->privy_user_id)->toBe('did:privy:palfirstowner')
            ->and(palStored($user, 'privy_linked_at'))->toBe($linkedAt)
            ->and($fresh?->email_verified_at)->toBeNull()
            ->and(Hash::check('legacy-password', (string) $fresh?->password))->toBeTrue()
            ->and(palEarlierAccessKept($user, $access))->toBeTrue()
            ->and(User::query()->where('privy_user_id', 'did:privy:palsecondowner')->exists())->toBeFalse()
            ->and(palLinkAudits($user))->toBe(0);
    });

    it('links an existing account while registration is closed, but refuses a brand-new one', function (): void {
        config(['registration.api_enabled' => false]);
        $user = palLegacyUser(['email' => 'closed-existing@example.com']);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palclosedold', 'closed-existing@example.com')])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.is_new_user', false);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palclosednew', 'closed-new@example.com')])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_001');

        expect($user->fresh()?->privy_user_id)->toBe('did:privy:palclosedold')
            ->and(User::query()->where('email', 'closed-new@example.com')->exists())->toBeFalse()
            ->and(User::query()->where('privy_user_id', 'did:privy:palclosednew')->exists())->toBeFalse();
    });

    it('links an existing account in invite-only mode without an invitation', function (): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => true]);
        $user = palLegacyUser(['email' => 'invite-existing@example.com']);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palinviteold', 'invite-existing@example.com')])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palinvitenew', 'invite-new@example.com')])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_002');

        expect(User::query()->where('email', 'invite-new@example.com')->exists())->toBeFalse();
    });

    it('leaves a returning Privy user unchanged', function (): void {
        $user = palLegacyUser([
            'email'             => 'returning@example.com',
            'email_verified_at' => null,
            'privy_user_id'     => 'did:privy:palreturning',
            'privy_linked_at'   => now()->subWeek(),
        ]);
        $access = palEarlierAccess($user);
        $linkedAt = palStored($user, 'privy_linked_at');

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palreturning', 'returning@example.com')])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.is_new_user', false);

        $fresh = $user->fresh();
        expect(palStored($user, 'privy_linked_at'))->toBe($linkedAt)
            ->and($fresh?->email_verified_at)->toBeNull()
            ->and(Hash::check('legacy-password', (string) $fresh?->password))->toBeTrue()
            ->and(palEarlierAccessKept($user, $access))->toBeTrue()
            ->and(palLinkAudits($user))->toBe(0);
    });

    it('still creates a brand-new user when no account matches', function (): void {
        $this->postJson('/api/v1/auth/privy-login', [
            'privy_token' => palToken('did:privy:palbrandnew', 'brand-new@example.com'),
            'name'        => 'Brand New',
            'timezone'    => 'Europe/Vilnius',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', true);

        $user = User::query()->where('privy_user_id', 'did:privy:palbrandnew')->firstOrFail();
        expect($user->email)->toBe('brand-new@example.com')
            ->and($user->name)->toBe('Brand New')
            ->and($user->timezone)->toBe('Europe/Vilnius')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and(palLinkAudits($user))->toBe(0);
    });

    it('writes an audit row for the link', function (): void {
        $verified = palLegacyUser(['email' => 'audit-verified@example.com']);
        $unverified = palLegacyUser(['email' => 'audit-unverified@example.com', 'email_verified_at' => null]);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palauditv', 'audit-verified@example.com')])->assertOk();
        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palauditu', 'audit-unverified@example.com')])->assertOk();

        $row = AuditLog::query()->where('action', 'auth.privy_identity_linked')->where('user_uuid', $verified->uuid)->sole();
        expect($row->auditable_type)->toBe(User::class)
            ->and((string) $row->auditable_id)->toBe((string) $verified->id)
            ->and($row->metadata)->toMatchArray(['was_email_verified' => true, 'flow' => 'mobile', 'earlier_access_ended' => false])
            ->and($row->new_values)->toBe(['privy_user_id' => 'did:privy:palauditv']);

        $row = AuditLog::query()->where('action', 'auth.privy_identity_linked')->where('user_uuid', $unverified->uuid)->sole();
        expect($row->metadata)->toMatchArray(['was_email_verified' => false, 'flow' => 'mobile', 'earlier_access_ended' => true]);
    });

    it('does not link twice when a concurrent sign-in for the same identity links first', function (): void {
        $user = palLegacyUser(['email' => 'race@example.com', 'email_verified_at' => null]);
        $access = palEarlierAccess($user);

        // Right after the email lookup, another request for the same
        // identity commits its link.
        $done = false;
        DB::listen(function (QueryExecuted $query) use (&$done, $user): void {
            if ($done || ! str_contains(strtolower($query->sql), 'lower(email)')) {
                return;
            }
            $done = true;
            DB::table('users')->where('id', $user->id)->update(['privy_user_id' => 'did:privy:palrace', 'privy_linked_at' => now()]);
        });

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palrace', 'race@example.com')])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.is_new_user', false);

        // The first request did the link; this one neither links again nor
        // ends access a second time.
        expect($done)->toBeTrue()
            ->and(User::query()->where('privy_user_id', 'did:privy:palrace')->count())->toBe(1)
            ->and(palEarlierAccessKept($user, $access))->toBeTrue()
            ->and(palLinkAudits($user))->toBe(0);
    });

    it('refuses when a concurrent sign-in links the account to a different identity first', function (): void {
        $user = palLegacyUser(['email' => 'race-other@example.com']);

        $done = false;
        DB::listen(function (QueryExecuted $query) use (&$done, $user): void {
            if ($done || ! str_contains(strtolower($query->sql), 'lower(email)')) {
                return;
            }
            $done = true;
            DB::table('users')->where('id', $user->id)->update(['privy_user_id' => 'did:privy:palracewinner', 'privy_linked_at' => now()]);
        });

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palraceloser', 'race-other@example.com')])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'EMAIL_ALREADY_EXISTS');

        expect($user->fresh()?->privy_user_id)->toBe('did:privy:palracewinner')
            ->and(palLinkAudits($user))->toBe(0);
    });

    it('signs in instead of failing when a concurrent first sign-in creates the same new user first', function (): void {
        $done = false;
        User::creating(function (User $creating) use (&$done): void {
            if ($done || $creating->privy_user_id !== 'did:privy:palcreaterace') {
                return;
            }
            $done = true;
            DB::table('users')->insert([
                'uuid'              => (string) Str::uuid(),
                'name'              => 'Winner',
                'email'             => 'create-race@example.com',
                'password'          => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'privy_user_id'     => 'did:privy:palcreaterace',
                'privy_linked_at'   => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        });

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palcreaterace', 'create-race@example.com')])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Winner')
            ->assertJsonPath('data.is_new_user', false);

        expect($done)->toBeTrue()
            ->and(User::query()->where('privy_user_id', 'did:privy:palcreaterace')->count())->toBe(1)
            ->and(User::query()->where('email', 'create-race@example.com')->count())->toBe(1);
    });

    it('never links or claims the address of an erased account', function (): void {
        $erased = palLegacyUser(['email' => 'deleted-' . Str::uuid() . '@anonymized.local', 'email_verified_at' => null]);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => palToken('did:privy:palerased', (string) $erased->email)])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'EMAIL_ALREADY_EXISTS');

        expect($erased->fresh()?->privy_user_id)->toBeNull()
            ->and($erased->fresh()?->email_verified_at)->toBeNull()
            ->and(User::query()->where('privy_user_id', 'did:privy:palerased')->exists())->toBeFalse();
    });
});

describe('web email-code login links an existing account', function (): void {
    it('links a verified account and signs it in, keeping its password, tokens and sessions', function (): void {
        $user = palLegacyUser(['email' => 'web-verified@example.com', 'email_verified_at' => now()->subYear()]);
        $access = palEarlierAccess($user);
        palWebCode('did:privy:palwebverified', 'web-verified@example.com');

        $this->post(route('login.privy.verify'), ['email' => 'web-verified@example.com', 'code' => '123456'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $fresh = $user->fresh();
        expect($fresh?->privy_user_id)->toBe('did:privy:palwebverified')
            ->and(Hash::check('legacy-password', (string) $fresh?->password))->toBeTrue()
            ->and(palEarlierAccessKept($user, $access))->toBeTrue();
    });

    it('links an unverified account, ends every earlier way into it and signs it in', function (): void {
        $user = palLegacyUser(['email' => 'web-unverified@example.com', 'email_verified_at' => null]);
        $access = palEarlierAccess($user);
        palWebCode('did:privy:palwebunverified', 'web-unverified@example.com');

        $this->post(route('login.privy.verify'), ['email' => 'web-unverified@example.com', 'code' => '123456'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $fresh = $user->fresh();
        expect($fresh?->privy_user_id)->toBe('did:privy:palwebunverified')
            ->and($fresh?->email_verified_at)->not->toBeNull()
            ->and(Hash::check('legacy-password', (string) $fresh?->password))->toBeFalse()
            ->and(palEarlierAccessEnded($user, $access))->toBeTrue();
    });

    it('refuses an account with confirmed two-factor authentication and changes nothing', function (): void {
        $user = palTwoFactorUser(['email' => 'web-two-factor@example.com']);
        $access = palEarlierAccess($user);
        $stored = palStoredRow($user);
        palWebCode('did:privy:palwebtwofactor', 'web-two-factor@example.com');

        $this->post(route('login.privy.verify'), ['email' => 'web-two-factor@example.com', 'code' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionHas('error_code', 'EMAIL_ALREADY_EXISTS');

        $this->assertGuest();
        expect(palUntouched($user, $access, $stored))->toBeTrue()
            ->and(palTwoFactorRefusal($user)?->metadata)->toMatchArray([
                'attempted_privy_user_id' => 'did:privy:palwebtwofactor',
                'flow'                    => 'web',
            ]);
    });

    it('links an unverified account whose two-factor secret was never confirmed', function (): void {
        $user = palTwoFactorUser([
            'email'                   => 'web-unconfirmed-2fa@example.com',
            'email_verified_at'       => null,
            'two_factor_confirmed_at' => null,
        ]);
        $access = palEarlierAccess($user);
        palWebCode('did:privy:palwebunconfirmed', 'web-unconfirmed-2fa@example.com');

        $this->post(route('login.privy.verify'), ['email' => 'web-unconfirmed-2fa@example.com', 'code' => '123456'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $fresh = $user->fresh();
        expect($fresh?->privy_user_id)->toBe('did:privy:palwebunconfirmed')
            ->and($fresh?->two_factor_secret)->toBeNull()
            ->and(palEarlierAccessEnded($user, $access))->toBeTrue()
            ->and(palTwoFactorRefusal($user))->toBeNull();
    });

    it('matches the email address whatever its letter case', function (): void {
        $user = palLegacyUser(['email' => 'Web.Mixed@Example.com']);
        palWebCode('did:privy:palwebcase', 'web.mixed@example.com');

        $this->post(route('login.privy.verify'), ['email' => 'WEB.mixed@example.com', 'code' => '123456'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        expect($user->fresh()?->privy_user_id)->toBe('did:privy:palwebcase');
    });

    it('refuses when the account is linked to a different Privy identity, and changes nothing', function (): void {
        $user = palLegacyUser([
            'email'             => 'web-other@example.com',
            'email_verified_at' => null,
            'privy_user_id'     => 'did:privy:palwebfirst',
            'privy_linked_at'   => now()->subMonth(),
        ]);
        $access = palEarlierAccess($user);
        palWebCode('did:privy:palwebsecond', 'web-other@example.com');

        $this->post(route('login.privy.verify'), ['email' => 'web-other@example.com', 'code' => '123456'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email')
            ->assertSessionHas('error_code', 'EMAIL_ALREADY_EXISTS');

        $this->assertGuest();
        $fresh = $user->fresh();
        expect($fresh?->privy_user_id)->toBe('did:privy:palwebfirst')
            ->and($fresh?->email_verified_at)->toBeNull()
            ->and(Hash::check('legacy-password', (string) $fresh?->password))->toBeTrue()
            ->and(palEarlierAccessKept($user, $access))->toBeTrue()
            ->and(palLinkAudits($user))->toBe(0);
    });

    it('links an existing account while registration is closed, but refuses a brand-new one', function (): void {
        config(['registration.api_enabled' => false]);
        $user = palLegacyUser(['email' => 'web-closed@example.com']);
        palWebCode('did:privy:palwebclosed', 'web-closed@example.com');

        $this->post(route('login.privy.verify'), ['email' => 'web-closed@example.com', 'code' => '123456'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        expect($user->fresh()?->privy_user_id)->toBe('did:privy:palwebclosed');

        auth()->guard('web')->logout();
        palWebCode('did:privy:palwebclosednew', 'web-closed-new@example.com');

        $this->post(route('login.privy.verify'), ['email' => 'web-closed-new@example.com', 'code' => '123456'])
            ->assertSessionHasErrors();

        $this->assertGuest();
        expect(User::query()->where('email', 'web-closed-new@example.com')->exists())->toBeFalse()
            ->and(User::query()->where('privy_user_id', 'did:privy:palwebclosednew')->exists())->toBeFalse();
    });

    it('leaves a returning Privy user unchanged', function (): void {
        $user = palLegacyUser([
            'email'             => 'web-returning@example.com',
            'email_verified_at' => null,
            'privy_user_id'     => 'did:privy:palwebreturning',
            'privy_linked_at'   => now()->subWeek(),
        ]);
        $access = palEarlierAccess($user);
        $linkedAt = palStored($user, 'privy_linked_at');
        palWebCode('did:privy:palwebreturning', 'web-returning@example.com');

        $this->post(route('login.privy.verify'), ['email' => 'web-returning@example.com', 'code' => '123456'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $fresh = $user->fresh();
        expect(palStored($user, 'privy_linked_at'))->toBe($linkedAt)
            ->and(Hash::check('legacy-password', (string) $fresh?->password))->toBeTrue()
            ->and(palEarlierAccessKept($user, $access))->toBeTrue()
            ->and(palLinkAudits($user))->toBe(0);
    });

    it('writes an audit row for the link with the web flow', function (): void {
        $user = palLegacyUser(['email' => 'web-audit@example.com', 'email_verified_at' => null]);
        palWebCode('did:privy:palwebaudit', 'web-audit@example.com');

        $this->post(route('login.privy.verify'), ['email' => 'web-audit@example.com', 'code' => '123456'])
            ->assertRedirect(route('dashboard'));

        $row = AuditLog::query()->where('action', 'auth.privy_identity_linked')->where('user_uuid', $user->uuid)->sole();
        expect($row->metadata)->toMatchArray(['was_email_verified' => false, 'flow' => 'web', 'earlier_access_ended' => true])
            ->and((string) $row->auditable_id)->toBe((string) $user->id);
    });
});
