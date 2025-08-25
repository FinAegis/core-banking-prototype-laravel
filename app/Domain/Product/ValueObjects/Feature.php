<?php

declare(strict_types=1);

namespace App\Domain\Product\ValueObjects;

class Feature
{
    public function __construct(
        private string $code,
        private string $name,
        private string $description,
        private bool $enabled = true,
        private array $configuration = [],
        private array $limits = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            name: $data['name'],
            description: $data['description'],
            enabled: $data['enabled'] ?? true,
            configuration: $data['configuration'] ?? [],
            limits: $data['limits'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'code'          => $this->code,
            'name'          => $this->name,
            'description'   => $this->description,
            'enabled'       => $this->enabled,
            'configuration' => $this->configuration,
            'limits'        => $this->limits,
        ];
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function getLimits(): array
    {
        return $this->limits;
    }

    public function getLimit(string $key, $default = null)
    {
        return $this->limits[$key] ?? $default;
    }

    public function hasLimit(string $key): bool
    {
        return isset($this->limits[$key]);
    }
}
