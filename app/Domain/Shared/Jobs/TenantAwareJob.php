<?php

declare(strict_types=1);

namespace App\Domain\Shared\Jobs;

use RuntimeException;

/**
 * Trait for queue jobs that should be aware of tenant context.
 *
 * When a job is dispatched within a tenant context, stancl/tenancy's
 * QueueTenancyBootstrapper automatically adds tenant_id to the job payload
 * and initializes tenancy when the job is processed.
 *
 * This trait provides additional functionality:
 * - Explicit tenant_id tracking for debugging/logging
 * - Tenant-specific tags for Horizon monitoring
 * - Helper methods for tenant context verification
 *
 * Usage:
 * ```php
 * class ProcessOrderJob implements ShouldQueue
 * {
 *     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
 *     use TenantAwareJob;
 *
 *     public function handle(): void
 *     {
 *         // Tenant context is automatically initialized by stancl/tenancy
 *         // Access current tenant with tenant() helper
 *         if ($this->requiresTenantContext() && !tenant()) {
 *             throw new RuntimeException('Tenant context required');
 *         }
 *     }
 * }
 * ```
 *
 * Note: The QueueTenancyBootstrapper must be enabled in config/tenancy.php
 * for automatic tenant context restoration to work.
 */
trait TenantAwareJob
{
    /**
     * The tenant ID at the time of dispatch.
     *
     * This is stored for reference/debugging purposes. The actual tenant
     * initialization is handled by stancl/tenancy's QueueTenancyBootstrapper
     * which stores tenant_id in the job payload.
     */
    public ?string $dispatchedTenantId = null;

    /**
     * Boot the trait and capture the current tenant context.
     *
     * This method is called automatically when the job is constructed.
     */
    public function initializeTenantAwareJob(): void
    {
        if (function_exists('tenant') && tenant()) {
            $this->dispatchedTenantId = (string) tenant()->getTenantKey();
        }
    }

    /**
     * Check if this job requires tenant context to execute.
     *
     * Override this in your job class if tenant context is optional.
     */
    public function requiresTenantContext(): bool
    {
        return true;
    }

    /**
     * Get tenant-related tags for Horizon/queue monitoring.
     *
     * Merge these with your job's custom tags.
     *
     * @return array<int, string>
     */
    public function tenantTags(): array
    {
        $tags = ['tenant-aware'];

        if ($this->dispatchedTenantId) {
            $tags[] = 'tenant:' . $this->dispatchedTenantId;
        }

        return $tags;
    }

    /**
     * Verify tenant context is available and matches expected tenant.
     *
     * @throws RuntimeException if tenant context is missing or mismatched
     */
    protected function verifyTenantContext(): void
    {
        if (! function_exists('tenant')) {
            throw new RuntimeException('Tenancy package not available');
        }

        if (! tenant()) {
            throw new RuntimeException('Job requires tenant context but none is initialized');
        }

        // If we tracked the original tenant, verify it matches
        if ($this->dispatchedTenantId !== null) {
            $currentTenantId = (string) tenant()->getTenantKey();
            if ($currentTenantId !== $this->dispatchedTenantId) {
                throw new RuntimeException(sprintf(
                    'Tenant context mismatch: expected %s, got %s',
                    $this->dispatchedTenantId,
                    $currentTenantId
                ));
            }
        }
    }

    /**
     * Get the current tenant ID, or null if not in tenant context.
     */
    protected function getCurrentTenantId(): ?string
    {
        if (function_exists('tenant') && tenant()) {
            return (string) tenant()->getTenantKey();
        }

        return null;
    }
}
