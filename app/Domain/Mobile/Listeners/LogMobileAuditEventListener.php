<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Listeners;

use App\Domain\Mobile\Events\BiometricAuthFailed;
use App\Domain\Mobile\Events\BiometricAuthSucceeded;
use App\Domain\Mobile\Events\BiometricDeviceBlocked;
use App\Domain\Mobile\Events\MobileDeviceBlocked;
use App\Domain\Mobile\Events\MobileDeviceRegistered;
use App\Domain\Mobile\Events\MobileSessionCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Logs mobile security events for audit and compliance purposes.
 */
class LogMobileAuditEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue connection for this listener.
     */
    public string $connection = 'redis';

    /**
     * The queue name for this listener.
     */
    public string $queue = 'mobile';

    /**
     * Handle MobileDeviceRegistered event.
     */
    public function handleDeviceRegistered(MobileDeviceRegistered $event): void
    {
        Log::channel('audit')->info('Mobile device registered', [
            'event_type' => 'device.registered',
            'tenant_id'  => $event->tenantId,
            'user_id'    => $event->userId,
            'device_id'  => $event->deviceId,
            'platform'   => $event->platform,
            'metadata'   => $event->metadata,
        ]);
    }

    /**
     * Handle MobileDeviceBlocked event.
     */
    public function handleDeviceBlocked(MobileDeviceBlocked $event): void
    {
        Log::channel('audit')->warning('Mobile device blocked', [
            'event_type' => 'device.blocked',
            'tenant_id'  => $event->tenantId,
            'user_id'    => $event->userId,
            'device_id'  => $event->deviceId,
            'reason'     => $event->reason,
            'blocked_by' => $event->blockedBy,
            'blocked_at' => $event->blockedAt->toIso8601String(),
            'metadata'   => $event->metadata,
        ]);
    }

    /**
     * Handle MobileSessionCreated event.
     */
    public function handleSessionCreated(MobileSessionCreated $event): void
    {
        Log::channel('audit')->info('Mobile session created', [
            'event_type' => 'session.created',
            'tenant_id'  => $event->tenantId,
            'user_id'    => $event->userId,
            'device_id'  => $event->deviceId,
            'session_id' => $event->sessionId,
            'ip_address' => $event->ipAddress,
            'expires_at' => $event->expiresAt->toIso8601String(),
            'created_at' => $event->createdAt->toIso8601String(),
            'metadata'   => $event->metadata,
        ]);
    }

    /**
     * Handle BiometricAuthSucceeded event.
     */
    public function handleBiometricSucceeded(BiometricAuthSucceeded $event): void
    {
        Log::channel('audit')->info('Biometric authentication succeeded', [
            'event_type'       => 'biometric.succeeded',
            'tenant_id'        => $event->tenantId,
            'user_id'          => $event->userId,
            'device_id'        => $event->deviceId,
            'ip_address'       => $event->ipAddress,
            'authenticated_at' => $event->authenticatedAt->toIso8601String(),
            'metadata'         => $event->metadata,
        ]);
    }

    /**
     * Handle BiometricAuthFailed event.
     */
    public function handleBiometricFailed(BiometricAuthFailed $event): void
    {
        Log::channel('audit')->warning('Biometric authentication failed', [
            'event_type'     => 'biometric.failed',
            'tenant_id'      => $event->tenantId,
            'user_id'        => $event->userId,
            'device_id'      => $event->deviceId,
            'failure_reason' => $event->failureReason,
            'failure_count'  => $event->failureCount,
            'ip_address'     => $event->ipAddress,
            'failed_at'      => $event->failedAt->toIso8601String(),
            'metadata'       => $event->metadata,
        ]);
    }

    /**
     * Handle BiometricDeviceBlocked event.
     */
    public function handleBiometricBlocked(BiometricDeviceBlocked $event): void
    {
        Log::channel('audit')->warning('Device biometric authentication blocked', [
            'event_type'    => 'biometric.blocked',
            'tenant_id'     => $event->tenantId,
            'user_id'       => $event->userId,
            'device_id'     => $event->deviceId,
            'failure_count' => $event->failureCount,
            'blocked_at'    => $event->blockedAt->toIso8601String(),
            'blocked_until' => $event->blockedUntil->toIso8601String(),
            'metadata'      => $event->metadata,
        ]);
    }

    /**
     * Register the listener mappings for the events.
     *
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            MobileDeviceRegistered::class => 'handleDeviceRegistered',
            MobileDeviceBlocked::class    => 'handleDeviceBlocked',
            MobileSessionCreated::class   => 'handleSessionCreated',
            BiometricAuthSucceeded::class => 'handleBiometricSucceeded',
            BiometricAuthFailed::class    => 'handleBiometricFailed',
            BiometricDeviceBlocked::class => 'handleBiometricBlocked',
        ];
    }
}
