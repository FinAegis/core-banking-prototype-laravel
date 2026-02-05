<?php

declare(strict_types=1);

use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobileDeviceSession;
use App\Domain\Mobile\Services\MobileSessionService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Cache::flush();

    $this->service = new MobileSessionService();
    $this->user = User::factory()->create();
    $this->device = MobileDevice::factory()->create([
        'user_id' => $this->user->id,
    ]);
});

/**
 * Helper to create an active session for the test device.
 */
function createActiveSession(
    MobileDevice $device,
    User $user,
    bool $isBiometric = false,
    int $expiresInMinutes = 60,
    ?string $ipAddress = null,
): MobileDeviceSession {
    return MobileDeviceSession::create([
        'mobile_device_id'     => $device->id,
        'user_id'              => $user->id,
        'session_token'        => MobileDeviceSession::generateToken(),
        'ip_address'           => $ipAddress,
        'expires_at'           => now()->addMinutes($expiresInMinutes),
        'is_biometric_session' => $isBiometric,
        'last_activity_at'     => now(),
    ]);
}

/**
 * Helper to create an expired session for the test device.
 */
function createExpiredSession(
    MobileDevice $device,
    User $user,
    bool $isBiometric = false,
): MobileDeviceSession {
    return MobileDeviceSession::create([
        'mobile_device_id'     => $device->id,
        'user_id'              => $user->id,
        'session_token'        => MobileDeviceSession::generateToken(),
        'expires_at'           => now()->subMinutes(5),
        'is_biometric_session' => $isBiometric,
        'last_activity_at'     => now()->subMinutes(65),
    ]);
}

