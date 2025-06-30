# Continuous Growth Offering (CGO)

## Overview

The Continuous Growth Offering (CGO) is FinAegis's innovative funding mechanism that allows continuous investment in the platform's development. Unlike traditional ICOs or IPOs with fixed end dates, the CGO continues indefinitely, enabling investors to participate in the platform's growth at any time.

## Key Features

### 1. Investment Tiers
- **Bronze Tier** ($100 - $999)
  - Digital ownership certificate
  - Early access to new features
  - Monthly investor updates
  
- **Silver Tier** ($1,000 - $9,999)
  - Everything in Bronze
  - Physical certificate option
  - Voting rights on platform decisions
  - Quarterly investor calls
  
- **Gold Tier** ($10,000+)
  - Everything in Silver
  - Direct access to founding team
  - Advisory board consideration
  - Lifetime premium features

### 2. Ownership Limits
- Maximum 1% ownership per investment round
- Ensures fair distribution among investors
- Prevents concentration of ownership

### 3. Payment Methods
- **Cryptocurrency**: BTC, ETH, USDT, USDC
- **Bank Transfer**: Wire transfer with reference number
- **Card Payment**: Coming soon via Stripe integration

## Technical Implementation

### Database Schema

#### cgo_pricing_rounds
```sql
- id
- round_number (unique)
- share_price
- max_shares_available
- shares_sold
- total_raised
- started_at
- ended_at
- is_active
- timestamps
```

#### cgo_investments
```sql
- id
- uuid
- user_id
- round_id
- amount
- currency
- share_price
- shares_purchased
- ownership_percentage
- tier
- status (pending, confirmed, cancelled, refunded)
- payment_method
- crypto_address
- crypto_tx_hash
- certificate_number
- certificate_issued_at
- metadata (JSON)
- timestamps
```

#### cgo_notifications
```sql
- id
- email
- ip_address
- user_agent
- timestamps
```

### Models

#### CgoPricingRound
- Manages investment rounds
- Tracks share availability and pricing
- Calculates progress and remaining shares

#### CgoInvestment
- Records individual investments
- Manages payment processing
- Generates ownership certificates

#### CgoNotification
- Stores early access notification requests
- Used for marketing and investor communications

### Controllers

#### CgoController
- `notify()`: Handles early access notifications
- `showInvest()`: Displays investment page
- `invest()`: Processes new investments
- `downloadCertificate()`: Generates ownership certificates

### Views

#### Public Views
- `cgo.blade.php`: Main CGO landing page
- Shows countdown timer
- Investment tiers information
- Early access form for non-authenticated users

#### Authenticated Views
- `cgo/invest.blade.php`: Investment form
- `cgo/crypto-payment.blade.php`: Crypto payment instructions
- `cgo/bank-transfer.blade.php`: Bank transfer details
- `cgo/card-payment.blade.php`: Card payment (placeholder)

## User Flows

### 1. Unauthenticated User Flow
1. User visits `/cgo`
2. Views CGO information and investment tiers
3. Can submit email for early access notifications
4. Must register/login to invest

### 2. Authenticated User Flow
1. User visits `/cgo` or clicks CGO in navigation
2. Clicks "Invest Now" button
3. Redirected to `/cgo/invest`
4. Fills out investment form:
   - Amount (minimum $100)
   - Payment method selection
   - Terms acceptance
5. Based on payment method:
   - **Crypto**: Shows wallet address and QR code
   - **Bank Transfer**: Shows bank details and reference
   - **Card**: Placeholder for Stripe integration

### 3. Investment Processing
1. Investment record created with 'pending' status
2. Payment instructions displayed
3. Manual verification by admin (automated in production)
4. Status updated to 'confirmed'
5. Certificate number generated
6. Certificate available for download

## Security Considerations

1. **Investment Limits**
   - 1% maximum ownership per round enforced
   - Minimum investment amount validation
   - Available shares verification

2. **Payment Security**
   - Unique reference numbers for tracking
   - Crypto addresses stored securely
   - Transaction verification required

3. **Data Protection**
   - IP address and user agent logging
   - Secure certificate generation
   - Investment history access control

## Testing

### Feature Tests
- `CgoInvestmentTest.php`: Comprehensive test coverage
  - Public page access
  - Authentication requirements
  - Investment flow validation
  - Tier calculation
  - Ownership limits
  - Payment method handling

### Test Scenarios Covered
1. Unauthenticated access to CGO page
2. Authenticated user investment flow
3. Investment tier calculations
4. Ownership percentage limits
5. Share availability checks
6. Minimum investment validation
7. Terms acceptance requirement
8. Payment method validations

## Future Enhancements

1. **Automated Payment Processing**
   - Stripe integration for card payments
   - Blockchain monitoring for crypto payments
   - Bank API integration for wire transfers

2. **Certificate Generation**
   - PDF certificate generation with QR codes
   - Blockchain-based ownership verification
   - NFT certificates on Ethereum/Polygon

3. **Investment Dashboard**
   - Real-time portfolio valuation
   - Secondary market for share trading
   - Dividend distribution system

4. **Regulatory Compliance**
   - KYC/AML integration
   - Accredited investor verification
   - Geographic restrictions handling

## Configuration

### Environment Variables
```env
# CGO Settings
CGO_MIN_INVESTMENT=100
CGO_MAX_OWNERSHIP_PERCENTAGE=1.0
CGO_TOTAL_SHARES=1000000
CGO_LAUNCH_DATE="2025-07-21 00:00:00"
```

### Seeding Data
```bash
# Create initial pricing round
php artisan db:seed --class=CgoPricingRoundSeeder
```

## API Endpoints (Future)

### Public Endpoints
- `GET /api/cgo/current-round`: Current round information
- `GET /api/cgo/tiers`: Investment tier details

### Authenticated Endpoints
- `POST /api/cgo/invest`: Submit investment
- `GET /api/cgo/investments`: User's investment history
- `GET /api/cgo/certificate/{uuid}`: Download certificate

## Monitoring

### Key Metrics
- Total investments per round
- Average investment size
- Tier distribution
- Payment method preferences
- Conversion rate (visitors to investors)

### Admin Dashboard (Future)
- Real-time investment tracking
- Payment verification queue
- Certificate generation status
- Investor analytics

## Support

For CGO-related inquiries:
- Email: invest@finaegis.com
- Support: /support/contact
- FAQ: /support/faq#cgo