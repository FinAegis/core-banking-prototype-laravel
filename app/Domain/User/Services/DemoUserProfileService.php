<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Models\UserActivity;
use App\Domain\User\Models\UserProfile;
use App\Models\User;
use Illuminate\Support\Str;

class DemoUserProfileService
{
    /**
     * Create a demo user profile with sample data.
     */
    public function createDemoProfile(User $user, array $data = []): UserProfile
    {
        $demoData = [
            'first_name'   => $data['first_name'] ?? 'Demo',
            'last_name'    => $data['last_name'] ?? 'User',
            'phone_number' => $data['phone_number'] ?? '+1234567890',
            'country'      => $data['country'] ?? 'US',
            'city'         => $data['city'] ?? 'New York',
            'status'       => 'active',
            'is_verified'  => true,
            'preferences'  => [
                'language'         => 'en',
                'timezone'         => 'America/New_York',
                'currency'         => 'USD',
                'darkMode'         => false,
                'dashboardWidgets' => ['balance', 'transactions', 'quickActions'],
            ],
            'notification_preferences' => [
                'emailNotifications' => true,
                'smsNotifications'   => false,
                'pushNotifications'  => true,
                'notificationTypes'  => [
                    'transactions' => true,
                    'security'     => true,
                    'marketing'    => false,
                    'updates'      => true,
                ],
            ],
            'privacy_settings' => [
                'profileVisibility' => true,
                'showEmail'         => false,
                'showPhone'         => false,
                'showActivity'      => true,
                'allowAnalytics'    => true,
            ],
            'metadata' => [
                'demo_mode'   => true,
                'created_via' => 'demo_service',
            ],
        ];

        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($demoData, ['email' => $user->email])
        );

        // Create some demo activities
        $this->createDemoActivities(strval($user->id));

        return $profile;
    }

    /**
     * Create demo activities for the user.
     */
    private function createDemoActivities(string $userId): void
    {
        $activities = [
            ['activity' => 'login', 'context' => ['method' => 'password', 'device' => 'web']],
            ['activity' => 'profile_updated', 'context' => ['fields' => ['phone_number', 'city']]],
            ['activity' => 'preferences_updated', 'context' => ['changed' => ['currency', 'timezone']]],
            ['activity' => 'security_check', 'context' => ['type' => '2fa_enabled']],
            ['activity' => 'transaction_viewed', 'context' => ['transaction_id' => Str::uuid()]],
        ];

        foreach ($activities as $index => $activity) {
            UserActivity::create([
                'user_id'  => $userId,
                'activity' => $activity['activity'],
                'context'  => array_merge($activity['context'], [
                    'demo_data'  => true,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Demo Browser',
                ]),
                'tracked_at' => now()->subHours($index * 2),
            ]);
        }
    }

    /**
     * Update demo profile with sample changes.
     */
    public function updateDemoProfile(string $userId, array $data): UserProfile
    {
        $profile = UserProfile::where('user_id', $userId)->first();

        if (! $profile) {
            throw new \Exception("Profile not found for user {$userId}");
        }

        // Simulate some updates
        $updates = array_merge($data, [
            'metadata' => array_merge($profile->metadata ?? [], [
                'last_demo_update' => now()->toDateTimeString(),
                'update_count'     => ($profile->metadata['update_count'] ?? 0) + 1,
            ]),
        ]);

        $profile->update($updates);

        // Track the update activity
        UserActivity::create([
            'user_id'  => $userId,
            'activity' => 'demo_profile_updated',
            'context'  => [
                'fields'    => array_keys($data),
                'demo_mode' => true,
            ],
            'tracked_at' => now(),
        ]);

        return $profile->fresh();
    }

    /**
     * Generate demo analytics data.
     */
    public function generateDemoAnalytics(string $userId): array
    {
        return [
            'profile_completeness' => 85,
            'activity_score'       => rand(60, 95),
            'engagement_level'     => 'high',
            'last_30_days'         => [
                'logins'          => rand(15, 30),
                'transactions'    => rand(5, 20),
                'profile_updates' => rand(1, 5),
            ],
            'preferences' => [
                'most_used_features' => ['transfers', 'payments', 'investments'],
                'preferred_time'     => '09:00-17:00',
                'device_preference'  => 'mobile',
            ],
            'recommendations' => [
                'Enable two-factor authentication',
                'Complete KYC verification',
                'Set up automatic payments',
            ],
        ];
    }
}
