<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Aggregates\UserActivityAggregate;
use App\Domain\User\Aggregates\UserProfile;
use App\Domain\User\Models\UserProfile as UserProfileModel;
use App\Domain\User\ValueObjects\NotificationPreferences;
use App\Domain\User\ValueObjects\PrivacySettings;
use App\Domain\User\ValueObjects\UserPreferences;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EnhancedUserProfileService
{
    public function __construct(
        private UserAnalyticsService $analyticsService
    ) {
    }

    /**
     * Create a new user profile with activity tracking.
     */
    public function createProfile(
        string $userId,
        string $email,
        array $profileData = []
    ): UserProfileModel {
        // Create profile aggregate
        $aggregate = UserProfile::retrieve(Str::uuid()->toString());
        $aggregate->create(
            userId: $userId,
            email: $email,
            firstName: $profileData['first_name'] ?? null,
            lastName: $profileData['last_name'] ?? null,
            phoneNumber: $profileData['phone_number'] ?? null,
            dateOfBirth: isset($profileData['date_of_birth'])
                ? new DateTimeImmutable($profileData['date_of_birth'])
                : null,
            country: $profileData['country'] ?? null,
            city: $profileData['city'] ?? null,
            address: $profileData['address'] ?? null,
            postalCode: $profileData['postal_code'] ?? null
        );
        $aggregate->persist();

        // Track profile creation activity
        $this->trackActivity($userId, 'profile_created', [
            'email'        => $email,
            'profile_data' => array_keys($profileData),
        ]);

        // Create or get the model
        return UserProfileModel::firstOrCreate(
            ['user_id' => $userId],
            array_merge($profileData, [
                'email'  => $email,
                'status' => 'active',
            ])
        );
    }

    /**
     * Update user profile with activity tracking.
     */
    public function updateProfile(
        string $userId,
        array $updates
    ): UserProfileModel {
        $profile = UserProfileModel::where('user_id', $userId)->firstOrFail();

        // Track what's being updated
        $changedFields = array_keys($updates);

        // Update through aggregate if significant changes
        if (array_intersect($changedFields, ['first_name', 'last_name', 'email', 'phone_number'])) {
            $aggregate = UserProfile::retrieve($profile->aggregate_uuid ?? Str::uuid()->toString());
            $aggregate->update($updates);
            $aggregate->persist();
        }

        // Update model
        $profile->update($updates);
        $profile->update(['last_activity_at' => now()]);

        // Track update activity
        $this->trackActivity($userId, 'profile_updated', [
            'changed_fields'        => $changedFields,
            'has_sensitive_changes' => ! empty(array_intersect($changedFields, ['email', 'phone_number'])),
        ]);

        // Clear cache
        Cache::forget("user_profile_{$userId}");

        return $profile->fresh();
    }

    /**
     * Update user preferences with activity tracking.
     */
    public function updatePreferences(
        string $userId,
        array $preferences
    ): UserProfileModel {
        $profile = UserProfileModel::where('user_id', $userId)->firstOrFail();

        $aggregate = UserProfile::retrieve($profile->aggregate_uuid ?? Str::uuid()->toString());
        $aggregate->updatePreferences(new UserPreferences($preferences));
        $aggregate->persist();

        $profile->update(['preferences' => $preferences]);

        // Track preference update
        $this->trackActivity($userId, 'preferences_updated', [
            'preference_categories' => array_keys($preferences),
        ]);

        return $profile;
    }

    /**
     * Update notification preferences with activity tracking.
     */
    public function updateNotificationPreferences(
        string $userId,
        array $preferences
    ): UserProfileModel {
        $profile = UserProfileModel::where('user_id', $userId)->firstOrFail();

        $aggregate = UserProfile::retrieve($profile->aggregate_uuid ?? Str::uuid()->toString());
        $aggregate->updateNotificationPreferences(new NotificationPreferences($preferences));
        $aggregate->persist();

        $profile->update(['notification_preferences' => $preferences]);

        // Track notification preference update
        $this->trackActivity($userId, 'notification_preferences_updated', [
            'enabled_channels'  => array_keys(array_filter($preferences, fn ($v) => $v === true)),
            'disabled_channels' => array_keys(array_filter($preferences, fn ($v) => $v === false)),
        ]);

        return $profile;
    }

    /**
     * Update privacy settings with activity tracking.
     */
    public function updatePrivacySettings(
        string $userId,
        array $settings
    ): UserProfileModel {
        $profile = UserProfileModel::where('user_id', $userId)->firstOrFail();

        $aggregate = UserProfile::retrieve($profile->aggregate_uuid ?? Str::uuid()->toString());
        $aggregate->updatePrivacySettings(new PrivacySettings($settings));
        $aggregate->persist();

        $profile->update(['privacy_settings' => $settings]);

        // Track privacy settings update
        $this->trackActivity($userId, 'privacy_settings_updated', [
            'settings_changed'    => array_keys($settings),
            'is_more_restrictive' => $this->isMoreRestrictive($profile->privacy_settings, $settings),
        ]);

        return $profile;
    }

    /**
     * Track user activity.
     */
    public function trackActivity(
        string $userId,
        string $activity,
        array $context = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $sessionId = null
    ): void {
        // Get or create activity aggregate for user
        $aggregateUuid = "user-activity-{$userId}";
        $aggregate = UserActivityAggregate::retrieve($aggregateUuid);

        $aggregate->trackActivity(
            userId: $userId,
            activity: $activity,
            context: $context,
            ipAddress: $ipAddress ?? request()->ip(),
            userAgent: $userAgent ?? request()->userAgent(),
            sessionId: $sessionId ?? session()->getId()
        );

        $aggregate->persist();

        // Update last activity timestamp on profile
        UserProfileModel::where('user_id', $userId)
            ->update(['last_activity_at' => now()]);
    }

    /**
     * Get user analytics with activity data.
     */
    public function getUserAnalytics(string $userId, int $days = 30): array
    {
        return $this->analyticsService->getUserAnalytics($userId, $days);
    }

    /**
     * Get user profile with recent activities.
     */
    public function getProfileWithActivities(string $userId): array
    {
        $profile = UserProfileModel::where('user_id', $userId)->firstOrFail();

        // Get recent activities from aggregate
        $aggregateUuid = "user-activity-{$userId}";
        $aggregate = UserActivityAggregate::retrieve($aggregateUuid);

        return [
            'profile'           => $profile->toArray(),
            'recent_activities' => $aggregate->getRecentActivities(10),
            'activity_count'    => $aggregate->getActivityCount(),
            'last_activity'     => $aggregate->getLastActivityAt(),
            'segment'           => $this->analyticsService->getUserSegment($userId),
        ];
    }

    /**
     * Verify user profile.
     */
    public function verifyProfile(string $userId, string $verificationType = 'email'): void
    {
        $profile = UserProfileModel::where('user_id', $userId)->firstOrFail();

        $aggregate = UserProfile::retrieve($profile->aggregate_uuid ?? Str::uuid()->toString());
        $aggregate->verify($verificationType, 'system');
        $aggregate->persist();

        $profile->update(['is_verified' => true]);

        // Track verification
        $this->trackActivity($userId, 'profile_verified', [
            'verification_type' => $verificationType,
        ]);
    }

    /**
     * Suspend user profile.
     */
    public function suspendProfile(string $userId, string $reason): void
    {
        $profile = UserProfileModel::where('user_id', $userId)->firstOrFail();

        $aggregate = UserProfile::retrieve($profile->aggregate_uuid ?? Str::uuid()->toString());
        $aggregate->suspend($reason);
        $aggregate->persist();

        $profile->update([
            'status'            => 'suspended',
            'suspended_at'      => now(),
            'suspension_reason' => $reason,
        ]);

        // Track suspension
        $this->trackActivity($userId, 'profile_suspended', [
            'reason' => $reason,
        ]);
    }

    /**
     * Check if privacy settings are more restrictive.
     */
    private function isMoreRestrictive(?array $oldSettings, array $newSettings): bool
    {
        if (empty($oldSettings)) {
            return true;
        }

        $restrictiveCount = 0;
        $lessRestrictiveCount = 0;

        foreach ($newSettings as $key => $value) {
            if (! isset($oldSettings[$key])) {
                continue;
            }

            // Assuming boolean settings where false is more restrictive
            if ($value === false && $oldSettings[$key] === true) {
                $restrictiveCount++;
            } elseif ($value === true && $oldSettings[$key] === false) {
                $lessRestrictiveCount++;
            }
        }

        return $restrictiveCount > $lessRestrictiveCount;
    }
}
