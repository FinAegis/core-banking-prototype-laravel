<?php

declare(strict_types=1);

namespace App\Infrastructure\Bridge;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin HTTP wrapper around the Bridge.xyz REST API.
 *
 * Lives in app/Infrastructure/* rather than under either Ramp or
 * Compliance because both BridgeProvider (Ramp) and BridgeKycProvider
 * (Compliance/Kyc) consume it. Putting it in either domain would create
 * a cross-domain dependency the other side doesn't need.
 *
 * Auth: Bridge uses an API key sent as the `Api-Key` header. The exact
 * header name should be verified against the deployment's Bridge dashboard
 * before going live — see TODO note on apiHeaders().
 *
 * Errors: any non-2xx response throws RuntimeException with the response
 * body interpolated, so the call site can log + surface a meaningful error
 * to the user. Callers should distinguish "Bridge said no" from "we
 * couldn't reach Bridge" via the exception message; transport-level errors
 * surface as the underlying Guzzle ConnectionException.
 *
 * Idempotency: `Idempotency-Key` header is set when supplied, so retries
 * of POST customers / kyc_links are safe under network blips.
 *
 * @see docs/BACKEND_HANDOVER_BRIDGE_RAMP.md §3.1
 * @see docs/adr/0005-bridge-xyz-over-stripe-crypto-onramp.md
 */
final class BridgeClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {
    }

    public static function fromConfig(): self
    {
        $apiKey = (string) config('kyc.providers.bridge.api_key', '');
        $baseUrl = rtrim((string) config('kyc.providers.bridge.base_url', 'https://api.bridge.xyz'), '/');

        return new self($apiKey, $baseUrl);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCustomer(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->post('/v0/customers', $payload, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function patchCustomer(string $customerId, array $payload): array
    {
        return $this->patch("/v0/customers/{$customerId}", $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createKycLink(string $customerId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->post("/v0/customers/{$customerId}/kyc_links", $payload, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createVirtualAccount(string $customerId, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->post("/v0/customers/{$customerId}/virtual_accounts", $payload, $idempotencyKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function listVirtualAccounts(string $customerId): array
    {
        return $this->get("/v0/customers/{$customerId}/virtual_accounts");
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createTransfer(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->post('/v0/transfers', $payload, $idempotencyKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransfer(string $transferId): array
    {
        return $this->get("/v0/transfers/{$transferId}");
    }

    /**
     * Delete a Bridge customer record (GDPR Art. 17(2) processor fan-out).
     *
     * A 404 is treated as success — the customer is already gone (Bridge's
     * sandbox purges records periodically), so the call stays idempotent and
     * a local erasure is never blocked by a missing remote record. Any other
     * non-2xx follows the client convention and throws RuntimeException.
     */
    public function deleteCustomer(string $customerId): void
    {
        $path = "/v0/customers/{$customerId}";

        $response = Http::withHeaders($this->apiHeaders())
            ->acceptJson()
            ->delete($this->baseUrl . $path);

        if ($response->status() === 404) {
            Log::info('Bridge customer already absent during GDPR deletion', [
                'path'   => $path,
                'status' => 404,
            ]);

            return;
        }

        if ($response->failed()) {
            Log::warning('Bridge API call failed', [
                'verb'   => 'DELETE',
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new RuntimeException(sprintf(
                'Bridge API DELETE %s returned %d: %s',
                $path,
                $response->status(),
                $response->body(),
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload, ?string $idempotencyKey = null): array
    {
        $headers = $this->apiHeaders();
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $response = Http::withHeaders($headers)
            ->acceptJson()
            ->post($this->baseUrl . $path, $payload);

        return $this->decode($response, 'POST', $path);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function patch(string $path, array $payload): array
    {
        $response = Http::withHeaders($this->apiHeaders())
            ->acceptJson()
            ->patch($this->baseUrl . $path, $payload);

        return $this->decode($response, 'PATCH', $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        $response = Http::withHeaders($this->apiHeaders())
            ->acceptJson()
            ->get($this->baseUrl . $path);

        return $this->decode($response, 'GET', $path);
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        // TODO: confirm the exact header name in the Bridge sandbox before
        // going live — the brief references Bridge's docs but doesn't pin
        // the auth header. `Api-Key` is the documented v0 pattern as of
        // 2026-05; if Bridge moves to `Authorization: Bearer`, swap here.
        return [
            'Api-Key' => $this->apiKey,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response, string $verb, string $path): array
    {
        if ($response->failed()) {
            Log::warning('Bridge API call failed', [
                'verb'   => $verb,
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new RuntimeException(sprintf(
                'Bridge API %s %s returned %d: %s',
                $verb,
                $path,
                $response->status(),
                $response->body(),
            ));
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf(
                'Bridge API %s %s returned non-object JSON',
                $verb,
                $path,
            ));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
