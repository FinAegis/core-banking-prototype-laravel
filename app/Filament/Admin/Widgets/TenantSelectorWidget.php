<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\Tenant;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

/**
 * Widget for selecting and displaying the current tenant context.
 *
 * This widget is displayed in the Filament admin panel header area
 * and allows users to switch between tenants they have access to.
 */
class TenantSelectorWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.tenant-selector-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -100;

    /**
     * Get the tenants available to the current user.
     *
     * @return array<string, string>
     */
    public function getTenants(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        // Platform admins can see all tenants
        if ($this->isPlatformAdmin($user)) {
            return Tenant::all()
                ->pluck('name', 'id')
                ->toArray();
        }

        // Regular users can only see tenants for their teams
        if (! method_exists($user, 'allTeams')) {
            return [];
        }

        $teamIds = $user->allTeams()->pluck('id');

        return Tenant::whereIn('team_id', $teamIds)
            ->get()
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Get the current tenant ID.
     */
    public function getCurrentTenantId(): ?string
    {
        if (! function_exists('tenant') || ! tenant()) {
            return null;
        }

        return (string) tenant()->getTenantKey();
    }

    /**
     * Get the current tenant name.
     */
    public function getCurrentTenantName(): ?string
    {
        if (! function_exists('tenant') || ! tenant()) {
            return 'All Tenants (Platform View)';
        }

        return tenant()->name ?? 'Unknown Tenant';
    }

    /**
     * Check if tenancy is currently active.
     */
    public function hasTenantContext(): bool
    {
        return function_exists('tenant') && tenant() !== null;
    }

    /**
     * Check if the user is a platform admin.
     *
     * @param object $user
     */
    protected function isPlatformAdmin(object $user): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('platform_admin')) {
            return true;
        }

        if (method_exists($user, 'hasPermission') && $user->hasPermission('access_all_tenants')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the widget should be visible.
     */
    public static function canView(): bool
    {
        // Only show if tenancy is configured
        return class_exists(\Stancl\Tenancy\Tenancy::class);
    }

    /**
     * Get data for the view.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'tenants'           => $this->getTenants(),
            'currentTenantId'   => $this->getCurrentTenantId(),
            'currentTenantName' => $this->getCurrentTenantName(),
            'hasTenantContext'  => $this->hasTenantContext(),
        ];
    }
}