describe('MobileSessionService', function (): void {
    describe('getUserSessions', function (): void {
        it('returns active sessions for a user', function (): void {
            $session1 = createActiveSession($this->device, $this->user);
            $session2 = createActiveSession($this->device, $this->user, true);

            $sessions = $this->service->getUserSessions($this->user);

            expect($sessions)->toHaveCount(2);
            expect($sessions->pluck('id')->toArray())->toContain($session1->id, $session2->id);
        });

        it('excludes expired sessions', function (): void {
            createActiveSession($this->device, $this->user);
            createExpiredSession($this->device, $this->user);

            $sessions = $this->service->getUserSessions($this->user);

            expect($sessions)->toHaveCount(1);
        });

        it('returns empty collection when user has no sessions', function (): void {
            $sessions = $this->service->getUserSessions($this->user);

            expect($sessions)->toBeEmpty();
        });

        it('does not return sessions belonging to other users', function (): void {
            $otherUser = User::factory()->create();
            $otherDevice = MobileDevice::factory()->create(['user_id' => $otherUser->id]);

            createActiveSession($this->device, $this->user);
            createActiveSession($otherDevice, $otherUser);

            $sessions = $this->service->getUserSessions($this->user);

            expect($sessions)->toHaveCount(1);
            expect($sessions->first()->user_id)->toBe($this->user->id);
        });

        it('orders sessions by last activity descending', function (): void {
            Carbon::setTestNow(now());

            $olderSession = createActiveSession($this->device, $this->user);
            $olderSession->update(['last_activity_at' => now()->subMinutes(30)]);

            Carbon::setTestNow(now()->addSecond());

            $newerSession = createActiveSession($this->device, $this->user);
            $newerSession->update(['last_activity_at' => now()]);

            $sessions = $this->service->getUserSessions($this->user);

            expect($sessions->first()->id)->toBe($newerSession->id);
            expect($sessions->last()->id)->toBe($olderSession->id);

            Carbon::setTestNow();
        });
    });

    describe('getUserSessionsPaginated', function (): void {
        it('returns paginated active sessions', function (): void {
            for ($i = 0; $i < 15; $i++) {
                createActiveSession($this->device, $this->user);
            }

            $paginated = $this->service->getUserSessionsPaginated($this->user, 10);

            expect($paginated->count())->toBe(10);
            expect($paginated->total())->toBe(15);
            expect($paginated->lastPage())->toBe(2);
        });
    });

    describe('findSessionForUser', function (): void {
        it('finds a session by ID for the correct user', function (): void {
            $session = createActiveSession($this->device, $this->user);

            $found = $this->service->findSessionForUser($session->id, $this->user);

            expect($found)->not->toBeNull();
            expect($found->id)->toBe($session->id);
        });

        it('returns null when session belongs to another user', function (): void {
            $otherUser = User::factory()->create();
            $otherDevice = MobileDevice::factory()->create(['user_id' => $otherUser->id]);
            $session = createActiveSession($otherDevice, $otherUser);

            $found = $this->service->findSessionForUser($session->id, $this->user);

            expect($found)->toBeNull();
        });

        it('returns null for non-existent session ID', function (): void {
            $found = $this->service->findSessionForUser('nonexistent-id', $this->user);

            expect($found)->toBeNull();
        });
    });

    describe('revokeSession', function (): void {
        it('revokes a specific session', function (): void {
            $session = createActiveSession($this->device, $this->user);

            $result = $this->service->revokeSession($session);

            expect($result)->toBeTrue();
            expect(MobileDeviceSession::find($session->id))->toBeNull();
        });

        it('does not affect other sessions when revoking one', function (): void {
            $session1 = createActiveSession($this->device, $this->user);
            $session2 = createActiveSession($this->device, $this->user);

            $this->service->revokeSession($session1);

            expect(MobileDeviceSession::find($session1->id))->toBeNull();
            expect(MobileDeviceSession::find($session2->id))->not->toBeNull();
        });
    });

    describe('revokeAllUserSessions', function (): void {
        it('revokes all sessions for a user', function (): void {
            createActiveSession($this->device, $this->user);
            createActiveSession($this->device, $this->user, true);

            $count = $this->service->revokeAllUserSessions($this->user);

            expect($count)->toBe(2);
            expect(MobileDeviceSession::where('user_id', $this->user->id)->count())->toBe(0);
        });

        it('excludes the specified session when revoking all', function (): void {
            $keepSession = createActiveSession($this->device, $this->user);
            createActiveSession($this->device, $this->user, true);
            createActiveSession($this->device, $this->user);

            $count = $this->service->revokeAllUserSessions($this->user, $keepSession->id);

            expect($count)->toBe(2);
            expect(MobileDeviceSession::find($keepSession->id))->not->toBeNull();
        });

        it('does not count expired sessions', function (): void {
            createActiveSession($this->device, $this->user);
            createExpiredSession($this->device, $this->user);

            $count = $this->service->revokeAllUserSessions($this->user);

            // Only the active session should be counted and revoked
            expect($count)->toBe(1);
        });

        it('does not affect other users sessions', function (): void {
            $otherUser = User::factory()->create();
            $otherDevice = MobileDevice::factory()->create(['user_id' => $otherUser->id]);

            createActiveSession($this->device, $this->user);
            $otherSession = createActiveSession($otherDevice, $otherUser);

            $this->service->revokeAllUserSessions($this->user);

            expect(MobileDeviceSession::find($otherSession->id))->not->toBeNull();
        });

        it('returns zero when user has no active sessions', function (): void {
            $count = $this->service->revokeAllUserSessions($this->user);

            expect($count)->toBe(0);
        });
    });

    describe('revokeDeviceSessions', function (): void {
        it('revokes all sessions for a specific device', function (): void {
            createActiveSession($this->device, $this->user);
            createActiveSession($this->device, $this->user, true);

            $count = $this->service->revokeDeviceSessions($this->device);

            expect($count)->toBe(2);
            expect(MobileDeviceSession::where('mobile_device_id', $this->device->id)->count())->toBe(0);
        });

        it('does not revoke sessions for other devices', function (): void {
            $otherDevice = MobileDevice::factory()->create(['user_id' => $this->user->id]);

            createActiveSession($this->device, $this->user);
            $otherSession = createActiveSession($otherDevice, $this->user);

            $this->service->revokeDeviceSessions($this->device);

            expect(MobileDeviceSession::find($otherSession->id))->not->toBeNull();
        });
    });

    describe('session expiration checking', function (): void {
        it('correctly identifies valid sessions', function (): void {
            $session = createActiveSession($this->device, $this->user);

            expect($session->isValid())->toBeTrue();
            expect($session->isExpired())->toBeFalse();
        });

        it('correctly identifies expired sessions', function (): void {
            $session = createExpiredSession($this->device, $this->user);

            expect($session->isValid())->toBeFalse();
            expect($session->isExpired())->toBeTrue();
        });

        it('detects session expiration after time passes', function (): void {
            Carbon::setTestNow(now());

            $session = createActiveSession($this->device, $this->user, false, 30);

            expect($session->isValid())->toBeTrue();

            Carbon::setTestNow(now()->addMinutes(31));

            expect($session->isExpired())->toBeTrue();
            expect($session->isValid())->toBeFalse();

            Carbon::setTestNow();
        });
    });

    describe('extendSession', function (): void {
        it('extends a session expiration', function (): void {
            Carbon::setTestNow(now());

            $session = createActiveSession($this->device, $this->user, false, 30);

            $extended = $this->service->extendSession($session, 120);

            $expectedExpiry = now()->addMinutes(120);
            expect($extended->expires_at->diffInMinutes($expectedExpiry))->toBeLessThanOrEqual(1);
            expect($extended->last_activity_at->diffInSeconds(now()))->toBeLessThanOrEqual(1);

            Carbon::setTestNow();
        });

        it('uses default 60 minutes when no duration specified', function (): void {
            Carbon::setTestNow(now());

            $session = createActiveSession($this->device, $this->user, false, 10);

            $extended = $this->service->extendSession($session);

            $expectedExpiry = now()->addMinutes(60);
            expect($extended->expires_at->diffInMinutes($expectedExpiry))->toBeLessThanOrEqual(1);

            Carbon::setTestNow();
        });
    });

    describe('getSessionStats', function (): void {
        it('returns correct session statistics for a user', function (): void {
            // Create devices
            $biometricDevice = MobileDevice::factory()->create([
                'user_id'           => $this->user->id,
                'biometric_enabled' => true,
            ]);

            // Active sessions
            createActiveSession($this->device, $this->user);
            createActiveSession($biometricDevice, $this->user, true);

            // Expired session (should not count)
            createExpiredSession($this->device, $this->user);

            $stats = $this->service->getSessionStats($this->user);

            expect($stats['active_sessions'])->toBe(2);
            expect($stats['registered_devices'])->toBe(2);
            expect($stats['biometric_devices'])->toBe(1);
        });

        it('excludes blocked devices from device counts', function (): void {
            $blockedDevice = MobileDevice::factory()->blocked()->create([
                'user_id' => $this->user->id,
            ]);

            $stats = $this->service->getSessionStats($this->user);

            // Only the non-blocked device should be counted
            expect($stats['registered_devices'])->toBe(1);
        });

        it('returns zero counts when user has no data', function (): void {
            $newUser = User::factory()->create();

            $stats = $this->service->getSessionStats($newUser);

            expect($stats['active_sessions'])->toBe(0);
            expect($stats['registered_devices'])->toBe(0);
            expect($stats['biometric_devices'])->toBe(0);
        });
    });

    describe('cleanupExpiredSessions', function (): void {
        it('deletes expired sessions', function (): void {
            createExpiredSession($this->device, $this->user);
            createExpiredSession($this->device, $this->user, true);

            $count = $this->service->cleanupExpiredSessions();

            expect($count)->toBe(2);
            expect(MobileDeviceSession::where('mobile_device_id', $this->device->id)->count())->toBe(0);
        });

        it('preserves active sessions during cleanup', function (): void {
            $activeSession = createActiveSession($this->device, $this->user);
            createExpiredSession($this->device, $this->user);

            $count = $this->service->cleanupExpiredSessions();

            expect($count)->toBe(1);
            expect(MobileDeviceSession::find($activeSession->id))->not->toBeNull();
        });

        it('returns zero when no expired sessions exist', function (): void {
            createActiveSession($this->device, $this->user);

            $count = $this->service->cleanupExpiredSessions();

            expect($count)->toBe(0);
        });
    });
});
