<?php

declare(strict_types=1);

namespace App\Domain\Shared\Traits;

/**
 * Trait for models that should use the tenant database connection.
 *
 * Apply this trait to any Eloquent model that contains tenant-specific data.
 * The model will automatically use the 'tenant' connection which is
 * dynamically configured by stancl/tenancy based on the current tenant context.
 *
 * Usage:
 * ```php
 * class Account extends Model
 * {
 *     use UsesTenantConnection;
 * }
 * ```
 */
trait UsesTenantConnection
{
    /**
     * Get the database connection for the model.
     *
     * When tenancy is initialized, stancl/tenancy creates a dynamic 'tenant'
     * connection pointing to the current tenant's database.
     *
     * @return string|null
     */
    public function getConnectionName(): ?string
    {
        return 'tenant';
    }
}
