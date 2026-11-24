# GCU Governance Demo Guide

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.
>
> See [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md).

## Overview

The GCU demo includes a simulated governance flow in which demo accounts cast basket "votes" weighted by simulated demo balances. Votes have no monetary effect.

The voting pages and endpoints need the Governance module. The demo environment turns it on. Production deployments such as the Zelta site disable it.

The demo has two kinds of vote.

- **GCU proposals** are what you see on the `/gcu/voting` pages. Each proposal suggests new basket weights, and you vote for, against or abstain.
- **Basket polls** are available through the `/api/voting` API. In a basket poll, you send your own preferred weights for each component.

## How It Works

### Monthly Cycle

On the 20th of each month, the scheduler runs `php artisan voting:setup`. It creates the GCU composition proposal for the next month. Voting on that proposal opens on the 20th of that month and stays open for seven days.

A monthly proposal passes when at least 10% of the total simulated GCU balance takes part and at least 50% of the weight that voted is for it. The result is decided once the proposal is closed.

### Your Demo Voting Weight

Your voting weight equals your simulated demo GCU balance. You need a balance above zero to vote. Your weight is shown before you vote.

## Step-by-Step Voting Process

### 1. Open the Voting Pages

Open **Governance** in the top navigation, or go to `/gcu/voting` directly. Anyone can see the proposals. You need to log in to vote.

### 2. View the Active Proposal

The proposal page shows the following.
- The current basket weights
- The proposed weights
- When voting opens and closes
- The running vote totals

The monthly proposal copies the example weights from `config/platform.php` as both its current and its proposed weights. Those weights are USD 35%, EUR 30%, GBP 20%, CHF 10%, JPY 3% and gold 2%.

### 3. Cast Your Vote

Choose **For**, **Against** or **Abstain**, and submit your vote.

### 4. Change Your Vote

You can change your vote while voting is open. Only your latest vote counts.

## Voting Rules

### Eligibility
- You need a simulated demo GCU balance above zero.
- Each user has one vote per proposal.

### Creating Proposals
- Only administrators can create new proposals. They do this in the admin panel under **GCU Management**.
- A proposal needs a title, a description, a rationale, the voting dates, the participation and approval thresholds, and weights that add up to exactly 100%.

### Transparency
- All votes are recorded in the application database (not on a blockchain).
- Anyone can see the proposals and their vote totals.

## API Integration

### GCU Proposals

| Method | Path | Login needed |
|--------|------|--------------|
| GET | `/api/v2/gcu/voting/proposals` | No |
| GET | `/api/v2/gcu/voting/proposals/{id}` | No |
| POST | `/api/v2/gcu/voting/proposals/{id}/vote` | Yes |
| GET | `/api/v2/gcu/voting/my-votes` | Yes |

```bash
POST /api/v2/gcu/voting/proposals/{id}/vote
Authorization: Bearer {your-token}
Content-Type: application/json

{
  "vote": "for"
}
```

The `vote` field takes `for`, `against` or `abstain`.

### Basket Polls

| Method | Path | What it does |
|--------|------|--------------|
| GET | `/api/voting/polls` | Lists the active polls |
| GET | `/api/voting/polls/upcoming` | Lists the polls that have not started |
| GET | `/api/voting/polls/history` | Lists the polls you voted in |
| GET | `/api/voting/dashboard` | Returns a summary for the voting dashboard |
| POST | `/api/voting/polls/{uuid}/vote` | Records your preferred weights |
| GET | `/api/polls/{uuid}/voting-power` | Returns your voting power for a poll |

All of these need a bearer token.

```bash
POST /api/voting/polls/{poll-uuid}/vote
Authorization: Bearer {your-token}
Content-Type: application/json

{
  "allocations": {
    "USD": 35,
    "EUR": 30,
    "GBP": 20,
    "CHF": 10,
    "JPY": 3,
    "XAU": 2
  }
}
```

The allocations must add up to 100. You can vote only once in each basket poll, and you cannot change that vote. Your voting power in a basket poll is your balance of the basket asset named in `PRIMARY_BASKET_CODE`, divided by 100. `.env.example` sets that code to `GCU`.

## Understanding Results

### GCU Proposals
- The vote totals add up the voting weight of every for, against and abstain vote.
- A proposal passes when it reaches its participation and approval thresholds.

### Basket Polls
- The new weight of each component is the average of the votes, weighted by each voter's voting power.
- An administrator completes the poll in the admin panel or through GraphQL.
- At v7.18.1, completing a poll does not change the demo basket. The poll names `UpdateBasketCompositionWorkflow`, but the completion step does not start that workflow, so an operator has to start it by hand. The [basket tutorial](../tutorials/BUILDING_BASKET_CURRENCIES.md) shows how.

## Frequently Asked Questions

### Q: What if I don't vote?
A: Your demo voting weight is not counted for that proposal or poll. You can vote in the next one.

### Q: Can I delegate my voting power?
A: No. Delegation is not supported, so you must vote directly.

### Q: How are proposals created?
A: The scheduler creates the monthly proposal. Administrators can create other proposals with a description, a rationale and proposed demo basket weights.

### Q: Is voting mandatory?
A: No, voting in the demo is optional.

## Security and Privacy

- Votes travel over HTTPS on the hosted demo.
- Each proposal vote is stored with a signature that the application generates.
- Each basket poll vote is stored with the time, your IP address and your browser's user agent. You can check a basket poll vote through `POST /api/votes/{id}/verify`.

## Support

If you find a problem with voting, note the proposal or poll ID and the time. Then open a [GitHub issue](https://github.com/FinAegis/core-banking-prototype-laravel/issues) with that information and a screenshot of any error.

## Conclusion

The governance demo shows how basket-weight proposals and weighted polls can be implemented with FinAegis.

---
*Last updated: November 2026 (v7.18.1)*
