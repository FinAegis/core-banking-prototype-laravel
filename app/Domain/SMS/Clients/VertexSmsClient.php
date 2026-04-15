<?php

declare(strict_types=1);

namespace App\Domain\SMS\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

/**
 * HTTP client wrapper for the VertexSMS REST API (kube-api.vertexsms.com).
 *
 * @see https://vertexsms.com/en/api
 */
class VertexSmsClient
{
    private readonly string $apiToken;

    private readonly string $baseUrl;

    public function __construct()
    {
        /** @var array{api_token?: string, base_url?: string} $config */
        $config = config('sms.providers.vertexsms', []);

        $this->apiToken = (string) ($config['api_token'] ?? '');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://kube-api.vertexsms.com'), '/');
    }

    /**
     * Estimate cost + parts for a message before actually sending.
     *
     * Hits POST /sms/cost. This is authoritative for `parts` and `countryISO`
     * (replaces the brittle X-VertexSMS-Amount-Sent header workaround).
     *
     * @return array{parts: int, price_per_part_eur: float, total_price_eur: float, country_iso: string, mccmnc: ?string}
     */
    public function estimateCost(string $to, string $from, string $message): array
    {
        $this->requireApiToken();

        $response = $this->request()->post("{$this->baseUrl}/sms/cost", [
            'to'      => $to,
            'from'    => $from,
            'message' => $message,
        ]);

        if (! $response->successful()) {
            Log::warning('VertexSMS: /sms/cost estimate failed', [
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
                'to'     => $to,
            ]);

            throw new RuntimeException(
                'VertexSMS /sms/cost failed: HTTP ' . $response->status()
            );
        }

        /** @var mixed $data */
        $data = $response->json();

        if (! is_array($data) || $data === []) {
            throw new RuntimeException('VertexSMS /sms/cost returned empty response');
        }

        // Response can be a single object or an array of one object (per docs).
        /** @var array<string, mixed> $entry */
        $entry = isset($data[0]) && is_array($data[0]) ? $data[0] : $data;

        return [
            'parts'              => $this->extractInt($entry, 'parts', 1, 1),
            'price_per_part_eur' => $this->extractFloat($entry, 'pricePerPart'),
            'total_price_eur'    => $this->extractFloat($entry, 'totalPrice'),
            'country_iso'        => strtoupper($this->extractString($entry, 'countryISO')),
            'mccmnc'             => $this->extractOptionalString($entry, 'mccmnc'),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function extractInt(array $source, string $key, int $default = 0, int $min = 0): int
    {
        $value = $source[$key] ?? $default;

        return is_numeric($value) ? max($min, (int) $value) : $default;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function extractFloat(array $source, string $key, float $default = 0.0): float
    {
        $value = $source[$key] ?? $default;

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function extractString(array $source, string $key, string $default = ''): string
    {
        $value = $source[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function extractOptionalString(array $source, string $key): ?string
    {
        $value = $source[$key] ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Send an SMS message via POST /sms.
     *
     * Includes the configured `dlrUrl` so Vertex can deliver the DLR callback
     * back to us. URL-token auth (`?t=<token>`) is appended when configured.
     *
     * @return array{message_id: string}
     */
    public function sendSms(string $to, string $from, string $message, bool $testMode = false): array
    {
        $this->requireApiToken();

        $payload = [
            'to'      => $to,
            'from'    => $from,
            'message' => $message,
        ];

        $dlrUrl = $this->buildDlrUrl();
        if ($dlrUrl !== null) {
            $payload['dlrUrl'] = $dlrUrl;
        }

        if ($testMode) {
            $payload['testMode'] = '1';
        }

        $response = $this->request()->post("{$this->baseUrl}/sms", $payload);

        if (! $response->successful()) {
            Log::error('VertexSMS: SMS send failed', [
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
                'to'     => $to,
            ]);

            throw new RuntimeException(
                'VertexSMS SMS send failed: HTTP ' . $response->status()
            );
        }

        /** @var array<int, string|int>|null $data */
        $data = $response->json();

        $messageId = is_array($data) && isset($data[0]) ? (string) $data[0] : '';

        if ($messageId === '') {
            throw new RuntimeException(
                'VertexSMS API returned empty or invalid message ID'
            );
        }

        Log::info('VertexSMS: SMS sent', [
            'message_id' => $messageId,
            'to'         => $to,
            'test_mode'  => $testMode,
            'dlr_url'    => $dlrUrl !== null,
        ]);

        return [
            'message_id' => $messageId,
        ];
    }

    /**
     * Fetch the rate card for all destinations.
     *
     * @return array<int, array{CountryCode: string, Country: string, Operator: string, Rate: string}>
     */
    public function getRates(): array
    {
        $response = $this->request()->get("{$this->baseUrl}/rates/", [
            'format' => 'json',
        ]);

        if (! $response->successful()) {
            Log::warning('VertexSMS: Rate card fetch failed', [
                'status' => $response->status(),
            ]);

            return [];
        }

        /** @var array<int, array{CountryCode: string, Country: string, Operator: string, Rate: string}> $rates */
        $rates = $response->json() ?? [];

        return $rates;
    }

    /**
     * Verify a DLR webhook HMAC-SHA256 signature over the raw request body.
     *
     * Returns true in non-production when the secret is unset so local/test
     * webhooks can be exercised without signing.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secret = (string) config('sms.webhook.secret', '');

        if ($secret === '') {
            if (app()->environment('production')) {
                Log::error('VertexSMS: VERTEXSMS_WEBHOOK_SECRET not set in production');

                return false;
            }

            return true;
        }

        $computed = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computed, $signature);
    }

    /**
     * Constant-time comparison of the DLR URL token Vertex echoed back at us.
     *
     * Returns null when no token is configured (signals "fall through to HMAC
     * header verification"). Returns true/false when a configured token is
     * present.
     */
    public function verifyDlrUrlToken(string $provided): ?bool
    {
        $expected = (string) config('sms.webhook.dlr_url_token', '');

        if ($expected === '') {
            return null;
        }

        return hash_equals($expected, $provided);
    }

    /**
     * Assemble the dlrUrl that we want Vertex to POST back to.
     *
     * Preference order:
     *   1. `sms.webhook.dlr_url` config override (absolute URL)
     *   2. Named route `webhooks.vertexsms.dlr`
     *
     * Appends `?t=<token>` when a URL token is configured.
     */
    private function buildDlrUrl(): ?string
    {
        /** @var string $override */
        $override = (string) config('sms.webhook.dlr_url', '');

        if ($override !== '') {
            $url = $override;
        } else {
            try {
                $url = URL::route('webhooks.vertexsms.dlr');
            } catch (Throwable) {
                return null;
            }
        }

        $token = (string) config('sms.webhook.dlr_url_token', '');
        if ($token !== '') {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . 't=' . urlencode($token);
        }

        return $url;
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'X-VertexSMS-Token' => $this->apiToken,
            'Accept'            => 'application/json',
            'Content-Type'      => 'application/json',
        ])->timeout(30);
    }

    private function requireApiToken(): void
    {
        if ($this->apiToken === '') {
            throw new RuntimeException('VertexSMS API token is not configured. Set VERTEXSMS_API_TOKEN in .env');
        }
    }
}
