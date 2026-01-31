<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Jobs;

use App\Domain\Mobile\Services\BiometricAuthenticationService;
use App\Domain\Shared\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Cleans up expired biometric authentication challenges.
 *
 * Biometric challenges have a short TTL (typically 2 minutes) and should
 * be cleaned up regularly to prevent database bloat and ensure challenges
 * cannot be replayed after expiration.
 */
class CleanupExpiredChallenges implements ShouldQueue
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
    public function handle(BiometricAuthenticationService $service): void
    {
        $this->verifyTenantContext();

        $count = $service->cleanupExpiredChallenges();

        Log::info('Cleaned up expired biometric challenges', [
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
            ['mobile', 'biometric', 'cleanup'],
            $this->tenantTags()
        );
    }

    /**
     * Determine if this job requires tenant context.
     */
    public function requiresTenantContext(): bool
    {
        // Can run globally to clean up all tenants' expired challenges
        return false;
    }
}
