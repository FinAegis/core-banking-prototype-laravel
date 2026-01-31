<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Services;

use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobileDeviceSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing mobile device sessions.
 */
class MobileSessionService
{
    /**
     * Get active sessions for a user.
     *
     * @return Collection<int, MobileDeviceSession>
     */
    public function getUserSessions(User $user): Collection
    {
        return MobileDeviceSession::where('user_id', $user->id)
            ->active()
            ->with('mobileDevice')
            ->orderBy('last_activity_at', 'desc')
            ->get();
    }

    /**
     * Get paginated active sessions for a user.
     *
     * @return LengthAwarePaginator<int, MobileDeviceSession>
     */
    public function getUserSessionsPaginated(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return MobileDeviceSession::where('user_id', $user->id)
            ->active()
            ->with('mobileDevice')
            ->orderBy('last_activity_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get a session by ID for a user.
     */
    public function findSessionForUser(string $sessionId, User $user): ?MobileDeviceSession
    {
        return MobileDeviceSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Revoke a specific session.
     */
    public function revokeSession(MobileDeviceSession $session): bool
    {
        $session->invalidate();

        Log::info('Mobile session revoked', [
            'session_id' => $session->id,
            'user_id'    => $session->user_id,
            'device_id'  => $session->mobile_device_id,
        ]);

        return true;
    }

    /**
     * Revoke all sessions for a user, optionally excluding the current session.
     */
    public function revokeAllUserSessions(User $user, ?string $exceptSessionId = null): int
    {
        $query = MobileDeviceSession::where('user_id', $user->id)
            ->active();

        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $sessions = $query->get();
        $count = $sessions->count();

        foreach ($sessions as $session) {
            $session->invalidate();
        }

        Log::info('All mobile sessions revoked', [
            'user_id'           => $user->id,
            'count'             => $count,
            'except_session_id' => $exceptSessionId,
        ]);

        return $count;
    }

    /**
     * Revoke all sessions for a specific device.
     */
    public function revokeDeviceSessions(MobileDevice $device): int
    {
        $sessions = MobileDeviceSession::where('mobile_device_id', $device->id)
            ->active()
            ->get();

        $count = $sessions->count();

        foreach ($sessions as $session) {
            $session->invalidate();
        }

        Log::info('Device sessions revoked', [
            'device_id' => $device->id,
            'user_id'   => $device->user_id,
            'count'     => $count,
        ]);

        return $count;
    }

    /**
     * Extend a session's expiration time.
     */
    public function extendSession(MobileDeviceSession $session, int $minutes = 60): MobileDeviceSession
    {
        $session->extend($minutes);

        return $session;
    }

    /**
     * Get session statistics for a user.
     *
     * @return array<string, mixed>
     */
    public function getSessionStats(User $user): array
    {
        $activeSessions = MobileDeviceSession::where('user_id', $user->id)
            ->active()
            ->count();

        $devices = MobileDevice::where('user_id', $user->id)
            ->where('is_blocked', false)
            ->count();

        $biometricDevices = MobileDevice::where('user_id', $user->id)
            ->where('is_blocked', false)
            ->where('biometric_enabled', true)
            ->count();

        return [
            'active_sessions'    => $activeSessions,
            'registered_devices' => $devices,
            'biometric_devices'  => $biometricDevices,
        ];
    }

    /**
     * Cleanup expired sessions.
     */
    public function cleanupExpiredSessions(): int
    {
        $count = MobileDeviceSession::expired()->delete();

        Log::info('Expired mobile sessions cleaned up', [
            'count' => $count,
        ]);

        return $count;
    }
}
