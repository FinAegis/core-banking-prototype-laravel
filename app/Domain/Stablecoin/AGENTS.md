# Stablecoin Domain - AI Agent Guide

## Purpose
This domain manages stablecoin operations including minting, burning, redemption, reserve management, and regulatory compliance for digital currency issuance.

## Key Components

### Aggregates
- **StablecoinAggregate**: Core stablecoin state management with event sourcing
- **ReserveAggregate**: Reserve fund management and collateralization
- **CollateralAggregate**: Collateral position tracking and liquidation

### Services
- **StablecoinService**: Main service for minting, burning, and transfers
- **ReserveManagementService**: Reserve allocation and rebalancing
- **OracleService**: Price feed integration for collateral valuation
- **StabilityMechanismService**: Algorithmic stability maintenance
- **CollateralizationService**: Collateral ratio management and monitoring
- **DemoStablecoinService**: Demo implementation for development/testing

### Workflows
- **MintingWorkflow**: Multi-step minting with compliance checks
- **RedemptionWorkflow**: Stablecoin redemption with reserve withdrawal
- **RebalancingWorkflow**: Automatic reserve rebalancing
- **ComplianceWorkflow**: KYC/AML checks for large transactions

### Events (Event Sourcing)
All events extend `ShouldBeStored`:
- StablecoinMinted, StablecoinBurned, StablecoinTransferred
- ReserveDeposited, ReserveWithdrawn, ReserveRebalanced
- CollateralAdded, CollateralLiquidated
- StabilityAdjustmentTriggered, EmergencyPauseActivated

### Models
- **Stablecoin**: Main stablecoin configuration
- **StablecoinReserve**: Reserve holdings and allocations
- **StablecoinTransaction**: Transaction history
- **StablecoinCollateralPosition**: User collateral positions

## Common Tasks

### Mint Stablecoins
```php
use App\Domain\Stablecoin\Services\StablecoinService;

$service = app(StablecoinService::class);
$result = $service->mint(
    userId: 'user-123',
    amount: 1000000, // $10,000 in cents
    collateralType: 'USDT',
    collateralAmount: 10500 // 105% collateralization
);
```

### Burn/Redeem Stablecoins
```php
use App\Domain\Stablecoin\Services\StablecoinService;

$service = app(StablecoinService::class);
$result = $service->burn(
    userId: 'user-123',
    amount: 500000, // $5,000 in cents
    destinationAddress: '0xabc...'
);
```

### Check Collateralization Ratio
```php
use App\Domain\Stablecoin\Services\CollateralizationService;

$service = app(CollateralizationService::class);
$ratio = $service->getCollateralizationRatio('user-123');
// Returns: 1.5 (150% collateralized)
```

### Get Price from Oracle
```php
use App\Domain\Stablecoin\Services\OracleService;

$oracle = app(OracleService::class);
$price = $oracle->getPrice('BTC', 'USD');
// Returns: ['price' => 50000, 'timestamp' => '2025-01-08T10:00:00Z']
```

## Testing

### Key Test Files
- `tests/Unit/Domain/Stablecoin/Services/StablecoinServiceTest.php`
- `tests/Unit/Domain/Stablecoin/Services/StabilityMechanismServiceTest.php`
- `tests/Feature/Stablecoin/StablecoinIssuanceIntegrationTest.php`
- `tests/Feature/Stablecoin/StablecoinOperationsApiTest.php`

### Running Tests
```bash
# Run all Stablecoin domain tests
./vendor/bin/pest tests/Unit/Domain/Stablecoin tests/Feature/Stablecoin

# Run specific test suite
./vendor/bin/pest tests/Feature/Stablecoin/StablecoinApiTest.php
```

## Database

### Main Tables
- `stablecoins`: Stablecoin configurations
- `stablecoin_reserves`: Reserve holdings
- `stablecoin_transactions`: All stablecoin transactions
- `stablecoin_collateral_positions`: User collateral
- `stablecoin_events`: Event sourcing storage
- `stablecoin_snapshots`: Aggregate snapshots

### Migrations
Located in `database/migrations/`:
- `create_stablecoins_table.php`
- `create_stablecoin_reserves_table.php`
- `create_stablecoin_transactions_table.php`
- `create_stablecoin_collateral_positions_table.php`

