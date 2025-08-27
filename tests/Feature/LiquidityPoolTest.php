<?php

namespace Tests\Feature;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Exchange\Aggregates\LiquidityPool;
use App\Domain\Exchange\Projections\LiquidityPool as PoolProjection;
use App\Domain\Exchange\Projections\LiquidityProvider;
use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\DomainTestCase;

class LiquidityPoolTest extends DomainTestCase
{
    use RefreshDatabase;

    protected LiquidityPoolService $liquidityService;

    protected User $systemUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->liquidityService = app(LiquidityPoolService::class);

        // Create system user for pool accounts
        $this->systemUser = User::firstOrCreate(['email' => 'system@finaegis.com'], [
            'name'     => 'System',
            'password' => bcrypt('system'),
            'uuid'     => Str::uuid()->toString(),
        ]);

        // Create assets
        Asset::firstOrCreate(['code' => 'BTC'], ['name' => 'Bitcoin', 'symbol' => '₿', 'type' => 'crypto', 'precision' => 8]);
        Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€', 'type' => 'fiat', 'precision' => 2]);

        // Create user with account
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name'      => 'Test Account',
        ]);

        // Give user some balances
        AccountBalance::create([
            'account_uuid'      => $this->account->uuid,
            'asset_code'        => 'BTC',
            'current_balance'   => '10.00000000',
            'available_balance' => '10.00000000',
            'locked_balance'    => '0',
        ]);

        AccountBalance::create([
            'account_uuid'      => $this->account->uuid,
            'asset_code'        => 'EUR',
            'current_balance'   => '50000.00',
            'available_balance' => '50000.00',
            'locked_balance'    => '0',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_a_liquidity_pool()
    {
        $poolId = $this->liquidityService->createPool('BTC', 'EUR', '0.003');

        $this->assertNotNull($poolId);

        $pool = PoolProjection::where('pool_id', $poolId)->first();
        $this->assertNotNull($pool);
        $this->assertEquals('BTC', $pool->base_currency);
        $this->assertEquals('EUR', $pool->quote_currency);
        $this->assertEquals('0.003000', $pool->fee_rate);
        $this->assertTrue($pool->is_active);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_cannot_create_duplicate_pools()
    {
        $this->liquidityService->createPool('BTC', 'EUR');

        $this->expectException(DomainException::class);
        $this->liquidityService->createPool('BTC', 'EUR');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_add_liquidity_to_pool()
    {
        $poolId = $this->liquidityService->createPool('BTC', 'EUR');

        // Directly use the aggregate for testing
        $pool = LiquidityPool::retrieve($poolId);
        $pool->addLiquidity(
            providerId: $this->account->uuid,
            baseAmount: '1.00000000',
            quoteAmount: '48000.00',
            minShares: '0',
            metadata: ['test' => true]
        )->persist();

        // Check pool reserves updated
        $poolProjection = PoolProjection::where('pool_id', $poolId)->first();
        $this->assertEquals('1.000000000000000000', $poolProjection->base_reserve);
        $this->assertEquals('48000.000000000000000000', $poolProjection->quote_reserve);
        $this->assertGreaterThan(0, $poolProjection->total_shares);

        // Check provider record created
        $provider = LiquidityProvider::where('pool_id', $poolId)
            ->where('provider_id', $this->account->uuid)
            ->first();
        $this->assertNotNull($provider);
        $this->assertGreaterThan(0, $provider->shares);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_remove_liquidity_from_pool()
    {
        // First add liquidity
        $poolId = $this->liquidityService->createPool('BTC', 'EUR');

        $pool = LiquidityPool::retrieve($poolId);
        $pool->addLiquidity(
            providerId: $this->account->uuid,
            baseAmount: '1.00000000',
            quoteAmount: '48000.00',
            minShares: '0',
            metadata: ['test' => true]
        )->persist();

        // Get provider shares
        $provider = LiquidityProvider::where('pool_id', $poolId)
            ->where('provider_id', $this->account->uuid)
            ->first();

        // Remove half the liquidity
        $halfShares = bcdiv($provider->shares, '2', 18);

        $pool->removeLiquidity(
            providerId: $this->account->uuid,
            shares: $halfShares,
            minBaseAmount: '0',
            minQuoteAmount: '0',
            metadata: ['test' => true]
        )->persist();

        // Check pool reserves updated
        $poolProjection = PoolProjection::where('pool_id', $poolId)->first();
        $this->assertEquals('0.500000000000000000', $poolProjection->base_reserve);
        $this->assertEquals('24000.000000000000000000', $poolProjection->quote_reserve);

        // Check provider shares updated
        $provider->refresh();
        $this->assertEquals($halfShares, $provider->shares);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_calculates_shares_correctly_for_second_provider()
    {
        $poolId = $this->liquidityService->createPool('BTC', 'EUR');

        // First provider adds liquidity
        $pool = LiquidityPool::retrieve($poolId);
        $pool->addLiquidity(
            providerId: $this->account->uuid,
            baseAmount: '1.00000000',
            quoteAmount: '48000.00',
            minShares: '0',
            metadata: ['test' => true]
        )->persist();

        // Create second user
        $user2 = User::factory()->create();
        $account2 = Account::factory()->create([
            'user_uuid' => $user2->uuid,
            'name'      => 'Test Account 2',
        ]);

        AccountBalance::create([
            'account_uuid'      => $account2->uuid,
            'asset_code'        => 'BTC',
            'current_balance'   => '2.00000000',
            'available_balance' => '2.00000000',
        ]);

        AccountBalance::create([
            'account_uuid'      => $account2->uuid,
            'asset_code'        => 'EUR',
            'current_balance'   => '100000.00',
            'available_balance' => '100000.00',
        ]);

        // Second provider adds liquidity (double the amount)
        $pool->addLiquidity(
            providerId: $account2->uuid,
            baseAmount: '2.00000000',
            quoteAmount: '96000.00',
            minShares: '0',
            metadata: ['test' => true]
        )->persist();

        // Check pool reserves
        $poolProjection = PoolProjection::where('pool_id', $poolId)->first();
        $this->assertEquals('3.000000000000000000', $poolProjection->base_reserve);
        $this->assertEquals('144000.000000000000000000', $poolProjection->quote_reserve);

        // Check providers have correct share percentages
        $provider1 = LiquidityProvider::where('pool_id', $poolId)
            ->where('provider_id', $this->account->uuid)
            ->first();
        $provider2 = LiquidityProvider::where('pool_id', $poolId)
            ->where('provider_id', $account2->uuid)
            ->first();

        // Provider 1 should have 1/3 of the pool
        $this->assertEquals('33.333333', substr($provider1->share_percentage, 0, 9));
        // Provider 2 should have 2/3 of the pool
        $this->assertEquals('66.666666', substr($provider2->share_percentage, 0, 9));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_liquidity_addition_with_wrong_ratio()
    {
        $poolId = $this->liquidityService->createPool('BTC', 'EUR');

        // First provider establishes ratio (1 BTC = 48000 EUR)
        $pool = LiquidityPool::retrieve($poolId);
        $pool->addLiquidity(
            providerId: $this->account->uuid,
            baseAmount: '1.00000000',
            quoteAmount: '48000.00',
            minShares: '0',
            metadata: ['test' => true]
        )->persist();

        // Create second user
        $user2 = User::factory()->create();
        $account2 = Account::factory()->create([
            'user_uuid' => $user2->uuid,
            'name'      => 'Test Account 2',
        ]);

        AccountBalance::create([
            'account_uuid'      => $account2->uuid,
            'asset_code'        => 'BTC',
            'current_balance'   => '1.00000000',
            'available_balance' => '1.00000000',
        ]);

        AccountBalance::create([
            'account_uuid'      => $account2->uuid,
            'asset_code'        => 'EUR',
            'current_balance'   => '50000.00',
            'available_balance' => '50000.00',
        ]);

        // Try to add liquidity with wrong ratio (should fail)
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Input amounts deviate too much from pool ratio');

        $pool->addLiquidity(
            providerId: $account2->uuid,
            baseAmount: '1.00000000',
            quoteAmount: '50000.00', // Wrong ratio
            minShares: '0',
            metadata: ['test' => true]
        )->persist();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_calculate_pool_metrics()
    {
        $poolId = $this->liquidityService->createPool('BTC', 'EUR');

        $pool = LiquidityPool::retrieve($poolId);
        $pool->addLiquidity(
            providerId: $this->account->uuid,
            baseAmount: '1.00000000',
            quoteAmount: '48000.00',
            minShares: '0',
            metadata: ['test' => true]
        )->persist();

        $metrics = $this->liquidityService->getPoolMetrics($poolId);

        $this->assertEquals($poolId, $metrics['pool_id']);
        $this->assertEquals('BTC', $metrics['base_currency']);
        $this->assertEquals('EUR', $metrics['quote_currency']);
        $this->assertEquals('1.000000000000000000', $metrics['base_reserve']);
        $this->assertEquals('48000.000000000000000000', $metrics['quote_reserve']);
        $this->assertEquals('48000', substr($metrics['spot_price'], 0, 5));
        $this->assertEquals('96000', substr($metrics['tvl'], 0, 5)); // 1 BTC + 48000 EUR in EUR terms
        $this->assertEquals(1, $metrics['provider_count']);
    }
}
