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
 * Processes scheduled push notifications for mobile devices.
 *
 * This job runs periodically to process notifications that were scheduled
 * for delayed delivery, such as reminder notifications or time-sensitive alerts.
 */
class ProcessScheduledNotifications implements ShouldQueue
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
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $queue = null)
    {
        $this->onQueue($queue ?? 'mobile');
        $this->initializeTenantAwareJob();
    }

    /**
     * Execute the job.
     */
    public function handle(PushNotificationService $service): void
    {
        $this->verifyTenantContext();

        $count = $service->processScheduledNotifications();

        Log::info('Processed scheduled mobile notifications', [
            'count'  => $count,
            'tenant' => $this->dispatchedTenantId ?? 'global',
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
            ['mobile', 'notifications', 'scheduled'],
            $this->tenantTags()
        );
    }

    /**
     * Determine if this job requires tenant context.
     */
    public function requiresTenantContext(): bool
    {
        // Can run globally to process all tenants' notifications
        return false;
    }
}