## API Endpoints

### Stablecoin Operations
- `POST /api/v1/stablecoin/mint` - Mint new stablecoins
- `POST /api/v1/stablecoin/burn` - Burn/redeem stablecoins
- `POST /api/v1/stablecoin/transfer` - Transfer between users
- `GET /api/v1/stablecoin/balance/{userId}` - Get user balance

### Reserve Management
- `GET /api/v1/stablecoin/reserves` - View reserve status
- `POST /api/v1/stablecoin/reserves/rebalance` - Trigger rebalancing
- `GET /api/v1/stablecoin/reserves/audit` - Reserve audit report

### Collateral Management
- `POST /api/v1/stablecoin/collateral` - Add collateral
- `DELETE /api/v1/stablecoin/collateral` - Withdraw collateral
- `GET /api/v1/stablecoin/collateral/{userId}` - View positions
- `POST /api/v1/stablecoin/collateral/liquidate` - Liquidate position

### Oracle & Stability
- `GET /api/v1/stablecoin/oracle/{pair}` - Get price feed
- `GET /api/v1/stablecoin/stability/status` - Stability metrics
- `POST /api/v1/stablecoin/stability/adjust` - Manual adjustment

## Configuration

### Environment Variables
```env
# Stablecoin Configuration
STABLECOIN_NAME="FAUSD"
STABLECOIN_SYMBOL="FAUSD"
STABLECOIN_DECIMALS=6
STABLECOIN_INITIAL_SUPPLY=0

# Collateralization Requirements
STABLECOIN_MIN_COLLATERAL_RATIO=1.5
STABLECOIN_LIQUIDATION_RATIO=1.2
STABLECOIN_LIQUIDATION_PENALTY=0.1

# Reserve Management
RESERVE_TARGET_RATIO=1.0
RESERVE_REBALANCE_THRESHOLD=0.05
RESERVE_ASSET_ALLOCATION="USDT:40,USDC:40,DAI:20"

# Oracle Configuration
ORACLE_PROVIDER=chainlink
ORACLE_UPDATE_FREQUENCY=60
ORACLE_PRICE_DEVIATION_THRESHOLD=0.02

# Stability Mechanism
STABILITY_MECHANISM_ENABLED=true
STABILITY_ADJUSTMENT_FACTOR=0.001
STABILITY_EMERGENCY_PAUSE_THRESHOLD=0.1
```

## Best Practices

1. **Always check collateralization** before minting
2. **Use oracle prices** for all valuations
3. **Implement circuit breakers** for emergency situations
4. **Log all monetary operations** for audit trail
5. **Use event sourcing** for compliance and transparency
6. **Test with demo service** in development
7. **Monitor stability metrics** continuously
8. **Implement proper KYC/AML** for large transactions

## Common Issues

### Minting Failures
- Insufficient collateral provided
- Oracle price feed unavailable
- User KYC not completed
- Daily/monthly limits exceeded

### Redemption Issues
- Insufficient reserves available
- Network congestion for blockchain settlement
- Invalid destination address
- Pending compliance review

### Stability Problems
- Oracle price manipulation attempts
- Sudden collateral value drops
- Reserve imbalance
- High redemption pressure

### Integration Challenges
- Oracle API rate limits
- Blockchain network delays
- Exchange API downtime
- Regulatory compliance updates

## Regulatory Compliance

### Required Checks
- KYC verification for amounts > $10,000
- AML screening for suspicious patterns
- Transaction reporting for large volumes
- Reserve attestation monthly
- Audit trail for all operations

### Reporting
- Daily transaction summaries
- Monthly reserve reports
- Quarterly compliance audits
- Annual financial statements

## AI Agent Tips

- StablecoinService handles all core operations
- Use DemoStablecoinService for testing (no blockchain required)
- All amounts are in cents (multiply dollars by 100)
- Collateral ratios are decimals (1.5 = 150%)
- Oracle prices update every 60 seconds by default
- Event sourcing provides complete audit trail
- Stability mechanism runs automatically via activities
- Reserve rebalancing is triggered by threshold breaches
- Use workflows for complex multi-step operations
- Monitor stablecoin_events table for all state changes