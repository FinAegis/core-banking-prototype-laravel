<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Services;

use App\Domain\Mobile\Exceptions\DeviceTakeoverAttemptException;
use App\Domain\Mobile\Models\MobileDevice;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing mobile device registration and lifecycle.
 */
class MobileDeviceService
{
    /**
     * Maximum number of devices per user.
     */
    private const MAX_DEVICES_PER_USER = 5;

    /**
     * Register a new mobile device for a user.
     *
     * @param array<string, mixed>|null $metadata
     */
    public function registerDevice(
        User $user,
        string $deviceId,
        string $platform,
        string $appVersion,
        ?string $pushToken = null,
        ?string $deviceName = null,
        ?string $deviceModel = null,
        ?string $osVersion = null,
        ?array $metadata = null
    ): MobileDevice {
        // Check if device already exists
        $existingDevice = MobileDevice::where('device_id', $deviceId)->first();

        if ($existingDevice) {
            // SECURITY FIX: Reject device takeover attempts - never allow changing user_id
            // This prevents attackers from taking over legitimate user devices
            if ($existingDevice->user_id !== $user->id) {
                Log::critical('Device takeover attempt blocked', [
                    'device_id'         => $deviceId,
                    'existing_user_id'  => $existingDevice->user_id,
                    'attempted_user_id' => $user->id,
                    'ip_address'        => request()->ip(),
                    'user_agent'        => request()->userAgent(),
                ]);

                throw new DeviceTakeoverAttemptException(
                    deviceId: $deviceId,
                    existingUserId: $existingDevice->user_id,
                    attemptedUserId: $user->id,
                );
            }

            // Device exists for same user - update it
            return $this->updateDevice($existingDevice, [
                'platform'       => $platform,
                'push_token'     => $pushToken,
                'device_name'    => $deviceName,
                'device_model'   => $deviceModel,
                'os_version'     => $osVersion,
                'app_version'    => $appVersion,
                'metadata'       => $metadata,
                'last_active_at' => now(),
            ]);
        }

        // Check device limit
        $deviceCount = MobileDevice::where('user_id', $user->id)->count();
        if ($deviceCount >= self::MAX_DEVICES_PER_USER) {
            // Remove the oldest inactive device
            $this->removeOldestInactiveDevice($user);
        }

        // Create new device
        $device = MobileDevice::create([
            'user_id'        => $user->id,
            'device_id'      => $deviceId,
            'platform'       => $platform,
            'push_token'     => $pushToken,
            'device_name'    => $deviceName,
            'device_model'   => $deviceModel,
            'os_version'     => $osVersion,
            'app_version'    => $appVersion,
            'metadata'       => $metadata,
            'last_active_at' => now(),
        ]);

        Log::info('Mobile device registered', [
            'user_id'   => $user->id,
            'device_id' => $deviceId,
            'platform'  => $platform,
        ]);

        return $device;
    }

    /**
     * Update a mobile device.
     *
     * @param array<string, mixed> $data
     */
    public function updateDevice(MobileDevice $device, array $data): MobileDevice
    {
        $device->update($data);
        $device->refresh();

        return $device;
    }

    /**
     * Update the push token for a device.
     */
    public function updatePushToken(MobileDevice $device, string $pushToken): MobileDevice
    {
        // Check if token is already used by another device
        $existingDevice = MobileDevice::where('push_token', $pushToken)
            ->where('id', '!=', $device->id)
            ->first();

        if ($existingDevice) {
            // Clear the token from the other device
            $existingDevice->update(['push_token' => null]);
        }

        $device->update(['push_token' => $pushToken]);
        $device->refresh();

        return $device;
    }

    /**
     * Unregister a mobile device.
     */
    public function unregisterDevice(MobileDevice $device): void
    {
        Log::info('Mobile device unregistered', [
            'user_id'   => $device->user_id,
            'device_id' => $device->device_id,
        ]);

        $device->delete();
    }

    /**
     * Get all devices for a user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MobileDevice>
     */
    public function getUserDevices(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return MobileDevice::where('user_id', $user->id)
            ->orderBy('last_active_at', 'desc')
            ->get();
    }

    /**
     * Get active devices for a user (not blocked).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MobileDevice>
     */
    public function getActiveDevices(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return MobileDevice::where('user_id', $user->id)
            ->active()
            ->orderBy('last_active_at', 'desc')
            ->get();
    }

    /**
     * Get devices with push notification capability.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MobileDevice>
     */
    public function getPushEnabledDevices(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return MobileDevice::where('user_id', $user->id)
            ->active()
            ->withPushToken()
            ->get();
    }

    /**
     * Find device by device ID.
     */
    public function findByDeviceId(string $deviceId): ?MobileDevice
    {
        return MobileDevice::where('device_id', $deviceId)->first();
    }

    /**
     * Find device by ID and user.
     */
    public function findByIdForUser(string $id, User $user): ?MobileDevice
    {
        return MobileDevice::where('id', $id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Block a device.
     */
    public function blockDevice(MobileDevice $device, string $reason): void
    {
        $device->block($reason);

        Log::warning('Mobile device blocked', [
            'user_id'   => $device->user_id,
            'device_id' => $device->device_id,
            'reason'    => $reason,
        ]);
    }

    /**
     * Unblock a device.
     */
    public function unblockDevice(MobileDevice $device): void
    {
        $device->unblock();

        Log::info('Mobile device unblocked', [
            'user_id'   => $device->user_id,
            'device_id' => $device->device_id,
        ]);
    }

    /**
     * Trust a device (bypass some security checks).
     */
    public function trustDevice(MobileDevice $device, ?string $trustedBy = null): void
    {
        $device->trust($trustedBy);

        Log::info('Mobile device trusted', [
            'user_id'    => $device->user_id,
            'device_id'  => $device->device_id,
            'trusted_by' => $trustedBy,
        ]);
    }

    /**
     * Record device activity.
     */
    public function recordActivity(MobileDevice $device): void
    {
        $device->recordActivity();
    }

    /**
     * Remove the oldest inactive device for a user.
     */
    private function removeOldestInactiveDevice(User $user): void
    {
        $oldestDevice = MobileDevice::where('user_id', $user->id)
            ->orderBy('last_active_at', 'asc')
            ->first();

        if ($oldestDevice) {
            Log::info('Removing oldest inactive device to make room', [
                'user_id'   => $user->id,
                'device_id' => $oldestDevice->device_id,
            ]);
            $oldestDevice->delete();
        }
    }

    /**
     * Clean up stale devices (not active for X days).
     */
    public function cleanupStaleDevices(int $daysInactive = 90): int
    {
        $threshold = now()->subDays($daysInactive);

        $count = MobileDevice::where('last_active_at', '<', $threshold)
            ->orWhereNull('last_active_at')
            ->delete();

        Log::info('Cleaned up stale mobile devices', [
            'count'          => $count,
            'threshold_days' => $daysInactive,
        ]);

        return $count;
    }

    /**
     * Get device statistics for monitoring.
     *
     * @return array<string, int>
     */
    public function getStatistics(): array
    {
        return [
            'total_devices'     => MobileDevice::count(),
            'active_devices'    => MobileDevice::active()->count(),
            'blocked_devices'   => MobileDevice::where('is_blocked', true)->count(),
            'ios_devices'       => MobileDevice::forPlatform('ios')->count(),
            'android_devices'   => MobileDevice::forPlatform('android')->count(),
            'biometric_enabled' => MobileDevice::biometricEnabled()->count(),
            'with_push_token'   => MobileDevice::withPushToken()->count(),
        ];
    }
}
