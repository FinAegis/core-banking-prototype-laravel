# CGO (Continuous Growth Offering) Technical Documentation

**Last Updated:** November 2026 (reviewed for v7.18.1)  
**Status:** Reference implementation (demo module)

## Overview

The Continuous Growth Offering (CGO) module is reference software. It shows how an investment intake flow can work, with tiers, payment adapters, identity and anti-money-laundering (KYC/AML) checks, agreement generation and event-sourced refunds. It is not an offer of securities or tokens.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

The production site switches the Cgo module off through `MODULES_DISABLED`. The module runs in demo mode and on deployments that enable it. The Stripe and Coinbase Commerce code are integration adapters for third-party APIs, and no partnership or endorsement is implied. Both adapters are off by default (`CGO_STRIPE_ENABLED` and `CGO_COINBASE_COMMERCE_ENABLED`).

## Architecture

### Domain Structure

CGO is a bounded context in the Domain-Driven Design structure:

```
app/Domain/Cgo/
├── Models/
│   ├── CgoInvestment.php       # Core investment entity
│   ├── CgoPricingRound.php     # Pricing round management
│   ├── CgoRefund.php           # Refund records
│   ├── CgoNotification.php     # Interest sign-ups
│   └── CgoEvent.php            # Stored events of the cgo_events table
├── Events/
│   ├── RefundRequested.php     # Refund request event
│   ├── RefundApproved.php      # Refund approval event
│   ├── RefundRejected.php      # Refund rejection event
│   ├── RefundProcessed.php     # Refund processing event
│   ├── RefundCompleted.php     # Refund completion event
│   ├── RefundFailed.php        # Refund failure event
│   └── RefundCancelled.php     # Refund cancellation event
├── Aggregates/
│   └── RefundAggregate.php     # Event-sourced refund aggregate
├── Projectors/
│   └── RefundProjector.php     # Projects events to read model
├── Repositories/
│   └── CgoEventRepository.php  # Custom event repository
├── Workflows/
│   └── ProcessRefundWorkflow.php # Refund workflow and its activities
├── Jobs/
│   └── VerifyCgoPayment.php    # Payment verification job
└── Services/
    ├── StripePaymentService.php     # Stripe integration
    ├── CoinbaseCommerceService.php  # Coinbase Commerce integration
    ├── CgoKycService.php            # KYC/AML verification
    ├── InvestmentAgreementService.php # PDF generation
    ├── RefundProcessingService.php  # Refund processing
    └── PaymentVerificationService.php # Payment verification
```

## Investment Tiers

### Three Demo Tiers

`config/cgo.php` defines three tiers by amount:

| Tier | Amount range |
|------|--------------|
| `bronze` | 0 to 999 |
| `silver` | 1,000 to 9,999 |
| `gold` | 10,000 and above |

The default investment limits are 100 at the minimum (`CGO_MINIMUM_INVESTMENT`) and 1,000,000 at the maximum (`CGO_MAXIMUM_INVESTMENT`).

The level of the identity check depends on the amount, not on the tier. `CgoKycService` adds the new amount to what the user has already invested. It asks for a basic check up to 1,000, an enhanced check up to 10,000 and a full check above that. From 10,000 it also runs anti-money-laundering checks, and from 50,000 it also checks the source of funds.

## Payment Integration

### Stripe Integration

```php
// app/Domain/Cgo/Services/StripePaymentService.php
public function createCheckoutSession(CgoInvestment $investment): Session
{
    return Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => strtolower(config('cashier.currency')),
                'product_data' => [
                    'name' => 'CGO Investment - ' . $investment->package,
                ],
                'unit_amount' => $investment->amount * 100,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => route('cgo.payment.success', ['investment' => $investment->uuid]),
        'cancel_url' => route('cgo.payment.cancel', ['investment' => $investment->uuid]),
        'metadata' => [
            'investment_uuid' => $investment->uuid,
        ],
    ]);
}
```

### Coinbase Commerce Integration

