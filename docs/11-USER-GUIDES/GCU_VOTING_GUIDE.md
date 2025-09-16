# GCU Democratic Voting Guide

## Overview

The Global Currency Unit (GCU) implements a revolutionary democratic voting system where users can vote on the currency basket composition each month. Your voting power is proportional to your GCU holdings - 1 GCU equals 1 vote.

## How It Works

### Monthly Voting Cycle

1. **Voting Opens**: First day of each month
2. **Voting Period**: 7 days (1st - 7th)
3. **Results Calculation**: 8th of the month
4. **Basket Rebalancing**: Automated execution on the 9th

### Your Voting Power

- **Asset-Weighted**: 1 GCU = 1 vote
- **Real-time Calculation**: Based on your current GCU balance
- **Transparent**: See your exact voting power before voting

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
- Your voting power

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
Vote for the new allocation proposed by the community or platform.

### 4. Confirm Your Vote

- Review your selection
- See the impact of your voting power
- Click "Submit Vote"
- Receive confirmation

## Voting Rules

### Eligibility
- Must hold at least 1 GCU
- Account must be verified (KYC complete)
- One vote per account per poll

### Vote Changes
- You can change your vote until polls close
- Only your latest vote counts
- Previous votes are overwritten

### Transparency
- All votes are recorded on the blockchain
- Results are publicly viewable
- Individual votes remain anonymous

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
- Total votes = Sum of all GCU used for voting
- Winning option = Option with most votes
- Minimum participation: 10% of total GCU supply

### Implementation
- Automatic execution via smart contracts
- Gradual rebalancing over 24 hours
- Minimal market impact

## Voting Strategies

### Conservative Approach
- Vote to maintain current allocation
- Prioritize stability
- Suitable for risk-averse holders

### Progressive Approach
- Support new allocations
- Adapt to market conditions
- Suitable for active participants

### Factors to Consider
1. **Economic Indicators**: GDP, inflation, interest rates
2. **Currency Stability**: Political and economic events
3. **Diversification**: Risk distribution across currencies
4. **Personal Needs**: Your currency exposure preferences

## Frequently Asked Questions

### Q: What if I don't vote?
A: Your GCU continues with the outcome decided by active voters. Non-participation means you accept the community's decision.

### Q: Can I delegate my voting power?
A: Currently, delegation is not supported. You must vote directly.

### Q: How are proposals created?
A: The platform creates monthly proposals based on:
- Economic indicators
- Community feedback
- Risk management algorithms
- Regulatory requirements

### Q: Is voting mandatory?
A: No, voting is optional but encouraged for active participation in GCU governance.

### Q: What happens in a tie?
A: In the unlikely event of an exact tie, the current allocation is maintained.

## Security & Privacy

### Your Vote is:
- **Encrypted**: During transmission
- **Anonymous**: Results show totals, not individuals
- **Immutable**: Cannot be altered after submission
- **Auditable**: Verifiable through blockchain records

### Best Practices
1. Vote from secure devices
2. Verify poll authenticity
3. Don't share voting intentions publicly
4. Review results after polls close

## Mobile App Voting

The GCU mobile app (coming soon) will feature:
- Push notifications for new polls
- One-touch voting
- Biometric authentication
- Voting history

## Support

### Need Help?
- Email: support@finaegis.org
- In-app chat support
- Video tutorials available
- Community forum discussions

### Report Issues
If you experience voting problems:
1. Note the poll ID and timestamp
2. Screenshot any errors
3. Contact support immediately

## Conclusion

Democratic voting is at the heart of GCU's revolutionary approach to global currency. Your participation shapes the future of money. Every vote counts, and together we're building a more democratic financial system.

---
*Last Updated: September 2024*
*Version: 1.0*