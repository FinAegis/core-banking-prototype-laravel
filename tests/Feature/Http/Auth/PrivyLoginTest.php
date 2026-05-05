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
    $httpClient->shouldReceive('request')
        ->byDefault()
        ->andReturn(new PsrResponse(200, [], (string) json_encode(['keys' => [$this->keypair['jwk']]])));

    // Bind a verifier that uses the mocked HTTP client into the container so
    // controller-resolved instances pick it up.
    $this->app->bind(PrivyJwtVerifier::class, fn () => new PrivyJwtVerifier($httpClient));
});

it('creates a new user and returns a Sanctum token for a previously-unseen Privy user', function (): void {
    $token = privy_login_make_token([
        'sub' => 'did:privy:newuser0123456789',
        'iss' => 'privy.io',
        'aud' => 'mobile-app-id',
        'iat' => time() - 60,
        'exp' => time() + 3600,
    ], $this->keypair['privatePem'], $this->keypair['kid']);

    $response = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => $token]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['user' => ['id', 'email'], 'token', 'token_type']])
        ->assertJsonPath('data.token_type', 'Bearer');

    $this->assertDatabaseHas('users', [
        'privy_user_id' => 'did:privy:newuser0123456789',
        'email'         => 'privy_ewuser0123456789@placeholder.zelta.app',
    ]);

    $user = User::where('privy_user_id', 'did:privy:newuser0123456789')->firstOrFail();
    expect($user->tokens()->count())->toBe(1)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->privy_linked_at)->not->toBeNull();
});

it('returns a Sanctum token for an existing Privy user without creating a duplicate', function (): void {
    $existing = User::factory()->create([
        'email'           => 'existing@example.com',
        'privy_user_id'   => 'did:privy:existing12345',
        'privy_linked_at' => now(),
    ]);

    $token = privy_login_make_token([
        'sub' => 'did:privy:existing12345',
        'iss' => 'privy.io',
        'aud' => 'mobile-app-id',
        'iat' => time() - 60,
        'exp' => time() + 3600,
    ], $this->keypair['privatePem'], $this->keypair['kid']);

    $response = $this->postJson('/api/v1/auth/privy-login', ['privy_token' => $token]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.id', $existing->id);

    expect(User::where('privy_user_id', 'did:privy:existing12345')->count())->toBe(1);
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
