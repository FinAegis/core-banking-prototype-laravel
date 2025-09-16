# Basket Assets Design Document

**Version:** 1.0  
**Created:** 2024-06-16  
**Status:** Draft

## Overview

Basket assets (also known as composite assets or currency baskets) are financial instruments that represent a weighted combination of multiple underlying assets. This document outlines the design for implementing basket assets in the FinAegis platform.

## Use Cases

1. **Currency Baskets**: e.g., a "Stable Basket" containing 40% USD, 30% EUR, 30% GBP
2. **Crypto Index Funds**: e.g., "Top 5 Crypto" containing weighted BTC, ETH, and other cryptocurrencies
3. **Commodity Baskets**: e.g., "Precious Metals" containing gold, silver, platinum
4. **Mixed Asset Baskets**: Combinations of different asset types

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

Basket assets will be treated as regular assets in account balances:

```php
// User can hold basket assets
$account->addBalance('STABLE_BASKET', 10000); // 100 units

// Converting basket to components
$basketService->decompose($account, 'STABLE_BASKET', 5000); // Converts to individual assets
```

### Exchange Rates

Basket assets will have exchange rates calculated based on their composition:

```php
// Exchange rate for basket is derived from components
$basketRate = $exchangeRateService->getRate('STABLE_BASKET', 'USD');
```

### Events

New events for basket operations:

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
- `PUT /api/v2/baskets/{code}` - Update basket composition
- `DELETE /api/v2/baskets/{code}` - Deactivate basket

### Basket Operations
- `GET /api/v2/baskets/{code}/value` - Get current basket value
- `POST /api/v2/baskets/{code}/rebalance` - Trigger rebalancing
- `GET /api/v2/baskets/{code}/history` - Value history
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

1. **Authorization**: Only admins can create/modify basket definitions
2. **Validation**: Total weights must equal 100%
3. **Audit Trail**: All basket operations are event-sourced
4. **Rate Limiting**: Rebalancing operations are rate-limited

## Performance Considerations

1. **Caching**: Basket values cached for 5 minutes
2. **Batch Processing**: Rebalancing done in batches
3. **Async Calculation**: Value calculations done asynchronously
4. **Indexing**: Proper indexes on frequently queried fields

## Testing Strategy

1. **Unit Tests**: Services and calculations
2. **Integration Tests**: API endpoints and workflows
3. **Performance Tests**: Large basket calculations
4. **Edge Cases**: Invalid weights, missing components

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

Performance calculations run automatically:
- Hourly: Calculate hourly performance for all active baskets
- Daily: Calculate daily performance summary at 00:30

### Console Commands

- `basket:calculate-performance`: Manual performance calculation
  - Options: `--basket=CODE`, `--period=PERIOD`
  - Supports individual basket or all baskets
  - Can calculate specific periods or all periods

## Implementation Status

### Completed Features

1. ✅ **Basket Asset Models**: Full implementation with relationships
2. ✅ **Value Calculation Service**: Real-time basket valuation
3. ✅ **Rebalancing Service**: Automated and manual rebalancing
4. ✅ **API Layer**: Complete REST API with OpenAPI documentation
5. ✅ **Admin Interface**: Filament resources with widgets
6. ✅ **Event Sourcing**: Full audit trail for basket operations
7. ✅ **Performance Tracking**: Comprehensive metrics and analytics
8. ✅ **Testing**: Unit and integration tests with factories

### Database Schema Updates

Added performance tracking tables:
- `basket_performances`: Stores calculated performance metrics
- `component_performances`: Stores component-level performance data

## Future Enhancements

1. **Smart Rebalancing**: ML-based optimal rebalancing
2. **Custom Strategies**: User-defined rebalancing strategies
3. **Advanced Analytics**: Factor analysis and risk attribution
4. **Basket NFTs**: Tokenized basket shares
5. **Real-time Performance**: WebSocket-based live updates
6. **Mobile SDK**: Native basket management for mobile apps

---

This design provides a comprehensive foundation for basket assets with full performance tracking, maintaining consistency with the existing FinAegis architecture.