# FinAegis Stablecoin User Guide

**Last Updated:** 2025-08-14  
**Version:** 1.0

## Overview

FinAegis Stablecoins are cryptocurrency tokens designed to maintain stable value relative to reference assets like USD or EUR. They provide the benefits of cryptocurrency with the stability of traditional currencies.

## Table of Contents

1. [Introduction to Stablecoins](#introduction-to-stablecoins)
2. [Available Stablecoins](#available-stablecoins)
3. [Minting Stablecoins](#minting-stablecoins)
4. [Managing Positions](#managing-positions)
5. [Redeeming Stablecoins](#redeeming-stablecoins)
6. [Risk Management](#risk-management)
7. [Use Cases](#use-cases)
8. [FAQs](#faqs)

## Introduction to Stablecoins

### What are Stablecoins?

Stablecoins are digital currencies designed to minimize price volatility by being pegged to stable assets like:
- Fiat currencies (USD, EUR)
- Commodities (Gold)
- Other cryptocurrencies
- Algorithmic mechanisms

### FinAegis Stablecoin Features

- **Over-collateralized**: Backed by 150% collateral
- **Multi-collateral**: Accept various crypto assets
- **Transparent**: On-chain proof of reserves
- **Redeemable**: 1:1 redemption guarantee
- **Yield-generating**: Earn on collateral

## Available Stablecoins

### FUSD (FinAegis USD)
- **Peg**: US Dollar (1:1)
- **Collateral Types**: BTC, ETH, stablecoins
- **Minimum Collateral Ratio**: 150%
- **Stability Fee**: 2% annually

### FEUR (FinAegis EUR)
- **Peg**: Euro (1:1)
- **Collateral Types**: BTC, ETH, EUR stablecoins
- **Minimum Collateral Ratio**: 150%
- **Stability Fee**: 1.5% annually

### FGOLD (FinAegis Gold)
- **Peg**: 1 gram of gold
- **Collateral Types**: BTC, ETH, stablecoins
- **Minimum Collateral Ratio**: 175%
- **Stability Fee**: 3% annually

## Minting Stablecoins

### Prerequisites

1. **KYC Verification**: Complete Enhanced KYC
2. **Collateral**: Have sufficient approved assets
3. **Understanding**: Read risk disclosures

### Step-by-Step Minting Process

#### 1. Access Minting Interface
- Navigate to **DeFi → Stablecoins**
- Click **Mint New Stablecoins**
- Select desired stablecoin

#### 2. Choose Collateral
Select from available collateral types:
- **Bitcoin (BTC)**: High liquidity, volatile
- **Ethereum (ETH)**: Smart contract capable
- **Other Stablecoins**: Lower risk, lower returns
- **LP Tokens**: From liquidity pools

#### 3. Calculate Position

**Example: Minting 10,000 FUSD**
```
Collateral Required (150% ratio):
- Option 1: 0.33 BTC (at $45,000/BTC)
- Option 2: 7.5 ETH (at $2,000/ETH)
- Option 3: 15,000 USDC

Actual deposit (with buffer): 0.4 BTC
Collateral Ratio: 180%
Liquidation Price: $25,000/BTC
```

#### 4. Deposit Collateral
1. Review position details
2. Approve token spending (if needed)
3. Confirm collateral deposit
4. Wait for confirmations

#### 5. Mint Stablecoins
1. Specify amount to mint
2. Check collateral ratio
3. Review fees
4. Execute minting
5. Receive stablecoins

### Minting Fees

- **Origination Fee**: 0.5% of minted amount
- **Gas Fees**: Network transaction costs
- **Stability Fee**: Ongoing annual fee (accrued daily)

## Managing Positions

### Position Dashboard

Access via **DeFi → My Positions**

View for each position:
- **Collateral Value**: Current market value
- **Debt**: Stablecoins minted
- **Collateral Ratio**: Current safety level
- **Liquidation Price**: Danger threshold
- **Accrued Fees**: Stability fees owed

### Position Health Indicators

🟢 **Healthy (>170%)**
- Safe from liquidation
- Can mint more
- No action needed

🟡 **Warning (150-170%)**
- Monitor closely
- Consider adding collateral
- Prepare for volatility

🔴 **Danger (<150%)**
- Liquidation imminent
- Add collateral immediately
- Or repay debt

### Managing Collateral

#### Adding Collateral
1. Select position
2. Click **Add Collateral**
3. Choose asset and amount
4. Confirm transaction

Benefits:
- Improves health
- Enables more minting
- Reduces liquidation risk

#### Withdrawing Collateral
1. Check current ratio
2. Calculate safe withdrawal
3. Enter withdrawal amount
4. Execute if ratio stays >150%

### Stability Fee Management

Fees accrue continuously and compound:
- **Payment Options**: Pay anytime or at closure
- **Payment Methods**: Same stablecoin or collateral
- **Auto-pay**: Set up automatic payments

## Redeeming Stablecoins

### Full Redemption (Closing Position)

1. **Navigate to Position**
   - Go to position details
   - Click **Close Position**

2. **Repay Debt**
   - Full stablecoin amount
   - Plus accrued fees
   - Approve spending

3. **Receive Collateral**
   - All collateral returned
   - Minus any fees
   - To your wallet

### Partial Redemption

1. Calculate safe repayment amount
2. Maintain 150% ratio
3. Repay partial debt
4. Withdraw excess collateral

### Emergency Redemption

During system emergencies:
- Global settlement activated
- Fixed redemption price
- Direct collateral claims
- No further fees

## Risk Management

### Understanding Liquidation

**Liquidation occurs when:**
- Collateral ratio falls below 150%
- Due to collateral price decline
- Or fee accumulation

**Liquidation Process:**
1. Position marked for liquidation
2. 13% penalty applied
3. Collateral auctioned
4. Debt repaid from proceeds
5. Remaining collateral returned

### Liquidation Example

```
Initial Position:
- Collateral: 1 BTC at $45,000
- Debt: 25,000 FUSD
- Ratio: 180%

BTC drops to $37,000:
- Collateral Value: $37,000
- Ratio: 148% (Below minimum!)
- Liquidation triggered

Liquidation:
- Penalty: 13% = $4,810
- Debt repaid: 25,000 FUSD
- Collateral sold: 0.83 BTC
- Returned: 0.17 BTC
- Loss: $4,810 + price impact
```

### Risk Mitigation Strategies

#### 1. Over-collateralization
- Target 200%+ ratio
- Provides price buffer
- Peace of mind

#### 2. Diversified Collateral
- Multiple asset types
- Reduces correlation risk
- Smoother value changes

#### 3. Active Monitoring
- Set price alerts
- Check daily
- React quickly

#### 4. Automated Protection
- **Auto Top-up**: Add collateral automatically
- **Stop-loss**: Close position at preset ratio
- **Rebalancing**: Maintain target ratio

### Oracle Price Feeds

Prices determined by:
- Multiple oracle sources
- Median price calculation
- 5-minute updates
- Emergency price freezes

## Use Cases

### 1. Stable Trading Capital

**Scenario**: Crypto trader needs stable funds
```
Action: Deposit 2 BTC, mint 50,000 FUSD
Benefit: Trade without selling BTC
Result: Keep BTC upside, trade with stable funds
```

### 2. Leveraged Positions

**Scenario**: Bullish on ETH
```
Action: 
1. Deposit 10 ETH
2. Mint 15,000 FUSD
3. Buy 7.5 more ETH
4. Add as collateral
Result: 1.75x ETH exposure
```

### 3. Yield Farming

**Scenario**: Earn on idle crypto
```
Action:
1. Mint stablecoins against crypto
2. Provide liquidity in FUSD/USDC pool
3. Earn trading fees + rewards
4. Higher returns than holding
```

### 4. Cross-border Payments

**Scenario**: Send money internationally
```
Action:
1. Mint FEUR against crypto
2. Send to recipient instantly
3. Recipient redeems or uses
4. Avoid bank delays/fees
```

### 5. DeFi Building Block

Use stablecoins for:
- Lending/borrowing
- Liquidity provision
- Options strategies
- Payment systems

## Advanced Features

### Flash Minting

Mint and repay within one transaction:
- No collateral needed
- 0.1% fee
- For arbitrage/refinancing

### Collateral Swapping

Exchange collateral types without closing:
- Optimize for yields
- Adjust risk profile
- Tax efficiency

### Position Migration

Move positions between accounts:
- Account consolidation
- Risk segregation
- Operational flexibility

## System Parameters

### Global Parameters

| Parameter | Value | Description |
|-----------|-------|-------------|
| Minimum Collateral Ratio | 150% | Below triggers liquidation |
| Liquidation Penalty | 13% | Fee on liquidated collateral |
| Stability Fee (FUSD) | 2% | Annual fee on debt |
| Debt Ceiling | $100M | Maximum system debt |
| Surplus Buffer | $1M | System insurance fund |

### Collateral Parameters

| Asset | Max LTV | Debt Ceiling | Stability Fee |
|-------|---------|--------------|---------------|
| BTC | 66.7% | $50M | Base rate |
| ETH | 66.7% | $30M | Base rate |
| USDC | 90% | $20M | Base - 0.5% |

## Emergency Procedures

### System Shutdown

In extreme events:
1. Minting frozen
2. Prices fixed
3. Direct redemption enabled
4. No new positions

### User Actions During Shutdown

1. **Check position status**
2. **Note fixed prices**
3. **Redeem stablecoins**
4. **Claim collateral**
5. **No time pressure**

## FAQs

### General Questions

**Q: How is this different from USDC/USDT?**
A: FinAegis stablecoins are decentralized, over-collateralized, and transparent. You can always verify backing on-chain.

**Q: Can I lose money?**
A: Yes, through liquidation if collateral value drops too much. Also, stability fees reduce returns.

**Q: What backs the stablecoins?**
A: Cryptocurrency collateral locked in smart contracts, always worth 150%+ of stablecoins issued.

### Minting Questions

**Q: What's the minimum I can mint?**
A: 100 units of any stablecoin (100 FUSD, 100 FEUR, etc.)

**Q: How fast is minting?**
A: Instant after blockchain confirmations (typically 1-5 minutes).

**Q: Can I mint multiple types?**
A: Yes, you can have positions in all available stablecoins.

### Risk Questions

**Q: What happens in a flash crash?**
A: Oracle price delays (5 min) provide buffer. Emergency shutdown if needed. Insurance fund covers shortfalls.

**Q: Is liquidation automatic?**
A: Yes, it's executed by keeper bots when ratio drops below 150%. No manual intervention needed.

**Q: Can I get liquidated from fees alone?**
A: Yes, if stability fees accumulate enough to push ratio below 150%. Monitor regularly.

### Technical Questions

**Q: Which blockchains are supported?**
A: Currently Ethereum and Polygon. More chains coming.

**Q: Are the smart contracts audited?**
A: Yes, by three independent firms. Reports available on our website.

**Q: How are prices determined?**
A: Median of multiple oracle feeds (Chainlink, Band, API3) updated every 5 minutes.

## Best Practices

1. **Start Small**: Test with small amounts first
2. **Monitor Actively**: Check positions daily during volatility
3. **Keep Buffer**: Maintain 200%+ collateral ratio
4. **Understand Risks**: Liquidation can result in losses
5. **Use Alerts**: Set up price notifications
6. **Plan Exit**: Know how to close positions
7. **Track Fees**: Include in profitability calculations

## Support Resources

- **Documentation**: docs.finaegis.com/stablecoins
- **Risk Calculator**: app.finaegis.com/calculate
- **Discord**: discord.gg/finaegis-defi
- **Video Guides**: youtube.com/finaegis
- **Emergency Contact**: emergency@finaegis.com

---

**Risk Warning**: Minting stablecoins involves risks including liquidation. Crypto collateral is volatile. Smart contracts may have bugs. Not insured by any government. Please understand all risks before participating.