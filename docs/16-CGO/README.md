# Continuous Growth Offering (CGO) Documentation

**Last Updated:** 2026-11-24 (v7.18.1)  
**Status:** Concept and demo code. The CGO round is closed.

> Concept and demo code for an investment-round workflow (tiers, payments, KYC, agreements, refunds). The CGO round is closed.

## Overview

The CGO module is concept and demo code for an investment-round workflow (tiers, payments, KYC, agreements, refunds). It is built with event sourcing, payment integration and tiered KYC/AML checks.

The module lives in `app/Domain/Cgo/`. The production Zelta deployment switches it off. `.env.zelta.example` lists `Cgo` in `MODULES_DISABLED`, so the CGO pages return 404, the CGO GraphQL operations answer "This operation is not available." and the scheduled payment checks do not run. `.env.production.example` leaves `MODULES_DISABLED` empty, so another operator has to list `Cgo` there to switch the module off.

When the module is on, `/cgo/invest` shows a "round closed" page unless a pricing round is marked active. The standard seeders and `demo:populate` create no pricing round. `CgoPricingRoundSeeder` creates an active round, but only when someone runs it on its own.

## Contents

- **[CGO_IMPLEMENTATION_PLAN.md](CGO_IMPLEMENTATION_PLAN.md)** - Original implementation planning document (September 2024)
- **[CGO_ANALYSIS_REPORT.md](CGO_ANALYSIS_REPORT.md)** - Original functionality analysis (September 2024)
- **[CGO_REFUND_PROCESSING.md](CGO_REFUND_PROCESSING.md)** - Refund system technical documentation

## Key Features

### 1. Payment Methods

*Integration adapters for third-party APIs; no partnership or endorsement implied.*

#### Stripe (Card Payments)
- The card option creates a Stripe Checkout session through `StripePaymentService`.
- Stripe Checkout handles card authentication, including 3D Secure.
- Stripe events arrive on `POST /stripe/webhook` (`StripeWebhookController`, signature checked by `webhook.signature:stripe`).

#### Coinbase Commerce (Cryptocurrency)
- The crypto option creates a Coinbase Commerce charge when `CGO_COINBASE_COMMERCE_ENABLED=true` and `COINBASE_COMMERCE_API_KEY` is set.
- Without Coinbase Commerce, the page shows a configured address (`CGO_BTC_ADDRESS`, `CGO_ETH_ADDRESS`, `CGO_USDT_ADDRESS` or `CGO_USDC_ADDRESS`) with a QR code.
- In production, crypto payments stay blocked unless `CGO_PRODUCTION_CRYPTO_ENABLED=true`.
- Coinbase Commerce events arrive on `POST /api/webhooks/coinbase-commerce`.

#### Bank Transfer
- The bank option shows the account details from `CGO_BANK_*` and a payment reference in the form `CGO-{investment uuid}`.
- Bank transfers are matched by hand. There is no automated reconciliation.
- Investments are recorded in USD.

### 2. KYC/AML Checks

#### Tiered Verification System
`CgoKycService` decides the required level from the person's total invested amount.
- **Basic KYC** applies up to $1,000.
- **Enhanced KYC** applies up to $10,000.
- **Full KYC** applies above $10,000.

#### AML Checks
- The service flags people whose record has a politically exposed person (PEP) status.
- It checks the person's country against a short built-in list of sanctioned countries.
- It flags unusual transaction patterns.
- For an investment of $50,000 or more, it flags an unverified source of funds.
- It flags people whose record has a high risk rating.
- Adverse media screening is listed as a required check for full KYC, but the service does not perform it.

### 3. Agreements and Certificates

#### Agreement Generation
- `InvestmentAgreementService` renders the agreement as a PDF with dompdf and stores it with Laravel `Storage`.
- `POST /cgo/agreement/{investment}/sign` marks an agreement as signed. It does not apply a cryptographic signature.

#### Certificate Creation
- The same service renders a PDF certificate with a unique certificate number.
- People download agreements and certificates from the CGO pages.

### 4. Refund Processing

#### Event-Sourced Architecture
- `RefundAggregate` records refund events in the `cgo_events` table through `CgoEventRepository`.
- `RefundProjector` keeps the `cgo_refunds` read model up to date.

