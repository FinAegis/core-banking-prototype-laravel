<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Listeners;

use App\Domain\Mobile\Events\BiometricAuthFailed;
use App\Domain\Mobile\Events\BiometricDeviceBlocked;
use App\Domain\Mobile\Events\MobileDeviceBlocked;
use App\Domain\Mobile\Models\MobileNotificationPreference;
use App\Domain\Mobile\Services\NotificationPreferenceService;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Listens to security events and sends push notification alerts.
 */
class SendSecurityAlertListener implements ShouldQueue
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
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        private readonly PushNotificationService $pushService,
        private readonly NotificationPreferenceService $preferenceService,
    ) {
    }

    /**
     * Handle MobileDeviceBlocked event.
     */
    public function handleDeviceBlocked(MobileDeviceBlocked $event): void
    {
        try {
            $user = User::find($event->userId);

            if ($user === null) {
                return;
            }

            // Check if security notifications are enabled
            if (! $this->preferenceService->isPushEnabled($user, MobileNotificationPreference::TYPE_SECURITY_DEVICE)) {
                return;
            }

            $this->pushService->sendToUser(
                $user,
                'security_alert',
                'Device Blocked',
                'One of your devices has been blocked for security reasons: ' . $event->reason,
                [
                    'device_id'  => $event->deviceId,
                    'reason'     => $event->reason,
                    'blocked_at' => $event->blockedAt->toIso8601String(),
                ],
                'high'
            );

            Log::info('Device blocked security alert sent', [
                'user_id'   => $event->userId,
                'device_id' => $event->deviceId,
                'reason'    => $event->reason,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to send device blocked notification', [
                'user_id'   => $event->userId,
                'device_id' => $event->deviceId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle BiometricDeviceBlocked event.
     */
    public function handleBiometricBlocked(BiometricDeviceBlocked $event): void
    {
        try {
            $user = User::find($event->userId);

            if ($user === null) {
                return;
            }

            // Check if security notifications are enabled
            if (! $this->preferenceService->isPushEnabled($user, MobileNotificationPreference::TYPE_SECURITY_DEVICE)) {
                return;
            }

            $this->pushService->sendToUser(
                $user,
                'security_alert',
                'Biometric Authentication Blocked',
                'Biometric authentication has been temporarily blocked due to multiple failed attempts.',
                [
                    'device_id'     => $event->deviceId,
                    'blocked_until' => $event->blockedUntil->toIso8601String(),
                    'blocked_at'    => $event->blockedAt->toIso8601String(),
                ],
                'high'
            );

            Log::info('Biometric blocked security alert sent', [
                'user_id'   => $event->userId,
                'device_id' => $event->deviceId,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to send biometric blocked notification', [
                'user_id'   => $event->userId,
                'device_id' => $event->deviceId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle BiometricAuthFailed event.
     *
     * Only sends notification after multiple failures.
     */
    public function handleBiometricFailed(BiometricAuthFailed $event): void
    {
        try {
            // Only alert on third or more failure
            if ($event->failureCount < 3) {
                return;
            }

            $user = User::find($event->userId);

            if ($user === null) {
                return;
            }

            // Check if security notifications are enabled
            if (! $this->preferenceService->isPushEnabled($user, MobileNotificationPreference::TYPE_SECURITY_DEVICE)) {
                return;
            }

            $this->pushService->sendToUser(
                $user,
                'security_alert',
                'Failed Biometric Attempts',
                'Multiple failed biometric authentication attempts detected on your device.',
                [
                    'device_id'     => $event->deviceId,
                    'failure_count' => $event->failureCount,
                    'failed_at'     => $event->failedAt->toIso8601String(),
                ],
                'high'
            );

            Log::info('Biometric failed security alert sent', [
                'user_id'       => $event->userId,
                'device_id'     => $event->deviceId,
                'failure_count' => $event->failureCount,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to send biometric failed notification', [
                'user_id'   => $event->userId,
                'device_id' => $event->deviceId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register the listener mappings for the events.
     *
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            MobileDeviceBlocked::class    => 'handleDeviceBlocked',
            BiometricDeviceBlocked::class => 'handleBiometricBlocked',
            BiometricAuthFailed::class    => 'handleBiometricFailed',
        ];
    }
}
