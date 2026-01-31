<?php

declare(strict_types=1);

use App\Broadcasting\TenantChannelAuthorizer;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
| All tenant-specific channels are prefixed with 'tenant.' to ensure
| proper isolation. The TenantChannelAuthorization middleware verifies
| the user belongs to the correct tenant before granting access.
|
*/

// User-specific private channel
Broadcast::channel('App.Models.User.{id}', function ($user, int $id) {
    return $user->id === $id;
});

/*
|--------------------------------------------------------------------------
| Tenant-Scoped Channels (v2.0.0 Multi-Tenancy)
|--------------------------------------------------------------------------
|
| These channels are scoped to specific tenants. Users can only access
| channels belonging to their current tenant (team). The tenant_id
| in the channel name must match the user's active team.
|
*/

// Tenant notifications channel - all members of a tenant receive updates
Broadcast::channel('tenant.{tenantId}', function ($user, string $tenantId) {
    return TenantChannelAuthorizer::authorizeUser($user, $tenantId);
});

// Tenant-specific account updates
Broadcast::channel('tenant.{tenantId}.accounts', function ($user, string $tenantId) {
    return TenantChannelAuthorizer::authorizeUser($user, $tenantId);
});

// Tenant-specific transaction feed
Broadcast::channel('tenant.{tenantId}.transactions', function ($user, string $tenantId) {
    return TenantChannelAuthorizer::authorizeUser($user, $tenantId);
});

// Tenant-specific compliance alerts (admin only)
Broadcast::channel('tenant.{tenantId}.compliance', function ($user, string $tenantId) {
    return TenantChannelAuthorizer::authorizeAdmin($user, $tenantId);
});

// Tenant-specific exchange/trading updates
Broadcast::channel('tenant.{tenantId}.exchange', function ($user, string $tenantId) {
    return TenantChannelAuthorizer::authorizeUser($user, $tenantId);
});

// Tenant-specific multi-sig wallet updates (v2.1.0)
Broadcast::channel('tenant.{tenantId}.wallet.multi-sig', function ($user, string $tenantId) {
    return TenantChannelAuthorizer::authorizeUser($user, $tenantId);
});

// Tenant-specific mobile device/session updates (v2.2.0)
Broadcast::channel('tenant.{tenantId}.mobile', function ($user, string $tenantId) {
    return TenantChannelAuthorizer::authorizeUser($user, $tenantId);
});
