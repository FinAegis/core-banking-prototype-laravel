<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

class UserPreferences
{
    public function __construct(
        private string $language = 'en',
        private string $timezone = 'UTC',
        private string $dateFormat = 'Y-m-d',
        private string $currency = 'USD',
        private bool $darkMode = false,
        private array $dashboardWidgets = [],
        private array $customSettings = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            language: $data['language'] ?? 'en',
            timezone: $data['timezone'] ?? 'UTC',
            dateFormat: $data['dateFormat'] ?? 'Y-m-d',
            currency: $data['currency'] ?? 'USD',
            darkMode: $data['darkMode'] ?? false,
            dashboardWidgets: $data['dashboardWidgets'] ?? [],
            customSettings: $data['customSettings'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'language'         => $this->language,
            'timezone'         => $this->timezone,
            'dateFormat'       => $this->dateFormat,
            'currency'         => $this->currency,
            'darkMode'         => $this->darkMode,
            'dashboardWidgets' => $this->dashboardWidgets,
            'customSettings'   => $this->customSettings,
        ];
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isDarkMode(): bool
    {
        return $this->darkMode;
    }
}
