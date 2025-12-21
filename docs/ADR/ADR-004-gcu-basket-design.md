# ADR-004: GCU Basket Currency Design

## Status

Accepted

## Context

The Global Currency Unit (GCU) is designed as a democratic digital currency that:

1. **Provides Stability** - Less volatile than single currencies
2. **Democratic Governance** - Community decides composition
3. **Transparent Valuation** - Clear NAV calculation
4. **Automatic Rebalancing** - Maintains target weights

Design challenges:
- Currency basket composition and weighting
- NAV (Net Asset Value) calculation precision
- Rebalancing triggers and execution
- Governance integration for composition changes
- Performance at scale

## Decision

We will implement GCU as a **weighted basket currency** with these characteristics:

### Basket Composition

| Currency | Weight | Rationale |
|----------|--------|-----------|
| USD | 40% | Global reserve currency, liquidity |
| EUR | 30% | Second largest reserve, EU stability |
| GBP | 15% | Major trading currency, stability |
| CHF | 10% | Safe haven, inflation hedge |
| JPY | 3% | Asian market representation |
| XAU (Gold) | 2% | Inflation hedge, store of value |

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        GCU Basket                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────┐    ┌─────────────────┐                     │
│  │  BasketService  │    │  Governance     │                     │
│  │                 │◀───│  Voting System  │                     │
│  └────────┬────────┘    └─────────────────┘                     │
│           │                                                      │
│  ┌────────▼────────┐    ┌─────────────────┐                     │
│  │ NAV Calculation │    │  Rebalancing    │                     │
│  │    Service      │    │    Service      │                     │
│  └────────┬────────┘    └────────┬────────┘                     │
│           │                      │                               │
│  ┌────────▼────────────────────▼────────┐                      │
│  │         Exchange Rate Oracle          │                      │
│  │   (Real-time currency prices)         │                      │
│  └───────────────────────────────────────┘                      │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### NAV Calculation

```php
class BasketValueCalculationService
{
    public function calculateNAV(Basket $basket): Money
    {
        $nav = Money::zero('USD');

        foreach ($basket->components as $component) {
            // Get current exchange rate
            $rate = $this->exchangeRateService->getRate(
                $component->currency,
                'USD'
            );

            // Calculate component value
            $componentValue = $component->units
                ->multiply($rate)
                ->multiply($component->weight);

            $nav = $nav->add($componentValue);
        }

        return $nav;
    }
}
```

### Rebalancing Strategy

```php
class BasketRebalancingService
{
    // Rebalancing triggers when drift exceeds threshold
    private const DRIFT_THRESHOLD = 0.05; // 5%

    public function shouldRebalance(Basket $basket): bool
    {
        foreach ($basket->components as $component) {
            $currentWeight = $this->calculateCurrentWeight($basket, $component);
            $drift = abs($currentWeight - $component->targetWeight);

            if ($drift > self::DRIFT_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    public function executeRebalancing(Basket $basket): RebalancingResult
    {
        return DB::transaction(function () use ($basket) {
            $trades = [];

            foreach ($this->calculateTrades($basket) as $trade) {
                $result = $this->exchangeService->executeTrade($trade);
                $trades[] = $result;
            }

            $basket->recordRebalancing($trades);

            return new RebalancingResult($trades);
        });
    }
}
```

### Governance Integration

```php
class UpdateBasketCompositionWorkflow extends Workflow
{
    public function execute(CompositionChangeProposal $proposal): Generator
    {
        // Step 1: Create governance proposal
        $governanceProposal = yield ActivityStub::make(
            CreateGovernanceProposalActivity::class,
            $proposal,
        );

        // Step 2: Wait for voting period
        yield WorkflowStub::timer(days: 7);

        // Step 3: Tally votes
        $result = yield ActivityStub::make(
            TallyVotesActivity::class,
            $governanceProposal->id,
        );

        if ($result->approved) {
            // Step 4: Execute composition change
            yield ActivityStub::make(
                UpdateBasketCompositionActivity::class,
                $proposal->newComposition,
            );

            // Step 5: Trigger rebalancing
            yield ActivityStub::make(
                TriggerRebalancingActivity::class,
                $proposal->basketId,
            );
        }

        return $result;
    }
}
```

### Configuration

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
        'drift_threshold' => 0.05,
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

## Consequences

### Positive

- **Stability** - Diversified basket reduces volatility
- **Transparency** - Clear, auditable NAV calculation
- **Democratic** - Community controls composition
- **Flexible** - Composition can evolve over time
- **Educational** - Demonstrates basket currency concepts

### Negative

- **Complexity** - Multiple moving parts
- **Exchange rate dependency** - Needs reliable price feeds
- **Rebalancing costs** - Trading fees during rebalancing
- **Governance overhead** - Voting system complexity

### Mitigations

| Challenge | Mitigation |
|-----------|------------|
| Price feed reliability | Multiple oracle sources, fallbacks |
| Rebalancing costs | Threshold-based, batched trades |
| Governance complexity | Clear voting rules, UI guidance |
| NAV calculation accuracy | BigDecimal math, audit trail |

## Design Decisions

### Why These Currencies?

| Currency | Reason for Inclusion |
|----------|---------------------|
| USD (40%) | Most liquid, global benchmark |
| EUR (30%) | Large economy, stability |
| GBP (15%) | Historical stability, trading volume |
| CHF (10%) | Safe haven during crises |
| JPY (3%) | Asian representation, liquidity |
| XAU (2%) | Inflation hedge, non-fiat anchor |

### Why Monthly Rebalancing?

- **Balance**: Between responsiveness and transaction costs
- **Predictability**: Users know when changes occur
- **Cost efficiency**: Batched trades reduce fees
- **Governance**: Time for democratic input

### Why 5% Drift Threshold?

- **Materiality**: Small drifts don't matter
- **Cost**: Avoid unnecessary transactions
- **Stability**: Prevents over-trading
- **Standard**: Common in index funds

## Alternatives Considered

### 1. Fixed Basket (No Governance)

**Pros**: Simpler, predictable
**Cons**: Cannot adapt to economic changes

**Rejected because**: Democratic governance is core to GCU vision

### 2. Market-Cap Weighted

**Pros**: Automatic, objective
**Cons**: Volatile, USD dominated

**Rejected because**: Would defeat stability goals

### 3. GDP-Weighted

**Pros**: Economic representation
**Cons**: Infrequent updates, data lag

**Considered for**: Future governance proposals

## Future Considerations

1. **Additional Components** - Could add CNY, CAD, AUD
2. **Dynamic Weighting** - Algorithmic adjustment based on volatility
3. **Derivative Backing** - Options/futures for hedging
4. **Multi-Chain** - GCU on multiple blockchains

## References

- [IMF Special Drawing Rights (SDR)](https://www.imf.org/en/About/Factsheets/Sheets/2016/08/01/14/51/Special-Drawing-Right-SDR)
- [Currency Basket Theory](https://en.wikipedia.org/wiki/Currency_basket)
- [Index Fund Rebalancing](https://www.investopedia.com/terms/r/rebalancing.asp)
