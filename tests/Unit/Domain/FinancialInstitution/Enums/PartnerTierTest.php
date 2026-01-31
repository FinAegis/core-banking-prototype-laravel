<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\FinancialInstitution\Enums;

use App\Domain\FinancialInstitution\Enums\PartnerTier;
use Tests\TestCase;

class PartnerTierTest extends TestCase
{
    public function test_enum_values(): void
    {
        $this->assertEquals('starter', PartnerTier::STARTER->value);
        $this->assertEquals('growth', PartnerTier::GROWTH->value);
        $this->assertEquals('enterprise', PartnerTier::ENTERPRISE->value);
    }

    public function test_label_method(): void
    {
        $this->assertEquals('Starter', PartnerTier::STARTER->label());
        $this->assertEquals('Growth', PartnerTier::GROWTH->label());
        $this->assertEquals('Enterprise', PartnerTier::ENTERPRISE->label());
    }

    public function test_api_call_limit(): void
    {
        $this->assertEquals(10000, PartnerTier::STARTER->apiCallLimit());
        $this->assertEquals(100000, PartnerTier::GROWTH->apiCallLimit());
        $this->assertEquals(1000000, PartnerTier::ENTERPRISE->apiCallLimit());
    }

    public function test_rate_limit_per_minute(): void
    {
        $this->assertEquals(60, PartnerTier::STARTER->rateLimitPerMinute());
        $this->assertEquals(300, PartnerTier::GROWTH->rateLimitPerMinute());
        $this->assertEquals(1000, PartnerTier::ENTERPRISE->rateLimitPerMinute());
    }

    public function test_has_white_label(): void
    {
        $this->assertFalse(PartnerTier::STARTER->hasWhiteLabel());
        $this->assertTrue(PartnerTier::GROWTH->hasWhiteLabel());
        $this->assertTrue(PartnerTier::ENTERPRISE->hasWhiteLabel());
    }

    public function test_has_custom_domain(): void
    {
        $this->assertFalse(PartnerTier::STARTER->hasCustomDomain());
        $this->assertFalse(PartnerTier::GROWTH->hasCustomDomain());
        $this->assertTrue(PartnerTier::ENTERPRISE->hasCustomDomain());
    }

    public function test_has_dedicated_support(): void
    {
        $this->assertFalse(PartnerTier::STARTER->hasDedicatedSupport());
        $this->assertFalse(PartnerTier::GROWTH->hasDedicatedSupport());
        $this->assertTrue(PartnerTier::ENTERPRISE->hasDedicatedSupport());
    }

    public function test_has_sdk_access(): void
    {
        $this->assertFalse(PartnerTier::STARTER->hasSdkAccess());
        $this->assertTrue(PartnerTier::GROWTH->hasSdkAccess());
        $this->assertTrue(PartnerTier::ENTERPRISE->hasSdkAccess());
    }

    public function test_has_widgets(): void
    {
        $this->assertFalse(PartnerTier::STARTER->hasWidgets());
        $this->assertTrue(PartnerTier::GROWTH->hasWidgets());
        $this->assertTrue(PartnerTier::ENTERPRISE->hasWidgets());
    }

    public function test_monthly_price(): void
    {
        $this->assertEquals(99.00, PartnerTier::STARTER->monthlyPrice());
        $this->assertEquals(499.00, PartnerTier::GROWTH->monthlyPrice());
        $this->assertEquals(1999.00, PartnerTier::ENTERPRISE->monthlyPrice());
    }

    public function test_overage_price_per_thousand(): void
    {
        $this->assertEquals(1.00, PartnerTier::STARTER->overagePricePerThousand());
        $this->assertEquals(0.50, PartnerTier::GROWTH->overagePricePerThousand());
        $this->assertEquals(0.25, PartnerTier::ENTERPRISE->overagePricePerThousand());
    }

    public function test_features_returns_array(): void
    {
        $features = PartnerTier::STARTER->features();

        $this->assertIsArray($features);
        $this->assertArrayHasKey('white_label', $features);
        $this->assertArrayHasKey('custom_domain', $features);
        $this->assertArrayHasKey('sdk_access', $features);
        $this->assertArrayHasKey('widgets', $features);
        $this->assertArrayHasKey('webhooks', $features);
        $this->assertArrayHasKey('sandbox', $features);
    }

    public function test_starter_features(): void
    {
        $features = PartnerTier::STARTER->features();

        $this->assertFalse($features['white_label']);
        $this->assertFalse($features['custom_domain']);
        $this->assertFalse($features['sdk_access']);
        $this->assertFalse($features['widgets']);
        $this->assertFalse($features['production']);
        $this->assertTrue($features['sandbox']);
        $this->assertTrue($features['webhooks']);
    }

    public function test_growth_features(): void
    {
        $features = PartnerTier::GROWTH->features();

        $this->assertTrue($features['white_label']);
        $this->assertFalse($features['custom_domain']);
        $this->assertTrue($features['sdk_access']);
        $this->assertTrue($features['widgets']);
        $this->assertTrue($features['production']);
        $this->assertTrue($features['sandbox']);
    }

    public function test_enterprise_features(): void
    {
        $features = PartnerTier::ENTERPRISE->features();

        $this->assertTrue($features['white_label']);
        $this->assertTrue($features['custom_domain']);
        $this->assertTrue($features['sdk_access']);
        $this->assertTrue($features['widgets']);
        $this->assertTrue($features['production']);
        $this->assertTrue($features['dedicated_support']);
        $this->assertTrue($features['priority_support']);
        $this->assertTrue($features['sla_guarantee']);
    }

    public function test_values_static_method(): void
    {
        $values = PartnerTier::values();

        $this->assertCount(3, $values);
        $this->assertContains('starter', $values);
        $this->assertContains('growth', $values);
        $this->assertContains('enterprise', $values);
    }

    public function test_try_from_valid(): void
    {
        $tier = PartnerTier::tryFrom('growth');

        $this->assertInstanceOf(PartnerTier::class, $tier);
        $this->assertEquals(PartnerTier::GROWTH, $tier);
    }

    public function test_try_from_invalid(): void
    {
        $tier = PartnerTier::tryFrom('invalid');

        $this->assertNull($tier);
    }

    public function test_cases(): void
    {
        $cases = PartnerTier::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(PartnerTier::STARTER, $cases);
        $this->assertContains(PartnerTier::GROWTH, $cases);
        $this->assertContains(PartnerTier::ENTERPRISE, $cases);
    }
}
