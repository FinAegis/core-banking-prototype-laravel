<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Services;

use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobilePushNotification;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending push notifications to mobile devices.
 *
 * Supports Firebase Cloud Messaging (FCM) for Android and iOS.
 * APNS direct integration can be added if needed.
 */
class PushNotificationService
{
    /**
     * FCM API endpoint.
     */
    private const FCM_ENDPOINT = 'https://fcm.googleapis.com/fcm/send';

    /**
     * Send a push notification to a user on all their devices.
     *
     * @param array<string, mixed> $data
     * @return array<string, array<string, mixed>>
     */
    public function sendToUser(
        User $user,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $scheduledAt = null
    ): array {
        $devices = MobileDevice::where('user_id', $user->id)
            ->active()
            ->withPushToken()
            ->get();

        $results = [];

        foreach ($devices as $device) {
            $notification = $this->createNotification(
                $user,
                $device,
                $type,
                $title,
                $body,
                $data,
                $scheduledAt
            );

            if (! $scheduledAt) {
                $results[$device->id] = $this->sendNotification($notification);
            } else {
                $results[$device->id] = ['status' => 'scheduled', 'notification_id' => $notification->id];
            }
        }

        return $results;
    }

    /**
     * Send a push notification to a specific device.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sendToDevice(
        MobileDevice $device,
        string $type,
        string $title,
        string $body,
        array $data = []
    ): array {
        if (! $device->canReceivePush()) {
            return [
                'status'  => 'error',
                'message' => 'Device cannot receive push notifications',
            ];
        }

        $user = $device->user;
        if (! $user) {
            return [
                'status'  => 'error',
                'message' => 'Device has no associated user',
            ];
        }

        $notification = $this->createNotification(
            $user,
            $device,
            $type,
            $title,
            $body,
            $data
        );

        return $this->sendNotification($notification);
    }

    /**
     * Create a notification record.
     *
     * @param array<string, mixed> $data
     */
    private function createNotification(
        User $user,
        MobileDevice $device,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $scheduledAt = null
    ): MobilePushNotification {
        return MobilePushNotification::create([
            'user_id'           => $user->id,
            'mobile_device_id'  => $device->id,
            'notification_type' => $type,
            'title'             => $title,
            'body'              => $body,
            'data'              => $data,
            'status'            => MobilePushNotification::STATUS_PENDING,
            'scheduled_at'      => $scheduledAt ? \Carbon\Carbon::parse($scheduledAt) : null,
        ]);
    }

    /**
     * Send a notification via FCM.
     *
     * @return array<string, mixed>
     */
    public function sendNotification(MobilePushNotification $notification): array
    {
        $device = $notification->mobileDevice;

        if (! $device || ! $device->push_token) {
            $notification->markAsFailed('Device or push token not found');

            return [
                'status'  => 'error',
                'message' => 'Device or push token not found',
            ];
        }

        // Check if FCM is configured
        $serverKey = config('services.firebase.server_key');
        $projectId = config('services.firebase.project_id');

        if (! $serverKey && ! $projectId) {
            // Log but don't fail - useful for development
            Log::warning('FCM not configured, notification not sent', [
                'notification_id' => $notification->id,
            ]);

            // Mark as sent for development purposes
            $notification->markAsSent('dev_' . uniqid());

            return [
                'status'          => 'skipped',
                'message'         => 'FCM not configured',
                'notification_id' => $notification->id,
            ];
        }

        try {
            $response = $this->sendViaFcm($device, $notification);

            if ($response['success']) {
                $notification->markAsSent($response['message_id'] ?? uniqid());

                return [
                    'status'          => 'sent',
                    'message_id'      => $response['message_id'] ?? null,
                    'notification_id' => $notification->id,
                ];
            } else {
                $notification->markAsFailed($response['error'] ?? 'Unknown error');

                // Handle invalid token
                if ($this->isInvalidTokenError($response['error'] ?? '')) {
                    $device->update(['push_token' => null]);
                    Log::info('Cleared invalid push token', ['device_id' => $device->id]);
                }

                return [
                    'status'          => 'failed',
                    'error'           => $response['error'] ?? 'Unknown error',
                    'notification_id' => $notification->id,
                ];
            }
        } catch (Exception $e) {
            Log::error('Push notification error', [
                'notification_id' => $notification->id,
                'error'           => $e->getMessage(),
            ]);

            $notification->markAsFailed($e->getMessage());

            return [
                'status'          => 'error',
                'message'         => $e->getMessage(),
                'notification_id' => $notification->id,
            ];
        }
    }

