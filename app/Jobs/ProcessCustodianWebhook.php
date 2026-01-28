<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Custodian\Models\CustodianWebhook;
use App\Domain\Custodian\Services\WebhookProcessorService;
use App\Domain\Shared\Jobs\TenantAwareJob;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Process custodian webhooks in the queue.
 *
 * This job is tenant-aware since custodian webhooks are processed
 * within a specific tenant context. The tenant_id is captured at
 * dispatch time and restored when the job is processed.
 */
class ProcessCustodianWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use TenantAwareJob;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $webhookUuid
    ) {
        $this->initializeTenantAwareJob();
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookProcessorService $webhookProcessor): void
    {
        $webhook = CustodianWebhook::where('uuid', $this->webhookUuid)->first();

        if (! $webhook) {
            Log::error('Webhook not found', ['uuid' => $this->webhookUuid]);

            return;
        }

        try {
            // Mark webhook as processing
            $webhook->markAsProcessing();

            Log::info('Processing custodian webhook', [
                'custodian'  => $webhook->custodian_name,
                'event_type' => $webhook->event_type,
                'webhook_id' => $webhook->id,
            ]);

            // Delegate to the webhook processor service for actual business logic
            $webhookProcessor->process($webhook);

            // Mark webhook as processed
            $webhook->markAsProcessed();
        } catch (InvalidArgumentException $e) {
            // Unknown custodian or event type - mark as ignored
            Log::warning('Webhook processing skipped', [
                'webhook_id' => $webhook->id,
                'reason'     => $e->getMessage(),
            ]);

            $webhook->markAsIgnored($e->getMessage());
        } catch (Exception $e) {
            Log::error('Failed to process webhook', [
                'webhook_id' => $webhook->id,
                'error'      => $e->getMessage(),
            ]);

            // Mark webhook as failed
            $webhook->markAsFailed($e->getMessage());

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Webhooks may be processed without tenant context for global webhooks.
     */
    public function requiresTenantContext(): bool
    {
        return false;
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return array_merge(
            ['webhook', 'custodian'],
            $this->tenantTags()
        );
    }
}
