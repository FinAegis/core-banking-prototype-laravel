# Liquidity Pools

## Overview

FinAegis Exchange implements an automated market maker (AMM) model using liquidity pools. This allows users to trade assets without traditional order books by providing liquidity that enables instant swaps at algorithmically determined prices.

## Architecture

### Event-Driven Design

The liquidity pool system uses event sourcing for complete auditability:

1. **Pool Events**:
   - `LiquidityPoolCreated` - New pool initialization
   - `LiquidityAdded` - Provider adds liquidity
   - `LiquidityRemoved` - Provider withdraws liquidity
   - `PoolFeeCollected` - Trading fees collected
   - `LiquidityRewardsDistributed` - Rewards distributed to providers
   - `LiquidityRewardsClaimed` - Provider claims rewards
   - `PoolParametersUpdated` - Pool settings changed
   - `LiquidityPoolRebalanced` - Pool ratio adjusted

2. **Aggregate Root**:
   - `LiquidityPool` aggregate manages pool state and invariants
   - Enforces constant product formula (x * y = k)
   - Calculates shares and swap amounts
   - Handles fee collection and distribution

3. **Workflow-Based Operations**:
   - `LiquidityManagementWorkflow` orchestrates complex operations
   - Handles compensation for failed transactions
   - Manages multi-step processes with atomicity

## Core Concepts

### 1. **Constant Product Formula**
The AMM uses the constant product formula: `x * y = k`
- `x` = Amount of base currency in pool
- `y` = Amount of quote currency in pool
- `k` = Constant that remains unchanged during swaps

### 2. **Liquidity Shares**
- Providers receive shares representing their pool ownership
- First provider shares = √(baseAmount × quoteAmount)
- Subsequent providers receive proportional shares
- Shares determine reward distribution and withdrawal amounts

### 3. **Price Impact**
- Larger trades cause more price slippage
- Price impact = |spotPrice - executionPrice| / spotPrice × 100
- Users can set minimum output amounts to limit slippage

### 4. **Fee Structure**
- Default swap fee: 0.3% (configurable per pool)
- Fees remain in pool, increasing value for providers
- No separate protocol fees (can be added later)

## Features

### For Liquidity Providers

1. **Add Liquidity**
   - Deposit both assets in current pool ratio
   - Receive LP shares representing ownership
   - Start earning fees immediately

2. **Remove Liquidity**
   - Burn shares to withdraw assets
   - Receive proportional share of reserves
   - Can set minimum amounts to protect against slippage

3. **Earn Rewards**
   - Trading fees auto-compound in pool
   - Additional rewards can be distributed
   - Claim rewards without removing liquidity

4. **Position Tracking**
   - View current value of positions
   - Track impermanent loss
   - Monitor earned fees and rewards

### For Traders

1. **Instant Swaps**
   - No order matching required
   - Execute trades against pool liquidity
   - Predictable pricing algorithm

2. **Price Discovery**
   - Real-time spot prices
   - Calculate price impact before trading
   - Set slippage tolerance

3. **Multi-hop Routing**
   - Trade pairs without direct pools
   - Automatic route optimization
   - Best price execution

## Implementation Details

### Database Schema

1. **liquidity_pools**
   - Stores pool state and reserves
   - Tracks fees and volume metrics
   - Links to pool's system account

2. **liquidity_providers**
   - Records provider positions
   - Tracks shares and rewards
   - Maintains contribution history

3. **pool_swaps**
   - Logs all swap transactions
   - Records price impact and fees
   - Enables analytics and reporting

4. **balance_locks**
   - Temporary locks during operations
   - Ensures atomic transactions
   - Enables compensation on failure

### Workflow Activities

1. **ValidateLiquidityActivity**
   - Checks account KYC status
   - Validates pool existence
   - Ensures sufficient balances
   - Verifies ratio compliance

2. **LockLiquidityActivity**
   - Pessimistically locks user funds
   - Creates compensation records
   - Prevents double-spending

3. **TransferLiquidityActivity**
   - Moves assets to/from pool account
   - Updates account balances
   - Records transaction details

4. **CalculatePoolSharesActivity**
   - Computes shares to mint/burn
   - Calculates withdrawal amounts
   - Provides pool state information

## API Endpoints

### Public Endpoints

#### Get All Pools
```http
GET /api/liquidity/pools
```
Returns list of active liquidity pools with metrics.

#### Get Pool Details
```http
GET /api/liquidity/pools/{poolId}
```
Returns detailed information about a specific pool.

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

#### Add Liquidity
```http
POST /api/liquidity/add
Authorization: Bearer {token}

{
  "pool_id": "uuid",
  "base_amount": "1.0",
  "quote_amount": "48000",
  "min_shares": "0"
}
```

#### Remove Liquidity
```http
POST /api/liquidity/remove
Authorization: Bearer {token}

{
  "pool_id": "uuid",
  "shares": "100",
  "min_base_amount": "0.99",
  "min_quote_amount": "47500"
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

## Configuration

### Environment Variables
```env
# Pool defaults
LIQUIDITY_MIN_TVL=1000
LIQUIDITY_DEFAULT_FEE=0.003
LIQUIDITY_MAX_PRICE_IMPACT=0.05

# Rewards
LIQUIDITY_REWARD_INTERVAL=daily
LIQUIDITY_REWARD_SOURCE=protocol_revenue
```

### Pool Parameters
- **Fee Rate**: 0.01% - 1% (default 0.3%)
- **Minimum Liquidity**: Prevents manipulation
- **Maximum Price Impact**: Protects users
- **Active Status**: Can pause trading

## Security Considerations

1. **Reentrancy Protection**
   - All state changes before external calls
   - Pessimistic locking of balances
   - Atomic operations with rollback

2. **Price Manipulation**
   - Minimum liquidity requirements
   - Maximum price impact limits
   - Time-weighted average prices

3. **Sandwich Attack Prevention**
   - Slippage protection
   - MEV resistance mechanisms
   - Private mempool options

4. **Impermanent Loss**
   - Clear risk disclosure
   - IL tracking tools
   - Hedging strategies

## Testing

### Unit Tests
```php
// Test pool creation
$poolId = $liquidityService->createPool('BTC', 'EUR', '0.003');

// Test liquidity addition
$result = $liquidityService->addLiquidity(new LiquidityAdditionInput(...));

// Test swap execution
$swap = $liquidityService->swap($poolId, $accountId, 'BTC', '0.1');
```

### Integration Tests
- End-to-end liquidity provision flow
- Multi-provider scenarios
- Edge cases and error handling
- Performance under load

## Monitoring

### Key Metrics
1. **Pool Health**
   - Total Value Locked (TVL)
   - 24h volume and fees
   - Number of providers
   - Price deviation from external

2. **User Metrics**
   - Active liquidity providers
   - Average position size
   - Reward distribution
   - Withdrawal patterns

3. **System Performance**
   - Swap execution time
   - Gas costs per operation
   - Failed transaction rate
   - Slippage statistics

## Future Enhancements

1. **Advanced Features**
   - Concentrated liquidity (Uniswap V3 style)
   - Dynamic fees based on volatility
   - Multi-asset pools (Balancer style)
   - Flash loan functionality

2. **Incentive Mechanisms**
   - Liquidity mining programs
   - Trading fee rebates
   - Governance token rewards
   - Referral system

3. **Risk Management**
   - Automated IL hedging
   - Portfolio rebalancing
   - Stop-loss for LPs
   - Insurance fund

4. **Cross-Chain**
   - Bridge integrations
   - Cross-chain liquidity
   - Unified liquidity layer
   - Chain-agnostic swaps