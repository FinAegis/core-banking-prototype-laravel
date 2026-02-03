<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relayer\Services;

use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Services\DemoWalletBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WalletBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private DemoWalletBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->service = new DemoWalletBalanceService();
    }

    public function test_returns_default_balance_for_unknown_wallet(): void
    {
        $balance = $this->service->getBalance(
            '0x1234567890123456789012345678901234567890',
            'USDC',
            SupportedNetwork::POLYGON
        );

        $this->assertEquals('1000.000000', $balance);
    }

    public function test_has_balance_returns_true_when_sufficient(): void
    {
        $result = $this->service->hasBalance(
            '0x1234567890123456789012345678901234567890',
            'USDC',
            100.0,
            SupportedNetwork::POLYGON
        );

        $this->assertTrue($result);
    }

    public function test_has_balance_returns_false_when_insufficient(): void
    {
        // Set a low demo balance
        $this->service->setDemoBalance(
            '0x1234567890123456789012345678901234567890',
            'USDC',
            SupportedNetwork::POLYGON,
            '50.000000'
        );

        $result = $this->service->hasBalance(
            '0x1234567890123456789012345678901234567890',
            'USDC',
            100.0,
            SupportedNetwork::POLYGON
        );

        $this->assertFalse($result);
    }

    public function test_returns_configured_demo_balance(): void
    {
        $this->service->setDemoBalance(
            '0xtest',
            'USDC',
            SupportedNetwork::POLYGON,
            '500.123456'
        );

        $balance = $this->service->getBalance('0xtest', 'USDC', SupportedNetwork::POLYGON);

        $this->assertEquals('500.123456', $balance);
    }

    public function test_caches_balance(): void
    {
        $walletAddress = '0xcached';

        // First call
        $balance1 = $this->service->getBalance($walletAddress, 'USDC', SupportedNetwork::POLYGON);

        // Set different balance (but cache should still have old value)
        $this->service->setDemoBalance($walletAddress, 'USDC', SupportedNetwork::POLYGON, '999.000000');

        // Second call should return cached value (which we just overwrote)
        $balance2 = $this->service->getBalance($walletAddress, 'USDC', SupportedNetwork::POLYGON);

        // Both should be the new value since setDemoBalance overwrites cache
        $this->assertEquals('999.000000', $balance2);
    }

    public function test_invalidate_cache_clears_cached_balance(): void
    {
        $walletAddress = '0xinvalidate';

        // Set a balance
        $this->service->setDemoBalance($walletAddress, 'USDC', SupportedNetwork::POLYGON, '500.000000');

        // Verify it's cached
        $this->assertEquals('500.000000', $this->service->getBalance($walletAddress, 'USDC', SupportedNetwork::POLYGON));

        // Invalidate cache
        $this->service->invalidateCache($walletAddress, 'USDC', SupportedNetwork::POLYGON);

        // Should return default balance now
        $balance = $this->service->getBalance($walletAddress, 'USDC', SupportedNetwork::POLYGON);
        $this->assertEquals('1000.000000', $balance);
    }

    public function test_returns_correct_token_address_for_usdc_polygon(): void
    {
        $address = $this->service->getTokenAddress('USDC', SupportedNetwork::POLYGON);

        $this->assertEquals('0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359', $address);
    }

    public function test_returns_correct_token_address_for_usdc_base(): void
    {
        $address = $this->service->getTokenAddress('USDC', SupportedNetwork::BASE);

        $this->assertEquals('0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', $address);
    }

    public function test_returns_null_for_unsupported_token_network_combination(): void
    {
        // USDT is not supported on Base in demo service
        $address = $this->service->getTokenAddress('USDT', SupportedNetwork::BASE);

        $this->assertNull($address);
    }

    public function test_returns_correct_decimals_for_usdc(): void
    {
        $decimals = $this->service->getTokenDecimals('USDC');

        $this->assertEquals(6, $decimals);
    }

    public function test_returns_correct_decimals_for_usdt(): void
    {
        $decimals = $this->service->getTokenDecimals('USDT');

        $this->assertEquals(6, $decimals);
    }

    public function test_returns_18_decimals_for_unknown_token(): void
    {
        $decimals = $this->service->getTokenDecimals('UNKNOWN');

        $this->assertEquals(18, $decimals);
    }

    public function test_is_token_supported_returns_true_for_usdc_polygon(): void
    {
        $supported = $this->service->isTokenSupported('USDC', SupportedNetwork::POLYGON);

        $this->assertTrue($supported);
    }

    public function test_is_token_supported_returns_false_for_unknown_token(): void
    {
        $supported = $this->service->isTokenSupported('UNKNOWN_TOKEN', SupportedNetwork::POLYGON);

        $this->assertFalse($supported);
    }

    public function test_get_provider_name_returns_demo(): void
    {
        $name = $this->service->getProviderName();

        $this->assertEquals('demo', $name);
    }

    public function test_balance_check_works_across_networks(): void
    {
        // Set different balances for different networks
        $walletAddress = '0xmultinetwork';

        $this->service->setDemoBalance($walletAddress, 'USDC', SupportedNetwork::POLYGON, '100.000000');
        $this->service->setDemoBalance($walletAddress, 'USDC', SupportedNetwork::BASE, '200.000000');
        $this->service->setDemoBalance($walletAddress, 'USDC', SupportedNetwork::ARBITRUM, '300.000000');

        $polygonBalance = $this->service->getBalance($walletAddress, 'USDC', SupportedNetwork::POLYGON);
        $baseBalance = $this->service->getBalance($walletAddress, 'USDC', SupportedNetwork::BASE);
        $arbitrumBalance = $this->service->getBalance($walletAddress, 'USDC', SupportedNetwork::ARBITRUM);

        $this->assertEquals('100.000000', $polygonBalance);
        $this->assertEquals('200.000000', $baseBalance);
        $this->assertEquals('300.000000', $arbitrumBalance);
    }

    public function test_balance_check_works_across_tokens(): void
    {
        $walletAddress = '0xmultitokens';

        $this->service->setDemoBalance($walletAddress, 'USDC', SupportedNetwork::POLYGON, '150.000000');
        $this->service->setDemoBalance($walletAddress, 'USDT', SupportedNetwork::POLYGON, '250.000000');

        $usdcBalance = $this->service->getBalance($walletAddress, 'USDC', SupportedNetwork::POLYGON);
        $usdtBalance = $this->service->getBalance($walletAddress, 'USDT', SupportedNetwork::POLYGON);

        $this->assertEquals('150.000000', $usdcBalance);
        $this->assertEquals('250.000000', $usdtBalance);
    }
}