#### Refund Workflow
- `RequestRefundAction` checks eligibility, decides on auto-approval and starts `ProcessRefundWorkflow`.
- No page, API route or admin action calls `RequestRefundAction` yet.
- See [CGO_REFUND_PROCESSING.md](CGO_REFUND_PROCESSING.md) for the rules.

## Technical Architecture

### Domain Structure
```
app/Domain/Cgo/
├── Actions/
│   └── RequestRefundAction.php
├── Activities/
│   ├── ApproveRefundActivity.php
│   ├── CompleteRefundActivity.php
│   ├── FailRefundActivity.php
│   ├── InitiateRefundActivity.php
│   └── ProcessRefundActivity.php
├── Aggregates/
│   └── RefundAggregate.php
├── Console/Commands/
│   └── VerifyCgoPayments.php
├── DataObjects/
│   └── RefundRequest.php
├── Events/
│   ├── RefundApproved.php
│   ├── RefundCancelled.php
│   ├── RefundCompleted.php
│   ├── RefundFailed.php
│   ├── RefundProcessed.php
│   ├── RefundRejected.php
│   └── RefundRequested.php
├── Jobs/
│   └── VerifyCgoPayment.php
├── Mail/
│   ├── CgoBankTransferInstructions.php
│   ├── CgoCryptoPaymentInstructions.php
│   ├── CgoInvestmentConfirmed.php
│   ├── CgoInvestmentReceived.php
│   └── CgoNotificationReceived.php
├── Models/
│   ├── CgoEvent.php
│   ├── CgoInvestment.php
│   ├── CgoNotification.php
│   ├── CgoPricingRound.php
│   └── CgoRefund.php
├── Projectors/
│   └── RefundProjector.php
├── Repositories/
│   └── CgoEventRepository.php
├── Services/
│   ├── CgoKycService.php
│   ├── CoinbaseCommerceService.php
│   ├── InvestmentAgreementService.php
│   ├── PaymentVerificationService.php
│   ├── RefundProcessingService.php
│   └── StripePaymentService.php
└── Workflows/
    └── ProcessRefundWorkflow.php
```

The payment instruction mails (`CgoBankTransferInstructions` and `CgoCryptoPaymentInstructions`) pointed at views that did not exist. v7.18.0 added the views and fixed the amount formatting.

### Database Schema

- `cgo_investments` holds the investment records, payment provider references, KYC status and agreement and certificate paths.
- `cgo_pricing_rounds` holds the round configuration, including the active flag.
- `cgo_refunds` is the refund read model that `RefundProjector` writes.
- `cgo_events` is the event store table for the refund aggregate. The tenant migrations also create a `cgo_snapshots` table.
- `cgo_notifications` holds the email addresses from the "notify me" form on `/cgo`.

## Routes

The CGO module has web routes and GraphQL operations. There is no `/api/cgo` REST API.

### Public Pages
```
GET    /cgo                 - Concept page
GET    /cgo/terms           - CGO terms
POST   /cgo/notify          - "Notify me" email form
GET    /cgo/notify-success  - Confirmation page
```

### Signed-In Pages (`auth`, `verified`)
```
GET    /cgo/investments                                  - My investments
GET    /cgo/invest                                       - Invest form (closed page when no round is active)
POST   /cgo/invest                                       - Submit an investment
GET    /cgo/payment/success/{investment}                 - Payment success page
GET    /cgo/payment/cancel/{investment}                  - Payment cancelled page
GET    /cgo/certificate/{uuid}                           - Certificate page
POST   /cgo/agreement/{investment}/generate              - Generate agreement PDF
GET    /cgo/agreement/{investment}/download              - Download agreement
GET    /cgo/agreement/{investment}/preview               - Preview agreement
POST   /cgo/agreement/{investment}/sign                  - Mark agreement as signed
POST   /cgo/certificate/{investment}/generate            - Generate certificate PDF
GET    /cgo/certificate/{investment}/download            - Download certificate
GET    /cgo/kyc/status                                   - KYC status
POST   /cgo/kyc/check-requirements                       - Check KYC requirements
GET    /cgo/kyc/documents                                - KYC document upload page
POST   /cgo/kyc/submit                                   - Submit KYC documents
POST   /cgo/kyc/verify/{investment}                      - Verify KYC for an investment
GET    /cgo/payment-verification                         - My pending payments
POST   /cgo/payment-verification/{investment}/check      - Check a payment
POST   /cgo/payment-verification/{investment}/resend     - Resend payment instructions
GET    /cgo/payment-verification/{investment}/timeline   - Payment timeline
```

