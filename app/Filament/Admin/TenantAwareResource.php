<?php

declare(strict_types=1);

namespace App\Filament\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for Filament resources that should be tenant-aware.
 *
 * Apply this trait to any Filament Resource that manages tenant-specific data.
 * It automatically filters the Eloquent query to only show records belonging
 * to the current tenant when in a tenant context.
 *
 * Usage:
 * ```php
 * class AccountResource extends Resource
 * {
 *     use TenantAwareResource;
 *
 *     // If your model uses a different tenant column name:
 *     protected static ?string $tenantColumn = 'organization_id';
 *
 *     // Override for custom filtering logic:
 *     protected static function applyTenantScope(Builder $query): Builder
 *     {
 *         return $query->where('custom_tenant_field', tenant()->id);
 *     }
 * }
 * ```
 *
 * Note: This trait relies on the stancl/tenancy package's tenant() helper
 * function. When no tenant context is active, no filtering is applied
 * (for platform-level admin access).
 */
trait TenantAwareResource
{
    /**
     * The column name used for tenant filtering.
     *
     * Override this in your resource if the model uses a different column name.
     * If null, the trait will attempt to use the model's tenant scope automatically.
     */
    protected static ?string $tenantColumn = null;

    /**
     * Whether to show all records when no tenant context is active.
     *
     * Set to false to hide all records when there's no tenant context.
     */
    protected static bool $showAllWithoutTenant = true;

    /**
     * Get the base Eloquent query with tenant filtering applied.
     *
     * This method is called by Filament to get the query for listing records.
     * It automatically applies tenant filtering when a tenant context is active.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Model> $query */
        $query = parent::getEloquentQuery();

        return static::applyTenantScope($query);
    }

    /**
     * Apply tenant scoping to the query.
     *
     * Override this method in your resource for custom tenant filtering logic.
     *
     * @param Builder<Model> $query
     * @return Builder<Model>
     */
    protected static function applyTenantScope(Builder $query): Builder
    {
        // Check if tenant context is active
        if (! function_exists('tenant') || ! tenant()) {
            // No tenant context - return unfiltered if allowed
            if (static::$showAllWithoutTenant) {
                return $query;
            }

            // Otherwise, return empty result set
            return $query->whereRaw('1 = 0');
        }

        // Check if the model uses the UsesTenantConnection trait
        // If so, the connection-based isolation is already in effect
        /** @var class-string<Model> $model */
        $model = static::getModel();
        if (method_exists($model, 'getConnectionName')) {
            /** @var Model $instance */
            $instance = new $model();
            // If model already uses tenant connection, the data is isolated at DB level
            if ($instance->getConnectionName() === 'tenant') {
                return $query;
            }
        }

        // Apply manual tenant filtering using the tenant column
        $column = static::$tenantColumn;
        if ($column !== null) {
            /** @phpstan-ignore argument.type */
            return $query->where($column, tenant()->id);
        }

        // Check if the model has a tenantScope method (custom scope)
        if (method_exists($model, 'scopeTenant')) {
            /** @phpstan-ignore method.notFound */
            return $query->tenant();
        }

        // Check for team_id column (common in Jetstream apps)
        /** @var Model $instance */
        $instance = new $model();
        /** @phpstan-ignore argument.type */
        if ($instance->getConnection()->getSchemaBuilder()->hasColumn($instance->getTable(), 'team_id')) {
            // Filter by team_id using the tenant's linked team
            /** @phpstan-ignore argument.type */
            return $query->where('team_id', tenant()->team_id);
        }

        // No tenant filtering available - return unfiltered query with warning
        return $query;
    }

    /**
     * Check if the current resource should be tenant-filtered.
     *
     * Returns true if a tenant context is active.
     */
    protected static function hasTenantContext(): bool
    {
        return function_exists('tenant') && tenant() !== null;
    }

    /**
     * Get the current tenant ID.
     *
     * Returns null if no tenant context is active.
     */
    protected static function getTenantId(): ?string
    {
        if (! static::hasTenantContext()) {
            return null;
        }

        return (string) tenant()->getTenantKey();
    }

    /**
     * Get the current team ID (if tenant is linked to a team).
     *
     * Returns null if no tenant context is active or tenant has no team.
     */
    protected static function getTeamId(): ?int
    {
        if (! static::hasTenantContext()) {
            return null;
        }

        return tenant()->team_id;
    }
}
