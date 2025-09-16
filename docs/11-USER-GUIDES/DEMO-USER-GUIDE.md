# Demo Environment User Guide

## Welcome to FinAegis Demo

Welcome to the FinAegis Core Banking Platform demonstration environment! This guide will help you explore all the features of our comprehensive banking platform without any real financial transactions.

## Quick Start

### 1. Access the Demo

Visit the demo environment at: **[finaegis.org](https://finaegis.org)**

### 2. Demo Credentials

Use these pre-configured demo accounts:

| Role | Email | Password | Description |
|------|-------|----------|-------------|
| **Customer** | john@demo.finaegis.com | Demo123! | Regular customer account with transaction history |
| **Premium** | jane@demo.finaegis.com | Demo123! | Premium account with higher limits |
| **Admin** | admin@demo.finaegis.com | Admin123! | Full administrative access |

### 3. Demo Mode Indicators

Look for these indicators that confirm you're in demo mode:
- 🟡 **Yellow banner** at the top of every page
- **"DEMO"** badge in the header
- **Test mode** labels on payment forms
- **Simulated data** watermarks on reports

## Features Overview

### 💳 Banking Operations

#### Account Management
1. **View Accounts**: Navigate to Dashboard → Accounts
2. **Create Account**: Click "New Account" → Select account type
3. **Account Details**: Click any account to view transactions, balance, and settings

**Demo Features:**
- Pre-populated transaction history
- Instant balance updates
- Multiple currency support (EUR, USD, GBP)

#### Transactions
1. **Send Money**: Dashboard → Send Money
   - Enter recipient: `demo@recipient.com`
   - Amount: Any value up to €100,000
   - Demo transactions process instantly

2. **Request Payment**: Dashboard → Request Payment
   - Create payment requests with QR codes
   - Share demo payment links
   - Track payment status in real-time

**Demo Transaction Behaviors:**
- 90% success rate for normal transactions
- Instant processing (1-2 seconds)
- Automatic receipt generation

### 📈 Trading Platform

#### Exchange Trading
1. **Access Trading**: Navigate to Trading → Exchange
2. **Place Orders**:
   - Market orders execute immediately
   - Limit orders fill based on simulated market conditions
   - View order book with realistic depth

**Demo Trading Features:**
- Simulated real-time price movements
- €1,000,000 demo trading balance
- Historical chart data
- Order types: Market, Limit, Stop-Loss

#### Market Data
- Live price feeds (simulated with realistic volatility)
- 24-hour volume statistics
- Price charts with technical indicators
- Market depth visualization

### 💰 Stablecoin Operations

#### EUR Stablecoin (EURS)
1. **Mint Tokens**: Stablecoins → Mint EURS
   - Enter amount (up to €100,000)
   - View backing ratio (always 105% in demo)
   - Instant minting confirmation

2. **Redeem Tokens**: Stablecoins → Redeem
   - Convert EURS back to EUR
   - No fees in demo mode
   - Instant redemption

**Demo Stablecoin Features:**
- Transparent reserve status
- Simulated audit reports
- Backing composition breakdown
- Yield generation simulation (2-3% APY)

### 🏦 P2P Lending

#### As a Borrower
1. **Apply for Loan**: Lending → Apply for Loan
2. **Complete Application**:
   - Amount: €100 - €50,000
   - Term: 3-60 months
   - Purpose: Select from dropdown
3. **View Offers**: See investor offers with rates
4. **Accept Offer**: Choose best rate and terms

**Demo Credit Scores:**
- Automatically generated (600-850)
- Instant approval decisions
- Interest rates: 5-15% APR

#### As an Investor
1. **Browse Loans**: Lending → Investment Opportunities
2. **Filter Options**:
   - Risk level (A-F rating)
   - Interest rate range
   - Loan term
3. **Invest**: Select amount to invest (min €50)
4. **Track Portfolio**: Monitor returns and repayments

### 🗳️ Governance (GCU Voting)

#### Participating in Votes
1. **View Proposals**: Governance → Active Proposals
2. **Review Details**: Click proposal for full information
3. **Cast Vote**: 
   - Select: Approve, Reject, or Abstain
   - Confirm with demo signature
4. **Track Results**: Real-time vote counting

**Demo Voting Power:**
- Each demo account has 1,000 GCU tokens
- Voting power proportional to holdings
- Instant vote confirmation

### 💼 CGO Investment

#### Investment Process
1. **Access CGO**: Navigate to Invest → CGO
2. **Review Terms**: Read investment memorandum
3. **Select Amount**: €100 - €100,000
4. **Choose Payment**:
   - Card (use test card: 4242 4242 4242 4242)
   - Crypto (generates demo address)
   - Bank transfer (provides demo IBAN)
5. **Receive Confirmation**: Instant token allocation

**Demo Investment Features:**
- Simulated KYC (auto-approved)
- Demo investment certificates (PDF)
- Token price: €1.00 (fixed in demo)
- Bonus calculation preview

## Demo Scenarios

### Test Payment Scenarios

Use these test inputs to trigger specific outcomes:

#### Card Payments
| Card Number | Outcome |
|------------|---------|
| 4242 4242 4242 4242 | Success |
| 4000 0000 0000 0002 | Declined |
| 4000 0000 0000 9995 | Insufficient funds |
| 4000 0000 0000 9987 | Lost card error |

#### Bank Transfers
| IBAN | Outcome |
|------|---------|
| DE89 3704 0044 0532 0130 00 | Success |
| DE89 3704 0044 0000 0000 01 | Account closed |
| DE89 3704 0044 0000 0000 02 | Invalid account |

### Trading Scenarios

#### Trigger Market Events
1. **Bull Market**: Place large buy order (>€10,000)
2. **Bear Market**: Place large sell order (>€10,000)
3. **Volatility Spike**: Rapid small trades (<1 min apart)
4. **Circuit Breaker**: Try order >€1,000,000

### Lending Scenarios

#### Credit Score Simulations
- Email ending in `@good.demo`: Score 750-850 (auto-approved)
- Email ending in `@fair.demo`: Score 650-749 (manual review)
- Email ending in `@poor.demo`: Score 500-649 (declined)

## Advanced Features

### API Access

#### Demo API Endpoints
```
Base URL: https://finaegis.org/api
Authentication: Bearer {demo_token}
```

#### Get Demo Token
```bash
curl -X POST https://finaegis.org/api/auth/demo-token \
  -H "Content-Type: application/json" \
  -d '{"email": "john@demo.finaegis.com"}'
```

#### Example API Calls
```bash
# Get account balance
curl https://finaegis.org/api/accounts \
  -H "Authorization: Bearer {demo_token}"

# Create transaction
curl -X POST https://finaegis.org/api/transactions \
  -H "Authorization: Bearer {demo_token}" \
  -H "Content-Type: application/json" \
  -d '{"to": "demo@recipient.com", "amount": 100, "currency": "EUR"}'
```

### Webhook Testing

#### Demo Webhook Events
Configure webhook URL in Settings → Webhooks

**Available Events:**
- `payment.succeeded`
- `payment.failed`
- `transaction.created`
- `loan.approved`
- `trade.executed`

**Demo Webhook Payload:**
```json
{
  "event": "payment.succeeded",
  "data": {
    "id": "demo_pay_123",
    "amount": 100,
    "currency": "EUR",
    "status": "succeeded"
  },
  "timestamp": "2024-09-15T10:30:00Z",
  "demo": true
}
```

## Demo Limitations

### What You Cannot Do
- ❌ Real money transactions
- ❌ Withdraw funds to real bank accounts
- ❌ Trade on real exchanges
- ❌ Access production data
- ❌ Send emails to non-demo addresses

### Data Persistence
- Demo data persists for **24 hours**
- Automatic cleanup at midnight UTC
- Download your demo data anytime
- Reset option available in Settings

### Rate Limits
- API: 100 requests per minute
- Transactions: 50 per hour
- Trades: 100 per hour
- Bulk operations: 10 per hour

## Tips & Tricks

### 1. Quick Testing
- Use keyboard shortcuts:
  - `Ctrl+D`: Toggle demo controls
  - `Ctrl+R`: Reset demo data
  - `Ctrl+T`: Open test scenarios

### 2. Bulk Operations
- Import CSV files with demo data
- Use batch API endpoints
- Access bulk testing tools in Admin panel

### 3. Mobile Testing
- Responsive design on all devices
- Touch-optimized interfaces
- Mobile app demo available

### 4. Multi-Language
- Switch languages in Settings
- Available: EN, ES, FR, DE, IT
- All features fully translated

## Troubleshooting

### Common Issues

#### "Demo Mode Not Active"
- Clear browser cache
- Check URL includes `/demo`
- Verify demo credentials

#### "Transaction Failed"
- Normal behavior (10% failure rate)
- Try again for success
- Check demo balance limits

#### "Cannot Access Feature"
- Some features require admin role
- Switch to admin@demo.finaegis.com
- Check feature availability in demo

### Reset Demo Environment
1. Go to Settings → Demo Controls
2. Click "Reset Demo Data"
3. Confirm reset
4. Fresh start with default data

## Support

### Demo Support Channels
- 📧 Email: demo-support@finaegis.com
- 💬 Live Chat: Available 9 AM - 5 PM CET
- 📚 Documentation: docs.finaegis.com
- 🎥 Video Tutorials: youtube.com/finaegis

### Feedback
We value your feedback! Please share your demo experience:
- In-app feedback widget
- Survey after demo session
- Email suggestions to feedback@finaegis.com

## Next Steps

### Ready for Production?
1. **Schedule a Demo**: Book personalized walkthrough
2. **Free Trial**: 30-day production trial available
3. **Contact Sales**: sales@finaegis.com
4. **View Pricing**: finaegis.com/pricing

### Technical Documentation
- [API Documentation](/docs/api)
- [Integration Guides](/docs/integration)
- [Security Overview](/docs/security)
- [Compliance Information](/docs/compliance)

---

**Note**: This is a demonstration environment. No real financial transactions occur. All data is simulated and will be cleared periodically.