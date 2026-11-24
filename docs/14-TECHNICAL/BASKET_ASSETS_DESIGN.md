# Basket Assets Design Document

**Version:** 1.0  
**Created:** 2024-06-16  
**Status:** Implemented as a demo module (reviewed for v7.18.1)

> The Basket module is reference software. The production site switches it off through `MODULES_DISABLED`, so it runs only in demo mode and on deployments that enable it. The GCU demo is built on this module.
>
> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

## Overview

A basket asset is an asset whose value comes from a weighted mix of other assets. Such assets are also called composite assets or currency baskets. This document describes how basket assets are designed in FinAegis.

## Use Cases

1. A currency basket, such as a "Stable Basket" of 40% USD, 30% EUR and 30% GBP.
2. A crypto index, such as a "Top 5 Crypto" basket of weighted BTC, ETH and other cryptocurrencies.
3. A commodity basket, such as a "Precious Metals" basket of gold, silver and platinum.
4. A mixed basket that combines different types of assets.

## Domain Model

### BasketAsset

The main entity representing a basket asset.

```php
class BasketAsset extends Model
{
    protected $fillable = [
        'code',           // Unique identifier (e.g., 'STABLE_BASKET')
        'name',           // Human-readable name
        'description',    // Detailed description
        'type',           // 'fixed' or 'dynamic'
        'rebalance_frequency', // daily, weekly, monthly, quarterly, never
        'last_rebalanced_at',
        'is_active',
        'created_by',     // User who created the basket
        'metadata',       // JSON field for additional properties
    ];
}
```

### BasketComponent

Represents the composition of a basket.

```php
class BasketComponent extends Model
{
    protected $fillable = [
        'basket_asset_id',
        'asset_code',     // Reference to assets table
        'weight',         // Percentage weight (0-100)
        'min_weight',     // Minimum allowed weight for rebalancing
        'max_weight',     // Maximum allowed weight for rebalancing
        'is_active',
    ];
}
```

### BasketValue

Tracks the calculated value of a basket over time.

```php
class BasketValue extends Model
{
    protected $fillable = [
        'basket_asset_code',
        'value',          // Calculated value in base currency (USD)
        'calculated_at',
        'component_values', // JSON breakdown of each component's value
    ];
}
```

## Database Schema

### basket_assets
```sql
CREATE TABLE basket_assets (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type ENUM('fixed', 'dynamic') DEFAULT 'fixed',
    rebalance_frequency ENUM('daily', 'weekly', 'monthly', 'quarterly', 'never') DEFAULT 'never',
    last_rebalanced_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by CHAR(36),
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active),
    INDEX idx_type (type)
);
```

### basket_components
```sql
CREATE TABLE basket_components (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    basket_asset_id BIGINT NOT NULL,
    asset_code VARCHAR(10) NOT NULL,
    weight DECIMAL(5,2) NOT NULL, -- Percentage 0.00-100.00
    min_weight DECIMAL(5,2),
    max_weight DECIMAL(5,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (basket_asset_id) REFERENCES basket_assets(id) ON DELETE CASCADE,
    FOREIGN KEY (asset_code) REFERENCES assets(code),
    UNIQUE KEY unique_basket_asset (basket_asset_id, asset_code),
    INDEX idx_basket (basket_asset_id),
    CHECK (weight >= 0 AND weight <= 100),
    CHECK (min_weight IS NULL OR min_weight >= 0 AND min_weight <= weight),
    CHECK (max_weight IS NULL OR max_weight >= weight AND max_weight <= 100)
);
```

### basket_values
```sql
CREATE TABLE basket_values (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    basket_asset_code VARCHAR(20) NOT NULL,
    value DECIMAL(20,8) NOT NULL,
    calculated_at TIMESTAMP NOT NULL,
    component_values JSON,
    created_at TIMESTAMP,
    INDEX idx_basket_time (basket_asset_code, calculated_at),
    FOREIGN KEY (basket_asset_code) REFERENCES basket_assets(code) ON DELETE CASCADE
);
```

## Services

### BasketValueCalculationService

Calculates the current value of a basket based on its components.

