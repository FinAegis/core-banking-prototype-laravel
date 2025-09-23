<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Workflows\Activities;

use App\Domain\AgentProtocol\Services\AgentWebhookService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Workflow\Activity;

class DeliverMessageActivity extends Activity
{
    private Client $httpClient;

    private AgentWebhookService $webhookService;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout'         => 30,
            'connect_timeout' => 10,
            'http_errors'     => false,
            'verify'          => config('agent_protocol.ssl_verification', true),
        ]);

        $this->webhookService = app(AgentWebhookService::class);
    }

    public function execute(
        string $messageId,
        string $endpoint,
        array $payload,
        array $headers = []
    ): array {
        $deliveryMethod = $this->determineDeliveryMethod($endpoint);
        $startTime = microtime(true);

        try {
            $response = match ($deliveryMethod) {
                'http'      => $this->deliverViaHttp($endpoint, $messageId, $payload, $headers),
                'webhook'   => $this->deliverViaWebhook($endpoint, $messageId, $payload, $headers),
                'websocket' => $this->deliverViaWebSocket($endpoint, $messageId, $payload, $headers),
                'grpc'      => $this->deliverViaGrpc($endpoint, $messageId, $payload, $headers),
                default     => throw new RuntimeException("Unsupported delivery method: {$deliveryMethod}")
            };

            $deliveryTime = microtime(true) - $startTime;

            return [
                'deliveredAt'    => now()->toIso8601String(),
                'method'         => $deliveryMethod,
                'response'       => $response,
                'deliveryTimeMs' => round($deliveryTime * 1000, 2),
                'statusCode'     => $response['statusCode'] ?? 200,
                'success'        => true,
            ];
        } catch (RequestException $e) {
            $this->logDeliveryFailure($messageId, $endpoint, $e);

            throw new RuntimeException(
                "Failed to deliver message to {$endpoint}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        } catch (Exception $e) {
            $this->logDeliveryFailure($messageId, $endpoint, $e);

            throw $e;
        }
    }

    private function determineDeliveryMethod(string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return 'http';
        }

        if (str_starts_with($endpoint, 'ws://') || str_starts_with($endpoint, 'wss://')) {
            return 'websocket';
        }

        if (str_starts_with($endpoint, 'grpc://')) {
            return 'grpc';
        }

        if (str_starts_with($endpoint, 'webhook://')) {
            return 'webhook';
        }

        // Default to HTTP for endpoints without explicit protocol
        return 'http';
    }

    private function deliverViaHttp(
        string $endpoint,
        string $messageId,
        array $payload,
        array $headers
    ): array {
        $defaultHeaders = [
            'Content-Type'       => 'application/json',
            'Accept'             => 'application/json',
            'X-Message-Id'       => $messageId,
            'X-Protocol'         => 'A2A',
            'X-Protocol-Version' => '1.0.0',
            'X-Timestamp'        => now()->toIso8601String(),
            'X-Idempotency-Key'  => $messageId,
        ];

        $mergedHeaders = array_merge($defaultHeaders, $headers);

        $response = $this->httpClient->post($endpoint, [
            'headers' => $mergedHeaders,
            'json'    => [
                'messageId' => $messageId,
                'payload'   => $payload,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        if ($statusCode >= 400) {
            throw new RuntimeException(
                "HTTP delivery failed with status {$statusCode}: {$body}"
            );
        }

        return [
            'statusCode'   => $statusCode,
            'headers'      => $response->getHeaders(),
            'body'         => json_decode($body, true) ?? $body,
            'reasonPhrase' => $response->getReasonPhrase(),
        ];
    }

    private function deliverViaWebhook(
        string $endpoint,
        string $messageId,
        array $payload,
        array $headers
    ): array {
        // Use the webhook service for specialized webhook delivery
        $result = $this->webhookService->sendWebhook(
            $endpoint,
            [
                'messageId' => $messageId,
                'payload'   => $payload,
                'headers'   => $headers,
            ],
            $headers
        );

        if (! $result['success']) {
            throw new RuntimeException(
                'Webhook delivery failed: ' . ($result['error'] ?? 'Unknown error')
            );
        }

        return $result;
    }

    private function deliverViaWebSocket(
        string $endpoint,
        string $messageId,
        array $payload,
        array $headers
    ): array {
        // WebSocket implementation would go here
        // For now, throw an exception as it's not yet implemented
        throw new RuntimeException('WebSocket delivery not yet implemented');
    }

    private function deliverViaGrpc(
        string $endpoint,
        string $messageId,
        array $payload,
        array $headers
    ): array {
        // gRPC implementation would go here
        // For now, throw an exception as it's not yet implemented
        throw new RuntimeException('gRPC delivery not yet implemented');
    }

    private function logDeliveryFailure(string $messageId, string $endpoint, Exception $e): void
    {
        Log::error('Message delivery failed', [
            'messageId'  => $messageId,
            'endpoint'   => $endpoint,
            'error'      => $e->getMessage(),
            'errorClass' => get_class($e),
            'trace'      => $e->getTraceAsString(),
        ]);
    }
}
