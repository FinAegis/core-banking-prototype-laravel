<?php

declare(strict_types=1);

namespace App\Domain\Mobile\Services;

use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobileNotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing mobile notification preferences.
 */
class NotificationPreferenceService
{
    /**
     * Get all notification preferences for a user.
     *
     * Returns a merged view of global defaults, user preferences, and device-specific preferences.
     *
     * @return array<string, array{type: string, description: string, push_enabled: bool, email_enabled: bool}>
     */
    public function getUserPreferences(User $user, ?MobileDevice $device = null): array
    {
        // Start with defaults
        $defaults = MobileNotificationPreference::getDefaults();
        $types = MobileNotificationPreference::getAvailableTypes();

        // Get user's global preferences
        $userPrefs = MobileNotificationPreference::where('user_id', $user->id)
            ->whereNull('mobile_device_id')
            ->get()
            ->keyBy('notification_type');

        // Get device-specific preferences if device is specified
        $devicePrefs = new Collection();
        if ($device !== null) {
            $devicePrefs = MobileNotificationPreference::where('user_id', $user->id)
                ->where('mobile_device_id', $device->id)
                ->get()
                ->keyBy('notification_type');
        }

        // Merge preferences: device > user > defaults
        $result = [];
        foreach ($types as $type => $description) {
            $default = $defaults[$type] ?? ['push_enabled' => true, 'email_enabled' => false];
            $userPref = $userPrefs->get($type);
            $devicePref = $devicePrefs->get($type);

            // Priority: device-specific > user-global > default
            if ($devicePref !== null) {
                $pushEnabled = $devicePref->push_enabled;
                $emailEnabled = $devicePref->email_enabled;
            } elseif ($userPref !== null) {
                $pushEnabled = $userPref->push_enabled;
                $emailEnabled = $userPref->email_enabled;
            } else {
                $pushEnabled = $default['push_enabled'];
                $emailEnabled = $default['email_enabled'];
            }

            $result[$type] = [
                'type'          => $type,
                'description'   => $description,
                'push_enabled'  => $pushEnabled,
                'email_enabled' => $emailEnabled,
            ];
        }

        return $result;
    }

    /**
     * Update notification preferences for a user.
     *
     * @param array<string, array{push_enabled?: bool, email_enabled?: bool}> $preferences
     */
    public function updatePreferences(User $user, array $preferences, ?MobileDevice $device = null): void
    {
        $availableTypes = array_keys(MobileNotificationPreference::getAvailableTypes());

        DB::transaction(function () use ($user, $preferences, $device, $availableTypes) {
            foreach ($preferences as $type => $settings) {
                if (! in_array($type, $availableTypes, true)) {
                    continue;
                }

                MobileNotificationPreference::updateOrCreate(
                    [
                        'user_id'           => $user->id,
                        'mobile_device_id'  => $device?->id,
                        'notification_type' => $type,
                    ],
                    [
                        'push_enabled'  => $settings['push_enabled'] ?? true,
                        'email_enabled' => $settings['email_enabled'] ?? false,
                    ]
                );
            }
        });
    }

    /**
     * Check if a user has push notifications enabled for a specific type.
     */
    public function isPushEnabled(User $user, string $notificationType, ?MobileDevice $device = null): bool
    {
        // Check device-specific first
        if ($device !== null) {
            $devicePref = MobileNotificationPreference::where('user_id', $user->id)
                ->where('mobile_device_id', $device->id)
                ->where('notification_type', $notificationType)
                ->first();

            if ($devicePref !== null) {
                return $devicePref->push_enabled;
            }
        }

        // Check user global preference
        $userPref = MobileNotificationPreference::where('user_id', $user->id)
            ->whereNull('mobile_device_id')
            ->where('notification_type', $notificationType)
            ->first();

        if ($userPref !== null) {
            return $userPref->push_enabled;
        }

        // Return default
        $defaults = MobileNotificationPreference::getDefaults();

        return $defaults[$notificationType]['push_enabled'] ?? true;
    }

    /**
     * Reset preferences to defaults for a user.
     */
    public function resetToDefaults(User $user, ?MobileDevice $device = null): void
    {
        $query = MobileNotificationPreference::where('user_id', $user->id);

        if ($device !== null) {
            $query->where('mobile_device_id', $device->id);
        } else {
            $query->whereNull('mobile_device_id');
        }

        $query->delete();
    }

    /**
     * Initialize default preferences for a new user.
     */
    public function initializeForUser(User $user): void
    {
        $defaults = MobileNotificationPreference::getDefaults();

        foreach ($defaults as $type => $settings) {
            MobileNotificationPreference::firstOrCreate(
                [
                    'user_id'           => $user->id,
                    'mobile_device_id'  => null,
                    'notification_type' => $type,
                ],
                [
                    'push_enabled'  => $settings['push_enabled'],
                    'email_enabled' => $settings['email_enabled'],
                ]
            );
        }
    }
}
