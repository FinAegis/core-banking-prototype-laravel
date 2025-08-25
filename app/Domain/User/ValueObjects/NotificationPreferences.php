<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

class NotificationPreferences
{
    public function __construct(
        private bool $emailNotifications = true,
        private bool $smsNotifications = false,
        private bool $pushNotifications = true,
        private array $notificationTypes = [],
        private array $emailFrequency = [],
        private array $quietHours = []
    ) {
        $this->notificationTypes = $notificationTypes ?: [
            'transactions' => true,
            'security'     => true,
            'marketing'    => false,
            'updates'      => true,
            'reminders'    => true,
        ];

        $this->emailFrequency = $emailFrequency ?: [
            'immediate' => ['security', 'transactions'],
            'daily'     => ['updates'],
            'weekly'    => ['marketing'],
        ];

        $this->quietHours = $quietHours ?: [
            'enabled' => false,
            'start'   => '22:00',
            'end'     => '08:00',
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            emailNotifications: $data['emailNotifications'] ?? true,
            smsNotifications: $data['smsNotifications'] ?? false,
            pushNotifications: $data['pushNotifications'] ?? true,
            notificationTypes: $data['notificationTypes'] ?? [],
            emailFrequency: $data['emailFrequency'] ?? [],
            quietHours: $data['quietHours'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'emailNotifications' => $this->emailNotifications,
            'smsNotifications'   => $this->smsNotifications,
            'pushNotifications'  => $this->pushNotifications,
            'notificationTypes'  => $this->notificationTypes,
            'emailFrequency'     => $this->emailFrequency,
            'quietHours'         => $this->quietHours,
        ];
    }

    public function shouldSendEmail(): bool
    {
        return $this->emailNotifications;
    }

    public function shouldSendSms(): bool
    {
        return $this->smsNotifications;
    }

    public function shouldSendPush(): bool
    {
        return $this->pushNotifications;
    }

    public function isTypeEnabled(string $type): bool
    {
        return $this->notificationTypes[$type] ?? false;
    }

    public function isInQuietHours(\DateTimeImmutable $time): bool
    {
        if (! ($this->quietHours['enabled'] ?? false)) {
            return false;
        }

        $currentTime = $time->format('H:i');
        $start = $this->quietHours['start'];
        $end = $this->quietHours['end'];

        if ($start < $end) {
            return $currentTime >= $start && $currentTime <= $end;
        }

        // Handle overnight quiet hours
        return $currentTime >= $start || $currentTime <= $end;
    }
}
