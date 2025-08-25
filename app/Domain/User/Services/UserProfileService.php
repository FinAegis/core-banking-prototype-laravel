<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Aggregates\UserProfile;
use App\Domain\User\Models\UserActivity;
use App\Domain\User\Models\UserProfile as UserProfileModel;
use App\Domain\User\ValueObjects\NotificationPreferences;
use App\Domain\User\ValueObjects\PrivacySettings;
use App\Domain\User\ValueObjects\UserPreferences;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserProfileService
{
    /**
     * Create a new user profile.
     */
    public function createProfile(User $user, array $data = []): UserProfileModel
    {
        return DB::transaction(function () use ($user, $data) {
            $profileId = (string) Str::uuid();

            $profile = UserProfile::create(
                userId: (string) $user->id,
                email: $user->email,
                firstName: $data['first_name'] ?? null,
                lastName: $data['last_name'] ?? null,
                phoneNumber: $data['phone_number'] ?? null,
                metadata: [
                    'source'     => $data['source'] ?? 'registration',
                    'ip_address' => request()->ip(),
                ]
            );

            $profile->persist();

            // Set default preferences
            if (! isset($data['skip_defaults'])) {
                $this->setDefaultPreferences((string) $user->id);
            }

            return UserProfileModel::where('user_id', $user->id)->first();
        });
    }

    /**
     * Update user profile.
     */
    public function updateProfile(string $userId, array $data, string $updatedBy): UserProfileModel
    {
        return DB::transaction(function () use ($userId, $data, $updatedBy) {
            $profile = UserProfile::retrieve($userId);
            $profile->updateProfile($data, $updatedBy);
            $profile->persist();

            return UserProfileModel::where('user_id', $userId)->first();
        });
    }

    /**
     * Verify user profile.
     */
    public function verifyProfile(string $userId, string $verificationType, string $verifiedBy): UserProfileModel
    {
        return DB::transaction(function () use ($userId, $verificationType, $verifiedBy) {
            $profile = UserProfile::retrieve($userId);
            $profile->verify($verifiedBy, $verificationType);
            $profile->persist();

            return UserProfileModel::where('user_id', $userId)->first();
        });
    }

    /**
     * Suspend user profile.
     */
    public function suspendProfile(string $userId, string $reason, string $suspendedBy): UserProfileModel
    {
        return DB::transaction(function () use ($userId, $reason, $suspendedBy) {
            $profile = UserProfile::retrieve($userId);
            $profile->suspend($reason, $suspendedBy);
            $profile->persist();

            return UserProfileModel::where('user_id', $userId)->first();
        });
    }

    /**
     * Update user preferences.
     */
    public function updatePreferences(string $userId, array $preferences, string $updatedBy): UserProfileModel
    {
        return DB::transaction(function () use ($userId, $preferences, $updatedBy) {
            $profile = UserProfile::retrieve($userId);
            $userPreferences = UserPreferences::fromArray($preferences);
            $profile->updatePreferences($userPreferences, $updatedBy);
            $profile->persist();

            return UserProfileModel::where('user_id', $userId)->first();
        });
    }

    /**
     * Update notification preferences.
     */
    public function updateNotificationPreferences(string $userId, array $preferences, string $updatedBy): UserProfileModel
    {
        return DB::transaction(function () use ($userId, $preferences, $updatedBy) {
            $profile = UserProfile::retrieve($userId);
            $notificationPreferences = NotificationPreferences::fromArray($preferences);
            $profile->updateNotificationPreferences($notificationPreferences, $updatedBy);
            $profile->persist();

            return UserProfileModel::where('user_id', $userId)->first();
        });
    }

    /**
     * Update privacy settings.
     */
    public function updatePrivacySettings(string $userId, array $settings, string $updatedBy): UserProfileModel
    {
        return DB::transaction(function () use ($userId, $settings, $updatedBy) {
            $profile = UserProfile::retrieve($userId);
            $privacySettings = PrivacySettings::fromArray($settings);
            $profile->updatePrivacySettings($privacySettings, $updatedBy);
            $profile->persist();

            return UserProfileModel::where('user_id', $userId)->first();
        });
    }

    /**
     * Track user activity.
     */
    public function trackActivity(string $userId, string $activity, array $context = []): void
    {
        DB::transaction(function () use ($userId, $activity, $context) {
            $profile = UserProfile::retrieve($userId);

            $enrichedContext = array_merge($context, [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => session()->getId(),
            ]);

            $profile->trackActivity($activity, $enrichedContext);
            $profile->persist();
        });
    }

    /**
     * Delete user profile (soft delete).
     */
    public function deleteProfile(string $userId, string $deletedBy, string $reason = 'user_request'): void
    {
        DB::transaction(function () use ($userId, $deletedBy, $reason) {
            $profile = UserProfile::retrieve($userId);
            $profile->delete($deletedBy, $reason);
            $profile->persist();
        });
    }

    /**
     * Get user profile.
     */
    public function getProfile(string $userId): ?UserProfileModel
    {
        return UserProfileModel::where('user_id', $userId)->first();
    }

    /**
     * Get user activities.
     */
    public function getUserActivities(string $userId, int $limit = 50): \Illuminate\Support\Collection
    {
        return UserActivity::forUser($userId)
            ->orderBy('tracked_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Set default preferences for a new user.
     */
    private function setDefaultPreferences(string $userId): void
    {
        $this->updatePreferences($userId, [
            'language' => 'en',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'darkMode' => false,
        ], 'system');

        $this->updateNotificationPreferences($userId, [
            'emailNotifications' => true,
            'smsNotifications'   => false,
            'pushNotifications'  => true,
        ], 'system');

        $this->updatePrivacySettings($userId, [
            'profileVisibility' => true,
            'showActivity'      => true,
            'allowAnalytics'    => true,
        ], 'system');
    }
}
