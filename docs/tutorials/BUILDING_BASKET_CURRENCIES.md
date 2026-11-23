# Building Custom Basket Currencies on FinAegis

This tutorial walks you through creating a custom basket currency using FinAegis, using the GCU (Global Currency Unit) as a reference implementation.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.
>
> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory
> compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
>
> See [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md).

The Basket module is demo code. Production deployments switch it off by listing `Basket` in `MODULES_DISABLED`. Everything below runs in a local or demo environment.

## Table of Contents

1. [Introduction](#introduction)
2. [Prerequisites](#prerequisites)
3. [Step 1: Design Your Basket](#step-1-design-your-basket)
4. [Step 2: Create the Basket](#step-2-create-the-basket)
5. [Step 3: Value the Basket](#step-3-value-the-basket)
6. [Step 4: Compose and Decompose](#step-4-compose-and-decompose)
7. [Step 5: Rebalancing and Governance](#step-5-rebalancing-and-governance)
8. [Step 6: Testing](#step-6-testing)
9. [Advanced Topics](#advanced-topics)

---

## Introduction

A basket currency is a unit of account whose value comes from a weighted set of other currencies or assets. Two well-known examples exist outside FinAegis.

- The IMF's Special Drawing Rights (SDR) are a basket of USD, EUR, CNY, JPY and GBP.
- The European Currency Unit (ECU) was the basket that came before the euro.

The GCU demo in FinAegis is a reference implementation of the same idea.

### What a Basket Design Gives You

| Property | Explanation |
|---------|-------------|
| **Diversification** | The value depends on several currencies, so a move in one of them has a smaller effect. |
| **Governance** | The composition can be changed through polls. |
| **Transparency** | The value is a published weighted sum of exchange rates. |
| **Flexibility** | The weights can change over time. |

### What FinAegis Already Provides

You do not write tables or services for a new basket. The Basket domain in `app/Domain/Basket/` already has them.

- The tables `basket_assets`, `basket_components` and `basket_values` come from `database/migrations/2025_06_16_232847_create_basket_assets_tables.php`.
- The models are `BasketAsset`, `BasketComponent`, `BasketValue` and `BasketPerformance`.
- `BasketValueCalculationService` values a basket in USD.
- `BasketRebalancingService` keeps component weights inside their bounds.
- `BasketService` composes and decomposes baskets through `ComposeBasketWorkflow` and `DecomposeBasketWorkflow`.
- The REST API lives under `/api/v2/baskets`, and the GraphQL schema is `graphql/basket.graphql`.

A new basket is data. You add the component assets, create the basket and its components, and then use the existing services.

---

## Prerequisites

Before starting, make sure you have these in place.

- A FinAegis development environment is running, with the Basket module enabled.
- You know the basics of [Event Sourcing](../adr/0007-event-sourcing.md) and the [Saga Pattern](../adr/0009-saga-pattern.md).
- Each component currency has an exchange rate to USD in the `exchange_rates` table, or you add test rates as in Step 6.

---

## Step 1: Design Your Basket

### 1.1 Define the Purpose

Answer these questions first.
- What problem does this basket solve?
- Who will use it?
- What assets should be included?
- How often should it rebalance?

### 1.2 Choose Components

This tutorial builds an **Asian Currency Basket (ACB)**. Weights in FinAegis are percentages from 0 to 100, and they must add up to 100.

| Currency | Weight | Rationale |
|----------|--------|-----------|
| CNY | 40 | Largest Asian economy |
| JPY | 30 | Second largest economy in the basket |
| KRW | 15 | Large export economy |
| SGD | 10 | Regional financial centre |
| HKD | 5 | Regional trade centre |

### 1.3 Define Rebalancing Rules

A FinAegis basket has three rebalancing settings.

- `type` is `fixed` or `dynamic`. Only dynamic baskets are rebalanced.
- `rebalance_frequency` is `daily`, `weekly`, `monthly`, `quarterly` or `never`.
- Each component can have a `min_weight` and a `max_weight`. Rebalancing moves a component's weight back inside these bounds.

```php
// Rebalancing settings for the ACB basket
$settings = [
    'type'                => 'dynamic',
    'rebalance_frequency' => 'monthly',
    // Per component: weight with lower and upper bounds (percent)
    'bounds' => [
        'CNY' => ['weight' => 40, 'min_weight' => 35, 'max_weight' => 45],
        'JPY' => ['weight' => 30, 'min_weight' => 25, 'max_weight' => 35],
    ],
];
```

---

## Step 2: Create the Basket

### 2.1 Add the Component Assets

`basket_components.asset_code` is a foreign key to `assets.code`. The default `AssetSeeder` adds USD, EUR, GBP, CHF, JPY, XAU, GCU, BTC and ETH. Add the other ACB currencies first.

```php
// database/seeders/ACBAssetSeeder.php

namespace Database\Seeders;

use App\Domain\Asset\Models\Asset;
use Illuminate\Database\Seeder;

class ACBAssetSeeder extends Seeder
{
    public function run(): void
    {
        $assets = [
            ['code' => 'CNY', 'name' => 'Chinese Yuan'],
            ['code' => 'KRW', 'name' => 'South Korean Won'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar'],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar'],
        ];

        foreach ($assets as $asset) {
            Asset::updateOrCreate(
                ['code' => $asset['code']],
                ['name' => $asset['name'], 'type' => 'fiat', 'precision' => 2, 'is_active' => true]
            );
        }
    }
}
```

### 2.2 Create the Basket Through the API

`POST /api/v2/baskets` creates a basket with its components. Only platform operators (the `admin`, `super_admin` and `bank_admin` roles) may call it.

```http
POST /api/v2/baskets
Authorization: Bearer {operator-token}
Content-Type: application/json

{
  "code": "ACB",
  "name": "Asian Currency Basket",
  "description": "A demo basket of major Asian currencies",
  "type": "dynamic",
  "rebalance_frequency": "monthly",
  "components": [
    {"asset_code": "CNY", "weight": 40, "min_weight": 35, "max_weight": 45},
    {"asset_code": "JPY", "weight": 30, "min_weight": 25, "max_weight": 35},
    {"asset_code": "KRW", "weight": 15, "min_weight": 10, "max_weight": 20},
    {"asset_code": "SGD", "weight": 10, "min_weight": 5, "max_weight": 15},
    {"asset_code": "HKD", "weight": 5, "min_weight": 0, "max_weight": 10}
  ]
}
```

The endpoint returns 422 when the weights do not add up to 100. On success it also creates a matching row in `assets` (through `BasketAsset::toAsset()`) and stores the first value.

### 2.3 Or Create It in a Seeder

```php
// database/seeders/ACBBasketSeeder.php

namespace Database\Seeders;

use App\Domain\Basket\Models\BasketAsset;
use Illuminate\Database\Seeder;

class ACBBasketSeeder extends Seeder
{
    public function run(): void
    {
        $basket = BasketAsset::updateOrCreate(
            ['code' => 'ACB'],
            [
                'name'                => 'Asian Currency Basket',
                'description'         => 'A demo basket of major Asian currencies',
                'type'                => 'dynamic',
                'rebalance_frequency' => 'monthly',
                'is_active'           => true,
            ]
        );

        $basket->components()->delete();

        $components = [
            ['asset_code' => 'CNY', 'weight' => 40.0, 'min_weight' => 35.0, 'max_weight' => 45.0],
            ['asset_code' => 'JPY', 'weight' => 30.0, 'min_weight' => 25.0, 'max_weight' => 35.0],
            ['asset_code' => 'KRW', 'weight' => 15.0, 'min_weight' => 10.0, 'max_weight' => 20.0],
            ['asset_code' => 'SGD', 'weight' => 10.0, 'min_weight' => 5.0, 'max_weight' => 15.0],
            ['asset_code' => 'HKD', 'weight' => 5.0, 'min_weight' => 0.0, 'max_weight' => 10.0],
        ];

        foreach ($components as $component) {
            $basket->components()->create($component + ['is_active' => true]);
        }

        // Register the basket as an asset so accounts can hold it
        $basket->toAsset();
    }
}
```

`database/seeders/GCUBasketSeeder.php` builds the GCU demo basket the same way.

---

## Step 3: Value the Basket

### 3.1 Calculate the Value

`BasketValueCalculationService::calculateValue()` values one basket unit in USD. For each active component it multiplies the component's USD rate by its weight divided by 100, and it adds the results up.

```php
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Services\BasketValueCalculationService;

$basket = BasketAsset::where('code', 'ACB')->firstOrFail();
$service = app(BasketValueCalculationService::class);

$value = $service->calculateValue($basket, useCache: false);

$value->value;            // USD value of one ACB unit
$value->component_values; // per-component rate, weight and weighted value
$value->calculated_at;
```

The service reads rates through `App\Domain\Asset\Services\ExchangeRateService`. A component without a USD rate is logged and left out of that calculation, and the error is kept in `component_values._metadata.calculation_errors`. Each result is stored in `basket_values` under the basket's code (`basket_asset_code`). Results are cached for five minutes under `basket_value:{code}`, unless you pass `useCache: false`.

Before v7.18.0 the GCU endpoints looked up stored values by a column that does not exist, so they never found a GCU value. They now use `basket_asset_code`.

### 3.2 History and Performance

```php
$history = $service->getHistoricalValues($basket, now()->subDays(30), now());
$performance = $service->calculatePerformance($basket, now()->subDays(30), now());
```

The same data is available over the API and the console.

```bash
GET /api/v2/baskets/ACB/value
GET /api/v2/baskets/ACB/history
GET /api/v2/baskets/ACB/performance

php artisan basket:calculate-performance --basket=ACB --period=month
php artisan baskets:performance ACB --period=30d --detailed
```

The scheduler in `routes/console.php` runs `basket:calculate-performance` every hour and with `--period=day` every day at 00:30, as long as the Basket module is enabled.

---

## Step 4: Compose and Decompose

Composing turns component balances into basket units. Decomposing turns basket units back into components. Amounts are integers in the smallest unit, and each component amount is `amount × weight / 100`, rounded.

```http
POST /api/v2/accounts/{uuid}/baskets/compose
Authorization: Bearer {token}
Content-Type: application/json

{"basket_code": "ACB", "amount": 1000}
```

`POST /api/v2/accounts/{uuid}/baskets/decompose` takes the same body, and `GET /api/v2/accounts/{uuid}/baskets` lists the account's basket holdings. The account must belong to the signed-in user.

In code, `BasketService::composeBasket()` checks the basket, its weights and the account's balances, and then starts `ComposeBasketWorkflow`.

```php
use App\Domain\Basket\Services\BasketService;

$result = app(BasketService::class)->composeBasket(
    accountUuid: $account->uuid,
    basketCode: 'ACB',
    amount: 1000
);
```

`ComposeBasketWorkflow` runs `ComposeBasketActivity`. If a later step fails, its compensation runs `DecomposeBasketActivity` to undo the composition.

```php
// app/Domain/Basket/Workflows/ComposeBasketWorkflow.php (shipped)

class ComposeBasketWorkflow extends Workflow
{
    public function execute(AccountUuid $accountUuid, string $basketCode, int $amount): Generator
    {
        try {
            $result = yield ActivityStub::make(
                ComposeBasketActivity::class,
                $accountUuid,
                $basketCode,
                $amount
            );

            // Add compensation to decompose the basket if composition fails later
            $this->addCompensation(
                fn () => ActivityStub::make(
                    DecomposeBasketActivity::class,
                    $accountUuid,
                    $basketCode,
                    $amount
                )
            );

            return $result;
        } catch (Throwable $th) {
            yield from $this->compensate();
            throw $th;
        }
    }
}
```

---

## Step 5: Rebalancing and Governance

### 5.1 Rebalancing

`BasketRebalancingService` works on dynamic baskets. A basket is due when its `rebalance_frequency` period has passed since `last_rebalanced_at`. Rebalancing moves any component weight that is outside its `min_weight` and `max_weight` back to the nearest bound, and it normalises the weights to 100. It changes the stored weights and fires a `BasketRebalanced` event. It does not place trades.

```php
use App\Domain\Basket\Services\BasketRebalancingService;

$rebalancing = app(BasketRebalancingService::class);

$rebalancing->needsRebalancing($basket);     // is the basket due?
$rebalancing->simulateRebalancing($basket);  // dry run, weights stay unchanged
$rebalancing->rebalance($basket);            // apply the adjustments
$rebalancing->getRebalancingHistory($basket);
```

```bash
php artisan baskets:rebalance --basket=ACB --dry-run
php artisan baskets:rebalance --basket=ACB --force
```

`POST /api/v2/baskets/{code}/rebalance` does the same for platform operators. The scheduler runs `baskets:rebalance` on the first day of each month at 00:05.

### 5.2 Composition Polls

The Governance domain can change a basket's composition through a poll. `VotingTemplateService::createMonthlyBasketVotingPoll()` shows the pattern for the primary basket. It creates a weighted-choice poll with asset-weighted voting, sets `execution_workflow` to `UpdateBasketCompositionWorkflow` and stores the basket code in the poll metadata.

```php
use App\Domain\Governance\Enums\PollType;
use App\Domain\Governance\Strategies\AssetWeightedVotingStrategy;
use App\Domain\Governance\Services\GovernanceService;
use App\Domain\Governance\Workflows\UpdateBasketCompositionWorkflow;

$poll = app(GovernanceService::class)->createPoll([
    'title'                  => 'ACB composition - next quarter',
    'type'                   => PollType::WEIGHTED_CHOICE->value,
    'options'                => [
        [
            'id'         => 'basket_weights',
            'label'      => 'ACB weights',
            'type'       => 'allocation',
            'currencies' => [
                ['code' => 'CNY', 'name' => 'Chinese Yuan', 'min' => 30, 'max' => 50, 'default' => 40],
                ['code' => 'JPY', 'name' => 'Japanese Yen', 'min' => 20, 'max' => 40, 'default' => 30],
                // ...
            ],
            'constraint' => 'must_sum_to_100',
        ],
    ],
    'start_date'             => now(),
    'end_date'               => now()->addDays(7),
    'required_participation' => 10,
    'voting_power_strategy'  => AssetWeightedVotingStrategy::class,
    'execution_workflow'     => UpdateBasketCompositionWorkflow::class,
    'created_by'             => $operator->uuid,
    'metadata'               => ['basket_code' => 'ACB'],
]);
```

`UpdateBasketCompositionWorkflow` takes the poll's UUID. It calculates the weighted average of the votes, updates the basket components, triggers rebalancing and records a governance event.

`GovernanceService::completePoll()` does not start this workflow today. It only knows the short names `AddAssetWorkflow`, `FeatureToggleWorkflow` and `UpdateConfigurationWorkflow`. After the poll closes, start the workflow yourself.

```php
use Workflow\WorkflowStub;

WorkflowStub::make(UpdateBasketCompositionWorkflow::class)->start($poll->uuid);
```

The GCU demo also has a separate path. Simulated GCU proposals (`GcuVotingProposal`) are applied with `php artisan baskets:apply-vote`, which updates the GCU composition and rebalances the dynamic baskets. v7.18.0 fixed the GCU voting power calculation, which read columns that do not exist.

---

## Step 6: Testing

### 6.1 Value Calculation

The shipped tests in `tests/Feature/Basket/` and `tests/Domain/Basket/` follow this pattern. Create the assets and exchange rates, create the basket, and check the calculated value.

```php
// tests/Feature/Basket/ACBValueTest.php

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Services\BasketValueCalculationService;

it('values a 50/50 USD and EUR basket', function () {
    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);

    ExchangeRate::create([
        'from_asset_code' => 'EUR',
        'to_asset_code'   => 'USD',
        'rate'            => 1.1000,
        'is_active'       => true,
        'source'          => 'test',
        'valid_at'        => now(),
        'expires_at'      => now()->addHours(2),
    ]);

    $basket = BasketAsset::create([
        'code'                => 'TEST_5050',
        'name'                => 'Test 50/50',
        'type'                => 'fixed',
        'rebalance_frequency' => 'never',
    ]);
    $basket->components()->createMany([
        ['asset_code' => 'USD', 'weight' => 50.0, 'is_active' => true],
        ['asset_code' => 'EUR', 'weight' => 50.0, 'is_active' => true],
    ]);

    $value = app(BasketValueCalculationService::class)->calculateValue($basket, useCache: false);

    // (1.00 × 0.50) + (1.10 × 0.50) = 1.05
    expect($value->value)->toEqualWithDelta(1.05, 0.0001)
        ->and($basket->values()->count())->toBe(1);
});
```

### 6.2 Existing Tests to Read

- `tests/Feature/Basket/BasketValueCalculationServiceTest.php`
- `tests/Feature/Basket/BasketRebalancingServiceTest.php`
- `tests/Feature/Basket/BasketAccountServiceTest.php`
- `tests/Feature/Basket/GcuBasketValueLookupTest.php`
- `tests/Feature/Domain/Basket/Activities/ComposeBasketActivityTest.php`
- `tests/Domain/Basket/Workflows/DecomposeBasketWorkflowTest.php`
- `tests/Integration/GraphQL/BasketGraphQLTest.php`

---

## Advanced Topics

The examples in this section are sketches. FinAegis does not ship them.

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
            fn($asset) => $asset['market_cap'] / $totalMarketCap * 100,
            $assets
        );
    }
}

class EqualWeightingStrategy implements WeightingStrategyInterface
{
    public function calculateWeights(array $assets): array
    {
        $weight = 100 / count($assets);
        return array_fill(0, count($assets), $weight);
    }
}
```

The strategies return percentages, because `basket_components.weight` stores 0 to 100.

### Multi-Chain Basket

A basket whose components live on several blockchains needs a lock step per chain. FinAegis baskets have no chain field, so this sketch assumes you add one.

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

### Real-Time Value Updates

```php
// Using Laravel Broadcasting
class BasketValueBroadcaster
{
    public function broadcastValue(BasketAsset $basket): void
    {
        $value = $this->valueService->calculateValue($basket, useCache: false);

        broadcast(new BasketValueUpdated($basket->code, $value->value))->toOthers();
    }
}
```

`BasketValueUpdated` is an event class you would write yourself.

---

## Summary

You have learned how to do these things.

1. Design a basket with a clear purpose, weights and rebalancing bounds.
2. Create the basket and its component assets through the API or a seeder.
3. Value the basket and read its history and performance.
4. Compose and decompose basket units through the shipped workflows.
5. Rebalance the basket and change its composition through a poll.
6. Test the valuation.

Use the [GCU implementation](../../app/Domain/Basket/) as your reference.

---

## Related Resources

- [ADR-0010: GCU Basket Design](../adr/0010-gcu-basket-design.md)
- [GCU Domain README](../../app/Domain/Basket/README.md)
- [Event Sourcing ADR](../adr/0007-event-sourcing.md)
- [Saga Pattern ADR](../adr/0009-saga-pattern.md)
