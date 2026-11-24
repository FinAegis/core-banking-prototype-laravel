# Liquidity Pools

## Overview

Liquidity pools are part of the FinAegis demo trading engine. A pool holds two assets and prices swaps with an automated market maker (AMM) formula instead of an order book.

The whole feature sits behind the trading switch. Routes use the `feature:trading.enabled` middleware, which reads `TRADING_ENABLED`. The switch is on by default only for `APP_ENV=local`, `testing` and `demo`, and production sets `TRADING_ENABLED=false`. Zelta does not use the exchange.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

### Removed in v7.18.0

The flow for adding, removing and rebalancing liquidity was removed in v7.18.0. It never worked, because it used a class, a table and columns that do not exist. The removal covered these parts:

- `LiquidityManagementWorkflow` and `EnhancedLiquidityManagementWorkflow`, with their activities and retry policy.
- The `POST /api/liquidity/add` and `POST /api/liquidity/remove` endpoints and the web add and remove pages.
- `PoolRebalancingService` and the `liquidity:rebalance` command with its schedule.
- The `add_liquidity` and `remove_liquidity` actions of the AI liquidity pool tool.

The `LiquidityPool` aggregate still has `addLiquidity()`, `removeLiquidity()` and `rebalancePool()` as domain logic, but no API, page, command or workflow calls them. A new pool therefore has no reserves unless a test or a script puts them there.

Pool lists, pool creation, swaps, positions, rewards, impermanent-loss protection and pool analytics stay.

## Architecture

### Events and aggregate

The pools use event sourcing. The `LiquidityPool` aggregate in `app/Domain/Exchange/Aggregates/LiquidityPool.php` keeps the pool state and records these events:

- `LiquidityPoolCreated` when a pool is created.
- `PoolFeeCollected` when a swap is calculated.
- `LiquidityRewardsDistributed` and `LiquidityRewardsClaimed` for rewards.
- `PoolParametersUpdated` when pool settings change.
- `ImpermanentLossProtectionEnabled` and `ImpermanentLossProtectionClaimed` for impermanent-loss protection.

The event classes `LiquidityAdded`, `LiquidityRemoved` and `LiquidityPoolRebalanced` still exist for the aggregate methods above, but nothing records them in normal use.

### Services

- `LiquidityPoolService` creates pools, runs swaps, lists pools and positions, and handles rewards and impermanent-loss protection.
- `LiquidityIncentivesService` calculates and distributes rewards.
- `ImpermanentLossProtectionService` calculates impermanent loss and protection claims.
- `AutomatedMarketMakerService` generates market-making orders for the order book.

## Core Concepts

### Constant product formula

The pool prices swaps with the constant product formula `x * y = k`.

- `x` is the amount of the base currency in the pool.
- `y` is the amount of the quote currency in the pool.
- `k` is the constant that the formula keeps during a swap.

### Price impact

A larger trade moves the price more. The service reports price impact as `|spotPrice - executionPrice| / spotPrice × 100`. A caller can set a minimum output amount, and the swap fails with "Slippage tolerance exceeded" when the output would be lower.

### Fees

The default swap fee is 0.3%. A pool can be created with a fee rate between 0.01% and 1%. There is no separate protocol fee.

## Features

### Swaps

A swap trades one asset of the pool for the other. The pool aggregate calculates the output with the constant product formula. `ExchangeService::executePoolSwap` then records two transfers between the person's account and a pool account.

Swaps are a demonstration path.
A new pool starts with no reserves, and since v7.18.0 no endpoint adds liquidity.
A swap therefore needs a pool whose reserves were set up some other way, for example in a test.
The swap also does not update the pool reserves, and the code marks this with a `@todo` in `ExchangeService::executePoolSwap`.

### Positions

The positions endpoint lists the pools a person has shares in, with the shares and the value of each position.

### Rewards

The `liquidity:distribute-rewards` command calculates rewards for providers and runs every hour when trading is enabled. A provider can claim rewards with the claim endpoint.

### Impermanent-loss protection

