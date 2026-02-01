<?php

declare(strict_types=1);

namespace App\Domain\FinancialInstitution\Enums;

enum PartnerTier: string
{
    case STARTER = 'starter';
    case GROWTH = 'growth';
    case ENTERPRISE = 'enterprise';

    /**
     * Get human-readable tier name.
     */
    public function label(): string
    {
        return match ($this) {
            self::STARTER    => 'Starter',
            self::GROWTH     => 'Growth',
            self::ENTERPRISE => 'Enterprise',
        };
    }

    /**
     * Get monthly API call limit.
     */
    public function apiCallLimit(): int
    {
        return match ($this) {
            self::STARTER    => 10000,      // 10K calls
            self::GROWTH     => 100000,      // 100K calls
            self::ENTERPRISE => 1000000, // 1M calls
        };
    }

    /**
     * Get rate limit per minute.
     */
    public function rateLimitPerMinute(): int
    {
        return match ($this) {
            self::STARTER    => 60,
            self::GROWTH     => 300,
            self::ENTERPRISE => 1000,
        };
    }

    /**
     * Check if white-label is available.
     */
    public function hasWhiteLabel(): bool
    {
        return match ($this) {
            self::STARTER    => false,
            self::GROWTH     => true,
            self::ENTERPRISE => true,
        };
    }

    /**
     * Check if custom domain is available.
     */
    public function hasCustomDomain(): bool
    {
        return match ($this) {
            self::STARTER    => false,
            self::GROWTH     => false,
            self::ENTERPRISE => true,
        };
    }

    /**
     * Check if dedicated support is available.
     */
    public function hasDedicatedSupport(): bool
    {
        return match ($this) {
            self::STARTER    => false,
            self::GROWTH     => false,
            self::ENTERPRISE => true,
        };
    }

    /**
     * Check if SDK access is available.
     */
    public function hasSdkAccess(): bool
    {
        return match ($this) {
            self::STARTER    => false,
            self::GROWTH     => true,
            self::ENTERPRISE => true,
        };
    }

    /**
     * Check if widgets are available.
     */
    public function hasWidgets(): bool
    {
        return match ($this) {
            self::STARTER    => false,
            self::GROWTH     => true,
            self::ENTERPRISE => true,
        };
    }

    /**
     * Get monthly base price in USD.
     */
    public function monthlyPrice(): float
    {
        return match ($this) {
            self::STARTER    => 99.00,
            self::GROWTH     => 499.00,
            self::ENTERPRISE => 1999.00,
        };
    }

    /**
     * Get overage price per 1000 API calls.
     */
    public function overagePricePerThousand(): float
    {
        return match ($this) {
            self::STARTER    => 1.00,
            self::GROWTH     => 0.50,
            self::ENTERPRISE => 0.25,
        };
    }

    /**
     * Get all available features for this tier.
     *
     * @return array<string, bool>
     */
    public function features(): array
    {
        return [
            'white_label'       => $this->hasWhiteLabel(),
            'custom_domain'     => $this->hasCustomDomain(),
            'dedicated_support' => $this->hasDedicatedSupport(),
            'sdk_access'        => $this->hasSdkAccess(),
            'widgets'           => $this->hasWidgets(),
            'webhooks'          => true,
            'sandbox'           => true,
            'production'        => $this !== self::STARTER,
            'api_analytics'     => true,
            'priority_support'  => $this === self::ENTERPRISE,
            'sla_guarantee'     => $this === self::ENTERPRISE,
        ];
    }

    /**
     * Get all values as array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
