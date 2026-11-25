<?php

namespace FinAegis\Resources;

use FinAegis\Models\PaginatedResponse;
use FinAegis\Models\Webhook;

class Webhooks extends BaseResource
{
    /**
     * List all webhooks.
     *
     * @param  int  $page  Page number
     * @param  int  $perPage  Items per page
     */
    public function list(int $page = 1, int $perPage = 20): PaginatedResponse
    {
        $response = $this->httpGet('/webhooks', ['page' => $page, 'per_page' => $perPage]);

        return new PaginatedResponse($response, Webhook::class);
    }

    /**
     * Create a new webhook.
     *
     * @param  string  $name  Webhook name
     * @param  string  $url  Webhook URL
     * @param  array  $events  List of events to subscribe to
     * @param  array|null  $headers  Optional custom headers
     * @param  string|null  $secret  Optional secret for signature verification
     */
    public function create(
        string $name,
        string $url,
        array $events,
        ?array $headers = null,
        ?string $secret = null
    ): Webhook {
        $data = [
            'name' => $name,
            'url' => $url,
            'events' => $events,
        ];

        if ($headers !== null) {
            $data['headers'] = $headers;
        }

        if ($secret !== null) {
            $data['secret'] = $secret;
        }

        $response = $this->httpPost('/webhooks', $data);

        return new Webhook($response['data']);
    }

    /**
     * Get webhook details.
     *
     * @param  string  $webhookId  Webhook UUID
     */
    public function get(string $webhookId): Webhook
    {
        $response = $this->httpGet("/webhooks/{$webhookId}");

        return new Webhook($response['data']);
    }

    /**
     * Update a webhook.
     *
     * @param  string  $webhookId  Webhook UUID
     * @param  array  $data  Update data
     */
    public function update(string $webhookId, array $data): Webhook
    {
        $response = $this->httpPut("/webhooks/{$webhookId}", $data);

        return new Webhook($response['data']);
    }

    /**
     * Delete a webhook.
     *
     * @param  string  $webhookId  Webhook UUID
     */
    public function delete(string $webhookId): array
    {
        return $this->httpDelete("/webhooks/{$webhookId}");
    }

    /**
     * Get webhook delivery history.
     *
     * @param  string  $webhookId  Webhook UUID
     * @param  int  $page  Page number
     * @param  int  $perPage  Items per page
     */
    public function getDeliveries(string $webhookId, int $page = 1, int $perPage = 20): array
    {
        return $this->httpGet("/webhooks/{$webhookId}/deliveries", ['page' => $page, 'per_page' => $perPage]);
    }

    /**
     * Get available webhook events.
     */
    public function getEvents(): array
    {
        $response = $this->httpGet('/webhooks/events');

        return $response['data'];
    }

    /**
     * Verify webhook signature.
     *
     * @param  string  $payload  Webhook payload
     * @param  string  $signature  Webhook signature
     * @param  string  $secret  Webhook secret
     */
    public static function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