```php
class BasketValueCalculationService
{
    public function calculateValue(BasketAsset $basket): BasketValue
    {
        $components = $basket->components()->active()->get();
        $totalValue = 0;
        $componentValues = [];
        
        foreach ($components as $component) {
            $assetValue = $this->getAssetValue($component->asset_code);
            $weightedValue = $assetValue * ($component->weight / 100);
            
            $totalValue += $weightedValue;
            $componentValues[$component->asset_code] = [
                'value' => $assetValue,
                'weight' => $component->weight,
                'weighted_value' => $weightedValue,
            ];
        }
        
        return BasketValue::create([
            'basket_asset_code' => $basket->code,
            'value' => $totalValue,
            'calculated_at' => now(),
            'component_values' => $componentValues,
        ]);
    }
    
    private function getAssetValue(string $assetCode): float
    {
        // Convert to USD base currency
        if ($assetCode === 'USD') {
            return 1.0;
        }
        
        $rate = app(ExchangeRateService::class)->getRate($assetCode, 'USD');
        return $rate ? $rate->rate : 0;
    }
}
```

### BasketRebalancingService

Handles rebalancing of dynamic baskets.

```php
class BasketRebalancingService
{
    public function rebalance(BasketAsset $basket): array
    {
        if ($basket->type !== 'dynamic') {
            throw new \Exception('Only dynamic baskets can be rebalanced');
        }
        
        $components = $basket->components()->active()->get();
        $currentValues = $this->getCurrentComponentValues($basket);
        $totalValue = array_sum(array_column($currentValues, 'value'));
        
        $adjustments = [];
        
        foreach ($components as $component) {
            $currentWeight = ($currentValues[$component->asset_code]['value'] / $totalValue) * 100;
            $targetWeight = $component->weight;
            
            // Check if rebalancing is needed
            if ($component->min_weight && $currentWeight < $component->min_weight) {
                $adjustments[] = [
                    'asset' => $component->asset_code,
                    'current_weight' => $currentWeight,
                    'target_weight' => max($component->min_weight, $targetWeight),
                    'action' => 'buy',
                ];
            } elseif ($component->max_weight && $currentWeight > $component->max_weight) {
                $adjustments[] = [
                    'asset' => $component->asset_code,
                    'current_weight' => $currentWeight,
                    'target_weight' => min($component->max_weight, $targetWeight),
                    'action' => 'sell',
                ];
            }
        }
        
        if (!empty($adjustments)) {
            $this->executeRebalancing($basket, $adjustments);
            $basket->update(['last_rebalanced_at' => now()]);
        }
        
        return $adjustments;
    }
}
```

## Integration Points

### Account Balances

Basket assets are held in account balances like any other asset:

```php
// User can hold basket assets
$account->addBalance('STABLE_BASKET', 10000); // 100 units

// Converting basket to components
$basketService->decompose($account, 'STABLE_BASKET', 5000); // Converts to individual assets
```

### Exchange Rates

The exchange rate of a basket asset is calculated from its components:

```php
// Exchange rate for basket is derived from components
$basketRate = $exchangeRateService->getRate('STABLE_BASKET', 'USD');
```

### Events

Basket operations record these events:

```php
class BasketCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $basketCode,
        public readonly array $components,
        public readonly string $createdBy
    ) {}
}

class BasketRebalanced extends ShouldBeStored
{
    public function __construct(
        public readonly string $basketCode,
        public readonly array $adjustments,
        public readonly DateTimeInterface $rebalancedAt
    ) {}
}

class BasketDecomposed extends ShouldBeStored
{
    public function __construct(
        public readonly string $accountUuid,
        public readonly string $basketCode,
        public readonly int $amount,
        public readonly array $componentAmounts
    ) {}
}
```

## API Endpoints

### Basket Management
- `GET /api/v2/baskets` - List all basket assets
- `GET /api/v2/baskets/{code}` - Get basket details
- `POST /api/v2/baskets` - Create new basket (admin)

There is no update or delete endpoint. Administrators change a basket in the admin panel.

### Basket Operations
- `GET /api/v2/baskets/{code}/value` - Get current basket value
- `POST /api/v2/baskets/{code}/rebalance` - Trigger rebalancing
- `GET /api/v2/baskets/{code}/history` - Value history
- `GET /api/v2/accounts/{uuid}/baskets` - Basket holdings of an account
- `POST /api/v2/accounts/{uuid}/baskets/compose` - Convert components into basket units
- `POST /api/v2/accounts/{uuid}/baskets/decompose` - Convert basket to components

## Admin Interface

### Filament Resources