### GraphQL
`graphql/cgo.graphql` adds the `investment` and `investments` queries and the `createInvestment` mutation. They are registered only when the `Cgo` module is enabled.

### Webhooks
```
POST   /stripe/webhook                       - Stripe events (card payments)
POST   /api/webhooks/coinbase-commerce       - Coinbase Commerce events
```

## Admin Interface

The admin panel has two CGO resources in the "Platform" group. See [CGO_ADMIN_RESOURCES.md](../15-ADMINISTRATION/CGO_ADMIN_RESOURCES.md).
- `CgoInvestmentResource` is a scaffold with an empty form and table.
- `CgoNotificationResource` ("CGO Early Access") lists the email sign-ups.

There is no admin resource for pricing rounds or refunds.

## Security Features

### Payment Security
- Stripe and Coinbase Commerce webhooks are signature-checked.
- Investments are referenced by UUID.
- Card details never reach the platform, because Stripe Checkout collects them.

### Data Protection
- The investment, KYC and payment pages require a signed-in, verified user.
- KYC documents go through the platform's general KYC service (`KycService::submitKyc`).
- Generated agreements and certificates are stored through Laravel `Storage`.

## Configuration

### Environment Variables
```env
# Stripe
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# Coinbase Commerce
CGO_COINBASE_COMMERCE_ENABLED=false
COINBASE_COMMERCE_API_KEY=xxx
COINBASE_COMMERCE_WEBHOOK_SECRET=xxx

# CGO Settings
CGO_MINIMUM_INVESTMENT=100
CGO_MAXIMUM_INVESTMENT=1000000
CGO_BTC_ADDRESS=test_btc_address
CGO_ETH_ADDRESS=test_eth_address
CGO_USDT_ADDRESS=test_usdt_address
CGO_USDC_ADDRESS=test_usdc_address
CGO_PRODUCTION_CRYPTO_ENABLED=false
CGO_BANK_NAME="Test Bank"
CGO_BANK_IBAN="TEST1234567890"
CGO_BANK_SWIFT_CODE="TESTSWIFT"
```

`config/cgo.php` holds all CGO settings. Two of them do not reach the invest form today. The form reads `cgo.packages`, which the config file does not define, so it offers no package. It also checks `cgo.min_investment` and `cgo.max_investment`, while the config file stores the limits under `cgo.limits`.

## Testing

### Key Test Files
- `tests/Unit/Domain/Cgo/RefundAggregateTest.php`
- `tests/Domain/Cgo/Services/CgoKycServiceTest.php`
- `tests/Domain/Cgo/Services/InvestmentAgreementServiceTest.php`
- `tests/Domain/Cgo/Services/PaymentVerificationServiceTest.php`
- `tests/Feature/Cgo/CgoPaymentInstructionMailTest.php`
- `tests/Integration/GraphQL/CgoGraphQLTest.php`

## Monitoring & Operations

### Scheduled Tasks
`routes/console.php` schedules these only when the `Cgo` module is enabled:
```php
Schedule::command('cgo:verify-payments')->everyThirtyMinutes();
Schedule::command('cgo:verify-payments --expired')->everyTwoHours();
```

`cgo:verify-payments` also takes `--investment=<uuid>` to check one investment. The output goes to `storage/logs/cgo-payment-verification.log` and `storage/logs/cgo-expired-payments.log`.

## Development Resources

### Related Documentation
- [Technical Implementation](../14-TECHNICAL/CGO_DOCUMENTATION.md)
- [KYC/AML Implementation](../06-DEVELOPMENT/CGO_KYC_AML.md)
- [Payment Verification](../06-DEVELOPMENT/CGO_PAYMENT_VERIFICATION.md)
- [Investment Agreements](../06-DEVELOPMENT/CGO_INVESTMENT_AGREEMENTS.md)

### Support
- Technical Issues: GitHub Issues
