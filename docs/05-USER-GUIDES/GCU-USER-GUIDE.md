# Global Currency Unit (GCU) Demo Guide

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.
>
> See [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md).

## Overview

GCU demo — a reference implementation of a basket-referenced unit built with FinAegis. This guide describes the simulated demo flows. All balances, conversions and votes are simulated and have no monetary value.

## What the Demo Shows

### 1. **Simulated Governance**
- Monthly demo polls on basket composition
- Vote weight = simulated demo GCU balance
- Transparent tallying

### 2. **Illustrative Basket Valuation**
- Illustrative value calculated from 6 currencies + gold

## Getting Started

### Trying a Simulated GCU Conversion

In the demo, convert demo balances into GCU demo units; no real funds are used.

1. **Open the GCU demo conversion page**
   ```
   /gcu/trading
   ```

2. **Select Source Currency**
   - Choose from your demo balances
   - View the illustrative rate
   - Check the simulated fee

3. **Enter Amount**
   - Minimum: 100 GCU
   - Maximum: Based on verification level
   - See real-time conversion

4. **Review and Confirm**
   - Check conversion details
   - Verify fees
   - Confirm with 2FA

5. **Simulated Conversion Complete**
   - Demo ledger balances updated

### Understanding the Illustrative GCU Value

The illustrative GCU demo value is calculated based on the weighted average of its components:

**Current Composition (Example)**
```
USD: 35% × $1.00 = $0.35
EUR: 30% × $1.08 = $0.324
GBP: 20% × $1.26 = $0.252
CHF: 10% × $1.12 = $0.112
JPY: 3% × $0.0067 = $0.0002
XAU: 2% × $63.50 = $1.27

Total: 1 GCU = ~$2.31 USD (illustrative only; no monetary value)
```

## Custodian Allocation (simulated)

> Demo only: no funds are held at any bank and no deposit insurance applies. Bank names in the demo are illustrative placeholders; no partnership or endorsement is implied.

### Setting Your Preferences

1. **Access the demo allocation page**
   ```
   /wallet/bank-allocation
   ```

2. **Allocate Demo Balances**
   
   **Demo custodians (illustrative placeholders):**
   - Demo Bank A - 0-50%
   - Demo Bank B - 0-40%
   - Demo Bank C - 0-40%
   - Demo Bank D - 0-30%
   - Demo Bank E - 0-30%

3. **Rules for Allocation**
   - Must total exactly 100%
   - Respect maximum limits per demo custodian

4. **Select Primary Demo Custodian**
   - Should have highest allocation
   - Can change monthly

## Simulated Governance

### How Voting Works

1. **Monthly Polls**
   - Created on 1st of each month
   - Open for 7 days
   - Results applied on 10th

2. **Demo Voting Weight**
   - 1 simulated demo GCU = 1 demo vote
   - Snapshot taken at poll creation
   - No minimum required

3. **What the Demo Polls Cover**
   - Demo basket composition

### Casting Your Vote

1. **Check Active Polls**
   ```
   Governance → Active Polls
   ```

2. **Review Options**
   - Current composition
   - Proposed changes

3. **Make Your Choice**
   
   **Example: Monthly Basket Vote**
   ```
   Proposed Allocation:
   □ USD: 35% (current)
   □ EUR: 30% (current)
   □ GBP: 20% (current)
   
   Your Preferred Allocation:
   USD: [32%] ▼
   EUR: [33%] ▲
   GBP: [20%] →
   CHF: [10%] →
   JPY: [3%] →
   XAU: [2%] →
   
   Total: 100% ✓
   ```

4. **Submit Vote**
   - Review your choices
   - Confirm submission
   - Get confirmation receipt
   - Track results

### Understanding Results

**Weighted Average Calculation**
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
A: It is recalculated periodically from exchange-rate data; it remains illustrative only.

### Voting Questions

**Q: What if I miss a vote?**
A: Your demo voting weight isn't used, but you can participate in the next month's demo poll.

**Q: Can I delegate my votes?**
A: Not currently, but this feature is under consideration.

**Q: How are ties resolved?**
A: The current composition is maintained in case of exact ties.

## Support and Resources

### Getting Help

- **Documentation**: Available in /docs
- **Demo Mode**: Fully functional demonstration
- **Test Accounts**: Pre-configured for testing
- **Source Code**: Available on GitHub

### Stay Updated

- **Demo Features**: All features available for testing
- **API Documentation**: See /docs/04-API
- **Technical Guides**: See /docs/06-DEVELOPMENT
- **Architecture**: See /docs/02-ARCHITECTURE

### Feedback

This is a demonstration platform:
- GitHub Issues: Report bugs and suggestions
- Bug reports: GitHub issues
- General feedback: In-app feedback form

## Conclusion

The GCU demo shows how a basket-referenced unit, its valuation and a governance module can be built with FinAegis. All balances, conversions and votes are simulated.
