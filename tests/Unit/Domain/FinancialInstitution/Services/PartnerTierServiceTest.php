<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\FinancialInstitution\Services;

use App\Domain\FinancialInstitution\Enums\PartnerTier;
use App\Domain\FinancialInstitution\Models\FinancialInstitutionPartner;
use App\Domain\FinancialInstitution\Models\PartnerBranding;
use App\Domain\FinancialInstitution\Services\PartnerTierService;
use Mockery;
use Tests\TestCase;

class PartnerTierServiceTest extends TestCase
{
    private PartnerTierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PartnerTierService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createMockPartner(array $attributes = []): FinancialInstitutionPartner
    {
        $mock = Mockery::mock(FinancialInstitutionPartner::class)->makePartial();
        $mock->shouldReceive('update')->andReturn(true);
        $mock->shouldReceive('refresh')->andReturnSelf();

        // Set default attributes
        $defaults = [
            'id'                    => fake()->uuid(),
            'tier'                  => 'starter',
            'white_label_enabled'   => false,
            'custom_domain'         => null,
            'rate_limit_per_minute' => 60,
            'institution_name'      => 'Test Partner',
        ];

        foreach (array_merge($defaults, $attributes) as $key => $value) {
            $mock->{$key} = $value;
        }

        return $mock;
    }

    public function test_get_partner_tier(): void
    {
        $partner = $this->createMockPartner(['tier' => 'growth']);

        $tier = $this->service->getPartnerTier($partner);

        $this->assertEquals(PartnerTier::GROWTH, $tier);
    }

    public function test_get_partner_tier_defaults_to_starter(): void
    {
        $partner = $this->createMockPartner(['tier' => null]);

        $tier = $this->service->getPartnerTier($partner);

        $this->assertEquals(PartnerTier::STARTER, $tier);
    }

    public function test_upgrade_tier(): void
    {
        // Test upgrade without white-label (starter to growth includes white-label)
        // Use enterprise to enterprise is same-tier, so test starter to enterprise
        // But starter to enterprise also includes white-label, so test within same capabilities
        $partner = $this->createMockPartner(['tier' => 'growth']);

        $result = $this->service->upgradeTier($partner, PartnerTier::ENTERPRISE);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Upgraded', $result['message']);
        $this->assertEquals('growth', $result['changes']['previous_tier']);
        $this->assertEquals('enterprise', $result['changes']['new_tier']);
    }

    public function test_upgrade_tier_same_tier_returns_error(): void
    {
        $partner = $this->createMockPartner(['tier' => 'growth']);

        $result = $this->service->upgradeTier($partner, PartnerTier::GROWTH);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already on this tier', $result['message']);
    }

    public function test_upgrade_to_lower_tier_triggers_downgrade(): void
    {
        $partner = $this->createMockPartner(['tier' => 'enterprise']);

        $result = $this->service->upgradeTier($partner, PartnerTier::STARTER);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Downgraded', $result['message']);
    }

    public function test_upgrade_from_starter_to_growth_creates_white_label_flag(): void
    {
        // Create a mock service that doesn't actually create branding
        $service = Mockery::mock(PartnerTierService::class)->makePartial();
        $service->shouldReceive('createDefaultBranding')->andReturn(
            Mockery::mock(PartnerBranding::class)
        );

        $partner = $this->createMockPartner(['tier' => 'starter', 'white_label_enabled' => false]);

        $result = $service->upgradeTier($partner, PartnerTier::GROWTH);

        $this->assertTrue($result['changes']['white_label_enabled'] ?? false);
    }

    public function test_downgrade_tier(): void
    {
        $partner = $this->createMockPartner([
            'tier'                => 'enterprise',
            'white_label_enabled' => true,
            'custom_domain'       => 'custom.example.com',
        ]);

        $result = $this->service->downgradeTier($partner, PartnerTier::STARTER);

        $this->assertTrue($result['success']);
        $this->assertContains('white_label', $result['changes']['features_removed']);
        $this->assertContains('custom_domain', $result['changes']['features_removed']);
    }

