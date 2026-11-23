# Global Currency Unit (GCU) Demo Guide

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.
>
> See [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md).

## Overview

The GCU demo is a reference implementation of a basket-referenced unit built with FinAegis. This guide describes the simulated demo flows. All balances, conversions and votes are simulated and have no monetary value.

The GCU pages run in the demo environment at [finaegis.org](https://finaegis.org) and on local copies. They need the Basket and Governance modules. Production deployments such as the Zelta site disable both modules.

## What the Demo Shows

### 1. Simulated Governance

Demo accounts vote on the basket weights once a month. Each vote is weighted by the account's simulated GCU balance. The vote counts are visible to everyone.

### 2. Illustrative Basket Valuation

The demo calculates an illustrative GCU value from six components. They are five currencies (USD, EUR, GBP, CHF and JPY) and gold (XAU). When the Basket module is on, the scheduler recalculates the value every hour.

## Getting Started

### Trying a Simulated GCU Conversion

In the demo, you can convert demo balances into GCU demo units and back. No real funds are used.

The conversion runs through the API.

- `POST /api/v2/gcu/buy` converts a demo balance in EUR, USD, GBP or CHF into GCU demo units. The minimum amount is 100.
- `POST /api/v2/gcu/sell` converts GCU demo units back into EUR, USD, GBP or CHF. The minimum amount is 10.
- `GET /api/v2/gcu/quote` returns a quote before you convert.
- `GET /api/v2/gcu/trading-limits` returns your limits.

Each conversion applies a simulated fee of 1%. All four endpoints need a bearer token and the Basket module.

```bash
curl -X POST https://finaegis.org/api/v2/gcu/buy \
  -H "Authorization: Bearer {access_token}" \
  -H "Content-Type: application/json" \
  -d '{"amount": 100, "currency": "EUR"}'
```

The web page at `/gcu/trading` is meant to show the same conversion with your balances and the recent price history. At v7.18.1 the page does not load, because its forms point to a route name that does not exist. Use the API until it is fixed.

### Understanding the Illustrative GCU Value

The illustrative GCU demo value is a weighted average of its components. The example below shows how the calculation works. The weights and prices in it are examples only.

**Example composition**
```
USD: 35% × $1.00 = $0.35
EUR: 30% × $1.08 = $0.324
GBP: 20% × $1.26 = $0.252
CHF: 10% × $1.12 = $0.112
JPY: 3% × $0.0067 = $0.0002
XAU: 2% × $63.50 = $1.27

Total: 1 GCU = ~$2.31 USD (illustrative only; no monetary value)
```

The public `/gcu` page shows the example weights from `config/platform.php`, which are the ones above. The basket that `GCUBasketSeeder` creates starts at USD 40%, EUR 30%, GBP 15%, CHF 10%, JPY 3% and gold 2%.

## Custodian Allocation (simulated)

> Demo only: no funds are held at any bank and no deposit insurance applies. Bank names in the demo are illustrative placeholders; no partnership or endorsement is implied.

### Setting Your Preferences

1. Open the demo allocation page at `/wallet/bank-allocation`.

2. Move the sliders to split your demo balance across the three demo custodians.

   | Demo custodian | Starting share | Allowed range |
   |----------------|----------------|---------------|
   | Demo Bank A | 40% | 20% to 60% |
   | Demo Bank B | 30% | 10% to 40% |
   | Demo Bank C | 30% | 10% to 40% |

3. Make sure the shares add up to exactly 100%. Each share must stay inside its allowed range.

4. Choose a primary demo custodian. Demo Bank A is the primary one at the start.

The page saves your choice through the `/api/bank-allocations` API. The allocation is only a stored preference. No money moves between banks.

## Simulated Governance

### How Voting Works

1. **Monthly proposals.** On the 20th of each month, the scheduler runs `voting:setup`. It creates the GCU composition proposal for the next month. Voting on that proposal opens on the 20th of that month and stays open for seven days.
2. **Demo voting weight.** Your vote counts with your simulated demo GCU balance. You need a balance above zero to vote.
3. **What the demo polls cover.** The polls cover the demo basket weights only.

### Casting Your Vote

1. Open **Governance** in the top navigation. It leads to `/gcu/voting`.
2. Click the active proposal. It shows the current weights and the proposed weights.
3. Vote for, against or abstain.
4. You can change your vote until voting closes. Only your latest vote counts.

The governance polls API takes a different kind of vote. With `POST /api/voting/polls/{uuid}/vote` you send your own preferred weights, and they must add up to 100%. See the [GCU voting guide](GCU_VOTING_GUIDE.md).

**Example of preferred weights:**
```
USD: 32%
EUR: 33%
GBP: 20%
CHF: 10%
JPY: 3%
XAU: 2%

Total: 100%
```

### Understanding Results

When voters send their preferred weights, the result for each component is the average of the votes, weighted by each voter's simulated balance.

```
Example with 3 demo voters (simulated balances):
Voter A: 1,000 GCU votes USD=40%
Voter B: 500 GCU votes USD=35%
Voter C: 1,500 GCU votes USD=30%

Result: (1000×40 + 500×35 + 1500×30) / 3000 = 34.17%
```

## Frequently Asked Questions

### General Questions

**Q: How is GCU different from cryptocurrencies?**
A: GCU is a software demonstration recorded only in the demo application's database. It is not issued, offered or sold to anyone and has no monetary value.

**Q: Can I lose money with GCU?**
A: No real money is involved. GCU has no monetary value and all balances in the demo are simulated.

### Technical Questions

**Q: How often is the illustrative GCU value updated?**
A: When the Basket module is on, the scheduler recalculates it every hour from exchange-rate data. It remains illustrative only.

### Voting Questions

**Q: What if I miss a vote?**
A: Your demo voting weight is not used for that proposal. You can take part in the next month's proposal.

**Q: Can I delegate my votes?**
A: No. Delegation is not supported.

**Q: Who can create proposals?**
A: Only administrators can create new proposals in the demo.

## Support and Resources

- The source code and the issue tracker are on [GitHub](https://github.com/FinAegis/core-banking-prototype-laravel).
- The [API documentation](../04-API/) describes the GCU and voting endpoints.
- The [Development guides](../06-DEVELOPMENT/) explain how to run the platform.
- The [Architecture documents](../02-ARCHITECTURE/) explain how it is built.

Please report bugs and suggestions as [GitHub Issues](https://github.com/FinAegis/core-banking-prototype-laravel/issues).

## Conclusion

The GCU demo shows how a basket-referenced unit, its valuation and a governance module can be built with FinAegis. All balances, conversions and votes are simulated.
