# Continuous Growth Offering (CGO) Documentation

**Last Updated:** January 2025  
**Status:** ✅ COMPLETED - Production Ready

## Overview

The Continuous Growth Offering (CGO) is FinAegis's innovative investment platform that allows continuous participation in the platform's growth. Built with event sourcing, comprehensive payment integration, and tiered KYC/AML compliance, the CGO provides a secure and scalable investment mechanism.

## Contents

- **[CGO_IMPLEMENTATION_PLAN.md](CGO_IMPLEMENTATION_PLAN.md)** - Original implementation planning document
- **[CGO_REFUND_PROCESSING.md](CGO_REFUND_PROCESSING.md)** - Refund system technical documentation

## Key Features

### 1. Investment Tiers

#### Explorer Tier ($1,000 - $9,999)
- Digital ownership certificate
- Early access to new features
- Quarterly investor updates
- Basic KYC verification required

#### Innovator Tier ($10,000 - $49,999)
- Everything in Explorer tier
- Monthly investor updates
- Priority support access
- Enhanced KYC verification required

#### Visionary Tier ($50,000+)
- Everything in Innovator tier
- Weekly updates and reports
- Direct access to founding team
- Advisory board consideration
- Full KYC verification required

### 2. Payment Methods

#### Stripe (Card Payments)
- Secure checkout sessions
- 3D Secure authentication
- Automated payment verification
- Webhook-based status updates

#### Coinbase Commerce (Cryptocurrency)
- BTC, ETH, USDC support
- Real-time exchange rates
- QR code generation
- Blockchain confirmation tracking

#### Bank Transfer
- SEPA/SWIFT support
- Unique reference generation
- Manual reconciliation interface
- Multi-currency support

### 3. KYC/AML Compliance

#### Tiered Verification System
- **Basic KYC** (up to $1,000): Identity verification
- **Enhanced KYC** (up to $10,000): Identity + address verification
- **Full KYC** ($50,000+): Complete due diligence

#### AML Features
- Sanctions list screening
- PEP (Politically Exposed Person) checks
- Adverse media screening
- Transaction pattern analysis
- Risk scoring algorithm

### 4. Investment Management

#### Agreement Generation
- Automated PDF generation
- Tier-specific terms
- Digital signatures
- Secure storage

#### Certificate Creation
- Unique certificate numbers
- QR verification codes
- Professional design templates
- Download functionality

### 5. Refund Processing

#### Event-Sourced Architecture
- Complete audit trail
- Refund aggregates
- Custom event repository
- Projectors for read models

#### Refund Workflow
- User-initiated requests
- Admin approval process
- Automated processing
- Payment provider integration

## Technical Architecture

### Domain Structure
```
app/Domain/Cgo/
├── Models/
│   ├── CgoInvestment.php
│   ├── CgoPricingRound.php
│   └── CgoRefund.php
├── Events/
│   ├── InvestmentCreated.php
│   ├── PaymentCompleted.php
│   ├── RefundRequested.php
│   ├── RefundProcessed.php
│   └── RefundFailed.php
├── Aggregates/
│   └── CgoRefundAggregate.php
├── Projectors/
│   └── RefundProjector.php
├── Repositories/
│   └── CgoEventRepository.php
└── Services/
    ├── StripePaymentService.php
    ├── CoinbaseCommerceService.php
    ├── CgoKycService.php
    ├── InvestmentAgreementService.php
    └── PaymentVerificationService.php
```

### Database Schema

#### cgo_investments
- UUID-based identification
- Payment provider references
- KYC status tracking
- Agreement/certificate paths
- Comprehensive status management

#### cgo_pricing_rounds
- Round configuration
- Target/raised amounts
- Active status management
- Investment limits

#### cgo_refunds
- Event-sourced design
- Status workflow
- Transaction references
- Audit metadata

#### cgo_events
- Event store table
- Aggregate versioning
- Event properties
- Metadata storage

## API Integration

