<?php

declare(strict_types=1);

use App\Domain\Auth\DataObjects\PrivyClaims;
use App\Domain\Auth\Exceptions\PrivyJwtException;
use App\Domain\Auth\Services\PrivyJwtVerifier;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

uses(Tests\TestCase::class);

/**
 * Generate an RSA keypair and the matching JWK (public-key) representation.
 *
 * @return array{privatePem: string, jwk: array<string, string>, kid: string}
 */
function privy_test_keypair(): array
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

    $kid = 'test-key-1';

    $base64Url = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

    $jwk = [
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => $kid,
        'n'   => $base64Url($details['rsa']['n']),
        'e'   => $base64Url($details['rsa']['e']),
    ];

    return [
        'privatePem' => $privatePem,
        'jwk'        => $jwk,
        'kid'        => $kid,
    ];
}

/**
 * @param array<string, mixed> $payload
 */
function privy_make_jwt(array $payload, string $privatePem, string $kid, string $alg = 'RS256'): string
{
    return JWT::encode($payload, $privatePem, $alg, $kid);
}

/**
 * @param array<int, array<string, string>> $jwks
 */
function privy_make_verifier(array $jwks, ?ClientInterface $clientOverride = null): PrivyJwtVerifier
{
    if ($clientOverride === null) {
        /** @var ClientInterface&MockInterface $client */
        $client = Mockery::mock(ClientInterface::class);
        $client->shouldReceive('request')
            ->with('GET', 'https://privy.example/.well-known/jwks.json', Mockery::any())
            ->andReturn(new PsrResponse(200, ['Content-Type' => 'application/json'], (string) json_encode(['keys' => $jwks])));
    } else {
        $client = $clientOverride;
    }

    return new PrivyJwtVerifier($client);
}

beforeEach(function (): void {
    config([
        'privy.app_id'                 => 'test-app',
        'privy.app_secret'             => 'test-secret',
        'privy.jwks_url'               => 'https://privy.example/.well-known/jwks.json',
        'privy.issuer'                 => 'privy.io',
        'privy.jwks_cache_ttl_seconds' => 3600,
        'cache.default'                => 'array',
    ]);
    Cache::store('array')->flush();
});

it('verifies a valid JWT and returns PrivyClaims', function (): void {
    $kp = privy_test_keypair();

    $now = time();
    $token = privy_make_jwt([
        'sub'             => 'did:privy:cl9abc123',
        'iss'             => 'privy.io',
        'aud'             => 'test-app',
        'iat'             => $now - 60,
        'exp'             => $now + 3600,
        'linked_accounts' => [['type' => 'wallet', 'address' => '0xdeadbeef']],
    ], $kp['privatePem'], $kp['kid']);

    $verifier = privy_make_verifier([$kp['jwk']]);

    $claims = $verifier->verify($token);

    expect($claims)->toBeInstanceOf(PrivyClaims::class)
        ->and($claims->privyUserId)->toBe('did:privy:cl9abc123')
        ->and($claims->issuer)->toBe('privy.io')
        ->and($claims->audience)->toBe('test-app')
        ->and($claims->linkedAccounts)->toHaveCount(1);
});

it('throws expired() when JWT exp is in the past', function (): void {
    $kp = privy_test_keypair();

    $token = privy_make_jwt([
        'sub' => 'did:privy:expired',
        'iss' => 'privy.io',
        'aud' => 'test-app',
        'iat' => time() - 7200,
        'exp' => time() - 3600,
    ], $kp['privatePem'], $kp['kid']);

    $verifier = privy_make_verifier([$kp['jwk']]);

    expect(fn () => $verifier->verify($token))
        ->toThrow(PrivyJwtException::class, 'expired');
});

it('throws wrongAudience() when aud does not match config', function (): void {
    $kp = privy_test_keypair();

    $token = privy_make_jwt([
        'sub' => 'did:privy:abc',
        'iss' => 'privy.io',
        'aud' => 'some-other-app',
        'iat' => time() - 60,
        'exp' => time() + 3600,
    ], $kp['privatePem'], $kp['kid']);

    $verifier = privy_make_verifier([$kp['jwk']]);

    expect(fn () => $verifier->verify($token))
        ->toThrow(PrivyJwtException::class, 'unexpected audience');
});

it('throws wrongIssuer() when iss does not match privy.io', function (): void {
    $kp = privy_test_keypair();

    $token = privy_make_jwt([
        'sub' => 'did:privy:abc',
        'iss' => 'evil.example.com',
        'aud' => 'test-app',
        'iat' => time() - 60,
        'exp' => time() + 3600,
    ], $kp['privatePem'], $kp['kid']);

    $verifier = privy_make_verifier([$kp['jwk']]);

    expect(fn () => $verifier->verify($token))
        ->toThrow(PrivyJwtException::class, 'unexpected issuer');
});

it('throws signatureInvalid() when token is signed with the wrong key', function (): void {
    $kpServing = privy_test_keypair();
    $kpAttacker = privy_test_keypair();

    // Sign with attacker's key but advertise the legitimate kid so the
    // verifier looks up the (mismatching) public key from the JWKS.
    $token = privy_make_jwt([
        'sub' => 'did:privy:abc',
        'iss' => 'privy.io',
        'aud' => 'test-app',
        'iat' => time() - 60,
        'exp' => time() + 3600,
    ], $kpAttacker['privatePem'], $kpServing['kid']);

    $verifier = privy_make_verifier([$kpServing['jwk']]);

    expect(fn () => $verifier->verify($token))
        ->toThrow(PrivyJwtException::class, 'signature is invalid');
});

it('throws malformed() when JWT has wrong number of segments', function (): void {
    $kp = privy_test_keypair();
    $verifier = privy_make_verifier([$kp['jwk']]);

    expect(fn () => $verifier->verify('not.a-valid-jwt'))
        ->toThrow(PrivyJwtException::class, 'malformed');
});

it('throws jwksUnreachable() when the JWKS HTTP fetch fails', function (): void {
    /** @var ClientInterface&MockInterface $client */
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('request')
        ->andThrow(new ConnectException('Connection refused', new PsrRequest('GET', 'https://privy.example/.well-known/jwks.json')));

    $verifier = new PrivyJwtVerifier($client);

    expect(fn () => $verifier->verify('eyJhbGciOiJIUzI1NiJ9.e30.x'))
        ->toThrow(PrivyJwtException::class, 'unreachable');
});
