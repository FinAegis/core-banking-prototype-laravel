<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

class PrivacySettings
{
    public function __construct(
        private bool $profileVisibility = true,
        private bool $showEmail = false,
        private bool $showPhone = false,
        private bool $showActivity = true,
        private bool $allowDataSharing = false,
        private bool $allowAnalytics = true,
        private array $blockedUsers = [],
        private array $dataRetention = []
    ) {
        $this->dataRetention = $dataRetention ?: [
            'transactionHistory' => 365, // days
            'activityLogs'       => 90,
            'communicationLogs'  => 30,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            profileVisibility: $data['profileVisibility'] ?? true,
            showEmail: $data['showEmail'] ?? false,
            showPhone: $data['showPhone'] ?? false,
            showActivity: $data['showActivity'] ?? true,
            allowDataSharing: $data['allowDataSharing'] ?? false,
            allowAnalytics: $data['allowAnalytics'] ?? true,
            blockedUsers: $data['blockedUsers'] ?? [],
            dataRetention: $data['dataRetention'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'profileVisibility' => $this->profileVisibility,
            'showEmail'         => $this->showEmail,
            'showPhone'         => $this->showPhone,
            'showActivity'      => $this->showActivity,
            'allowDataSharing'  => $this->allowDataSharing,
            'allowAnalytics'    => $this->allowAnalytics,
            'blockedUsers'      => $this->blockedUsers,
            'dataRetention'     => $this->dataRetention,
        ];
    }

    public function isProfileVisible(): bool
    {
        return $this->profileVisibility;
    }

    public function canShareData(): bool
    {
        return $this->allowDataSharing;
    }

    public function isUserBlocked(string $userId): bool
    {
        return in_array($userId, $this->blockedUsers, true);
    }

    public function getDataRetentionDays(string $type): int
    {
        return $this->dataRetention[$type] ?? 90;
    }
}
