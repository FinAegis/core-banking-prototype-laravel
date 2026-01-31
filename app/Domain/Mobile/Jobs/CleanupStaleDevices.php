<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Jobs;

use App\Domain\Mobile\Services\MobileDeviceService;
use App\Domain\Shared\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Cleans up stale mobile devices that haven't been active for a long time.
 *
 * Devices that have been inactive for the configured number of days are
 * cleaned up to prevent database bloat and remove abandoned device registrations.
 * This also helps with security by removing forgotten devices.
 */
class CleanupStaleDevices implements ShouldQueue
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
     * Number of days after which a device is considered stale.
     */
    private int $staleDays;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $staleDays = null, ?string $queue = null)
    {
        $this->staleDays = $staleDays ?? 90;
        $this->onQueue($queue ?? 'mobile');
        $this->initializeTenantAwareJob();
    }

    /**
     * Execute the job.
     */
    public function handle(MobileDeviceService $service): void
    {
        $this->verifyTenantContext();

        $count = $service->cleanupStaleDevices($this->staleDays);

        Log::info('Cleaned up stale mobile devices', [
            'count'      => $count,
            'stale_days' => $this->staleDays,
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
            ['mobile', 'devices', 'cleanup'],
            $this->tenantTags()
        );
    }

    /**
     * Determine if this job requires tenant context.
     */
    public function requiresTenantContext(): bool
    {
        // Can run globally to clean up all tenants' stale devices
        return false;
    }
}
