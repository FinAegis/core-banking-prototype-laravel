<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Exceptions\PrivyEmailOtpException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use JsonException;

/**
 * Server-side wrapper for Privy's REST email-OTP endpoints.
 *
 * Authenticates with Basic Auth using the configured app_id + app_secret —
 * we make these calls server-to-server so the response is authoritative
 * without an additional JWT verification step.
 *
 * The mobile flow (/api/v1/auth/privy-login) takes a JWT minted by the
 * Privy SDK on-device; this service is the parallel for the web /login
 * surface where the OTP form is rendered by us, not by a Privy SDK.
 */
class PrivyEmailOtpClient
{
    private const SEND_CODE_PATH = '/api/v1/passwordless/init';

    private const LOGIN_PATH = '/api/v1/passwordless/authenticate';

    public function __construct(
        private readonly ClientInterface $http,
    ) {
    }

    /**
     * Ask Privy to email an OTP to the given address.
     *
     * @throws PrivyEmailOtpException when Privy refuses the request (rate-limit, bad email, etc.)
     */
    public function sendCode(string $email): void
    {
        $email = strtolower(trim($email));
        $this->call(self::SEND_CODE_PATH, [
            'email'  => $email,
            'method' => 'email',
        ]);
    }

    /**
     * Verify the OTP and return the resolved Privy user.
     *
     * @return array{id: string, email: string} Privy DID + linked email lower-cased
     *
     * @throws PrivyEmailOtpException when the code is wrong, expired, or Privy returns malformed data
     */
    public function loginWithCode(string $email, string $code): array
    {
        $email = strtolower(trim($email));
        $code = trim($code);

        $payload = $this->call(self::LOGIN_PATH, [
            'email' => $email,
            'code'  => $code,
        ]);

        $userId = $payload['user']['id'] ?? null;
        if (! is_string($userId) || $userId === '') {
            throw PrivyEmailOtpException::malformedResponse('login_with_code', 'missing user.id');
        }

        $resolvedEmail = $this->resolveEmail($payload, $email);

        return ['id' => $userId, 'email' => $resolvedEmail];
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     *
     * @throws PrivyEmailOtpException
     */
    private function call(string $path, array $body): array
    {
        $base = rtrim((string) config('privy.api_base_url', 'https://auth.privy.io'), '/');
        $appId = (string) config('privy.app_id');
        $appSecret = (string) config('privy.app_secret');

        if ($appId === '' || $appSecret === '') {
            throw PrivyEmailOtpException::misconfigured();
        }

        $request = new GuzzleRequest(
            'POST',
            $base . $path,
            [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'privy-app-id'  => $appId,
                'Authorization' => 'Basic ' . base64_encode($appId . ':' . $appSecret),
                // Privy's REST API rejects requests without an Origin matching
                // the dashboard allowlist (403 "Must specify origin"). We send
                // the canonical app origin server-side rather than echoing the
                // browser's Origin header — the request is server-to-server,
                // and trusting a client-supplied Origin would defeat the
                // allowlist's purpose.
                'Origin' => $this->resolveOrigin(),
            ],
            (string) json_encode($body, JSON_THROW_ON_ERROR),
        );

        try {
            $response = $this->http->send($request, ['http_errors' => false]);
        } catch (GuzzleException $e) {
            throw PrivyEmailOtpException::transport($path, $e);
        }

        $status = $response->getStatusCode();
        $rawBody = (string) $response->getBody();

        if ($status >= 400) {
            $message = $this->extractErrorMessage($rawBody) ?? "Privy returned HTTP {$status}";
            throw PrivyEmailOtpException::apiError($path, $status, $message);
        }

        if ($rawBody === '') {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw PrivyEmailOtpException::malformedResponse($path, 'response is not JSON');
        }

        if (! is_array($decoded)) {
            throw PrivyEmailOtpException::malformedResponse($path, 'response is not a JSON object');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveEmail(array $payload, string $fallback): string
    {
        $linked = $payload['user']['linked_accounts'] ?? null;
        if (is_array($linked)) {
            foreach ($linked as $account) {
                if (! is_array($account)) {
                    continue;
                }
                $type = $account['type'] ?? null;
                $address = $account['address'] ?? null;
                if ($type === 'email' && is_string($address) && $address !== '') {
                    return strtolower($address);
                }
            }
        }

        return $fallback;
    }

    /**
     * Privy validates the Origin header against the dashboard allowlist.
     * Prefer an explicit privy.web_origin (so operators can decouple it from
     * the API host), fall back to app.url. We strip path/query so we send
     * only scheme://host[:port], which is what Privy compares against.
     */
    private function resolveOrigin(): string
    {
        $raw = (string) (config('privy.web_origin') ?: config('app.url', ''));
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $parts = parse_url($raw);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return rtrim($raw, '/');
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    private function extractErrorMessage(string $body): ?string
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (! is_array($decoded)) {
            return null;
        }
        $message = $decoded['error'] ?? $decoded['message'] ?? null;
        if (is_string($message) && $message !== '') {
            return $message;
        }
        if (is_array($message) && isset($message['message']) && is_string($message['message'])) {
            return $message['message'];
        }

        return null;
    }
}