1. **BasketAssetResource**
   - List all baskets with composition
   - Create/edit basket definitions
   - Set rebalancing rules
   - View performance metrics

2. **Basket Widgets**
   - Basket performance chart
   - Component weight visualization
   - Rebalancing history

## Security Considerations

1. Only platform operators (the `admin`, `super_admin` and `bank_admin` roles) can create, change or rebalance baskets.
2. The weights of a basket must add up to 100%.
3. Every basket operation is event-sourced, which gives an audit trail.
4. Rebalancing through the API also needs the `write` ability. It has no separate rate limit.

## Performance Considerations

1. Basket values are cached for 5 minutes.
2. Rebalancing runs in batches.
3. Value calculations run asynchronously.
4. Frequently queried fields are indexed.

## Testing Strategy

Unit tests cover the services and calculations. Integration tests cover the API endpoints and workflows. Performance tests cover large basket calculations, and edge-case tests cover invalid weights and missing components.

## Performance Tracking (Implemented)

### Models

1. **BasketPerformance**: Tracks performance metrics over different time periods
   - Stores return percentages, volatility, Sharpe ratio, maximum drawdown
   - Supports multiple period types (hour, day, week, month, quarter, year, all_time)
   - Links to component performance for detailed analysis

2. **ComponentPerformance**: Tracks individual component contributions
   - Records weight changes and contribution to overall performance
   - Enables identification of top and worst performers

### Services

1. **BasketPerformanceService**: Comprehensive performance calculation
   - Calculates returns, volatility, Sharpe ratio, and maximum drawdown
   - Supports all standard time periods
   - Provides component-level performance attribution
   - Includes comparison functionality against benchmarks

### API Endpoints

Performance tracking endpoints:
- `GET /api/v2/baskets/{code}/performance` - Get performance for specific period
- `GET /api/v2/baskets/{code}/performance/history` - Historical performance data
- `GET /api/v2/baskets/{code}/performance/summary` - Multi-period summary
- `GET /api/v2/baskets/{code}/performance/components` - Component breakdown
- `GET /api/v2/baskets/{code}/performance/top-performers` - Best performing components
- `GET /api/v2/baskets/{code}/performance/worst-performers` - Worst performing components
- `POST /api/v2/baskets/{code}/performance/calculate` - Trigger calculation
- `GET /api/v2/baskets/{code}/performance/compare` - Compare against benchmarks

### Admin Dashboard

1. **Performance Widgets**:
   - `BasketPerformanceChart`: Line chart showing returns and volatility
   - `BasketPerformanceStats`: Overview cards with key metrics
   - `BasketPerformanceWidget`: Detailed performance table with filtering

2. **Component Analysis**:
   - Interactive modal showing component contributions
   - Top/worst performer identification
   - Weight change tracking

### Scheduled Tasks

These scheduled tasks run while the Basket module is enabled:
- `basket:calculate-performance` runs every hour for all active baskets.
- A daily performance summary runs at 00:30.
- `baskets:rebalance` rebalances the dynamic baskets, including the GCU demo basket, once a month.

### Console Commands

- `basket:calculate-performance`: Manual performance calculation
  - Options: `--basket=CODE`, `--period=PERIOD`
  - Supports individual basket or all baskets
  - Can calculate specific periods or all periods

## Implementation Status

### Completed Features

| Feature | Status |
|---------|--------|
| Basket asset models and relationships | Done |
| Value calculation (`BasketValueCalculationService`) | Done |
| Automatic and manual rebalancing (`BasketRebalancingService`) | Done |
| REST API with OpenAPI documentation | Done |
| Filament resources and widgets | Done |
| Event sourcing for basket operations | Done |
| Performance tracking (`BasketPerformanceService`) | Done |
| Unit and integration tests with factories | Done |

Version 7.18.0 fixed a lookup that never found GCU basket values.

### Database Schema Updates

Added performance tracking tables:
- `basket_performances`: Stores calculated performance metrics
- `component_performances`: Stores component-level performance data

## Future Enhancements

Possible future work includes rebalancing driven by machine learning and user-defined rebalancing strategies. Other ideas are factor analysis, risk attribution, tokenized basket shares, live performance updates over WebSocket and basket management in the mobile SDKs.

---

This design gives basket assets a complete base with performance tracking. It follows the same architecture as the rest of FinAegis.