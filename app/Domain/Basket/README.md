# GCU Basket Domain - Reference Implementation

> **This domain serves as the reference implementation for FinAegis**, demonstrating how to build complex financial products using the platform's primitives.

## Overview

The Global Currency Unit (GCU) is a democratic basket currency that showcases:

- **Event Sourcing** - Complete audit trail of all basket operations
- **Workflow Orchestration** - Saga pattern for composition/decomposition
- **Governance Integration** - Democratic voting on basket composition
- **Multi-Domain Coordination** - Integrates Account, Exchange, Compliance, Treasury

## GCU Basket Composition

| Currency | Weight | Rationale |
|----------|--------|-----------|
| USD | 40% | Global reserve currency, maximum liquidity |
| EUR | 30% | Second largest reserve, EU economic stability |
| GBP | 15% | Major trading currency, historical stability |
| CHF | 10% | Safe haven during market volatility |
| JPY | 3% | Asian market representation |
| XAU (Gold) | 2% | Inflation hedge, non-fiat anchor |

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      Basket Domain                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────┐     ┌─────────────────┐                    │
│  │  BasketService  │────▶│    Workflows    │                    │
│  │  (Entry Point)  │     │  Compose/Decompose│                  │
│  └────────┬────────┘     └────────┬────────┘                    │
│           │                       │                              │
│  ┌────────▼────────┐     ┌────────▼────────┐                    │
│  │   Calculation   │     │   Activities    │                    │
│  │    Services     │     │ (Business Logic)│                    │
│  └─────────────────┘     └────────┬────────┘                    │
│                                   │                              │
│  ┌────────────────────────────────▼────────────────────────────┐│
│  │                    Account Domain                            ││
│  │              (Balance Management)                            ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Directory Structure

```
app/Domain/Basket/
├── Activities/                 # Workflow activities
│   ├── ComposeBasketActivity.php
│   ├── ComposeBasketBusinessActivity.php
│   ├── DecomposeBasketActivity.php
│   └── DecomposeBasketBusinessActivity.php
├── Console/Commands/           # Artisan commands
│   ├── BasketsRebalanceCommand.php
│   ├── CalculateBasketPerformance.php
│   ├── RebalanceBasketsCommand.php
│   └── ShowBasketPerformanceCommand.php
├── Events/                     # Domain events
│   ├── BasketCreated.php
│   ├── BasketDecomposed.php
│   └── BasketRebalanced.php
├── Models/                     # Eloquent models
│   ├── BasketAsset.php        # The basket definition
│   ├── BasketComponent.php    # Individual currency weights
│   ├── BasketPerformance.php  # Performance tracking
│   ├── BasketValue.php        # NAV snapshots
│   └── ComponentPerformance.php
├── Services/                   # Business logic
│   ├── BasketService.php      # Main entry point
│   ├── BasketAccountService.php
│   ├── BasketPerformanceService.php
│   ├── BasketRebalancingService.php
│   └── BasketValueCalculationService.php
├── Workflows/                  # Saga orchestration
│   ├── ComposeBasketWorkflow.php
│   └── DecomposeBasketWorkflow.php
└── README.md                   # This file
```

## Key Services

### BasketService

Main entry point for basket operations:

```php
use App\Domain\Basket\Services\BasketService;

$basketService = app(BasketService::class);

// Compose assets into basket
$result = $basketService->composeBasket(
    accountUuid: $account->uuid,
    basketCode: 'GCU',
    amount: 1000  // Units of GCU to create
);

// Decompose basket back to components
$result = $basketService->decomposeBasket(
    accountUuid: $account->uuid,
    basketCode: 'GCU',
    amount: 500  // Units to decompose
);

// Get holdings
$holdings = $basketService->getBasketHoldings($account->uuid);
```

### BasketValueCalculationService

Calculates Net Asset Value (NAV):

