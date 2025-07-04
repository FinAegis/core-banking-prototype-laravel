# CGO (Continuous Growth Offering) Technical Documentation

**Last Updated:** January 2025  
**Status:** ✅ COMPLETED - Production Ready

## Overview

The Continuous Growth Offering (CGO) is a sophisticated investment platform built on FinAegis that allows users to invest in the platform's growth through a tiered investment system. It features complete payment integration, KYC/AML compliance, automated agreement generation, and event-sourced refund processing.

## Architecture

### Domain Structure

CGO is implemented as a bounded context within the Domain-Driven Design architecture:

```
app/Domain/Cgo/
├── Models/
│   ├── CgoInvestment.php       # Core investment entity
│   ├── CgoPricingRound.php     # Pricing round management
│   └── CgoRefund.php           # Refund records
├── Events/
│   ├── InvestmentCreated.php   # Investment creation event
│   ├── PaymentCompleted.php    # Payment completion event
│   ├── RefundRequested.php     # Refund request event
│   ├── RefundProcessed.php     # Refund completion event
│   └── RefundFailed.php        # Refund failure event
├── Aggregates/
│   └── CgoRefundAggregate.php  # Event-sourced refund aggregate
├── Projectors/
│   └── RefundProjector.php     # Projects events to read model
├── Repositories/
│   └── CgoEventRepository.php  # Custom event repository
└── Services/
    ├── StripePaymentService.php     # Stripe integration
    ├── CoinbaseCommerceService.php  # Coinbase Commerce integration
    ├── CgoKycService.php            # KYC/AML verification
    ├── InvestmentAgreementService.php # PDF generation
    └── PaymentVerificationService.php # Payment verification
```

## Investment Tiers

### Three Investment Packages

1. **Explorer Tier**
   - Minimum: $1,000
   - Maximum: $9,999
   - Benefits: Early access, quarterly updates
   - KYC Level: Basic

2. **Innovator Tier**
   - Minimum: $10,000
   - Maximum: $49,999
   - Benefits: Premium features, monthly updates, priority support
   - KYC Level: Enhanced

3. **Visionary Tier**
   - Minimum: $50,000
   - Maximum: Unlimited
   - Benefits: Board advisory access, weekly updates, dedicated support
   - KYC Level: Full

## Payment Integration

### Stripe Integration

```php
// app/Services/Cgo/StripePaymentService.php
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
// app/Services/Cgo/CoinbaseCommerceService.php
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

Both payment providers use secure webhook verification:

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
// app/Services/Cgo/CgoKycService.php
class CgoKycService
{
    const BASIC_KYC_THRESHOLD = 1000;      // Up to $1,000
    const ENHANCED_KYC_THRESHOLD = 10000;  // Up to $10,000
    const FULL_KYC_THRESHOLD = 50000;      // Above $50,000
    
    public function determineRequiredKycLevel(int $amount): string
    {
        if ($amount >= self::FULL_KYC_THRESHOLD) {
            return 'full';
        } elseif ($amount >= self::ENHANCED_KYC_THRESHOLD) {
            return 'enhanced';
        }
        return 'basic';
    }
    
    public function performAmlCheck(User $user): array
    {
        // Sanctions list checking
        $sanctionsCheck = $this->checkSanctionsList($user);
        
        // PEP (Politically Exposed Person) check
        $pepCheck = $this->checkPepStatus($user);
        
        // Adverse media check
        $adverseMediaCheck = $this->checkAdverseMedia($user);
        
        return [
            'sanctions' => $sanctionsCheck,
            'pep' => $pepCheck,
            'adverse_media' => $adverseMediaCheck,
            'risk_score' => $this->calculateRiskScore($sanctionsCheck, $pepCheck, $adverseMediaCheck),
        ];
    }
}
```

## Investment Agreement Generation

### PDF Generation Service

```php
// app/Services/Cgo/InvestmentAgreementService.php
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
// app/Domain/Cgo/Aggregates/CgoRefundAggregate.php
class CgoRefundAggregate extends AggregateRoot
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

1. **CgoInvestmentResource**
   - View all investments with filtering
   - Verify payments manually
   - Download agreements
   - Process refunds
   - Export data

2. **CgoPricingRoundResource**
   - Manage pricing rounds
   - Set target amounts
   - Track progress
   - Close rounds

### Payment Verification Dashboard

```php
// app/Filament/Pages/CgoPaymentVerificationDashboard.php
class CgoPaymentVerificationDashboard extends Page
{
    protected static string $view = 'filament.pages.cgo-payment-verification-dashboard';
    