Impermanent loss is the value a provider loses compared with simply holding the two assets. Protection is enabled per pool with a threshold, a maximum coverage, a minimum holding period and a fund size. The defaults are a 2% threshold, 80% coverage and 168 hours. The service then calculates the loss for a position and processes protection claims for a pool.

The enable and process-claims endpoints only check that the caller is signed in. They do not check for an administrator, so a deployment that turns trading on should restrict them first.

### Market making

The `liquidity:update-market-making` command places automated market-making orders in the order book and runs every five minutes when trading is enabled.

## Implementation Details

### Database tables

- `liquidity_pools` stores the pool state, reserves, fees and volume.
- `liquidity_providers` stores provider positions, shares and rewards.
- `pool_swaps` stores swap records.
- `liquidity_pool_events` is the event store for the pool aggregate.

### Scheduled commands

Both commands are scheduled in `routes/console.php` only when the Exchange module is enabled and `trading.enabled` is true.

| Command | Schedule |
|---------|----------|
| `liquidity:distribute-rewards` | Hourly |
| `liquidity:update-market-making --cancel-existing` | Every five minutes |

## API Endpoints

All endpoints are under the `feature:trading.enabled` middleware. The two pool reads do not need a sign-in. The other endpoints need a Sanctum token.

### Public Endpoints

#### Get All Pools
```http
GET /api/liquidity/pools
```
Returns the active liquidity pools with their metrics.

#### Get Pool Details
```http
GET /api/liquidity/pools/{poolId}
```
Returns the details of one pool.

### Authenticated Endpoints

#### Create Pool
```http
POST /api/liquidity/pools
Authorization: Bearer {token}

{
  "base_currency": "BTC",
  "quote_currency": "EUR",
  "fee_rate": "0.003"
}
```

#### Execute Swap
```http
POST /api/liquidity/swap
Authorization: Bearer {token}

{
  "pool_id": "uuid",
  "input_currency": "BTC",
  "input_amount": "0.1",
  "min_output_amount": "4750"
}
```

#### Get Positions
```http
GET /api/liquidity/positions
Authorization: Bearer {token}
```

#### Claim Rewards
```http
POST /api/liquidity/claim-rewards
Authorization: Bearer {token}

{
  "pool_id": "uuid"
}
```

#### Impermanent-Loss Protection
```http
GET  /api/liquidity/il-protection/{positionId}
POST /api/liquidity/il-protection/enable
POST /api/liquidity/il-protection/process-claims
GET  /api/liquidity/il-protection/fund-requirements/{poolId}
```

The enable request takes `pool_id` and the optional `protection_threshold`, `max_coverage`, `min_holding_hours` and `fund_size`.

#### Pool Analytics
```http
GET /api/liquidity/analytics/{poolId}
Authorization: Bearer {token}
```

### Web Pages

- `/liquidity` lists the pools.
- `/liquidity/{poolId}` shows one pool.

Both pages use the same trading switch.

## Configuration

The trading switch is the only setting the pools need.

```env
TRADING_ENABLED=true   # demo and local; production sets false
```

The pool fee is set per pool when the pool is created. There are no separate `LIQUIDITY_*` environment variables.

## Security Considerations

- Amounts are calculated with `Brick\Math\BigDecimal`, not floats.
- The minimum output amount protects a swap against slippage.
- The create, swap and claim endpoints use the `idempotency` middleware, so a repeated request with the same idempotency key does not run twice.
- Impermanent-loss tracking shows providers the risk of holding a position.

## Testing

```php
// Create a pool
$poolId = $liquidityService->createPool('BTC', 'EUR', '0.003');

// Calculate and record a swap (the pool needs reserves first)
$swap = $liquidityService->swap($poolId, $accountId, 'BTC', '0.1');
```

## Future Enhancements

These ideas are not built.

- Rebuild adding and removing liquidity on tables and columns that exist.
- Update pool reserves when a swap runs.
- Concentrated liquidity, dynamic fees and pools with more than two assets.
