# Building Custom Basket Currencies on FinAegis

This tutorial walks you through creating a custom basket currency using FinAegis, using the GCU (Global Currency Unit) as a reference implementation.

## Table of Contents

1. [Introduction](#introduction)
2. [Prerequisites](#prerequisites)
3. [Step 1: Design Your Basket](#step-1-design-your-basket)
4. [Step 2: Create the Database Structure](#step-2-create-the-database-structure)
5. [Step 3: Implement the Services](#step-3-implement-the-services)
6. [Step 4: Create Workflows](#step-4-create-workflows)
7. [Step 5: Add Governance](#step-5-add-governance)
8. [Step 6: Testing](#step-6-testing)
9. [Advanced Topics](#advanced-topics)

---

## Introduction

A **basket currency** is a synthetic currency unit whose value is derived from a weighted combination of other currencies or assets. Famous examples include:

- **SDR (Special Drawing Rights)** - IMF's basket of USD, EUR, CNY, JPY, GBP
- **ECU (European Currency Unit)** - Predecessor to the Euro
- **GCU (Global Currency Unit)** - FinAegis reference implementation

### Why Basket Currencies?

| Benefit | Explanation |
|---------|-------------|
| **Stability** | Diversification reduces volatility |
| **Democratic Control** | Composition can be governed by community |
| **Transparency** | Clear, auditable valuation methodology |
| **Flexibility** | Adapt to economic changes over time |

---

## Prerequisites

Before starting, ensure you have:

- FinAegis development environment running
- Understanding of [Event Sourcing](../ADR/ADR-001-event-sourcing.md)
- Familiarity with [Saga Pattern](../ADR/ADR-003-saga-pattern.md)
- Access to exchange rate data (or mock service)

---

## Step 1: Design Your Basket

### 1.1 Define the Purpose

Ask yourself:
- What problem does this basket solve?
- Who will use it?
- What assets should be included?
- How often should it rebalance?

### 1.2 Choose Components

For our example, let's create an **Asian Currency Basket (ACB)**:

| Currency | Weight | Rationale |
|----------|--------|-----------|
| CNY | 40% | Largest Asian economy |
| JPY | 30% | Second largest, safe haven |
| KRW | 15% | Strong tech economy |
| SGD | 10% | Financial hub stability |
| HKD | 5% | International trade gateway |

### 1.3 Define Rebalancing Rules

```php
// Rebalancing triggers
$rules = [
    'drift_threshold' => 0.05,    // Rebalance if any component drifts 5%
    'schedule' => 'monthly',       // Regular monthly rebalance
    'min_trade_size' => 1000,      // Avoid small trades
];
```

---

## Step 2: Create the Database Structure

### 2.1 Create Migration

```bash
php artisan make:migration create_acb_basket_tables
```

```php
// database/migrations/xxxx_create_acb_basket_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Basket definition
        Schema::create('basket_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 10)->unique();  // 'ACB'
            $table->string('name');                 // 'Asian Currency Basket'
            $table->string('description')->nullable();
            $table->string('base_currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->boolean('rebalancing_enabled')->default(true);
            $table->string('rebalancing_schedule')->default('monthly');
            $table->decimal('drift_threshold', 5, 4)->default(0.05);
            $table->timestamps();
            $table->softDeletes();
        });

        // Basket components (the currencies in the basket)
        Schema::create('basket_components', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('basket_asset_id')->constrained()->cascadeOnDelete();
            $table->string('asset_code', 10);       // 'CNY', 'JPY', etc.
            $table->string('asset_name');
            $table->decimal('weight', 8, 4);        // 0.40 for 40%
            $table->decimal('target_weight', 8, 4); // Target for rebalancing
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['basket_asset_id', 'asset_code']);
        });

        // NAV history
        Schema::create('basket_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basket_asset_id')->constrained()->cascadeOnDelete();
            $table->decimal('nav', 20, 8);          // Net Asset Value
            $table->decimal('nav_change', 10, 4)->nullable();
            $table->json('component_values');       // Snapshot of component prices
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->index(['basket_asset_id', 'calculated_at']);
        });
    }
};
```

### 2.2 Create Seeder

```php
// database/seeders/ACBBasketSeeder.php

namespace Database\Seeders;

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketComponent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ACBBasketSeeder extends Seeder
{
    public function run(): void
    {
        $basket = BasketAsset::create([
            'uuid' => Str::uuid(),
            'code' => 'ACB',
            'name' => 'Asian Currency Basket',
            'description' => 'A basket of major Asian currencies',
            'base_currency' => 'USD',
            'is_active' => true,
            'rebalancing_enabled' => true,
            'rebalancing_schedule' => 'monthly',
            'drift_threshold' => 0.05,
        ]);

        $components = [
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'weight' => 0.40],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'weight' => 0.30],
            ['code' => 'KRW', 'name' => 'South Korean Won', 'weight' => 0.15],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'weight' => 0.10],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'weight' => 0.05],
        ];

        foreach ($components as $component) {
            BasketComponent::create([
                'uuid' => Str::uuid(),
                'basket_asset_id' => $basket->id,
                'asset_code' => $component['code'],
                'asset_name' => $component['name'],
                'weight' => $component['weight'],
                'target_weight' => $component['weight'],
                'is_active' => true,
            ]);
        }
    }
}
```

---

## Step 3: Implement the Services

### 3.1 NAV Calculation Service

```php
// app/Domain/Basket/Services/BasketValueCalculationService.php

namespace App\Domain\Basket\Services;

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketValue;
use App\Domain\Exchange\Contracts\ExchangeRateServiceInterface;

class BasketValueCalculationService
{
    public function __construct(
        private readonly ExchangeRateServiceInterface $exchangeRateService,
    ) {}

    /**
     * Calculate the current NAV for a basket.
     */
    public function calculateNAV(BasketAsset $basket): float
    {
        $nav = 0.0;
        $componentValues = [];

        foreach ($basket->activeComponents as $component) {
            // Get exchange rate to base currency
            $rate = $this->exchangeRateService->getRate(
                from: $component->asset_code,
                to: $basket->base_currency
            );

            // Calculate weighted value
            $componentValue = $rate * $component->weight;
            $nav += $componentValue;

            $componentValues[$component->asset_code] = [
                'rate' => $rate,
                'weight' => $component->weight,
                'value' => $componentValue,
            ];
        }

        // Store the calculation
        $this->storeNAV($basket, $nav, $componentValues);

        return $nav;
    }

    /**
     * Store NAV calculation for history.
     */
    private function storeNAV(
        BasketAsset $basket,
        float $nav,
        array $componentValues
    ): void {
        $previousNav = $basket->latestValue?->nav;
        $navChange = $previousNav ? (($nav - $previousNav) / $previousNav) * 100 : null;

        BasketValue::create([
            'basket_asset_id' => $basket->id,
            'nav' => $nav,
            'nav_change' => $navChange,
            'component_values' => $componentValues,
            'calculated_at' => now(),
        ]);
    }

    /**
     * Get historical NAV for a specific date.
     */
    public function getHistoricalNAV(BasketAsset $basket, \DateTimeInterface $date): ?float
    {
        return BasketValue::where('basket_asset_id', $basket->id)
            ->whereDate('calculated_at', $date)
            ->orderBy('calculated_at', 'desc')
            ->value('nav');
    }
}
```

### 3.2 Rebalancing Service

```php
// app/Domain/Basket/Services/BasketRebalancingService.php

namespace App\Domain\Basket\Services;

use App\Domain\Basket\Events\BasketRebalanced;
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Exchange\Services\ExchangeService;

class BasketRebalancingService
{
    public function __construct(
        private readonly ExchangeService $exchangeService,
        private readonly BasketValueCalculationService $navService,
    ) {}

    /**
     * Check if basket needs rebalancing.
     */
    public function shouldRebalance(BasketAsset $basket): bool
    {
        foreach ($basket->activeComponents as $component) {
            $currentWeight = $this->calculateCurrentWeight($basket, $component);
            $drift = abs($currentWeight - $component->target_weight);

            if ($drift > $basket->drift_threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate current weight based on market values.
     */
    private function calculateCurrentWeight(
        BasketAsset $basket,
        $component
    ): float {
        $totalValue = 0;
        $componentValue = 0;

        foreach ($basket->activeComponents as $comp) {
            $value = $this->navService->getComponentValue($comp);
            $totalValue += $value;

            if ($comp->id === $component->id) {
                $componentValue = $value;
            }
        }

        return $totalValue > 0 ? $componentValue / $totalValue : 0;
    }

    /**
     * Execute rebalancing trades.
     */
    public function executeRebalancing(BasketAsset $basket): array
    {
        $trades = [];
        $currentWeights = [];
        $targetWeights = [];

        // Calculate current vs target
        foreach ($basket->activeComponents as $component) {
            $currentWeights[$component->asset_code] = $this->calculateCurrentWeight($basket, $component);
            $targetWeights[$component->asset_code] = $component->target_weight;
        }

        // Determine required trades
        foreach ($basket->activeComponents as $component) {
            $currentWeight = $currentWeights[$component->asset_code];
            $targetWeight = $targetWeights[$component->asset_code];
            $difference = $targetWeight - $currentWeight;

            if (abs($difference) > 0.001) { // Minimum trade threshold
                $trades[] = [
                    'asset' => $component->asset_code,
                    'action' => $difference > 0 ? 'buy' : 'sell',
                    'weight_change' => abs($difference),
                ];
            }
        }

        // Execute trades through exchange
        foreach ($trades as &$trade) {
            $result = $this->exchangeService->executeTrade([
                'asset' => $trade['asset'],
                'action' => $trade['action'],
                'amount' => $this->calculateTradeAmount($basket, $trade),
            ]);
            $trade['result'] = $result;
        }

        // Fire domain event
        event(new BasketRebalanced($basket, $trades));

        return $trades;
    }
}
```

---

## Step 4: Create Workflows

### 4.1 Compose Basket Workflow

```php
// app/Domain/Basket/Workflows/ComposeBasketWorkflow.php

namespace App\Domain\Basket\Workflows;

use App\Domain\Basket\Activities\ComposeBasketActivity;
use App\Domain\Basket\Activities\ValidateCompositionActivity;
use App\Domain\Basket\Activities\LockComponentsActivity;
use App\Domain\Basket\Activities\IssueBasketUnitsActivity;
use Workflow\ActivityStub;
use Workflow\Workflow;

class ComposeBasketWorkflow extends Workflow
{
    public function execute(
        string $accountUuid,
        string $basketCode,
        int $amount
    ): array {
        // Step 1: Validate the composition request
        $validation = yield ActivityStub::make(
            ValidateCompositionActivity::class,
            $accountUuid,
            $basketCode,
            $amount
        );

        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        // Step 2: Lock component currencies from account
        $lockResult = yield ActivityStub::make(
            LockComponentsActivity::class,
            $accountUuid,
            $basketCode,
            $amount
        );

        try {
            // Step 3: Issue basket units
            $issueResult = yield ActivityStub::make(
                IssueBasketUnitsActivity::class,
                $accountUuid,
                $basketCode,
                $amount
            );

            return [
                'success' => true,
                'basket_units' => $issueResult['units'],
                'components_used' => $lockResult['components'],
            ];

        } catch (\Throwable $e) {
            // Compensation: Return locked components
            yield ActivityStub::make(
                UnlockComponentsActivity::class,
                $accountUuid,
                $lockResult['lock_id']
            );

            throw $e;
        }
    }
}
```

### 4.2 Activities

```php
// app/Domain/Basket/Activities/LockComponentsActivity.php

namespace App\Domain\Basket\Activities;

use App\Domain\Account\Services\AccountService;
use App\Domain\Basket\Models\BasketAsset;
use Workflow\Activity;

class LockComponentsActivity extends Activity
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    public function execute(
        string $accountUuid,
        string $basketCode,
        int $amount
    ): array {
        $basket = BasketAsset::where('code', $basketCode)->firstOrFail();
        $lockedComponents = [];

        foreach ($basket->activeComponents as $component) {
            $componentAmount = (int) round($amount * $component->weight);

            $this->accountService->lockBalance(
                accountUuid: $accountUuid,
                assetCode: $component->asset_code,
                amount: $componentAmount,
                reason: "Basket composition: {$basketCode}"
            );

            $lockedComponents[$component->asset_code] = $componentAmount;
        }

        return [
            'lock_id' => uniqid('lock_'),
            'components' => $lockedComponents,
        ];
    }
}
```

---

## Step 5: Add Governance

### 5.1 Create Composition Change Proposal

```php
// app/Domain/Basket/Services/BasketGovernanceService.php

namespace App\Domain\Basket\Services;

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Governance\Models\Proposal;
use App\Domain\Governance\Services\GovernanceService;

class BasketGovernanceService
{
    public function __construct(
        private readonly GovernanceService $governanceService,
    ) {}

    /**
     * Create a proposal to change basket composition.
     */
    public function proposeCompositionChange(
        BasketAsset $basket,
        array $newComposition,
        string $rationale
    ): Proposal {
        // Validate new composition sums to 100%
        $totalWeight = array_sum(array_column($newComposition, 'weight'));
        if (abs($totalWeight - 1.0) > 0.001) {
            throw new \InvalidArgumentException('Weights must sum to 100%');
        }

        return $this->governanceService->createProposal([
            'type' => 'basket_composition_change',
            'title' => "Update {$basket->code} composition",
            'description' => $rationale,
            'data' => [
                'basket_id' => $basket->id,
                'basket_code' => $basket->code,
                'current_composition' => $basket->activeComponents->toArray(),
                'proposed_composition' => $newComposition,
            ],
            'voting_period_days' => config('gcu.governance.voting_period_days', 7),
            'quorum' => config('gcu.governance.quorum_percentage', 0.10),
            'approval_threshold' => config('gcu.governance.approval_threshold', 0.66),
        ]);
    }

    /**
     * Execute an approved composition change.
     */
    public function executeApprovedChange(Proposal $proposal): void
    {
        if (!$proposal->isApproved()) {
            throw new \RuntimeException('Proposal not approved');
        }

        $basket = BasketAsset::findOrFail($proposal->data['basket_id']);
        $newComposition = $proposal->data['proposed_composition'];

        DB::transaction(function () use ($basket, $newComposition) {
            // Deactivate current components
            $basket->activeComponents()->update(['is_active' => false]);

            // Create new components
            foreach ($newComposition as $component) {
                BasketComponent::create([
                    'basket_asset_id' => $basket->id,
                    'asset_code' => $component['code'],
                    'asset_name' => $component['name'],
                    'weight' => $component['weight'],
                    'target_weight' => $component['weight'],
                    'is_active' => true,
                ]);
            }
        });

        event(new BasketCompositionChanged($basket, $newComposition));
    }
}
```

---

## Step 6: Testing

### 6.1 Unit Tests

```php
// tests/Domain/Basket/Services/BasketValueCalculationServiceTest.php

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Services\BasketValueCalculationService;

describe('BasketValueCalculationService', function () {
    beforeEach(function () {
        $this->service = app(BasketValueCalculationService::class);
        $this->basket = BasketAsset::factory()
            ->withComponents([
                ['code' => 'USD', 'weight' => 0.50],
                ['code' => 'EUR', 'weight' => 0.50],
            ])
            ->create();
    });

    it('calculates NAV correctly', function () {
        // Mock exchange rates
        $this->mock(ExchangeRateServiceInterface::class)
            ->shouldReceive('getRate')
            ->with('USD', 'USD')->andReturn(1.0)
            ->shouldReceive('getRate')
            ->with('EUR', 'USD')->andReturn(1.10);

        $nav = $this->service->calculateNAV($this->basket);

        // Expected: (1.0 * 0.50) + (1.10 * 0.50) = 1.05
        expect($nav)->toBe(1.05);
    });

    it('stores NAV history', function () {
        $this->service->calculateNAV($this->basket);

        expect($this->basket->values()->count())->toBe(1);
    });
});
```

### 6.2 Integration Tests

```php
// tests/Feature/Basket/ComposeBasketTest.php

use App\Domain\Account\Models\Account;
use App\Domain\Basket\Services\BasketService;

describe('Basket Composition', function () {
    it('composes basket from component currencies', function () {
        $account = Account::factory()->create();
        $account->credit('USD', 500);
        $account->credit('EUR', 500);

        $service = app(BasketService::class);
        $result = $service->composeBasket(
            accountUuid: $account->uuid,
            basketCode: 'ACB',
            amount: 1000
        );

        expect($result['success'])->toBeTrue()
            ->and($account->getBalance('ACB'))->toBe(1000)
            ->and($account->getBalance('USD'))->toBeLessThan(500);
    });

    it('fails with insufficient balance', function () {
        $account = Account::factory()->create();
        // No balances

        $service = app(BasketService::class);

        expect(fn() => $service->composeBasket(
            accountUuid: $account->uuid,
            basketCode: 'ACB',
            amount: 1000
        ))->toThrow(\Exception::class, 'Insufficient');
    });
});
```

---

## Advanced Topics

### Custom Weighting Strategies

```php
interface WeightingStrategyInterface
{
    public function calculateWeights(array $assets): array;
}

class MarketCapWeightingStrategy implements WeightingStrategyInterface
{
    public function calculateWeights(array $assets): array
    {
        $totalMarketCap = array_sum(array_column($assets, 'market_cap'));

        return array_map(
            fn($asset) => $asset['market_cap'] / $totalMarketCap,
            $assets
        );
    }
}

class EqualWeightingStrategy implements WeightingStrategyInterface
{
    public function calculateWeights(array $assets): array
    {
        $weight = 1.0 / count($assets);
        return array_fill(0, count($assets), $weight);
    }
}
```

### Multi-Chain Basket

For baskets spanning multiple blockchains:

```php
class MultiChainBasketService
{
    public function compose(string $basketCode, int $amount): array
    {
        $basket = BasketAsset::where('code', $basketCode)->first();

        foreach ($basket->components as $component) {
            match ($component->chain) {
                'ethereum' => $this->ethereumService->lock($component),
                'bitcoin' => $this->bitcoinService->lock($component),
                'polygon' => $this->polygonService->lock($component),
            };
        }
    }
}
```

### Real-Time NAV Updates

```php
// Using Laravel Broadcasting
class NAVUpdateBroadcaster
{
    public function broadcastNAVUpdate(BasketAsset $basket): void
    {
        $nav = $this->navService->calculateNAV($basket);

        broadcast(new NAVUpdated($basket->code, $nav))->toOthers();
    }
}
```

---

## Summary

You've learned how to:

1. **Design** a basket currency with clear purpose and composition
2. **Implement** NAV calculation and rebalancing
3. **Orchestrate** operations with saga workflows
4. **Integrate** with governance for democratic control
5. **Test** thoroughly with unit and integration tests

Use the [GCU implementation](../../app/Domain/Basket/) as your reference for production-ready patterns.

---

## Related Resources

- [ADR-004: GCU Basket Design](../ADR/ADR-004-gcu-basket-design.md)
- [GCU Domain README](../../app/Domain/Basket/README.md)
- [Event Sourcing ADR](../ADR/ADR-001-event-sourcing.md)
- [Saga Pattern ADR](../ADR/ADR-003-saga-pattern.md)
