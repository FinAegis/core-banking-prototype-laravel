<?php

declare(strict_types=1);

namespace App\Domain\User\Aggregates;

use App\Domain\User\Events\NotificationPreferencesUpdated;
use App\Domain\User\Events\PrivacySettingsUpdated;
use App\Domain\User\Events\UserActivityTracked;
use App\Domain\User\Events\UserPreferencesUpdated;
use App\Domain\User\Events\UserProfileCreated;
use App\Domain\User\Events\UserProfileDeleted;
use App\Domain\User\Events\UserProfileSuspended;
use App\Domain\User\Events\UserProfileUpdated;
use App\Domain\User\Events\UserProfileVerified;
use App\Domain\User\Exceptions\UserProfileException;
use App\Domain\User\ValueObjects\NotificationPreferences;
use App\Domain\User\ValueObjects\PrivacySettings;
use App\Domain\User\ValueObjects\UserPreferences;
use DateTimeImmutable;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class UserProfile extends AggregateRoot
{
    private string $userId = '';

    private string $email = '';

    private ?string $firstName = null;

    private ?string $lastName = null;

    private ?string $phoneNumber = null;

    private ?DateTimeImmutable $dateOfBirth = null;

    /** @phpstan-ignore-next-line */
    private ?string $country = null;

    /** @phpstan-ignore-next-line */
    private ?string $city = null;

    /** @phpstan-ignore-next-line */
    private ?string $address = null;

    /** @phpstan-ignore-next-line */
    private ?string $postalCode = null;

    private string $status = 'active';

    private bool $isVerified = false;

    private array $preferences = [];

    private array $notificationPreferences = [];

    private array $privacySettings = [];

    private ?DateTimeImmutable $suspendedAt = null;

    private ?string $suspensionReason = null;

    private array $activityLog = [];

    private ?DateTimeImmutable $lastActivityAt = null;

    public static function create(
        string $userId,
        string $email,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $phoneNumber = null,
        array $metadata = []
    ): self {
        $profile = (new self())->loadUuid($userId);
        $profile->recordThat(new UserProfileCreated(
            userId: $userId,
            email: $email,
            firstName: $firstName,
            lastName: $lastName,
            phoneNumber: $phoneNumber,
            metadata: $metadata,
            createdAt: new DateTimeImmutable()
        ));

        return $profile;
    }

    public function updateProfile(array $data, string $updatedBy): self
    {
        // Initialize userId if empty (for reconstituted aggregates)
        if (empty($this->userId)) {
            $this->userId = $this->uuid();
        }

        $allowedFields = [
            'firstName',
            'lastName',
            'phoneNumber',
            'dateOfBirth',
            'country',
            'city',
            'address',
            'postalCode',
        ];

        $updates = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updates)) {
            throw UserProfileException::noValidFieldsToUpdate();
        }

        $this->recordThat(new UserProfileUpdated(
            userId: $this->userId,
            updates: $updates,
            updatedBy: $updatedBy,
            updatedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    public function verify(string $verifiedBy, string $verificationType = 'email'): self
    {
        // Initialize userId if empty (for reconstituted aggregates)
        if (empty($this->userId)) {
            $this->userId = $this->uuid();
        }

        if ($this->isVerified) {
            throw UserProfileException::alreadyVerified($this->userId);
        }

        $this->recordThat(new UserProfileVerified(
            userId: $this->userId,
            verificationType: $verificationType,
            verifiedBy: $verifiedBy,
            verifiedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    public function suspend(string $reason, string $suspendedBy): self
    {
        // Initialize userId if empty (for reconstituted aggregates)
        if (empty($this->userId)) {
            $this->userId = $this->uuid();
        }

        if ($this->status === 'suspended') {
            throw UserProfileException::alreadySuspended($this->userId);
        }

        $this->recordThat(new UserProfileSuspended(
            userId: $this->userId,
            reason: $reason,
            suspendedBy: $suspendedBy,
            suspendedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    public function updatePreferences(UserPreferences $preferences, string $updatedBy): self
    {
        // Initialize userId if empty (for reconstituted aggregates)
        if (empty($this->userId)) {
            $this->userId = $this->uuid();
        }

        $this->recordThat(new UserPreferencesUpdated(
            userId: $this->userId,
            preferences: $preferences->toArray(),
            updatedBy: $updatedBy,
            updatedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    public function updateNotificationPreferences(
        NotificationPreferences $preferences,
        string $updatedBy
    ): self {
        // Initialize userId if empty (for reconstituted aggregates)
        if (empty($this->userId)) {
            $this->userId = $this->uuid();
        }

        $this->recordThat(new NotificationPreferencesUpdated(
            userId: $this->userId,
            preferences: $preferences->toArray(),
            updatedBy: $updatedBy,
            updatedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    public function updatePrivacySettings(PrivacySettings $settings, string $updatedBy): self
    {
        // Initialize userId if empty (for reconstituted aggregates)
        if (empty($this->userId)) {
            $this->userId = $this->uuid();
        }

        $this->recordThat(new PrivacySettingsUpdated(
            userId: $this->userId,
            settings: $settings->toArray(),
            updatedBy: $updatedBy,
            updatedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    public function trackActivity(string $activity, array $context = []): self
    {
        // Initialize userId if empty (for reconstituted aggregates)
        if (empty($this->userId)) {
            $this->userId = $this->uuid();
        }

        $this->recordThat(new UserActivityTracked(
            userId: $this->userId,
            activity: $activity,
            context: $context,
            trackedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    public function delete(string $deletedBy, string $reason = 'user_request'): self
    {
        // Initialize userId if empty (for reconstituted aggregates)
        if (empty($this->userId)) {
            $this->userId = $this->uuid();
        }

        if ($this->status === 'deleted') {
            throw UserProfileException::alreadyDeleted($this->userId);
        }

        $this->recordThat(new UserProfileDeleted(
            userId: $this->userId,
            reason: $reason,
            deletedBy: $deletedBy,
            deletedAt: new DateTimeImmutable()
        ));

        return $this;
    }

    // Apply methods for events
    protected function applyUserProfileCreated(UserProfileCreated $event): void
    {
        $this->userId = $event->userId;
        $this->email = $event->email;
        $this->firstName = $event->firstName;
        $this->lastName = $event->lastName;
        $this->phoneNumber = $event->phoneNumber;
        $this->status = 'active';
        $this->isVerified = false;
    }

    protected function applyUserProfileUpdated(UserProfileUpdated $event): void
    {
        // Ensure userId is set
        if (empty($this->userId)) {
            $this->userId = $event->userId;
        }

        foreach ($event->updates as $field => $value) {
            if (property_exists($this, $field)) {
                // Special handling for dateOfBirth to ensure it's a DateTimeImmutable
                if ($field === 'dateOfBirth' && $value !== null) {
                    $this->$field = $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable($value);
                } else {
                    $this->$field = $value;
                }
            }
        }
    }

    protected function applyUserProfileVerified(UserProfileVerified $event): void
    {
        // Ensure userId is set
        if (empty($this->userId)) {
            $this->userId = $event->userId;
        }

        $this->isVerified = true;
    }

    protected function applyUserProfileSuspended(UserProfileSuspended $event): void
    {
        // Ensure userId is set
        if (empty($this->userId)) {
            $this->userId = $event->userId;
        }

        $this->status = 'suspended';
        $this->suspendedAt = $event->suspendedAt;
        $this->suspensionReason = $event->reason;
    }

    protected function applyUserPreferencesUpdated(UserPreferencesUpdated $event): void
    {
        // Ensure userId is set
        if (empty($this->userId)) {
            $this->userId = $event->userId;
        }

        $this->preferences = $event->preferences;
    }

    protected function applyNotificationPreferencesUpdated(NotificationPreferencesUpdated $event): void
    {
        // Ensure userId is set
        if (empty($this->userId)) {
            $this->userId = $event->userId;
        }

        $this->notificationPreferences = $event->preferences;
    }

    protected function applyPrivacySettingsUpdated(PrivacySettingsUpdated $event): void
    {
        // Ensure userId is set
        if (empty($this->userId)) {
            $this->userId = $event->userId;
        }

        $this->privacySettings = $event->settings;
    }

    protected function applyUserActivityTracked(UserActivityTracked $event): void
    {
        // Ensure userId is set
        if (empty($this->userId)) {
            $this->userId = $event->userId;
        }

        $this->activityLog[] = [
            'activity'  => $event->activity,
            'context'   => $event->context,
            'trackedAt' => $event->trackedAt,
        ];
        $this->lastActivityAt = $event->trackedAt;
    }

    protected function applyUserProfileDeleted(UserProfileDeleted $event): void
    {
        // Ensure userId is set
        if (empty($this->userId)) {
            $this->userId = $event->userId;
        }

        $this->status = 'deleted';
    }

    // Getters
    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFullName(): ?string
    {
        if ($this->firstName || $this->lastName) {
            return trim("{$this->firstName} {$this->lastName}");
        }

        return null;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getPreferences(): array
    {
        return $this->preferences;
    }

    public function getNotificationPreferences(): array
    {
        return $this->notificationPreferences;
    }

    public function getPrivacySettings(): array
    {
        return $this->privacySettings;
    }
}