```php
// app/Domain/Cgo/Services/CoinbaseCommerceService.php
public function createCharge(CgoInvestment $investment): array
{
    $response = Http::withHeaders([
        'X-CC-Api-Key' => $this->apiKey,
        'X-CC-Version' => '2018-03-22',
    ])->post($this->apiUrl . '/charges', [
        'name' => 'CGO Investment - ' . ucfirst($investment->tier),
        'description' => 'Investment in FinAegis Continuous Growth Offering',
        'pricing_type' => 'fixed_price',
        'local_price' => [
            'amount' => (string) $investment->amount,
            'currency' => 'USD',
        ],
        'metadata' => [
            'investment_uuid' => $investment->uuid,
        ],
    ]);
    
    return $response->json()['data'];
}
```

### Webhook Verification

Both payment adapters verify the signature of each webhook. The `webhook.signature` middleware checks Stripe on `POST /stripe/webhook` and Coinbase Commerce on `POST /api/webhooks/coinbase-commerce`:

```php
// Stripe webhook verification
public function verifyWebhookSignature(Request $request): bool
{
    try {
        Webhook::constructEvent(
            $request->getContent(),
            $request->header('Stripe-Signature'),
            config('services.stripe.webhook_secret')
        );
        return true;
    } catch (\Exception $e) {
        return false;
    }
}

// Coinbase Commerce webhook verification
public function verifyWebhookSignature(Request $request): bool
{
    $payload = $request->getContent();
    $signature = $request->header('X-CC-Webhook-Signature');
    $computedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
    
    return hash_equals($signature, $computedSignature);
}
```

## KYC/AML Verification

### Tiered KYC System

```php
// app/Domain/Cgo/Services/CgoKycService.php
class CgoKycService
{
    const BASIC_KYC_THRESHOLD = 1000;      // Up to $1,000
    const ENHANCED_KYC_THRESHOLD = 10000;  // Up to $10,000
    const FULL_KYC_THRESHOLD = 50000;      // Above $50,000
    
    // The amount is the user's total: earlier investments plus the new one
    protected function determineRequiredKycLevel(float $amount): string
    {
        if ($amount <= self::BASIC_KYC_THRESHOLD) {
            return 'basic';
        } elseif ($amount <= self::ENHANCED_KYC_THRESHOLD) {
            return 'enhanced';
        }
        return 'full';
    }

    // Runs for investments of 10,000 and more
    protected function performAmlChecks(User $user, CgoInvestment $investment): array
    {
        // Politically exposed person flag on the user
        // Sanctions check (demo: compares the user's country with a short built-in list)
        // Unusual transaction patterns
        // Source of funds, for investments of 50,000 and more
        // High-risk profile flag
        // Returns ['passed' => bool, 'flags' => [...]]
    }
}
```

## Investment Agreement Generation

### PDF Generation Service

```php
// app/Domain/Cgo/Services/InvestmentAgreementService.php
public function generateAgreement(CgoInvestment $investment): string
{
    $investment->load(['user', 'round']);
    
    $data = [
        'investment' => $investment,
        'user' => $investment->user,
        'company' => $this->getCompanyDetails(),
        'terms' => $this->getTermsForTier($investment->tier),
        'generated_at' => now(),
    ];
    
    $pdf = Pdf::loadView('cgo.agreements.investment-agreement', $data);
    $pdf->setPaper('A4', 'portrait');
    
    $filename = "investment-agreement-{$investment->uuid}.pdf";
    $path = "cgo/agreements/{$filename}";
    
    Storage::put($path, $pdf->output());
    
    $investment->update(['agreement_path' => $path]);
    
    return $path;
}
```

## Event-Sourced Refund Processing

### Refund Aggregate

```php
// app/Domain/Cgo/Aggregates/RefundAggregate.php
class RefundAggregate extends AggregateRoot
{
    public function requestRefund(
        string $refundId,
        string $investmentId,
        string $userId,
        int $amount,
        string $currency,
        string $reason,
        ?string $reasonDetails,
        string $initiatedBy
    ): self {
        $this->recordThat(new RefundRequested(
            $refundId,
            $investmentId,
            $userId,
            $amount,
            $currency,
            $reason,
            $reasonDetails,
            $initiatedBy
        ));
        
        return $this;
    }
    
    public function processRefund(
        string $transactionId,
        string $processedBy,
        array $metadata = []
    ): self {
        $this->recordThat(new RefundProcessed(
            $this->refundId,
            $transactionId,
            $processedBy,
            now(),
            $metadata
        ));
        
        return $this;
    }
}
```

### Custom Event Repository