```php
use App\Domain\Basket\Services\BasketValueCalculationService;

$navService = app(BasketValueCalculationService::class);

// Get current NAV
$nav = $navService->calculateNAV($basket);

// Get historical NAV
$historicalNav = $navService->getHistoricalNAV($basket, $date);
```

### BasketRebalancingService

Manages basket rebalancing:

```php
use App\Domain\Basket\Services\BasketRebalancingService;

$rebalanceService = app(BasketRebalancingService::class);

// Check if rebalancing needed
if ($rebalanceService->shouldRebalance($basket)) {
    $result = $rebalanceService->executeRebalancing($basket);
}
```

## Workflows

### ComposeBasketWorkflow

Saga for converting component currencies into basket units:

```
1. Validate inputs and balances
2. Lock component currencies from account
3. Calculate basket units to issue
4. Credit basket units to account
5. Record composition event

On failure: Compensate by returning locked components
```

### DecomposeBasketWorkflow

Saga for converting basket units back to components:

```
1. Validate basket balance
2. Lock basket units from account
3. Calculate component amounts
4. Credit component currencies to account
5. Record decomposition event

On failure: Compensate by returning basket units
```

## Events

| Event | When Fired |
|-------|------------|
| `BasketCreated` | New basket definition created |
| `BasketRebalanced` | Basket weights adjusted |
| `BasketDecomposed` | User decomposes basket |

## Commands

```bash
# Rebalance all dynamic baskets
php artisan baskets:rebalance

# Calculate performance metrics
php artisan baskets:calculate-performance

# Show basket performance
php artisan baskets:show-performance GCU
```

## Configuration

```php
// config/gcu.php
return [
    'basket' => [
        'base_currency' => 'USD',
        'composition' => [
            'USD' => 0.40,
            'EUR' => 0.30,
            'GBP' => 0.15,
            'CHF' => 0.10,
            'JPY' => 0.03,
            'XAU' => 0.02,
        ],
    ],
    'rebalancing' => [
        'drift_threshold' => 0.05,  // 5% drift triggers rebalance
        'schedule' => 'monthly',
        'min_trade_size' => 1000,
    ],
    'governance' => [
        'voting_period_days' => 7,
        'quorum_percentage' => 0.10,
        'approval_threshold' => 0.66,
    ],
];
```

## Integration Points

### With Governance Domain

Basket composition changes require governance approval:

```php
// Create proposal for composition change
$proposal = $governanceService->createProposal([
    'type' => 'basket_composition',
    'basket_code' => 'GCU',
    'new_composition' => [...],
]);

// After voting period
if ($proposal->isApproved()) {
    $basketService->updateComposition($proposal->newComposition);
}
```

### With Exchange Domain

Rebalancing uses Exchange for currency trades:

```php
// During rebalancing
foreach ($trades as $trade) {
    $exchangeService->executeTrade($trade);
}
```

### With Compliance Domain

All operations pass compliance checks:

```php
// Before composition
$complianceService->checkTransactionLimits($account, $amount);
$complianceService->validateAML($account);
```

## Testing

```bash
# Run basket domain tests
./vendor/bin/pest tests/Domain/Basket/

# Run with coverage
./vendor/bin/pest tests/Domain/Basket/ --coverage
```

Test files:
- `tests/Domain/Basket/Services/BasketServiceTest.php`
- `tests/Domain/Basket/Services/BasketValueCalculationServiceTest.php`
- `tests/Domain/Basket/Workflows/ComposeBasketWorkflowTest.php`

## Related Documentation

- [ADR-004: GCU Basket Design](../../../docs/ADR/ADR-004-gcu-basket-design.md)
- [Building Custom Basket Currencies](../../../docs/tutorials/BUILDING_BASKET_CURRENCIES.md)
- [Governance Integration](../Governance/README.md)

---

**This is a reference implementation.** Use it as a template for building your own basket currencies and financial instruments on the FinAegis platform.
