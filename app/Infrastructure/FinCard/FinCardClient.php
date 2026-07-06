<?php

declare(strict_types=1);

namespace App\Infrastructure\FinCard;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin HTTP wrapper around the FinCard Virtual Card BFF API (finhub.cloud).
 *
 * Lives in app/Infrastructure/* (like BridgeClient) because both the
 * CardIssuance adapter and the webhook controller consume it.
 *
 * Transport model (from docs.finhub.cloud/paas/fincard-virtual):
 *  - RPC-over-POST: every call is a POST with a JSON body, including reads.
 *    An empty body is sent as `{}` (not `[]`).
 *  - Response envelope: `{ success: bool, code: int, msg: string, data: ... }`.
 *    A business failure is HTTP 200 with `success=false`; both that and a
 *    non-2xx transport failure raise {@see FinCardApiException}.
 *  - Auth: a JWT bearer obtained from a session login (1h expiry, NO refresh
 *    token) — cached until near-expiry and re-minted on a 401, mirroring the
 *    OndatoService pattern. We do NOT sign outbound requests: the mock's
 *    `X-WSB-SIGNATURE` is a Playground placeholder; FinHub's BFF performs the
 *    RSA signing to its upstream. Inbound webhooks ARE signed — see
 *    {@see FinCardWebhookVerifier}.
 *  - Context headers required on every call: X-Tenant-ID, X-Forwarded-For
 *    (end-user IP), X-Forwarded-From (our service name), platform, deviceId.
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md
 */
final class FinCardClient
{
    private const CACHE_TOKEN_KEY = 'fincard:jwt';

    /** Session JWT lifetime is 3600s; cache with a 5-minute safety buffer. */
    private const TOKEN_TTL_BUFFER_SECONDS = 300;

