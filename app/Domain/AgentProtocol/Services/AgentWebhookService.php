<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentWebhookService
{
    private const WEBHOOK_TIMEOUT = 10; // seconds

    private const RETRY_ATTEMPTS = 3;

    private const RETRY_DELAY = 1; // seconds

    /**
     * Send webhook to agent endpoint.
     */
    public function sendWebhook(
        string $endpoint,
        array $payload,
        array $headers = [],
        int $timeout = self::WEBHOOK_TIMEOUT
    ): array {
        $attempt = 0;
        $lastError = null;

        while ($attempt < self::RETRY_ATTEMPTS) {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders($headers)
                    ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY * 1000)
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'status'  => $response->status(),
                        'body'    => $response->json() ?? $response->body(),
                        'headers' => $response->headers(),
                    ];
                }

                $lastError = "HTTP {$response->status()}: {$response->body()}";
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                Log::warning('Webhook delivery failed', [
                    'endpoint' => $endpoint,
                    'attempt'  => $attempt + 1,
                    'error'    => $lastError,
                ]);
            }

            $attempt++;
            if ($attempt < self::RETRY_ATTEMPTS) {
                sleep(self::RETRY_DELAY * $attempt); // Exponential backoff
            }
        }

        return [
            'success'  => false,
            'error'    => $lastError ?: 'Unknown error',
            'attempts' => $attempt,
        ];
    }

    /**
     * Verify webhook signature.
     */
    public function verifySignature(
        string $payload,
        string $signature,
        string $secret
    ): bool {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate webhook signature.
     */
    public function generateSignature(
        string $payload,
        string $secret
    ): string {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Register webhook endpoint for agent.
     */
    public function registerEndpoint(
        string $agentId,
        string $endpoint,
        array $events = [],
        ?string $secret = null
    ): bool {
        $cacheKey = "webhook:endpoints:{$agentId}";
        $endpoints = Cache::get($cacheKey, []);

        $endpoints[$endpoint] = [
            'url'           => $endpoint,
            'events'        => $events,
            'secret'        => $secret,
            'registered_at' => now()->toDateTimeString(),
        ];

        Cache::put($cacheKey, $endpoints, 86400); // 24 hours

        return true;
    }

    /**
     * Get registered endpoints for agent.
     */
    public function getEndpoints(string $agentId): array
    {
        $cacheKey = "webhook:endpoints:{$agentId}";

        return Cache::get($cacheKey, []);
    }

    /**
     * Unregister webhook endpoint.
     */
    public function unregisterEndpoint(
        string $agentId,
        string $endpoint
    ): bool {
        $cacheKey = "webhook:endpoints:{$agentId}";
        $endpoints = Cache::get($cacheKey, []);

        unset($endpoints[$endpoint]);

        if (empty($endpoints)) {
            Cache::forget($cacheKey);
        } else {
            Cache::put($cacheKey, $endpoints, 86400);
        }

        return true;
    }
}
