<?php

declare(strict_types=1);

use App\Broadcasting\TenantChannelAuthorizer;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;

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

/*
|--------------------------------------------------------------------------
| Privacy Pool Channels (v2.6.0)
|--------------------------------------------------------------------------
|
| Network-specific channels for privacy pool Merkle tree updates.
| Any authenticated user can subscribe to receive Merkle root updates
| for a specific blockchain network (polygon, base, arbitrum).
|
*/

// Privacy pool Merkle tree updates - network-specific
Broadcast::channel('privacy.merkle.{network}', function ($user, string $network) {
    // Validate network is supported
    $supportedNetworks = config('privacy.merkle.networks', ['polygon', 'base', 'arbitrum']);

    return in_array($network, $supportedNetworks, true);
});

/*
|--------------------------------------------------------------------------
| Mobile Payment Channels (v2.7.0)
|--------------------------------------------------------------------------
|
| Real-time payment status updates for the mobile wallet app.
| Users can only subscribe to their own payment channel.
|
*/

// Payment status updates - user-specific
Broadcast::channel('payments.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});

/*
|--------------------------------------------------------------------------
| Mobile-Expected Channels (v5.8.0)
|--------------------------------------------------------------------------
|
| Channels that the mobile app subscribes to for real-time updates across
| privacy operations, commerce payments, and TrustCert status changes.
|
*/

// User-level general notifications (mobile)
Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});

// Privacy operation status updates - user-specific
Broadcast::channel('privacy.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});

// Privacy proof completion updates (gap fix - events broadcast but channel was never registered)
Broadcast::channel('privacy.proof.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});

// Commerce payment confirmations - merchant-specific
Broadcast::channel('commerce.{merchantId}', function ($user, string $merchantId) {
    // In test/local environments, allow all authenticated users
    if (app()->environment('local', 'testing')) {
        return true;
    }

    // In production, verify merchant association via cache
    return (bool) Cache::get("merchant:{$merchantId}:user:{$user->id}");
});

// TrustCert certificate status changes - user-specific
Broadcast::channel('trustcert.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});
