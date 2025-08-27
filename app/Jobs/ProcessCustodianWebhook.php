<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Custodian\Models\CustodianWebhook;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCustodianWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $webhookUuid
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $webhook = CustodianWebhook::where('uuid', $this->webhookUuid)->first();

        if (! $webhook) {
            Log::error('Webhook not found', ['uuid' => $this->webhookUuid]);

            return;
        }

        try {
            // Process the webhook based on custodian and event type
            Log::info('Processing custodian webhook', [
                'custodian'  => $webhook->custodian,
                'event_type' => $webhook->event_type,
                'webhook_id' => $webhook->id,
            ]);

            // TODO: Implement actual webhook processing logic based on custodian and event type
            // This is a placeholder implementation

            // Mark webhook as processed
            $webhook->update([
                'status'       => 'processed',
                'processed_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to process webhook', [
                'webhook_id' => $webhook->id,
                'error'      => $e->getMessage(),
            ]);

            // Mark webhook as failed
            $webhook->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['webhook', 'custodian'];
    }
}
