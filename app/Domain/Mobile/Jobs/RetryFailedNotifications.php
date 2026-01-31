<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Jobs;

use App\Domain\Mobile\Services\PushNotificationService;
use App\Domain\Shared\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Retries failed push notifications that had temporary failures.
 *
 * This job runs periodically to retry notifications that failed due to
 * network issues, FCM temporary unavailability, or other transient errors.
 * Notifications with permanent failures (invalid tokens) are not retried.
 */
class RetryFailedNotifications implements ShouldQueue
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
    public int $backoff = 120;

    /**
     * The maximum number of notifications to retry per run.
     */
    private int $batchSize;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $batchSize = null, ?string $queue = null)
    {
        $this->batchSize = $batchSize ?? 100;
        $this->onQueue($queue ?? 'mobile');
        $this->initializeTenantAwareJob();
    }

    /**
     * Execute the job.
     */
    public function handle(PushNotificationService $service): void
    {
        $this->verifyTenantContext();

        $count = $service->retryFailedNotifications($this->batchSize);

        Log::info('Retried failed mobile notifications', [
            'count'      => $count,
            'batch_size' => $this->batchSize,
            'tenant'     => $this->dispatchedTenantId ?? 'global',
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return array_merge(
            ['mobile', 'notifications', 'retry'],
            $this->tenantTags()
        );
    }

    /**
     * Determine if this job requires tenant context.
     */
    public function requiresTenantContext(): bool
    {
        // Can run globally to process all tenants' failed notifications
        return false;
    }
}
