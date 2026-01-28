<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Messaging;

use App\Domain\Shared\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to process A2A messages asynchronously.
 *
 * This job is tenant-aware since agents and their messages are scoped to
 * specific tenants. The tenant_id is captured at dispatch time and restored
 * by stancl/tenancy's QueueTenancyBootstrapper when the job is processed.
 */
class ProcessA2AMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use TenantAwareJob;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * @param array<string, mixed> $envelopeData
     */
    public function __construct(
        private readonly array $envelopeData
    ) {
        $this->initializeTenantAwareJob();
    }

    /**
     * Execute the job.
     */
    public function handle(AgentMessageBusService $messageBus): void
    {
        $envelope = A2AMessageEnvelope::fromArray($this->envelopeData);

        Log::info('Processing A2A message', [
            'messageId' => $envelope->messageId,
            'type'      => $envelope->messageType->value,
            'from'      => $envelope->senderDid,
            'to'        => $envelope->recipientDid,
        ]);

        $result = $messageBus->receive($envelope);

        if (! $result->success && ! $result->duplicate) {
            Log::error('Failed to process A2A message', [
                'messageId' => $envelope->messageId,
                'error'     => $result->error,
            ]);

            // If retries are available and message allows retry
            if ($envelope->enableRetry && $this->attempts() < $envelope->maxRetries) {
                $this->release($this->calculateBackoff());
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('A2A message job failed permanently', [
            'messageId' => $this->envelopeData['messageId'] ?? 'unknown',
            'exception' => $exception?->getMessage(),
        ]);
    }

    /**
     * Calculate backoff delay based on priority.
     */
    private function calculateBackoff(): int
    {
        $priority = $this->envelopeData['priority'] ?? 50;
        $multiplier = match (true) {
            $priority >= 90 => 0.5,
            $priority >= 70 => 1.0,
            $priority >= 40 => 1.5,
            $priority >= 20 => 2.0,
            default         => 3.0,
        };

        return (int) ($this->backoff * $multiplier * $this->attempts());
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return array_merge(
            [
                'a2a-message',
                'message-type:' . ($this->envelopeData['messageType'] ?? 'unknown'),
                'sender:' . ($this->envelopeData['senderDid'] ?? 'unknown'),
            ],
            $this->tenantTags()
        );
    }
}
