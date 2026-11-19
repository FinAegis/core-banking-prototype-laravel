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

## How It Works

### Monthly Voting Cycle

1. **Voting Opens**: First day of each month
2. **Voting Period**: 7 days (1st - 7th)
3. **Results Calculation**: 8th of the month
4. **Demo Basket Update**: Applied to the simulated demo basket on the 9th

### Your Demo Voting Weight

- **Weighted by simulated balance**: 1 simulated demo GCU = 1 demo vote
- **Real-time Calculation**: Based on your simulated demo GCU balance
- **Transparent**: See your demo voting weight before voting

## Step-by-Step Voting Process

### 1. Access the Voting Dashboard

Navigate to the GCU Voting section in your dashboard or visit:
```
https://platform.finaegis.org/voting/gcu
```

### 2. View Active Polls

You'll see the current month's currency basket voting poll with:
- Current basket composition
- Proposed changes
- Time remaining to vote
- Your demo voting weight

### 3. Cast Your Vote

#### Option A: Keep Current Allocation
Vote to maintain the existing currency basket:
- USD: 40%
- EUR: 30%
- GBP: 15%
- CHF: 10%
- JPY: 3%
- Gold: 2%

#### Option B: Approve Proposed Changes
Vote for the new allocation in the demo proposal.

### 4. Confirm Your Vote

- Review your selection
- See the impact of your demo voting weight
- Click "Submit Vote"
- Receive confirmation

## Voting Rules

### Eligibility
- Must have a simulated demo GCU balance of at least 1 GCU
- One vote per account per poll

### Vote Changes
- You can change your vote until polls close
- Only your latest vote counts
- Previous votes are overwritten

### Transparency
- All votes are recorded in the application database (not on a blockchain)
- Results are publicly viewable

## API Integration

### Get Active Polls
```bash
GET /api/voting/polls
Authorization: Bearer {your-token}
```

### Submit Vote
```bash
POST /api/voting/polls/{poll-uuid}/vote
Authorization: Bearer {your-token}
Content-Type: application/json

{
  "option_id": "keep-current",
  "amount": 1000
}
```

### Check Voting Power
```bash
GET /api/voting/polls/{poll-uuid}/voting-power
Authorization: Bearer {your-token}
```

## Understanding Results

### Vote Calculation
- Total votes = Sum of all simulated demo GCU used for voting
- Winning option = Option with most votes
- Minimum participation: 10% of total simulated demo GCU balances

### Implementation
- Automatic update of the demo basket

## Frequently Asked Questions

### Q: What if I don't vote?
A: The demo basket follows the outcome of the simulated votes that were cast.

### Q: Can I delegate my voting power?
A: Currently, delegation is not supported. You must vote directly.

### Q: How are proposals created?
A: Demo proposals are created by administrators or demo users with a description, a rationale and proposed demo basket weights.

### Q: Is voting mandatory?
A: No, voting in the demo is optional.

### Q: What happens in a tie?
A: In the unlikely event of an exact tie, the current allocation is maintained.

## Security & Privacy

### Your Vote is:
- **Encrypted**: During transmission
- **Auditable**: Verifiable through the application's vote records

### Best Practices
1. Vote from secure devices
2. Verify poll authenticity
3. Don't share voting intentions publicly
4. Review results after polls close

## Support

### Need Help?
- Email: support@finaegis.org

### Report Issues
If you experience voting problems:
1. Note the poll ID and timestamp
2. Screenshot any errors
3. Contact support immediately

## Conclusion

The governance demo shows how basket-weight proposals and weighted polls can be implemented with FinAegis.

---
*Last Updated: September 2024*
*Version: 1.0*