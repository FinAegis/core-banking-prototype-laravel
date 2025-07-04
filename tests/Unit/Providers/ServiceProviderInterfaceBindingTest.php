<?php

namespace Tests\Unit\Providers;

use Tests\TestCase;
use App\Domain\Exchange\Contracts\ExchangeServiceInterface;
use App\Domain\Exchange\Contracts\FeeCalculatorInterface;
use App\Domain\Exchange\Contracts\ExternalLiquidityServiceInterface;
use App\Domain\Exchange\Contracts\LiquidityPoolServiceInterface;
use App\Domain\Stablecoin\Contracts\CollateralServiceInterface;
use App\Domain\Stablecoin\Contracts\LiquidationServiceInterface;
use App\Domain\Stablecoin\Contracts\StabilityMechanismServiceInterface;
use App\Domain\Stablecoin\Contracts\StablecoinIssuanceServiceInterface;
use App\Domain\Wallet\Contracts\WalletServiceInterface;
use App\Domain\Wallet\Contracts\WalletConnectorInterface;
use App\Domain\Exchange\Services\ExchangeService;
use App\Domain\Exchange\Services\FeeCalculator;
use App\Domain\Exchange\Services\ExternalLiquidityService;
use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Stablecoin\Services\LiquidationService;
use App\Domain\Stablecoin\Services\StabilityMechanismService;
use App\Domain\Stablecoin\Services\StablecoinIssuanceService;
use App\Domain\Wallet\Services\WalletService;
use App\Domain\Wallet\Services\BlockchainWalletService;

class ServiceProviderInterfaceBindingTest extends TestCase
{
    /**
     * Test Exchange service interfaces are properly bound
     */
    public function test_exchange_service_interfaces_are_bound()
    {
        // Exchange Service
        $exchangeService = app(ExchangeServiceInterface::class);
        $this->assertInstanceOf(ExchangeService::class, $exchangeService);
        
        // Fee Calculator
        $feeCalculator = app(FeeCalculatorInterface::class);
        $this->assertInstanceOf(FeeCalculator::class, $feeCalculator);
        
        // External Liquidity Service
        $externalLiquidity = app(ExternalLiquidityServiceInterface::class);
        $this->assertInstanceOf(ExternalLiquidityService::class, $externalLiquidity);
        
        // Liquidity Pool Service
        $liquidityPool = app(LiquidityPoolServiceInterface::class);
        $this->assertInstanceOf(LiquidityPoolService::class, $liquidityPool);
    }

    /**
     * Test Stablecoin service interfaces are properly bound
     */
    public function test_stablecoin_service_interfaces_are_bound()
    {
        // Collateral Service
        $collateralService = app(CollateralServiceInterface::class);
        $this->assertInstanceOf(CollateralService::class, $collateralService);
        
        // Liquidation Service
        $liquidationService = app(LiquidationServiceInterface::class);
        $this->assertInstanceOf(LiquidationService::class, $liquidationService);
        
        // Stability Mechanism Service
        $stabilityService = app(StabilityMechanismServiceInterface::class);
        $this->assertInstanceOf(StabilityMechanismService::class, $stabilityService);
        
        // Stablecoin Issuance Service
        $issuanceService = app(StablecoinIssuanceServiceInterface::class);
        $this->assertInstanceOf(StablecoinIssuanceService::class, $issuanceService);
    }

    /**
     * Test Wallet service interfaces are properly bound
     */
    public function test_wallet_service_interfaces_are_bound()
    {
        // Wallet Service
        $walletService = app(WalletServiceInterface::class);
        $this->assertInstanceOf(WalletService::class, $walletService);
        
        // Wallet Connector (Blockchain Wallet Service)
        $walletConnector = app(WalletConnectorInterface::class);
        $this->assertInstanceOf(BlockchainWalletService::class, $walletConnector);
    }

    /**
     * Test services are singletons
     */
    public function test_services_are_registered_as_singletons()
    {
        // Exchange services
        $exchange1 = app(ExchangeServiceInterface::class);
        $exchange2 = app(ExchangeServiceInterface::class);
        $this->assertSame($exchange1, $exchange2);
        
        // Stablecoin services
        $collateral1 = app(CollateralServiceInterface::class);
        $collateral2 = app(CollateralServiceInterface::class);
        $this->assertSame($collateral1, $collateral2);
        
        // Wallet services
        $wallet1 = app(WalletServiceInterface::class);
        $wallet2 = app(WalletServiceInterface::class);
        $this->assertSame($wallet1, $wallet2);
    }

    /**
     * Test concrete classes are still resolvable for backward compatibility
     */
    public function test_concrete_classes_are_resolvable()
    {
        // Exchange services
        $this->assertInstanceOf(ExchangeService::class, app(ExchangeService::class));
        $this->assertInstanceOf(FeeCalculator::class, app(FeeCalculator::class));
        
        // Stablecoin services
        $this->assertInstanceOf(CollateralService::class, app(CollateralService::class));
        $this->assertInstanceOf(LiquidationService::class, app(LiquidationService::class));
        
        // Wallet services
        $this->assertInstanceOf(WalletService::class, app(WalletService::class));
        $this->assertInstanceOf(BlockchainWalletService::class, app(BlockchainWalletService::class));
    }
}