    private const REQUEST_TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $tenantId,
        private readonly string $orgId,
        private readonly string $userId,
        private readonly string $username,
        private readonly string $password,
        private readonly string $forwardedFrom = 'zelta',
    ) {
    }

    public static function fromConfig(): self
    {
        /** @var array<string, mixed> $cfg */
        $cfg = (array) config('cardissuance.issuers.fincard', []);

        return new self(
            baseUrl: rtrim((string) ($cfg['base_url'] ?? 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual'), '/'),
            tenantId: (string) ($cfg['tenant_id'] ?? ''),
            orgId: (string) ($cfg['org_id'] ?? ''),
            userId: (string) ($cfg['user_id'] ?? ''),
            username: (string) ($cfg['username'] ?? ''),
            password: (string) ($cfg['password'] ?? ''),
            forwardedFrom: (string) ($cfg['forwarded_from'] ?? 'zelta'),
        );
    }

    // ── Auth ─────────────────────────────────────────────────────────────

    /**
     * Return a cached session JWT, minting a fresh one on a cache miss.
     */
    public function getAccessToken(): string
    {
        $cached = Cache::get(self::CACHE_TOKEN_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->login();
    }

    /**
     * Perform the session login and cache the JWT until near-expiry.
     */
    public function login(): string
    {
        $url = $this->host() . "/api/v2.1/admin/organization/{$this->orgId}/users/{$this->userId}/sessions";

        $response = Http::asJson()
            ->timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->withHeaders(['X-Tenant-ID' => $this->tenantId])
            ->post($url, [
                'username' => $this->username,
                'password' => $this->password,
            ]);

        if ($response->failed()) {
            Log::error('FinCard login failed', ['status' => $response->status()]);

            throw FinCardApiException::transport('/sessions', $response->status());
        }

        $body = $response->json();
        // The token may sit at the top level or inside `data` depending on the
        // tenant/gateway version, so accept both (mirrors the mock's test script).
        $token = '';
        $expiresIn = 3600;
        if (is_array($body)) {
            $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
            $token = (string) ($body['accessToken'] ?? $data['accessToken'] ?? '');
            $expiresIn = (int) ($body['expiresIn'] ?? $data['expiresIn'] ?? 3600);
        }

        if ($token === '') {
            throw FinCardApiException::business('/sessions', null, 'login response contained no accessToken');
        }

        $ttl = max($expiresIn - self::TOKEN_TTL_BUFFER_SECONDS, 60);
        Cache::put(self::CACHE_TOKEN_KEY, $token, $ttl);

        return $token;
    }

    // ── Common / reference data (Phase 1) ────────────────────────────────

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function getRegions(array $context = []): array
    {
        return $this->rpc('/common/region', [], $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function getCities(string $regionCode, array $context = []): array
    {
        return $this->rpc('/common/v2/city', ['regionCode' => $regionCode], $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function getMobileAreaCodes(array $context = []): array
    {
        return $this->rpc('/common/mobileAreaCode', [], $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function getOccupations(array $context = []): array
    {
        return $this->rpc('/card/holder/occupations', [], $context);
    }

    /**
     * Supported card types / BINs for the tenant.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function getCardTypes(array $context = []): array
    {
        return $this->rpc('/card/v2/cardTypes', [], $context);
    }

    /**
     * Supported crypto deposit coins — the runtime source of truth for the
     * dynamic coin set (do not hard-code USDC support).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function getCoins(array $context = []): array
    {
        return $this->rpc('/wallet/v2/coins', [], $context);
    }

    // ── Cardholder / KYC (Phase 2) ───────────────────────────────────────

    /**
     * Upload a KYC document (multipart). Returns the envelope whose `data`
     * carries the `fileId` referenced by createCardholder.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function uploadKycDocument(string $contents, string $filename, string $mimeType, array $context = []): array
    {
        return $this->dispatch(
            '/common/file/upload',
            fn (PendingRequest $request): PendingRequest => $request->attach('file', $contents, $filename, ['Content-Type' => $mimeType]),
            $context,
        );
    }

    /**
     * Create a FinCard cardholder (Cardholder-V2). `$payload` must already be in
     * FinCard's field shape (see FinCardCardholderPayload). Returns the envelope
     * whose `data` carries the `holderId`.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function createCardholder(array $payload, array $context = []): array
    {
        return $this->rpc('/card/holder/v2/create', $payload, $context);
    }

    /**
     * List cardholders (used to reconcile KYC state for a known holder).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function listCardholders(int $pageNum = 1, int $pageSize = 20, array $context = []): array
    {
        return $this->rpc('/card/holder/list', ['pageNum' => $pageNum, 'pageSize' => $pageSize], $context);
    }

    // ── Core RPC ─────────────────────────────────────────────────────────

    /**
     * POST a JSON-RPC call and return the decoded envelope.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context  platform / device_id / forwarded_for overrides
     * @return array<string, mixed>
     */
    public function rpc(string $path, array $params = [], array $context = []): array
    {
        return $this->dispatch(
            $path,
            fn (PendingRequest $request): PendingRequest => $request->asJson()->withBody($this->encodeBody($params), 'application/json'),
            $context,
        );
    }

    // ── Internals ────────────────────────────────────────────────────────

    /**
     * Send an authenticated POST and return the decoded envelope. `$applyBody`
     * shapes the request (JSON body vs multipart). Re-authenticates once on a
     * 401 (expired/invalidated token) so a stale cached JWT self-heals.
     *
     * @param  Closure(PendingRequest): PendingRequest  $applyBody
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function dispatch(string $path, Closure $applyBody, array $context, bool $isRetry = false): array
    {
        $request = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->retry(2, 500, $this->shouldRetry(...), throw: false)
            ->withToken($this->getAccessToken())
            ->withHeaders($this->contextHeaders($context));

        $response = $applyBody($request)->post($this->baseUrl . $path);

        if ($response->status() === 401 && ! $isRetry) {
            Cache::forget(self::CACHE_TOKEN_KEY);

            return $this->dispatch($path, $applyBody, $context, isRetry: true);
        }

        return $this->decode($response, $path);
    }

    /**
     * Retry transient failures only — connection errors and 5xx. A 4xx (incl.
     * 401) is returned immediately so rpc() can re-authenticate rather than
     * replay a doomed request with the same stale token. Retrying is safe for
     * mutating calls because they carry a client `merchantOrderNo` idempotency
     * key.
     */
    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response->serverError());
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response, string $path): array
    {
        if ($response->failed()) {
            // Never log the body — it may carry PAN / PII.
            Log::warning('FinCard API transport failure', [
                'path'   => $path,
                'status' => $response->status(),
            ]);

            throw FinCardApiException::transport($path, $response->status());
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw FinCardApiException::malformed($path);
        }

        if (($decoded['success'] ?? null) !== true) {
            $code = isset($decoded['code']) ? (int) $decoded['code'] : null;
            $msg = (string) ($decoded['msg'] ?? '');
            Log::warning('FinCard API business failure', [
                'path' => $path,
                'code' => $code,
                'msg'  => $msg,
            ]);

            throw FinCardApiException::business($path, $code, $msg);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function contextHeaders(array $context): array
    {
        return [
            'X-Tenant-ID'      => $this->tenantId,
            'X-Forwarded-For'  => (string) ($context['forwarded_for'] ?? '127.0.0.1'),
            'X-Forwarded-From' => $this->forwardedFrom,
            'platform'         => (string) ($context['platform'] ?? 'web'),
            'deviceId'         => (string) ($context['device_id'] ?? 'zelta-server'),
        ];
    }

    /**
     * Serialize the RPC body, sending an empty payload as `{}` (FinCard rejects
     * a bare `[]`).
     *
     * @param  array<string, mixed>  $params
     */
    private function encodeBody(array $params): string
    {
        return $params === [] ? '{}' : (string) json_encode($params);
    }

    /**
     * Scheme + host of the API (the admin/session login lives outside the
     * /fincard/virtual base path).
     */
    private function host(): string
    {
        $parts = parse_url($this->baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'sandbox.finhub.cloud';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }
}