    public function test_has_feature(): void
    {
        $starterPartner = $this->createMockPartner(['tier' => 'starter']);
        $enterprisePartner = $this->createMockPartner(['tier' => 'enterprise']);

        $this->assertFalse($this->service->hasFeature($starterPartner, 'white_label'));
        $this->assertTrue($this->service->hasFeature($enterprisePartner, 'white_label'));
        $this->assertTrue($this->service->hasFeature($enterprisePartner, 'custom_domain'));
    }

    public function test_get_partner_features(): void
    {
        $partner = $this->createMockPartner(['tier' => 'growth']);

        $features = $this->service->getPartnerFeatures($partner);

        $this->assertIsArray($features);
        $this->assertTrue($features['white_label']);
        $this->assertFalse($features['custom_domain']);
        $this->assertTrue($features['sdk_access']);
    }

    public function test_can_use_white_label(): void
    {
        $enabledPartner = $this->createMockPartner([
            'tier'                => 'growth',
            'white_label_enabled' => true,
        ]);
        $disabledPartner = $this->createMockPartner([
            'tier'                => 'growth',
            'white_label_enabled' => false,
        ]);
        $starterPartner = $this->createMockPartner([
            'tier'                => 'starter',
            'white_label_enabled' => true,
        ]);

        $this->assertTrue($this->service->canUseWhiteLabel($enabledPartner));
        $this->assertFalse($this->service->canUseWhiteLabel($disabledPartner));
        $this->assertFalse($this->service->canUseWhiteLabel($starterPartner));
    }

    public function test_can_use_custom_domain(): void
    {
        $withDomain = $this->createMockPartner([
            'tier'          => 'enterprise',
            'custom_domain' => 'custom.example.com',
        ]);
        $withoutDomain = $this->createMockPartner([
            'tier'          => 'enterprise',
            'custom_domain' => null,
        ]);
        $growthWithDomain = $this->createMockPartner([
            'tier'          => 'growth',
            'custom_domain' => 'custom.example.com',
        ]);

        $this->assertTrue($this->service->canUseCustomDomain($withDomain));
        $this->assertFalse($this->service->canUseCustomDomain($withoutDomain));
        $this->assertFalse($this->service->canUseCustomDomain($growthWithDomain));
    }

    public function test_get_api_call_limit(): void
    {
        $starterPartner = $this->createMockPartner(['tier' => 'starter']);
        $growthPartner = $this->createMockPartner(['tier' => 'growth']);
        $enterprisePartner = $this->createMockPartner(['tier' => 'enterprise']);

        $this->assertEquals(10000, $this->service->getApiCallLimit($starterPartner));
        $this->assertEquals(100000, $this->service->getApiCallLimit($growthPartner));
        $this->assertEquals(1000000, $this->service->getApiCallLimit($enterprisePartner));
    }

    public function test_get_rate_limit_per_minute(): void
    {
        $starterPartner = $this->createMockPartner(['tier' => 'starter']);
        $growthPartner = $this->createMockPartner(['tier' => 'growth']);

        $this->assertEquals(60, $this->service->getRateLimitPerMinute($starterPartner));
        $this->assertEquals(300, $this->service->getRateLimitPerMinute($growthPartner));
    }

    public function test_get_tier_comparison(): void
    {
        $comparison = $this->service->getTierComparison(PartnerTier::STARTER);

        $this->assertCount(3, $comparison);
        $this->assertTrue($comparison['starter']['is_current']);
        $this->assertFalse($comparison['growth']['is_current']);
        $this->assertTrue($comparison['growth']['is_upgrade']);
        $this->assertTrue($comparison['enterprise']['is_upgrade']);
        $this->assertFalse($comparison['starter']['is_upgrade']);
    }
}