### Investment Endpoints
```
POST   /api/cgo/investments              - Create investment
GET    /api/cgo/investments/{uuid}       - Get investment details
GET    /api/cgo/investments              - List user investments
POST   /api/cgo/investments/{uuid}/cancel - Cancel investment
```

### Payment Endpoints
```
POST   /api/cgo/payments/stripe/checkout    - Create Stripe session
POST   /api/cgo/payments/coinbase/charge   - Create Coinbase charge
POST   /api/cgo/payments/verify            - Verify payment
GET    /api/cgo/payments/{uuid}/status     - Get payment status
```

### Document Endpoints
```
GET    /api/cgo/investments/{uuid}/agreement   - Download agreement
GET    /api/cgo/investments/{uuid}/certificate - Download certificate
```

### Refund Endpoints
```
POST   /api/cgo/investments/{uuid}/refund - Request refund
GET    /api/cgo/refunds/{refund_id}       - Get refund status
```

### Webhook Endpoints
```
POST   /api/cgo/webhooks/stripe    - Stripe webhook handler
POST   /api/cgo/webhooks/coinbase  - Coinbase webhook handler
```

## Admin Interface

### Filament Resources

#### CgoInvestmentResource
- Comprehensive investment management
- Advanced filtering and search
- Bulk operations support
- Payment verification actions
- Export functionality

#### CgoPricingRoundResource
- Round configuration
- Progress tracking
- Target management
- Round closure

### Payment Verification Dashboard
- Real-time payment status
- Pending verification queue
- Failed payment tracking
- Manual verification tools
- Auto-refresh capability

## Security Features

### Payment Security
- Webhook signature verification
- HTTPS-only communication
- No sensitive data storage
- UUID-based references
- Rate limiting

### Data Protection
- Encrypted storage
- Access control
- Audit logging
- GDPR compliance
- Data retention policies

## Configuration

### Environment Variables
```env
# Stripe
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# Coinbase Commerce
COINBASE_COMMERCE_API_KEY=xxx
COINBASE_COMMERCE_WEBHOOK_SECRET=xxx

# CGO Settings
CGO_MINIMUM_INVESTMENT=1000
CGO_MAXIMUM_INVESTMENT=1000000
CGO_CRYPTO_BTC_ADDRESS=test_btc_address
CGO_CRYPTO_ETH_ADDRESS=test_eth_address
CGO_CRYPTO_USDT_ADDRESS=test_usdt_address
CGO_BANK_NAME="Test Bank"
CGO_BANK_IBAN="TEST1234567890"
CGO_BANK_SWIFT="TESTSWIFT"
```

## Testing

### Test Coverage
- Unit tests for all services
- Feature tests for workflows
- Integration tests for payments
- Browser tests for UI flows

### Key Test Files
- `tests/Feature/Cgo/CgoInvestmentTest.php`
- `tests/Feature/Cgo/PaymentVerificationTest.php`
- `tests/Feature/Cgo/RefundProcessingTest.php`
- `tests/Unit/Services/Cgo/CgoKycServiceTest.php`

## Monitoring & Operations

### Scheduled Tasks
```php
$schedule->command('cgo:verify-payments')->everyTenMinutes();
$schedule->command('cgo:process-refunds')->everyFifteenMinutes();
$schedule->command('cgo:sync-payment-status')->hourly();
```

### Key Metrics
- Total investments by tier
- Payment success rate
- KYC approval rate
- Average processing time
- Refund rate

### Alerts
- Failed payments
- KYC rejections
- Webhook failures
- System errors

## Development Resources

### Related Documentation
- [Technical Implementation](../05-TECHNICAL/CGO_DOCUMENTATION.md)
- [KYC/AML Implementation](../06-DEVELOPMENT/CGO_KYC_AML.md)
- [Payment Verification](../06-DEVELOPMENT/CGO_PAYMENT_VERIFICATION.md)
- [Investment Agreements](../06-DEVELOPMENT/CGO_INVESTMENT_AGREEMENTS.md)

### Support
- Technical Issues: GitHub Issues
- Security Concerns: security@finaegis.com
- Integration Support: developers@finaegis.com