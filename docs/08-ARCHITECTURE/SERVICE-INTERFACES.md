# Service Interfaces Architecture

This document describes the service interface architecture implemented in Phase 8 to improve testability, flexibility, and adherence to SOLID principles.

## Overview

We've implemented interfaces for user-facing services while keeping internal/technical services as concrete implementations. This approach balances clean architecture with pragmatism.

## Interface Design Principles

### 1. User-Facing Services Get Interfaces
Services that provide functionality directly consumed by the frontend or API endpoints have interfaces.

### 2. Internal Services Remain Concrete
Technical services like registries, key management, and internal orchestration remain as concrete implementations.

### 3. Focus on Frontend Needs
Interfaces define methods that support user interactions and frontend requirements.

## Implemented Interfaces

### Exchange Domain

#### ExchangeServiceInterface
**Purpose**: Core trading functionality
```php
interface ExchangeServiceInterface
{
    public function placeOrder(...): array;
    public function cancelOrder(string $orderId, string $reason): array;
    public function getOrderBook(string $baseCurrency, string $quoteCurrency, int $depth): array;
    public function getMarketData(string $baseCurrency, string $quoteCurrency): array;
}
```

#### FeeCalculatorInterface
**Purpose**: Trading fee calculations
```php
interface FeeCalculatorInterface
{
    public function calculateFees(...): object;
    public function calculateMinimumOrderValue(...): BigDecimal;
}
```

#### LiquidityPoolServiceInterface
**Purpose**: Liquidity pool management
```php
interface LiquidityPoolServiceInterface
{
    public function createPool(...): string;
    public function addLiquidity(...): array;
    public function removeLiquidity(...): array;
    public function swap(...): array;
}
```

#### ExternalLiquidityServiceInterface
**Purpose**: External exchange integration
```php
interface ExternalLiquidityServiceInterface
{
    public function findArbitrageOpportunities(...): array;
    public function provideLiquidity(...): array;
    public function alignPrices(...): array;
}
```

### Stablecoin Domain

#### StablecoinIssuanceServiceInterface
**Purpose**: Stablecoin minting and burning
```php
interface StablecoinIssuanceServiceInterface
{
    public function mint(...): array;
    public function burn(...): array;
    public function addCollateral(...): void;
    public function removeCollateral(...): void;
}
```

#### CollateralServiceInterface
**Purpose**: Collateral management
```php
interface CollateralServiceInterface
{
    public function convertToPegAsset(...): int;
    public function calculateTotalCollateralValue(...): int;
    public function getPositionsAtRisk(...): Collection;
    public function getPositionsForLiquidation(): Collection;
}
```

#### LiquidationServiceInterface
**Purpose**: Position liquidation
```php
interface LiquidationServiceInterface
{
    public function checkPosition(...): array;
    public function liquidatePosition(...): array;
    public function batchLiquidate(...): array;
}
```

#### StabilityMechanismServiceInterface
**Purpose**: Stability monitoring and control
```php
interface StabilityMechanismServiceInterface
{
    public function executeStabilityMechanisms(): array;
    public function checkSystemHealth(): array;
    public function checkPegDeviation(...): array;
}
```

### Wallet Domain

#### WalletServiceInterface
**Purpose**: Wallet operations
```php
interface WalletServiceInterface
{
    public function createWallet(...): array;
    public function getBalance(...): array;
    public function deposit(...): array;
    public function withdraw(...): array;
    public function transfer(...): array;
}
```

#### WalletConnectorInterface
**Purpose**: Blockchain wallet integration
```php
interface WalletConnectorInterface
{
    public function generateAddress(...): string;
    public function getBalance(...): BigDecimal;
    public function sendTransaction(...): string;
    public function getTransactionStatus(...): array;
}
```

## Service Provider Configuration

### ExchangeServiceProvider
```php
public function register(): void
{
    // Interface bindings
    $this->app->singleton(ExchangeServiceInterface::class, ExchangeService::class);
    $this->app->singleton(FeeCalculatorInterface::class, FeeCalculator::class);
    $this->app->singleton(ExternalLiquidityServiceInterface::class, ExternalLiquidityService::class);
    $this->app->singleton(LiquidityPoolServiceInterface::class, LiquidityPoolService::class);
}
```

### StablecoinServiceProvider
```php
public function register(): void
{
    // Interface bindings
    $this->app->singleton(CollateralServiceInterface::class, CollateralService::class);
    $this->app->singleton(LiquidationServiceInterface::class, LiquidationService::class);
    $this->app->singleton(StabilityMechanismServiceInterface::class, StabilityMechanismService::class);
    $this->app->singleton(StablecoinIssuanceServiceInterface::class, StablecoinIssuanceService::class);
}
```

### WalletServiceProvider
```php
public function register(): void
{
    // Interface bindings
    $this->app->singleton(WalletServiceInterface::class, WalletService::class);
    $this->app->singleton(WalletConnectorInterface::class, BlockchainWalletService::class);
}
```

## Usage Examples

### Controller Using Interface
```php
class ExchangeController extends Controller
{
    public function __construct(
        private ExchangeServiceInterface $exchangeService,
        private FeeCalculatorInterface $feeCalculator
    ) {}

    public function placeOrder(Request $request)
    {
        $fees = $this->feeCalculator->calculateFees(...);
        $order = $this->exchangeService->placeOrder(...);
        
        return response()->json($order);
    }
}
```

### Testing with Mocks
```php
class ExchangeControllerTest extends TestCase
{
    public function test_place_order()
    {
        $mockExchange = $this->mock(ExchangeServiceInterface::class);
        $mockExchange->shouldReceive('placeOrder')
            ->once()
            ->andReturn(['order_id' => 'test-123']);
        
        $response = $this->postJson('/api/exchange/order', [...]);
        
        $response->assertStatus(200)
            ->assertJson(['order_id' => 'test-123']);
    }
}
```

## Benefits

1. **Testability**: Easy to mock interfaces in tests
2. **Flexibility**: Can swap implementations without changing dependent code
3. **Dependency Injection**: Laravel container handles interface resolution
4. **Contract Definition**: Clear API contracts for frontend integration
5. **SOLID Principles**: Adheres to Dependency Inversion Principle

## Excluded Services (Internal Only)

These services intentionally don't have interfaces as they're internal:

- **ExchangeRateProviderRegistry**: Internal provider management
- **OrderMatchingService**: Internal matching engine
- **OracleAggregator**: Internal price aggregation
- **KeyManagementService**: Security-sensitive, not exposed
- **ExternalExchangeService**: Internal exchange coordination

## Migration Guide

### For Existing Code
```php
// Old (still works)
public function __construct(ExchangeService $exchange) {}

// New (preferred)
public function __construct(ExchangeServiceInterface $exchange) {}
```

### For New Features
Always use interfaces when injecting these services:
```php
use App\Domain\Exchange\Contracts\ExchangeServiceInterface;
use App\Domain\Stablecoin\Contracts\CollateralServiceInterface;
use App\Domain\Wallet\Contracts\WalletServiceInterface;
```

## Testing Strategy

1. **Unit Tests**: Mock interfaces for isolated testing
2. **Integration Tests**: Use real implementations
3. **Contract Tests**: Verify interfaces match implementations

## Future Considerations

1. **API Versioning**: Interfaces make it easier to support multiple API versions
2. **Feature Toggles**: Can swap implementations based on feature flags
3. **External Integrations**: Easy to add new exchange/wallet connectors