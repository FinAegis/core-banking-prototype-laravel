<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exchange\LiquidityPool\Services;

use App\Domain\Exchange\LiquidityPool\Services\ImpermanentLossProtectionService;
use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Projections\LiquidityProvider;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImpermanentLossProtectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ImpermanentLossProtectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImpermanentLossProtectionService();
    }

    #[Test]
    public function it_calculates_impermanent_loss_correctly(): void
    {
        // Create a pool
        $pool = new LiquidityPool();
        $pool->pool_id = 'test-pool';
        $pool->base_currency = 'ETH';
        $pool->quote_currency = 'USDC';
        $pool->base_reserve = '100';
        $pool->quote_reserve = '200000'; // Price: 2000 USDC per ETH
        $pool->metadata = ['il_protection_enabled' => true];
        $pool->save();

        // Create a position
        $position = new LiquidityProvider();
        $position->pool_id = $pool->pool_id;
        $position->provider_id = 'provider-1';
        $position->initial_base_amount = 10.0;
        $position->initial_quote_amount = 20000.0;
        $position->shares = 1000.0;
        $position->metadata = ['entry_base_price' => '2000'];
        $position->created_at = now()->subDays(30);
        $position->save();
        $position->setRelation('pool', $pool);

        // Calculate IL when price doubles
        $currentPrice = BigDecimal::of('4000');
        $result = $this->service->calculateImpermanentLoss($position, $currentPrice);

        // Result is array
        $this->assertEquals($position->id, $result['position_id']);
        $this->assertEquals('2000', $result['entry_price']);
        $this->assertEquals('4000', $result['current_price']);

        // Verify IL is positive (loss occurred)
        $il = BigDecimal::of($result['impermanent_loss']);
        $this->assertTrue($il->isGreaterThan(0));

        // Verify IL percentage is reasonable (should be around 5.7% for 2x price change)
        $ilPercent = BigDecimal::of($result['impermanent_loss_percent']);
        $this->assertTrue($ilPercent->isGreaterThan(5));
        $this->assertTrue($ilPercent->isLessThan(7));
    }

    #[Test]
    public function it_determines_protection_eligibility_correctly(): void
    {
        $pool = new LiquidityPool();
        $pool->pool_id = 'test-pool';
        $pool->base_currency = 'ETH';
        $pool->quote_currency = 'USDC';
        $pool->base_reserve = '100';
        $pool->quote_reserve = '200000';
        $pool->metadata = ['il_protection_enabled' => true];
        $pool->save();

        // Reload to ensure metadata is properly saved
        $pool = LiquidityPool::where('pool_id', 'test-pool')->first();
        $this->assertTrue($pool->metadata['il_protection_enabled'] ?? false, 'Pool metadata should be saved');

        // Position held for less than minimum period
        $newPosition = new LiquidityProvider();
        $newPosition->pool_id = $pool->pool_id;
        $newPosition->provider_id = 'provider-1';
        $newPosition->shares = 100.0;
        $newPosition->initial_base_amount = 1.0;
        $newPosition->initial_quote_amount = 2000.0;
        $newPosition->created_at = now()->subDays(3); // Only 3 days
        $newPosition->save();
        $newPosition->setRelation('pool', $pool);

        $this->assertFalse($this->service->isEligibleForProtection($newPosition));

        // Position held for more than minimum period
        $oldPosition = new LiquidityProvider();
        $oldPosition->pool_id = $pool->pool_id;
        $oldPosition->provider_id = 'provider-2';
        $oldPosition->shares = 100.0;
        $oldPosition->initial_base_amount = 1.0;
        $oldPosition->initial_quote_amount = 2000.0;
        $oldPosition->created_at = now()->subDays(10); // 10 days
        $oldPosition->save();
        $oldPosition->setRelation('pool', $pool);

        // Debug: check pool metadata
        $this->assertNotNull($oldPosition->pool);
        $this->assertTrue($oldPosition->pool->metadata['il_protection_enabled'] ?? false, 'Pool IL protection should be enabled');

        // Debug more info
        $holdingHours = $oldPosition->created_at->diffInHours(now());
        $this->assertGreaterThan(7 * 24, $holdingHours, 'Should have enough holding hours'); // Should be > 168 hours
        $this->assertGreaterThan(0, $oldPosition->shares, 'Should have shares');

        $this->assertTrue($this->service->isEligibleForProtection($oldPosition));

        // Inactive position (no shares)
        $inactivePosition = new LiquidityProvider();
        $inactivePosition->pool_id = $pool->pool_id;
        $inactivePosition->provider_id = 'provider-3';
        $inactivePosition->shares = 0.0; // No shares = inactive
        $inactivePosition->initial_base_amount = 1.0;
        $inactivePosition->initial_quote_amount = 2000.0;
        $inactivePosition->created_at = now()->subDays(30);
        $inactivePosition->save();
        $inactivePosition->setRelation('pool', $pool);

        $this->assertFalse($this->service->isEligibleForProtection($inactivePosition));
    }

    #[Test]
    public function it_calculates_compensation_based_on_holding_period(): void
    {
        $pool = new LiquidityPool();
        $pool->pool_id = 'test-pool';
        $pool->base_currency = 'ETH';
        $pool->quote_currency = 'USDC';
        $pool->base_reserve = '100';
        $pool->quote_reserve = '200000';
        $pool->metadata = ['il_protection_enabled' => true];
        $pool->save();

        // 30-day position gets 40% coverage
        $position30Days = new LiquidityProvider();
        $position30Days->pool_id = $pool->pool_id;
        $position30Days->provider_id = 'provider-1';
        $position30Days->initial_base_amount = 10.0;
        $position30Days->initial_quote_amount = 20000.0;
        $position30Days->shares = 100.0;
        $position30Days->created_at = now()->subDays(30);
        $position30Days->save();
        $position30Days->setRelation('pool', $pool);

        $ilLoss = BigDecimal::of('1000'); // $1000 IL
        $ilPercent = BigDecimal::of('5'); // 5% IL

        $compensation = $this->service->calculateProtectionCompensation(
            $position30Days,
            $ilLoss,
            $ilPercent
        );

        $this->assertTrue($compensation['eligible']);
        // 5% - 2% threshold = 3% excess loss
        // 3% * 40% coverage = 1.2% compensation
        $this->assertStringContainsString('3', $compensation['excess_loss']);
        $this->assertStringContainsString('40', $compensation['coverage_rate']);

        // 180-day position gets 80% coverage
        $position180Days = new LiquidityProvider();
        $position180Days->pool_id = $pool->pool_id;
        $position180Days->provider_id = 'provider-2';
        $position180Days->initial_base_amount = 10.0;
        $position180Days->initial_quote_amount = 20000.0;
        $position180Days->shares = 100.0;
        $position180Days->created_at = now()->subDays(180);
        $position180Days->save();
        $position180Days->setRelation('pool', $pool);

        $compensation180 = $this->service->calculateProtectionCompensation(
            $position180Days,
            $ilLoss,
            $ilPercent
        );

        $this->assertTrue($compensation180['eligible']);
        $this->assertStringContainsString('80', $compensation180['coverage_rate']);
    }

    #[Test]
    public function it_does_not_compensate_below_threshold(): void
    {
        $pool = new LiquidityPool();
        $pool->pool_id = 'test-pool';
        $pool->base_currency = 'ETH';
        $pool->quote_currency = 'USDC';
        $pool->base_reserve = '100';
        $pool->quote_reserve = '200000';
        $pool->metadata = ['il_protection_enabled' => true];
        $pool->save();

        $position = new LiquidityProvider();
        $position->pool_id = $pool->pool_id;
        $position->provider_id = 'provider-1';
        $position->initial_base_amount = 10.0;
        $position->initial_quote_amount = 20000.0;
        $position->shares = 100.0;
        $position->created_at = now()->subDays(30);
        $position->save();
        $position->setRelation('pool', $pool);

        // IL below 2% threshold
        $smallLoss = BigDecimal::of('300');
        $smallPercent = BigDecimal::of('1.5');

        $compensation = $this->service->calculateProtectionCompensation(
            $position,
            $smallLoss,
            $smallPercent
        );

        $this->assertFalse($compensation['eligible']);
        $this->assertEquals('Impermanent loss below protection threshold', $compensation['reason']);
        $this->assertEquals('0', $compensation['compensation']);
    }

    #[Test]
    public function it_estimates_protection_fund_requirements(): void
    {
        $pool = new LiquidityPool();
        $pool->pool_id = 'test-pool';
        $pool->base_currency = 'ETH';
        $pool->quote_currency = 'USDC';
        $pool->base_reserve = '1000';
        $pool->quote_reserve = '2000000';
        $pool->metadata = ['il_protection_enabled' => true];
        $pool->save();

        // Create multiple positions
        for ($i = 1; $i <= 5; $i++) {
            $position = new LiquidityProvider();
            $position->pool_id = $pool->pool_id;
            $position->provider_id = "provider-$i";
            $position->initial_base_amount = 10.0;
            $position->initial_quote_amount = 20000.0;
            $position->shares = 100.0;
            $position->created_at = now()->subDays($i * 20);
            $position->save();
        }

        $requirements = $this->service->estimateProtectionFundRequirements($pool->pool_id);

        // Requirements is array
        $this->assertEquals('test-pool', $requirements['pool_id']);
        $this->assertEquals('USDC', $requirements['fund_currency']);
        $this->assertGreaterThan(0, $requirements['total_liquidity_value']);
        $this->assertGreaterThan(0, $requirements['protected_value']);
        $this->assertGreaterThan(0, $requirements['max_potential_compensation']);
        $this->assertGreaterThan(0, $requirements['recommended_fund_size']);
    }

    #[Test]
    public function it_processes_protection_claims_for_eligible_positions(): void
    {
        $pool = new LiquidityPool();
        $pool->pool_id = 'test-pool';
        $pool->base_currency = 'ETH';
        $pool->quote_currency = 'USDC';
        $pool->base_reserve = '100';
        $pool->quote_reserve = '400000'; // Current price: 4000
        $pool->metadata = ['il_protection_enabled' => true];
        $pool->save();

        // Create eligible position with significant IL
        $position = new LiquidityProvider();
        $position->pool_id = $pool->pool_id;
        $position->provider_id = 'provider-1';
        $position->initial_base_amount = 10.0;
        $position->initial_quote_amount = 20000.0;
        $position->shares = 100.0;
        $position->metadata = ['entry_base_price' => '2000']; // Entry price was 2000
        $position->created_at = now()->subDays(30);
        $position->save();

        $claims = $this->service->processProtectionClaims($pool->pool_id);

        $this->assertCount(1, $claims);
        $claim = $claims->first();
        $this->assertEquals('provider-1', $claim['provider_id']);
        $this->assertEquals('test-pool', $claim['pool_id']);
        $this->assertGreaterThan(0, $claim['compensation']);
        $this->assertEquals('USDC', $claim['compensation_currency']);
    }
}
