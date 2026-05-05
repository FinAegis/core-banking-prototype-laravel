<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\DataObjects\PrivyClaims;
use App\Domain\Auth\Exceptions\PrivyJwtException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use stdClass;
use Throwable;
use UnexpectedValueException;

/**
 * Verifies a Privy-issued session JWT and returns the trusted claims.
 *
 * The Guzzle client is injected (rather than newed up) so tests can mock the
 * JWKS HTTP fetch. The parsed JWKS key set is cached under
 * `privy:jwks` for `config('privy.jwks_cache_ttl_seconds')` so we don't hit
 * Privy on every request.
 */
final class PrivyJwtVerifier
{
    private const JWKS_CACHE_KEY = 'privy:jwks';

    public function __construct(
        private readonly ClientInterface $httpClient,
    ) {
    }

    /**
     * @throws PrivyJwtException
     */
    public function verify(string $token): PrivyClaims
    {
        $keys = $this->loadKeys();

        try {
            /** @var stdClass $payloadObject */
            $payloadObject = JWT::decode($token, $keys);
        } catch (ExpiredException) {
            throw PrivyJwtException::expired();
        } catch (SignatureInvalidException) {
            throw PrivyJwtException::signatureInvalid();
        } catch (UnexpectedValueException $e) {
            throw PrivyJwtException::malformed($e->getMessage());
        } catch (Throwable $e) {
            throw PrivyJwtException::malformed($e->getMessage());
        }

        $payload = (array) $payloadObject;

        $expectedIssuer = (string) config('privy.issuer');
        $expectedAudience = (string) config('privy.app_id');

        $iss = isset($payload['iss']) && is_string($payload['iss']) ? $payload['iss'] : '';
        if ($iss !== $expectedIssuer) {
            throw PrivyJwtException::wrongIssuer($iss);
        }

        $aud = isset($payload['aud']) && is_string($payload['aud']) ? $payload['aud'] : '';
        if ($aud !== $expectedAudience) {
            throw PrivyJwtException::wrongAudience($aud);
        }

        $exp = $payload['exp'] ?? null;
        if (! is_numeric($exp) || (int) $exp <= time()) {
            // JWT::decode normally rejects expired tokens, but enforce again
            // here so a verifier instance with leeway misconfigured upstream
            // still rejects the token.
            throw PrivyJwtException::expired();
        }

        return PrivyClaims::fromPayload($payload);
    }

    /**
     * @return array<string, Key>
     *
     * @throws PrivyJwtException
     */
    private function loadKeys(): array
    {
        $ttl = (int) config('privy.jwks_cache_ttl_seconds', 3600);
        $url = (string) config('privy.jwks_url');

        if ($url === '') {
            throw PrivyJwtException::jwksUnreachable();
        }

        /** @var array<string, mixed> $jwks */
        $jwks = Cache::remember(self::JWKS_CACHE_KEY, $ttl, function () use ($url): array {
            try {
                $response = $this->httpClient->request('GET', $url, ['timeout' => 5]);
            } catch (GuzzleException) {
                throw PrivyJwtException::jwksUnreachable();
            }

            if ($response->getStatusCode() !== 200) {
                throw PrivyJwtException::jwksUnreachable();
            }

            $body = (string) $response->getBody();
            $decoded = json_decode($body, true);
            if (! is_array($decoded) || ! isset($decoded['keys']) || ! is_array($decoded['keys'])) {
                throw PrivyJwtException::jwksUnreachable();
            }

            return $decoded;
        });

        try {
            return JWK::parseKeySet($jwks);
        } catch (Throwable $e) {
            throw PrivyJwtException::malformed('JWKS could not be parsed: ' . $e->getMessage());
        }
    }
}
