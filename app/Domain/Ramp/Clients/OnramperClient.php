<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OnramperClient
{
    private readonly string $apiKey;

    private readonly string $secretKey;

    private readonly string $baseUrl;

    public function __construct()
    {
        $config = config('ramp.providers.onramper');

        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->secretKey = (string) ($config['secret_key'] ?? '');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.onramper.com'), '/');

        if ($this->apiKey === '') {
            throw new RuntimeException('Onramper API key is not configured.');
        }
    }

    /**
     * Get quotes for a fiat-crypto conversion from all aggregated providers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQuotes(
        string $sourceCurrency,
        string $destinationCurrency,
        string $amount,
        ?string $paymentMethod = null,
        ?string $country = null,
    ): array {
        $query = ['amount' => $amount];

        if ($paymentMethod !== null) {
            $query['paymentMethod'] = $paymentMethod;
        }
        if ($country !== null) {
            $query['country'] = $country;
        }

        $response = $this->request()
            ->get("{$this->baseUrl}/quotes/{$sourceCurrency}/{$destinationCurrency}", $query);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Onramper quote request failed: ' . ($response->json('message') ?? $response->body())
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Create a checkout intent via API (returns provider checkout URL).
     *
     * @param  array<string, mixed>  $params  Must include quoteId, walletAddress, redirectURL
     * @return array<string, mixed>  Contains transactionId and checkoutUrl
     */
    public function createCheckoutIntent(array $params): array
    {
        $response = $this->request()
            ->post("{$this->baseUrl}/checkout/intent", $params);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Onramper checkout failed: ' . ($response->json('message') ?? $response->body())
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Get transaction status by ID.
     *
     * @return array<string, mixed>
     */
    public function getTransaction(string $transactionId): array
    {
        $response = $this->request()
            ->get("{$this->baseUrl}/transaction/{$transactionId}");

        if (! $response->successful()) {
            throw new RuntimeException(
                'Onramper transaction lookup failed: ' . ($response->json('message') ?? $response->body())
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Get supported assets/currencies.
     *
     * @return array<string, mixed>
     */
    public function getSupportedAssets(?string $source = null, ?string $destination = null): array
    {
        $query = array_filter([
            'source'      => $source,
            'destination' => $destination,
        ]);

        $response = $this->request()
            ->get("{$this->baseUrl}/supported", $query);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Onramper supported assets request failed: ' . ($response->json('message') ?? $response->body())
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Generate HMAC-SHA256 signature for request signing.
     */
    public function signPayload(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secretKey);
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if ($this->secretKey === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $payload, $this->secretKey);

        return hash_equals($computed, $signature);
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->timeout(30);
    }
}
