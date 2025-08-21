# Exchange Domain - AI Agent Guide

## Purpose
This domain handles all cryptocurrency and fiat exchange operations, including order matching, liquidity pools, market making, and trading workflows.

## Key Components

### Aggregates
- **OrderBookAggregate**: Manages order book state and order matching logic
- **LiquidityPoolAggregate**: Handles liquidity pool operations and AMM functionality
- **TradeAggregate**: Records and manages executed trades

### Services
- **OrderMatchingService**: Core order matching engine with price-time priority
- **LiquidityPoolService**: Pool management, liquidity provision, and fee calculations
- **ExchangeService**: Main service for placing, canceling, and executing orders
- **FeeTierService**: Volume-based fee tier management (Retail to VIP levels)
- **AutomatedMarketMakerService**: Automated market making with spread management
- **ImpermanentLossProtectionService**: IL protection calculations and compensation

### Sagas
- **OrderRoutingSaga**: Intelligent order routing across multiple pools
- **SpreadManagementSaga**: Dynamic spread adjustment based on market conditions

### Workflows
- **MarketMakerWorkflow**: Multi-step market making with risk management
- **OrderMatchingWorkflow**: Complex order matching with partial fills
- **LiquidityProvisionWorkflow**: Adding/removing liquidity with rewards

### Events (Event Sourcing)
All events extend `ShouldBeStored` for event sourcing:
- OrderPlaced, OrderMatched, OrderCancelled, OrderExecuted
- LiquidityAdded, LiquidityRemoved, LiquidityPoolCreated
- SpreadAdjusted, InventoryImbalanceDetected
- FeeTierUpdated, UserFeeTierAssigned

## Common Tasks

### Place a Market Order
```php
use App\Domain\Exchange\Services\ExchangeService;

$service = app(ExchangeService::class);
$order = $service->placeOrder(
    accountId: 'user-123',
    type: 'buy',
    orderType: 'market',
    baseCurrency: 'BTC',
    quoteCurrency: 'USDT',
    amount: '0.5'
);
```

### Add Liquidity to Pool
```php
use App\Domain\Exchange\Services\LiquidityPoolService;

$service = app(LiquidityPoolService::class);
$position = $service->addLiquidity(
    poolId: 'pool-123',
    providerId: 'user-456',
    baseAmount: '10',
    quoteAmount: '500000'
);
```

### Calculate Fee Tier
```php
use App\Domain\Exchange\Services\FeeTierService;

$service = app(FeeTierService::class);
$tier = $service->getUserFeeTier('user-123');
// Returns: ['tier' => ['name' => 'Gold', 'maker_fee' => 15, ...]]
```

## Testing

### Key Test Files
- `tests/Unit/Domain/Exchange/Services/ExchangeServiceTest.php`
- `tests/Unit/Domain/Exchange/Sagas/OrderRoutingSagaTest.php`
- `tests/Feature/Exchange/ExchangeWorkflowTest.php`

### Running Tests
```bash
# Run all Exchange domain tests
./vendor/bin/pest tests/Unit/Domain/Exchange tests/Feature/Exchange

# Run with coverage
./vendor/bin/pest --coverage --min=50 tests/Unit/Domain/Exchange
```

## Database

### Main Tables
- `orders`: Active orders and order history
- `liquidity_pools`: Pool configuration and reserves
- `trades`: Executed trades history
- `exchange_events`: Event sourcing storage
- `exchange_snapshots`: Aggregate snapshots

### Migrations
Located in `database/migrations/`:
- `create_orders_table.php`
- `create_liquidity_pools_table.php`
- `create_trades_table.php`

## API Endpoints

### Order Management
- `POST /api/v1/orders` - Place new order
- `GET /api/v1/orders/{id}` - Get order details
- `DELETE /api/v1/orders/{id}` - Cancel order
- `GET /api/v1/orders` - List user orders

### Liquidity Pools
- `GET /api/v1/pools` - List available pools
- `POST /api/v1/pools/{id}/liquidity` - Add liquidity
- `DELETE /api/v1/pools/{id}/liquidity` - Remove liquidity
- `GET /api/v1/pools/{id}/stats` - Pool statistics

### Market Data
- `GET /api/v1/orderbook/{pair}` - Get order book
- `GET /api/v1/ticker/{pair}` - Get ticker data
- `GET /api/v1/trades/{pair}` - Recent trades

## Configuration

### Environment Variables
```env
# Exchange Configuration
EXCHANGE_MATCHING_ENGINE=async
EXCHANGE_FEE_MODEL=tiered
EXCHANGE_MIN_ORDER_SIZE=10
EXCHANGE_MAX_SLIPPAGE=0.02

# Liquidity Pool Settings
POOL_MIN_LIQUIDITY=1000
POOL_FEE_TIER_STABLE=5
POOL_FEE_TIER_STANDARD=30
POOL_FEE_TIER_EXOTIC=100

# Market Making
MARKET_MAKER_ENABLED=true
MARKET_MAKER_SPREAD_BASIS_POINTS=20
MARKET_MAKER_MAX_INVENTORY_IMBALANCE=0.2
```

## Best Practices

1. **Always use services** for business logic, not controllers
2. **Event sourcing** for all state changes - dispatch domain events
3. **Use sagas** for complex multi-step operations
4. **Implement idempotency** for critical operations
5. **Add comprehensive logging** for trading operations
6. **Test with demo services** before production

## Common Issues

### Order Not Matching
- Check order book state
- Verify price compatibility
- Ensure sufficient liquidity
- Check fee tier calculations

### Liquidity Pool Errors
- Verify pool is active (`is_active = true`)
- Check minimum liquidity requirements
- Ensure balanced reserves for initial liquidity
- Verify fee tier configuration

### Event Sourcing Issues
- Events must extend `ShouldBeStored`
- Use projectors to build read models
- Implement snapshots for performance
- Handle event versioning carefully

## AI Agent Tips

- Use OrderRoutingSaga for complex order routing logic
- SpreadManagementSaga handles market volatility automatically
- Fee tiers are volume-based and cached for performance
- All monetary values are stored in smallest units (cents/satoshis)
- Use demo services for development (DemoExchangeService)
- Event sourcing provides complete audit trail