    /**
     * Send notification via FCM legacy HTTP API.
     *
     * @return array<string, mixed>
     */
    private function sendViaFcm(MobileDevice $device, MobilePushNotification $notification): array
    {
        $serverKey = config('services.firebase.server_key');

        $payload = [
            'to'           => $device->push_token,
            'notification' => [
                'title' => $notification->title,
                'body'  => $notification->body,
                'sound' => 'default',
                'badge' => 1,
            ],
            'data' => array_merge($notification->data ?? [], [
                'notification_id'   => $notification->id,
                'notification_type' => $notification->notification_type,
                'click_action'      => 'FLUTTER_NOTIFICATION_CLICK',
            ]),
            'priority' => 'high',
        ];

        // Platform-specific options
        if ($device->platform === 'android') {
            $payload['android'] = [
                'priority'     => 'high',
                'notification' => [
                    'channel_id' => 'finaegis_default',
                ],
            ];
        } elseif ($device->platform === 'ios') {
            $payload['apns'] = [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type'  => 'application/json',
        ])->post(self::FCM_ENDPOINT, $payload);

        if ($response->successful()) {
            $body = $response->json();
            if (isset($body['success']) && $body['success'] > 0) {
                return [
                    'success'    => true,
                    'message_id' => $body['results'][0]['message_id'] ?? null,
                ];
            } else {
                $error = $body['results'][0]['error'] ?? 'Unknown FCM error';

                return [
                    'success' => false,
                    'error'   => $error,
                ];
            }
        }

        return [
            'success' => false,
            'error'   => 'FCM request failed: ' . $response->status(),
        ];
    }

    /**
     * Check if error indicates invalid token.
     */
    private function isInvalidTokenError(string $error): bool
    {
        $invalidTokenErrors = [
            'NotRegistered',
            'InvalidRegistration',
            'MissingRegistration',
            'InvalidApnsCredential',
        ];

        return in_array($error, $invalidTokenErrors);
    }

    /**
     * Process pending scheduled notifications.
     */
    public function processScheduledNotifications(): int
    {
        $notifications = MobilePushNotification::pending()
            ->where('scheduled_at', '<=', now())
            ->limit(100)
            ->get();

        $sent = 0;
        foreach ($notifications as $notification) {
            $result = $this->sendNotification($notification);
            if ($result['status'] === 'sent') {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Retry failed notifications.
     */
    public function retryFailedNotifications(int $batchSize = 50): int
    {
        $notifications = MobilePushNotification::retryable()
            ->limit($batchSize)
            ->get();

        $sent = 0;
        foreach ($notifications as $notification) {
            $notification->resetForRetry();
            $result = $this->sendNotification($notification);
            if ($result['status'] === 'sent') {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Get unread notification count for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return MobilePushNotification::where('user_id', $user->id)
            ->unread()
            ->count();
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(MobilePushNotification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user): int
    {
        return MobilePushNotification::where('user_id', $user->id)
            ->unread()
            ->update([
                'status'  => MobilePushNotification::STATUS_READ,
                'read_at' => now(),
            ]);
    }

    /**
     * Get notification history for a user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, MobilePushNotification>
     */
    public function getNotificationHistory(User $user, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return MobilePushNotification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old notifications.
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        $threshold = now()->subDays($daysOld);

        return MobilePushNotification::where('created_at', '<', $threshold)
            ->delete();
    }

    /**
     * Send transaction received notification.
     *
     * @return array<string, array<string, mixed>>
     */
    public function sendTransactionReceived(
        User $user,
        string $amount,
        string $currency,
        string $senderName
    ): array {
        return $this->sendToUser(
            $user,
            MobilePushNotification::TYPE_TRANSACTION_RECEIVED,
            'Payment Received',
            "You received {$amount} {$currency} from {$senderName}",
            [
                'amount'   => $amount,
                'currency' => $currency,
                'sender'   => $senderName,
            ]
        );
    }

    /**
     * Send transaction sent notification.
     *
     * @return array<string, array<string, mixed>>
     */
    public function sendTransactionSent(
        User $user,
        string $amount,
        string $currency,
        string $recipientName
    ): array {
        return $this->sendToUser(
            $user,
            MobilePushNotification::TYPE_TRANSACTION_SENT,
            'Payment Sent',
            "You sent {$amount} {$currency} to {$recipientName}",
            [
                'amount'    => $amount,
                'currency'  => $currency,
                'recipient' => $recipientName,
            ]
        );
    }

    /**
     * Send security login notification.
     *
     * @return array<string, array<string, mixed>>
     */
    public function sendSecurityLoginNotification(User $user, string $deviceName, string $location): array
    {
        return $this->sendToUser(
            $user,
            MobilePushNotification::TYPE_SECURITY_LOGIN,
            'New Login Detected',
            "New login from {$deviceName} in {$location}",
            [
                'device_name' => $deviceName,
                'location'    => $location,
            ]
        );
    }

    /**
     * Send low balance notification.
     *
     * @return array<string, array<string, mixed>>
     */
    public function sendLowBalanceNotification(User $user, string $currency, string $balance, string $threshold): array
    {
        return $this->sendToUser(
            $user,
            MobilePushNotification::TYPE_BALANCE_LOW,
            'Low Balance Alert',
            "Your {$currency} balance ({$balance}) is below {$threshold}",
            [
                'currency'  => $currency,
                'balance'   => $balance,
                'threshold' => $threshold,
            ]
        );
    }
}
