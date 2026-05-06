<?php

declare(strict_types=1);

use App\Domain\Auth\Services\PrivyJwtVerifier;
use App\Models\User;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

/**
 * @return array{privatePem: string, jwk: array<string, string>, kid: string}
 */
function privy_login_test_keypair(): array
{
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if ($resource === false) {
        throw new RuntimeException('Failed to generate RSA keypair: ' . openssl_error_string());
    }

    $privatePem = '';
    openssl_pkey_export($resource, $privatePem);
    $details = openssl_pkey_get_details($resource);
    if ($details === false) {
        throw new RuntimeException('Failed to read RSA key details');
    }

    $kid = 'privy-feature-test';

    $base64Url = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

    $jwk = [
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => $kid,
        'n'   => $base64Url($details['rsa']['n']),
        'e'   => $base64Url($details['rsa']['e']),
    ];

    return ['privatePem' => $privatePem, 'jwk' => $jwk, 'kid' => $kid];
}

/**
 * @param array<string, mixed> $payload
 */
function privy_login_make_token(array $payload, string $privatePem, string $kid): string
{
    return JWT::encode($payload, $privatePem, 'RS256', $kid);
}

beforeEach(function (): void {
    config([
        'privy.app_id'                 => 'mobile-app-id',
        'privy.app_secret'             => 'mobile-secret',
        'privy.jwks_url'               => 'https://privy.example/.well-known/jwks.json',
        'privy.issuer'                 => 'privy.io',
        'privy.jwks_cache_ttl_seconds' => 3600,
        'cache.default'                => 'array',
    ]);

    Cache::store('array')->flush();

    $this->keypair = privy_login_test_keypair();

    /** @var ClientInterface&MockInterface $httpClient */
    $httpClient = Mockery::mock(ClientInterface::class);
    $jwksResponse = new PsrResponse(200, [], (string) json_encode(['keys' => [$this->keypair['jwk']]]));

    // Per-test overrides go into this map — keyed by URL prefix. Anything
    // not in the map and not the JWKS URL returns 404. Tests that need a
    // specific Privy users-API response set $this->usersApiResponses[<url>].
    $this->usersApiResponses = [];

    /** @var Mockery\Expectation $expectation */
    $expectation = $httpClient->shouldReceive('request');
    $expectation->andReturnUsing(function (string $method, string $url) use ($jwksResponse) {
        if ($url === 'https://privy.example/.well-known/jwks.json') {
            return $jwksResponse;
        }
        foreach ($this->usersApiResponses as $prefix => $response) {
            if (str_starts_with($url, (string) $prefix)) {
                return $response;
            }
        }

        return new PsrResponse(404, [], '{}');
    });

    $this->httpClient = $httpClient;

    // Bind a verifier that uses the mocked HTTP client into the container so
    // controller-resolved instances pick it up.
    $this->app->bind(PrivyJwtVerifier::class, fn () => new PrivyJwtVerifier($httpClient));
});

/**
 * @param array<string, mixed> $extraClaims
 */
function privy_login_jwt(array $extraClaims, object $context): string
{
    return privy_login_make_token([
        'sub' => 'did:privy:default0123456789',
        'iss' => 'privy.io',
        'aud' => 'mobile-app-id',
        'iat' => time() - 60,
        'exp' => time() + 3600,
        ...$extraClaims,
    ], $context->keypair['privatePem'], $context->keypair['kid']);
}

it('creates a new user with the linked email from the JWT', function (): void {
    $token = privy_login_jwt([
        'sub'             => 'did:privy:newuser0123456789',
        'linked_accounts' => [
            ['type' => 'email', 'address' => 'Jane@Example.com', 'verified_at' => time() - 100],
        ],
    ], $this);

    $response = $this->postJson('/api/v1/auth/privy-login', [
        'privy_token' => $token,
        'name'        => 'Jane Doe',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.is_new_user', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'jane@example.com');

    $user = User::where('privy_user_id', 'did:privy:newuser0123456789')->firstOrFail();
    expect($user->email)->toBe('jane@example.com')
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->privy_linked_at)->not->toBeNull()
        ->and($user->tokens()->count())->toBe(1);
});

it('falls back to the Privy users API when the JWT has no linked email', function (): void {
    $this->usersApiResponses['https://auth.privy.io/api/v1/users/did%3Aprivy%3Anolinkedclaim'] =
        new PsrResponse(200, [], (string) json_encode([
            'id'              => 'did:privy:nolinkedclaim',
            'linked_accounts' => [
                ['type' => 'wallet', 'address' => '0xabc'],
                ['type' => 'email', 'address' => 'fallback@example.com'],
            ],
        ]));

    $token = privy_login_jwt([
        'sub' => 'did:privy:nolinkedclaim',
    ], $this);

    $response = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => $token]);

    $response->assertOk()
        ->assertJsonPath('data.user.email', 'fallback@example.com')
        ->assertJsonPath('data.is_new_user', true);
});

it('returns 422 NO_EMAIL_LINKED when neither the JWT nor Privy API yield an email', function (): void {
    $this->usersApiResponses['https://auth.privy.io/api/v1/users/'] =
        new PsrResponse(200, [], (string) json_encode([
            'id'              => 'did:privy:noemail',
            'linked_accounts' => [['type' => 'wallet', 'address' => '0xdeadbeef']],
        ]));

    $token = privy_login_jwt(['sub' => 'did:privy:noemail'], $this);

    $response = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => $token]);

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'NO_EMAIL_LINKED');

    expect(User::where('privy_user_id', 'did:privy:noemail')->count())->toBe(0);
});

it('returns 409 EMAIL_ALREADY_EXISTS when a non-Privy user already owns the email', function (): void {
    User::factory()->create([
        'email'         => 'taken@example.com',
        'privy_user_id' => null,
    ]);

    $token = privy_login_jwt([
        'sub'             => 'did:privy:tryingtotakeover',
        'linked_accounts' => [['type' => 'email', 'address' => 'taken@example.com']],
    ], $this);

    $response = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => $token]);

    $response->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'EMAIL_ALREADY_EXISTS');

    expect(User::where('privy_user_id', 'did:privy:tryingtotakeover')->count())->toBe(0);
});

it('returns is_new_user=false and the existing user on a returning login', function (): void {
    $existing = User::factory()->create([
        'email'           => 'returning@example.com',
        'privy_user_id'   => 'did:privy:returning',
        'privy_linked_at' => now(),
    ]);

    $token = privy_login_jwt([
        'sub'             => 'did:privy:returning',
        'linked_accounts' => [['type' => 'email', 'address' => 'returning@example.com']],
    ], $this);

    $response = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => $token]);

    $response->assertOk()
        ->assertJsonPath('data.user.id', $existing->id)
        ->assertJsonPath('data.is_new_user', false);

    expect(User::where('privy_user_id', 'did:privy:returning')->count())->toBe(1);
});

it('uses "New User" when no name is supplied on signup', function (): void {
    $token = privy_login_jwt([
        'sub'             => 'did:privy:noname0123456789',
        'linked_accounts' => [['type' => 'email', 'address' => 'noname@example.com']],
    ], $this);

    $response = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => $token]);

    $response->assertOk();
    expect(User::where('privy_user_id', 'did:privy:noname0123456789')->value('name'))->toBe('New User');
});

it('returns 401 INVALID_PRIVY_TOKEN for an invalid Privy token', function (): void {
    $response = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => 'not-a-real-jwt']);

    $response->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_PRIVY_TOKEN');
});

it('returns 422 when privy_token field is missing', function (): void {
    $response = $this->postJson('/api/v1/auth/privy-login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['privy_token']);
});
