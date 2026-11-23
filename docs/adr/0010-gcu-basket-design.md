# ADR-004: GCU Basket Currency Design

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.
>
> See [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md).

## Status

Accepted

> **Implementation status (reviewed for v7.18.1).** The GCU demo runs on the Basket and Governance modules. The production site switches both off through `MODULES_DISABLED`. There is no `config/gcu.php` file. The composition and a minimum and maximum weight for each currency are seeded by `database/seeders/GCUBasketSeeder.php`, with `rebalance_frequency` set to monthly. Rebalancing moves components back inside those weight bands; the 5% drift threshold below is not a configured value. `baskets:rebalance` runs on the first day of each month at 00:05, and `voting:setup` creates the next month's poll on the 20th. `App\Domain\Governance\Workflows\UpdateBasketCompositionWorkflow` reads a finished poll, calculates the new composition from the votes, updates the components and triggers rebalancing. It has no seven-day timer. Version 7.18.0 fixed a lookup that never found GCU basket values and a voting-power query that read a missing column.

## Context

The GCU demo is a reference implementation of a unit whose value refers to a basket. It has four properties:

1. **Diversified reference.** Its value refers to a basket rather than to a single currency.
2. **Governance.** Weighted polls decide the composition. In the demo these polls are simulated.
3. **Transparent valuation.** The net asset value (NAV) calculation is clear.
4. **Automatic rebalancing.** The basket returns to its target weights.

The design had to settle the composition and weights of the basket and the precision of the NAV calculation. It also had to decide what triggers rebalancing and how it runs, how governance changes the composition and how the design performs at scale.

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

A basket reference depends less on any single currency. The NAV calculation is clear and auditable. Composition changes go through polls, which are simulated in the demo. The composition can change over time, and the demo shows how basket currencies work.

### Negative

There are many moving parts. The design depends on reliable exchange rate feeds. Rebalancing would cost trading fees in a real deployment, and the voting system adds complexity.

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

A monthly cycle balances responsiveness against transaction costs. Users know when changes happen. Batched trades cost less, and a month leaves time for the polls.

### Why 5% Drift Threshold?

Small drifts do not matter. A threshold avoids unnecessary transactions and prevents over-trading. Thresholds of this size are common in index funds.

## Alternatives Considered

### 1. Fixed Basket (No Governance)

**Pros**: Simpler, predictable
**Cons**: Cannot adapt to economic changes

**Rejected because**: Governance is a core part of the GCU demo

### 2. Market-Cap Weighted

**Pros**: Automatic, objective
**Cons**: Volatile, USD dominated

**Rejected because**: Would defeat stability goals

### 3. GDP-Weighted

**Pros**: Economic representation
**Cons**: Infrequent updates, data lag

**Considered for**: Future governance proposals

## Future Considerations

The basket could add CNY, CAD and AUD as components. Weights could also be adjusted automatically based on volatility.

## References

- [IMF Special Drawing Rights (SDR)](https://www.imf.org/en/About/Factsheets/Sheets/2016/08/01/14/51/Special-Drawing-Right-SDR)
- [Currency Basket Theory](https://en.wikipedia.org/wiki/Currency_basket)
- [Index Fund Rebalancing](https://www.investopedia.com/terms/r/rebalancing.asp)
