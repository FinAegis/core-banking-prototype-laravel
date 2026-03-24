<?php

declare(strict_types=1);

namespace App\Domain\SMS\Clients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * HTTP client wrapper for the VertexSMS REST API.
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
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.vertexsms.com'), '/');
    }

    /**
     * Send an SMS message.
     *
     * @return array{message_id: string, parts: int}
     */
    public function sendSms(string $to, string $from, string $message, bool $testMode = false): array
    {
        if ($this->apiToken === '') {
            throw new RuntimeException('VertexSMS API token is not configured. Set VERTEXSMS_API_TOKEN in .env');
        }

        $payload = [
            'to'      => $to,
            'from'    => $from,
            'message' => $message,
        ];

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

        /** @var array<int, string>|null $data */
        $data = $response->json();

        $messageId = is_array($data) && isset($data[0]) ? (string) $data[0] : '';

        if ($messageId === '') {
            throw new RuntimeException(
                'VertexSMS API returned empty or invalid message ID'
            );
        }

        $parts = max(1, (int) ($response->header('X-VertexSMS-Amount-Sent') ?? '1'));

        Log::info('VertexSMS: SMS sent', [
            'message_id' => $messageId,
            'to'         => $to,
            'parts'      => $parts,
            'test_mode'  => $testMode,
        ]);

        return [
            'message_id' => (string) $messageId,
            'parts'      => $parts,
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
     * Verify a DLR webhook signature.
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

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'X-VertexSMS-Token' => $this->apiToken,
            'Accept'            => 'application/json',
            'Content-Type'      => 'application/json',
        ])->timeout(30);
    }
}