```php
// app/Domain/Cgo/Repositories/CgoEventRepository.php
final class CgoEventRepository extends EloquentStoredEventRepository
{
    public function __construct(
        protected string $storedEventModel = CgoEvent::class
    ) {
        if (! new $this->storedEventModel() instanceof EloquentStoredEvent) {
            throw new InvalidEloquentStoredEventModel(
                "The class {$this->storedEventModel} must extend EloquentStoredEvent"
            );
        }
    }
}
```

## Admin Interface

### Filament Resources

1. **CgoInvestmentResource** lists all investments. Staff can create and edit an investment and delete investments in bulk.
2. **CgoNotificationResource** lists the people who signed up for CGO updates.

There is no admin resource for pricing rounds. Rounds are stored in the `cgo_pricing_rounds` table.

### Payment Verification Page

Investors follow their payments on the web page `/cgo/payment-verification`, served by `CgoPaymentVerificationController`. From there they can ask for a status check, have the payment instructions sent again and see the payment timeline. The `VerifyCgoPayment` job checks pending payments in the background.

## Database Schema

The blocks below show the main columns. The migrations in `database/migrations/` are the full source.

### cgo_investments Table
```sql
CREATE TABLE cgo_investments (
    id BIGINT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    user_id BIGINT NOT NULL,
    email VARCHAR(255),
    round_id BIGINT,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    share_price DECIMAL(10,4) NOT NULL,
    shares_purchased DECIMAL(15,4) NOT NULL,
    ownership_percentage DECIMAL(8,6) NOT NULL,
    tier ENUM('bronze', 'silver', 'gold') NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(255) NOT NULL,
    payment_status VARCHAR(255) DEFAULT 'pending',
    stripe_session_id VARCHAR(255),
    stripe_payment_intent_id VARCHAR(255),
    coinbase_charge_id VARCHAR(255),
    coinbase_charge_code VARCHAR(255),
    bank_transfer_reference VARCHAR(255),
    crypto_address VARCHAR(255),
    crypto_transaction_hash VARCHAR(255),
    kyc_level VARCHAR(255),
    kyc_verified_at DATETIME NULL,
    aml_checked_at DATETIME NULL,
    aml_flags JSON,
    agreement_path VARCHAR(255),
    certificate_number VARCHAR(255),
    certificate_path VARCHAR(255),
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX (user_id),
    INDEX (status),
    INDEX (tier),
    INDEX (created_at)
);
```

