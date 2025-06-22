<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessWebhookDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public WebhookDelivery $delivery
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WebhookService $webhookService): void
    {
        $webhook = $this->delivery->webhook;

        if (!$webhook->is_active) {
            Log::warning("Skipping delivery for inactive webhook: {$webhook->uuid}");
            return;
        }

        $webhook->markAsTriggered();

        try {
            $payloadJson = json_encode($this->delivery->payload);
            
            // Prepare headers
            $headers = $webhook->headers ?? [];
            $headers['Content-Type'] = 'application/json';
            $headers['User-Agent'] = 'FinAegis-Webhook/1.0';
            $headers['X-Webhook-ID'] = $webhook->uuid;
            $headers['X-Webhook-Event'] = $this->delivery->event_type;
            $headers['X-Webhook-Delivery'] = $this->delivery->uuid;

            // Add signature if secret is configured
            if ($webhook->secret) {
                $headers['X-Webhook-Signature'] = $webhookService->generateSignature($payloadJson, $webhook->secret);
            }

            // Send the webhook
            $startTime = microtime(true);
            
            $response = Http::withHeaders($headers)
                ->timeout($webhook->timeout_seconds)
                ->post($webhook->url, $this->delivery->payload);

            $duration = round((microtime(true) - $startTime) * 1000); // Convert to milliseconds

            // Check if response is successful (2xx status code)
            if ($response->successful()) {
                $this->delivery->markAsDelivered(
                    statusCode: $response->status(),
                    responseBody: $response->body(),
                    responseHeaders: $response->headers(),
                    durationMs: $duration
                );

                Log::info("Webhook delivered successfully", [
                    'webhook_id' => $webhook->uuid,
                    'delivery_id' => $this->delivery->uuid,
                    'status_code' => $response->status(),
                    'duration_ms' => $duration,
                ]);
            } else {
                throw new \Exception("HTTP {$response->status()}: {$response->body()}");
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $statusCode = $e instanceof \Illuminate\Http\Client\RequestException 
                ? $e->response->status() ?? 0 
                : 0;

            $this->delivery->markAsFailed(
                errorMessage: $errorMessage,
                statusCode: $statusCode,
                responseBody: $e instanceof \Illuminate\Http\Client\RequestException 
                    ? $e->response->body() 
                    : null
            );

            Log::error("Webhook delivery failed", [
                'webhook_id' => $webhook->uuid,
                'delivery_id' => $this->delivery->uuid,
                'error' => $errorMessage,
                'attempt' => $this->delivery->attempt_number,
            ]);

            // Re-throw to trigger retry if applicable
            throw $e;
        }
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        // Allow retries for up to 24 hours
        return now()->addDay();
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Exponential backoff: 1 min, 5 min, 15 min
        return [60, 300, 900];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Webhook delivery job failed permanently", [
            'delivery_id' => $this->delivery->uuid,
            'error' => $exception->getMessage(),
        ]);
    }
}