    public function getStats(): array
    {
        return [
            'pending_verifications' => CgoInvestment::where('payment_status', 'pending')->count(),
            'failed_payments' => CgoInvestment::where('payment_status', 'failed')->count(),
            'total_raised' => CgoInvestment::where('status', 'active')->sum('amount'),
            'active_round' => CgoPricingRound::where('is_active', true)->first(),
        ];
    }
}
```

## Database Schema

### cgo_investments Table
```sql
CREATE TABLE cgo_investments (
    id BIGINT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    user_id BIGINT NOT NULL,
    round_id BIGINT,
    tier ENUM('explorer', 'innovator', 'visionary'),
    amount INT NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(50),
    payment_status VARCHAR(50) DEFAULT 'pending',
    payment_reference VARCHAR(255),
    stripe_session_id VARCHAR(255),
    coinbase_charge_id VARCHAR(255),
    kyc_status VARCHAR(50) DEFAULT 'pending',
    kyc_level VARCHAR(50),
    kyc_verified_at TIMESTAMP NULL,
    status ENUM('pending', 'active', 'cancelled', 'refunded'),
    agreement_path VARCHAR(255),
    certificate_path VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_status (status)
);
```

### cgo_pricing_rounds Table
```sql
CREATE TABLE cgo_pricing_rounds (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    target_amount INT,
    minimum_investment INT DEFAULT 1000,
    maximum_investment INT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### cgo_refunds Table
```sql
CREATE TABLE cgo_refunds (
    id VARCHAR(36) PRIMARY KEY,
    investment_id VARCHAR(36) NOT NULL,
    user_id BIGINT NOT NULL,
    amount INT NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status VARCHAR(50) DEFAULT 'pending',
    reason VARCHAR(50),
    reason_details TEXT,
    initiated_by VARCHAR(255),
    processed_by VARCHAR(255),
    transaction_id VARCHAR(255),
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_investment_id (investment_id),
    INDEX idx_status (status)
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

### Investment Management
- `POST /api/cgo/investments` - Create new investment
- `GET /api/cgo/investments/{uuid}` - Get investment details
- `GET /api/cgo/investments` - List user's investments
- `POST /api/cgo/investments/{uuid}/cancel` - Cancel pending investment

### Payment Processing
- `POST /api/cgo/payments/stripe/checkout` - Create Stripe checkout session
- `POST /api/cgo/payments/coinbase/charge` - Create Coinbase Commerce charge
- `POST /api/cgo/payments/verify` - Verify payment status
- `GET /api/cgo/payments/{uuid}/status` - Get payment status

### Documents
- `GET /api/cgo/investments/{uuid}/agreement` - Download investment agreement
- `GET /api/cgo/investments/{uuid}/certificate` - Download investment certificate

### Refunds
- `POST /api/cgo/investments/{uuid}/refund` - Request refund
- `GET /api/cgo/refunds/{refund_id}` - Get refund status

### Webhooks
- `POST /api/cgo/webhooks/stripe` - Stripe webhook handler
- `POST /api/cgo/webhooks/coinbase` - Coinbase Commerce webhook handler

## Security Considerations

### Payment Security
- All payment processing uses secure HTTPS connections
- Webhook endpoints verify signatures to prevent replay attacks
- Payment references are unique UUIDs to prevent duplicate processing
- Sensitive payment data is never stored directly

### KYC/AML Compliance
- Tiered verification based on investment amount
- Automated sanctions list checking
- Manual review for high-risk profiles
- Complete audit trail of all verification steps

### Access Control
- Investment operations require authentication
- Admin functions require special permissions
- API rate limiting to prevent abuse
- CSRF protection on all forms

## Configuration

### Environment Variables
```env
# Stripe Configuration
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
STRIPE_WEBHOOK_SECRET=your_webhook_secret

# Coinbase Commerce Configuration
COINBASE_COMMERCE_API_KEY=your_api_key
COINBASE_COMMERCE_WEBHOOK_SECRET=your_webhook_secret

# CGO Configuration
CGO_MINIMUM_INVESTMENT=1000
CGO_MAXIMUM_INVESTMENT=1000000
CGO_CRYPTO_BTC_ADDRESS=test_btc_address
CGO_CRYPTO_ETH_ADDRESS=test_eth_address
CGO_CRYPTO_USDT_ADDRESS=test_usdt_address
CGO_BANK_NAME="Test Bank"
CGO_BANK_IBAN="TEST1234567890"
CGO_BANK_SWIFT="TESTSWIFT"
CGO_BANK_REFERENCE_PREFIX="CGO"
```

## Testing

### Unit Tests
```bash
php artisan test --filter=CgoTest
```

### Feature Tests
- Investment creation flow
- Payment processing
- KYC verification
- Agreement generation
- Refund processing

### Integration Tests
- Stripe webhook handling
- Coinbase Commerce webhook handling
- PDF generation
- Email notifications

## Monitoring

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

## Future Enhancements

1. **Token Distribution**
   - Automated token allocation
   - Vesting schedules
   - Token transfer restrictions

2. **Advanced Analytics**
   - Investment performance tracking
   - Investor demographics
   - Conversion funnel analysis

3. **Mobile App Integration**
   - Native mobile SDKs
   - Push notifications
   - Biometric authentication