### cgo_pricing_rounds Table
```sql
CREATE TABLE cgo_pricing_rounds (
    id BIGINT PRIMARY KEY,
    round_number INT UNIQUE NOT NULL,
    share_price DECIMAL(10,4) NOT NULL,
    max_shares_available DECIMAL(15,4) NOT NULL,
    shares_sold DECIMAL(15,4) DEFAULT 0,
    total_raised DECIMAL(15,2) DEFAULT 0,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    is_active BOOLEAN DEFAULT false,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### cgo_refunds Table
```sql
CREATE TABLE cgo_refunds (
    id CHAR(36) PRIMARY KEY,
    investment_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,      -- in cents
    amount_refunded BIGINT UNSIGNED NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    reason VARCHAR(50),
    reason_details TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    initiated_by BIGINT NOT NULL,
    approved_by BIGINT NULL,
    processor_reference VARCHAR(255),
    processed_at DATETIME NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    failure_reason TEXT,
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX (status),
    INDEX (investment_id, status)
);
```

### cgo_events Table
```sql
CREATE TABLE cgo_events (
    id BIGINT PRIMARY KEY,
    aggregate_uuid VARCHAR(36),
    aggregate_version INT,
    event_version INT DEFAULT 1,
    event_class VARCHAR(255),
    event_properties JSON,
    meta_data JSON,
    created_at TIMESTAMP,
    
    INDEX idx_aggregate_uuid (aggregate_uuid),
    UNIQUE KEY uk_aggregate_version (aggregate_uuid, aggregate_version)
);
```

## API Endpoints

The CGO module has no REST API. It is served as web pages under `/cgo`, most of them for signed-in users with a verified email, plus two webhooks. The GraphQL schema `graphql/cgo.graphql` adds the `investment` and `investments` queries and the `createInvestment` mutation.

### Investment Pages
- `GET /cgo` - Landing page of the demo offering
- `GET /cgo/invest` and `POST /cgo/invest` - Investment form and submission
- `GET /cgo/investments` - The user's investments
- `GET /cgo/payment/success/{investment}` and `GET /cgo/payment/cancel/{investment}` - Return pages after a Stripe checkout

### Identity Checks
- `GET /cgo/kyc/status` - Status of the user's identity check
- `POST /cgo/kyc/check-requirements` - Required level for an amount
- `GET /cgo/kyc/documents` and `POST /cgo/kyc/submit` - Document upload
- `POST /cgo/kyc/verify/{investment}` - Check an investment against the required level

### Payment Verification
- `GET /cgo/payment-verification` - Payment overview
- `POST /cgo/payment-verification/{investment}/check` - Check the payment status
- `POST /cgo/payment-verification/{investment}/resend` - Send the payment instructions again
- `GET /cgo/payment-verification/{investment}/timeline` - Payment timeline

### Documents
- `GET /cgo/agreement/{investment}/preview`, `POST /cgo/agreement/{investment}/generate`, `POST /cgo/agreement/{investment}/sign` and `GET /cgo/agreement/{investment}/download` - Investment agreement
- `POST /cgo/certificate/{investment}/generate`, `GET /cgo/certificate/{investment}/download` and `GET /cgo/certificate/{uuid}` - Investment certificate

### Webhooks
- `POST /stripe/webhook` - Stripe webhook handler (shared with the identity-check payments)
- `POST /api/webhooks/coinbase-commerce` - Coinbase Commerce webhook handler

Refunds have no page or admin action yet. `RefundProcessingService` and the `ProcessRefundWorkflow` workflow hold the refund logic, and no route or admin screen calls them.

## Security Considerations

### Payment Security
Payments run over HTTPS. Both webhooks verify the provider's signature, and the Stripe check also rejects old timestamps to stop replays. Each payment reference is a unique UUID, which prevents duplicate processing. Card data stays with Stripe and is never stored by the module.

### Identity and Anti-Money-Laundering Checks
The required check level depends on the investment amount. The sanctions check in the demo only compares the user's country with a short built-in list. A real deployment would have to connect a sanctions screening service. High-risk profiles are flagged, and each verification step is recorded.

### Access Control
Investment pages require a signed-in user with a verified email. Admin functions require an administrator role. Web forms are protected against cross-site request forgery.

## Configuration

### Environment Variables
```env
# Stripe Configuration
CGO_STRIPE_ENABLED=false
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
STRIPE_WEBHOOK_SECRET=your_webhook_secret

# Coinbase Commerce Configuration
CGO_COINBASE_COMMERCE_ENABLED=false
COINBASE_COMMERCE_API_KEY=your_api_key
COINBASE_COMMERCE_WEBHOOK_SECRET=your_webhook_secret

# CGO Configuration
CGO_MINIMUM_INVESTMENT=100
CGO_MAXIMUM_INVESTMENT=1000000
CGO_KYC_REQUIRED=true
CGO_KYC_THRESHOLD=1000
CGO_BTC_ADDRESS=test_btc_address
CGO_ETH_ADDRESS=test_eth_address
CGO_USDT_ADDRESS=test_usdt_address
CGO_USDC_ADDRESS=test_usdc_address
CGO_PRODUCTION_CRYPTO_ENABLED=false
CGO_BANK_NAME="Test Bank"
CGO_BANK_ACCOUNT_NAME="Test Account"
CGO_BANK_IBAN="TEST1234567890"
CGO_BANK_SWIFT_CODE="TESTSWIFT"
```

## Testing

### Running the Tests
```bash
./vendor/bin/pest tests/Feature/Cgo tests/Domain/Cgo tests/Unit/Domain/Cgo
./vendor/bin/pest tests/Integration/GraphQL/CgoGraphQLTest.php
```

The tests cover the identity check rules in `CgoKycService`, the payment instruction emails and the GraphQL queries and mutation.

## Monitoring

A deployment that runs the module should track the investments per tier, the payment success rate, the approval rate of identity checks, the processing time and the refund rate. It should alert on failed payments, rejected identity checks, webhook failures and system errors. The module does not ship these dashboards or alerts.

## Future Enhancements

Possible future work includes investment performance tracking, investor demographics and conversion funnel analysis. Mobile support with push notifications and biometric sign-in is another option.