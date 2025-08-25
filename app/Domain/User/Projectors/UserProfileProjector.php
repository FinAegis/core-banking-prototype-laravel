<?php

declare(strict_types=1);

namespace App\Domain\User\Projectors;

use App\Domain\User\Events\NotificationPreferencesUpdated;
use App\Domain\User\Events\PrivacySettingsUpdated;
use App\Domain\User\Events\UserActivityTracked;
use App\Domain\User\Events\UserPreferencesUpdated;
use App\Domain\User\Events\UserProfileCreated;
use App\Domain\User\Events\UserProfileDeleted;
use App\Domain\User\Events\UserProfileSuspended;
use App\Domain\User\Events\UserProfileUpdated;
use App\Domain\User\Events\UserProfileVerified;
use App\Domain\User\Models\UserActivity;
use App\Domain\User\Models\UserProfile;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class UserProfileProjector extends Projector
{
    public function onUserProfileCreated(UserProfileCreated $event): void
    {
        UserProfile::create([
            'user_id'      => $event->userId,
            'email'        => $event->email,
            'first_name'   => $event->firstName,
            'last_name'    => $event->lastName,
            'phone_number' => $event->phoneNumber,
            'status'       => 'active',
            'is_verified'  => false,
            'metadata'     => $event->metadata,
            'created_at'   => $event->createdAt,
        ]);
    }

    public function onUserProfileUpdated(UserProfileUpdated $event): void
    {
        $profile = UserProfile::where('user_id', $event->userId)->first();

        if ($profile) {
            $updates = [];
            foreach ($event->updates as $field => $value) {
                $dbField = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $field));
                $updates[$dbField] = $value;
            }

            $profile->update($updates);
        }
    }

    public function onUserProfileVerified(UserProfileVerified $event): void
    {
        UserProfile::where('user_id', $event->userId)->update([
            'is_verified' => true,
            'updated_at'  => $event->verifiedAt,
        ]);
    }

    public function onUserProfileSuspended(UserProfileSuspended $event): void
    {
        UserProfile::where('user_id', $event->userId)->update([
            'status'            => 'suspended',
            'suspended_at'      => $event->suspendedAt,
            'suspension_reason' => $event->reason,
        ]);
    }

    public function onUserPreferencesUpdated(UserPreferencesUpdated $event): void
    {
        UserProfile::where('user_id', $event->userId)->update([
            'preferences' => $event->preferences,
            'updated_at'  => $event->updatedAt,
        ]);
    }

    public function onNotificationPreferencesUpdated(NotificationPreferencesUpdated $event): void
    {
        UserProfile::where('user_id', $event->userId)->update([
            'notification_preferences' => $event->preferences,
            'updated_at'               => $event->updatedAt,
        ]);
    }

    public function onPrivacySettingsUpdated(PrivacySettingsUpdated $event): void
    {
        UserProfile::where('user_id', $event->userId)->update([
            'privacy_settings' => $event->settings,
            'updated_at'       => $event->updatedAt,
        ]);
    }

    public function onUserActivityTracked(UserActivityTracked $event): void
    {
        UserActivity::create([
            'user_id'    => $event->userId,
            'activity'   => $event->activity,
            'context'    => $event->context,
            'tracked_at' => $event->trackedAt,
        ]);

        UserProfile::where('user_id', $event->userId)->update([
            'last_activity_at' => $event->trackedAt,
        ]);
    }

    public function onUserProfileDeleted(UserProfileDeleted $event): void
    {
        UserProfile::where('user_id', $event->userId)->update([
            'status'     => 'deleted',
            'updated_at' => $event->deletedAt,
        ]);
    }
}
