<?php

declare(strict_types=1);

namespace App\Domain\Shared\Traits;

use Illuminate\Support\Facades\Config;

/**
 * Trait for models that should use the tenant database connection.
 *
 * Apply this trait to any Eloquent model that contains tenant-specific data.
 * The model will automatically use the 'tenant' connection which is
 * dynamically configured by stancl/tenancy based on the current tenant context.
 *
 * In testing environments with in-memory SQLite, this trait returns null
 * (which uses the default connection) to avoid issues with isolated
 * in-memory databases.
 *
 * Usage:
 * ```php
 * class Account extends Model
 * {
 *     use UsesTenantConnection;
 * }
 * ```
 *
 * Note: The 'tenant' connection must be configured in the database config.
 * For production, stancl/tenancy will configure this connection dynamically.
 */
trait UsesTenantConnection
{
    /**
     * Get the database connection for the model.
     *
     * When tenancy is initialized, stancl/tenancy creates a dynamic 'tenant'
     * connection pointing to the current tenant's database.
     *
     * For testing with in-memory SQLite, returns null to use the default
     * connection and avoid separate isolated databases.
     *
     * @return string|null
     */
    public function getConnectionName(): ?string
    {
        // In testing with in-memory SQLite, use the default connection
        // to avoid issues with isolated in-memory databases
        if ($this->shouldUseDefaultConnection()) {
            return null;
        }

        return 'tenant';
    }

    /**
     * Determine if the model should use the default connection instead of tenant.
     *
     * Returns true when APP_ENV is 'testing'. The
     * `database.force_real_tenant_connection` config flag overrides the
     * carve-out — set it to true in test base classes that need the production
     * multi-session topology (see tests/MultiConnection/).
     */
    protected function shouldUseDefaultConnection(): bool
    {
        // Strict === true: only programmatic boolean overrides count, never env strings.
        if (Config::get('database.force_real_tenant_connection') === true) {
            return false;
        }

        return Config::get('app.env') === 'testing';
    }
}
