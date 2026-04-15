<?php

declare(strict_types=1);

namespace App\Domain\SMS\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * HTTP client wrapper for the VertexSMS REST API (kube-api.vertexsms.com).
 *
 * @see https://vertexsms.com/en/api
 */
class VertexSmsClient
{
    private readonly string $apiToken;

    private readonly string $baseUrl;

    private bool $dlrUrlResolved = false;

    private ?string $dlrUrl = null;

    public function __construct()
    {
        /** @var array{api_token?: string, base_url?: string} $config */
        $config = config('sms.providers.vertexsms', []);

        $this->apiToken = (string) ($config['api_token'] ?? '');
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://kube-api.vertexsms.com'), '/');
    }

    /**
     * Estimate cost + parts for a message via POST /sms/cost.
     *
     * Numeric fields are returned as bcmath-safe `numeric-string`s. `mccmnc`
     * is split into separate `mcc` (3 digits) and `mnc` (rest) at this boundary.
     *
     * @return array{parts: int, price_per_part_eur: numeric-string, total_price_eur: numeric-string, country_iso: string, mcc: ?string, mnc: ?string}
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

        // Vertex returns a single-object response or an array-of-one (per docs).
        /** @var array<string, mixed> $entry */
        $entry = isset($data[0]) && is_array($data[0]) ? $data[0] : $data;

        [$mcc, $mnc] = $this->splitMccMnc(isset($entry['mccmnc']) ? (string) $entry['mccmnc'] : '');

        return [
            'parts'              => max(1, $this->toInt($entry['parts'] ?? 1)),
            'price_per_part_eur' => $this->toNumericString($entry['pricePerPart'] ?? 0),
            'total_price_eur'    => $this->toNumericString($entry['totalPrice'] ?? 0),
            'country_iso'        => is_string($entry['countryISO'] ?? null) ? strtoupper((string) $entry['countryISO']) : '',
            'mcc'                => $mcc,
            'mnc'                => $mnc,
        ];
    }

    /**
     * Send an SMS via POST /sms.
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

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    /**
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
     * Memoized at instance level — base URL + token are process-lifetime
     * stable, no point re-resolving the route + reading config on every send.
     */
    private function buildDlrUrl(): ?string
    {
        if ($this->dlrUrlResolved) {
            return $this->dlrUrl;
        }

        $override = (string) config('sms.webhook.dlr_url', '');
        $url = $override !== '' ? $override : URL::route('webhooks.vertexsms.dlr');

        $token = (string) config('sms.webhook.dlr_url_token', '');
        if ($token !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?') . 't=' . urlencode($token);
        }

        $this->dlrUrlResolved = true;

        return $this->dlrUrl = $url;
    }

    /**
     * Split "24601" → ["246", "01"]. Returns [null, null] for malformed input.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function splitMccMnc(string $mccmnc): array
    {
        if ($mccmnc === '' || ! ctype_digit($mccmnc) || strlen($mccmnc) < 4) {
            return [null, null];
        }

        return [substr($mccmnc, 0, 3), substr($mccmnc, 3)];
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Convert a JSON-decoded numeric (float|int|string) into a fixed-precision
     * numeric string suitable for bcmath. Six decimals matches USDC precision.
     *
     * @return numeric-string
     */
    private function toNumericString(mixed $value): string
    {
        if (is_string($value) && is_numeric($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 6, '.', '');
        }

        return '0';
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
