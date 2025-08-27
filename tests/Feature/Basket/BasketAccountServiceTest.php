<?php

declare(strict_types=1);

namespace Tests\Feature\Basket;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Services\BasketAccountService;
use App\Domain\Basket\Services\BasketValueCalculationService;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\ServiceTestCase;

class BasketAccountServiceTest extends ServiceTestCase
{
    use RefreshDatabase;

    protected BasketAccountService $service;

    protected Account $account;

    protected BasketAsset $basket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BasketAccountService::class);

        // Create test user and account
        $user = User::factory()->create();
        $this->account = Account::factory()->zeroBalance()->create(['user_uuid' => $user->uuid]);

        // Create test assets (use firstOrCreate to avoid conflicts)
        Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'GBP'], ['name' => 'British Pound', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
        Asset::firstOrCreate(['code' => 'STABLE_BASKET'], ['name' => 'Stable Basket', 'type' => 'custom', 'precision' => 4, 'is_active' => true]);

        // Create exchange rates
        ExchangeRate::factory()->create([
            'from_asset_code' => 'EUR',
            'to_asset_code'   => 'USD',
            'rate'            => 1.1000,
            'is_active'       => true,
        ]);

        ExchangeRate::factory()->create([
            'from_asset_code' => 'GBP',
            'to_asset_code'   => 'USD',
            'rate'            => 1.2500,
            'is_active'       => true,
        ]);

        // Create test basket
        $this->basket = BasketAsset::create([
            'code'                => 'STABLE_BASKET',
            'name'                => 'Stable Currency Basket',
            'type'                => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active'           => true,
        ]);

        $this->basket->components()->createMany([
            ['asset_code' => 'USD', 'weight' => 40.0],
            ['asset_code' => 'EUR', 'weight' => 35.0],
            ['asset_code' => 'GBP', 'weight' => 25.0],
        ]);

        // Calculate basket value
        app(BasketValueCalculationService::class)->calculateValue($this->basket);
    }

    #[Test]
    public function it_can_decompose_basket_into_components()
    {
        // Give account some basket holdings
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'STABLE_BASKET',
            'balance'      => 10000,
        ]); // 100.00 basket units

        $result = $this->service->decomposeBasket($this->account, 'STABLE_BASKET', 5000); // 50.00 units

        $this->assertArrayHasKey('basket_code', $result);
        $this->assertArrayHasKey('basket_amount', $result);
        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('decomposed_at', $result);

        $this->assertEquals('STABLE_BASKET', $result['basket_code']);
        $this->assertEquals(5000, $result['basket_amount']);

        // Check component amounts (50% of basket)
        $this->assertEquals(2000, $result['components']['USD']); // 40% of 5000
        $this->assertEquals(1750, $result['components']['EUR']); // 35% of 5000
        $this->assertEquals(1250, $result['components']['GBP']); // 25% of 5000

        // Note: Balance updates happen asynchronously via event sourcing
        // So we only verify the returned result structure, not the actual balance changes
    }

    #[Test]
    public function it_cannot_decompose_with_insufficient_balance()
    {
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'STABLE_BASKET',
            'balance'      => 1000,
        ]); // 10.00 basket units

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient basket balance');

        $this->service->decomposeBasket($this->account, 'STABLE_BASKET', 2000); // 20.00 units
    }

    #[Test]
    public function it_can_compose_basket_from_components()
    {
        // Give account component balances
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 4000,
        ]);  // 40.00 USD
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'EUR',
            'balance'      => 3500,
        ]);  // 35.00 EUR
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'GBP',
            'balance'      => 2500,
        ]);  // 25.00 GBP

        $result = $this->service->composeBasket($this->account, 'STABLE_BASKET', 5000); // 50.00 units

        $this->assertArrayHasKey('basket_code', $result);
        $this->assertArrayHasKey('basket_amount', $result);
        $this->assertArrayHasKey('components_used', $result);
        $this->assertArrayHasKey('composed_at', $result);

        $this->assertEquals('STABLE_BASKET', $result['basket_code']);
        $this->assertEquals(5000, $result['basket_amount']);

        // Check components used
        $this->assertEquals(2000, $result['components_used']['USD']); // 40% of 5000
        $this->assertEquals(1750, $result['components_used']['EUR']); // 35% of 5000
        $this->assertEquals(1250, $result['components_used']['GBP']); // 25% of 5000

        // Note: Balance updates happen asynchronously via event sourcing
        // So we only verify the returned result structure, not the actual balance changes
    }

    #[Test]
    public function it_cannot_compose_with_insufficient_components()
    {
        // Give account insufficient component balances
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 1000,
        ]);  // 10.00 USD (need 20.00)
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'EUR',
            'balance'      => 3500,
        ]);  // 35.00 EUR
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'GBP',
            'balance'      => 2500,
        ]);  // 25.00 GBP

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient USD balance');

        $this->service->composeBasket($this->account, 'STABLE_BASKET', 5000);
    }

    #[Test]
    public function it_can_get_basket_holdings_value()
    {
        // Give account various basket holdings
        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'STABLE_BASKET',
            'balance'      => 10000,
        ]); // 100.00 units

        // Create another basket
        $cryptoBasket = BasketAsset::create([
            'code'                => 'CRYPTO_BASKET',
            'name'                => 'Crypto Basket',
            'type'                => 'fixed',
            'rebalance_frequency' => 'never',
            'is_active'           => true,
        ]);

        Asset::firstOrCreate(['code' => 'CRYPTO_BASKET'], ['name' => 'Crypto Basket', 'type' => 'custom', 'precision' => 4, 'is_active' => true]);

        $cryptoBasket->components()->create([
            'asset_code' => 'USD',
            'weight'     => 100.0,
        ]);

        $basketValue = app(BasketValueCalculationService::class)->calculateValue($cryptoBasket);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'CRYPTO_BASKET',
            'balance'      => 5000,
        ]); // 50.00 units

        $holdings = $this->service->getBasketHoldingsValue($this->account);

        $this->assertArrayHasKey('account_uuid', $holdings);
        $this->assertArrayHasKey('basket_holdings', $holdings);
        $this->assertArrayHasKey('total_value', $holdings);
        $this->assertArrayHasKey('currency', $holdings);

        $this->assertEquals($this->account->uuid, $holdings['account_uuid']);
        $this->assertEquals('USD', $holdings['currency']);
        $this->assertCount(2, $holdings['basket_holdings']);

        // Check individual basket holdings
        $stableHolding = collect($holdings['basket_holdings'])->firstWhere('basket_code', 'STABLE_BASKET');
        $this->assertEquals(10000, $stableHolding['balance']);
        $this->assertGreaterThan(0, $stableHolding['unit_value']);
        $this->assertGreaterThan(0, $stableHolding['total_value']);
    }

    #[Test]
    public function it_only_decomposes_active_components()
    {
        // Deactivate EUR component
        $this->basket->components()->where('asset_code', 'EUR')->update(['is_active' => false]);

        AccountBalance::create([
            'account_uuid' => $this->account->uuid,
            'asset_code'   => 'STABLE_BASKET',
            'balance'      => 10000,
        ]);

        $result = $this->service->decomposeBasket($this->account, 'STABLE_BASKET', 5000);

        // EUR should not be in components
        $this->assertArrayNotHasKey('EUR', $result['components']);
        $this->assertArrayHasKey('USD', $result['components']);
        $this->assertArrayHasKey('GBP', $result['components']);

        // Check no EUR balance was created
        $this->assertEquals(0, $this->account->getBalance('EUR'));
    }

    #[Test]
    public function it_handles_basket_not_found()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Basket not found: INVALID_BASKET');

        $this->service->decomposeBasket($this->account, 'INVALID_BASKET', 1000);
    }

    #[Test]
    public function it_handles_inactive_basket()
    {
        $this->basket->update(['is_active' => false]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Basket STABLE_BASKET is not active');

        $this->service->decomposeBasket($this->account, 'STABLE_BASKET', 1000);
    }

    #[Test]
    public function it_validates_positive_amounts()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Amount must be positive');

        $this->service->decomposeBasket($this->account, 'STABLE_BASKET', -1000);
    }

    #[Test]
    public function it_handles_no_basket_holdings()
    {
        $holdings = $this->service->getBasketHoldingsValue($this->account);

        $this->assertEmpty($holdings['basket_holdings']);
        $this->assertEquals(0, $holdings['total_value']);
    }

    #[Test]
    public function it_calculates_required_components()
    {
        $required = $this->service->calculateRequiredComponents('STABLE_BASKET', 10000);

        $this->assertArrayHasKey('USD', $required);
        $this->assertArrayHasKey('EUR', $required);
        $this->assertArrayHasKey('GBP', $required);

        $this->assertEquals(4000, $required['USD']); // 40% of 10000
        $this->assertEquals(3500, $required['EUR']); // 35% of 10000
        $this->assertEquals(2500, $required['GBP']); // 25% of 10000
    }
}
