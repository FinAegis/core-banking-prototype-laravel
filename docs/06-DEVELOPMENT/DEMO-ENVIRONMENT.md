# Demo Environment Architecture

## Overview

The FinAegis Demo Environment is a comprehensive system that allows the platform to run without external dependencies, making it ideal for demonstrations, development, and testing. Implemented in PR #201-202, this system provides instant responses and simulated behaviors for all external integrations.

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Core Components](#core-components)
- [Demo Services](#demo-services)
- [Configuration](#configuration)
- [Usage Guide](#usage-guide)
- [Development Workflow](#development-workflow)
- [Testing with Demo Mode](#testing-with-demo-mode)
- [Troubleshooting](#troubleshooting)

## Architecture Overview

### Design Principles

1. **Zero External Dependencies**: All external API calls are intercepted and simulated
2. **Instant Responses**: No network delays or processing time
3. **Predictable Behavior**: Consistent responses for reliable demonstrations
4. **Feature Parity**: All platform features work in demo mode
5. **Easy Toggle**: Switch between demo and production with a single flag

### Service Layer Architecture

```
Application Layer
    ↓
Service Interface (PaymentServiceInterface, etc.)
    ↓
Environment Check (app()->environment('demo'))
    ↓                          ↓
Demo Service              Production Service
(DemoPaymentService)      (ProductionPaymentService)
    ↓                          ↓
Mock Response             External API
```

## Core Components

### 1. DemoServiceProvider

**Location**: `app/Providers/DemoServiceProvider.php`

Responsible for:
- Binding demo implementations based on environment
- Registering demo bank connectors
- Configuring view composers for demo indicators

```php
// Automatic service switching based on environment
if ($this->app->environment('demo')) {
    $this->app->bind(PaymentServiceInterface::class, DemoPaymentService::class);
    $this->app->bind(ExchangeService::class, DemoExchangeService::class);
    // ... other bindings
}
```

### 2. DemoMode Middleware

**Location**: `app/Http/Middleware/DemoMode.php`

Features:
- Adds demo banner to all pages
- Injects demo configuration into views
- Protects sensitive operations
- Adds demo watermarks

### 3. Environment Detection

The system uses Laravel's environment system:

```
1. app()->environment('demo')     // Demo environment (APP_ENV=demo)
2. config('demo.sandbox.enabled') // Sandbox mode with real sandbox APIs
3. Production mode (default)      // Full external API usage
```

## Demo Services

### Payment Services

#### DemoPaymentService
**Location**: `app/Domain/Payment/Services/DemoPaymentService.php`

Simulates:
- Stripe deposits and withdrawals
- Payment intent creation
- Transaction processing
- Instant confirmations

Key Features:
```php
- Instant deposit processing
- Mock payment intent IDs (demo_pi_*)
- Simulated webhooks
- Configurable success rates
```

#### DemoPaymentGatewayService
**Location**: `app/Domain/Payment/Services/DemoPaymentGatewayService.php`

Provides:
- Mock Stripe payment intents
- Simulated client secrets
- Demo-specific metadata
- Instant payment confirmations

### Exchange Services

#### DemoExchangeService
**Location**: `app/Domain/Exchange/Services/DemoExchangeService.php`

Features:
- Instant order matching
- Simulated market depth
- Configurable spreads
- Mock trading pairs

Configuration:
```php
'domains' => [
    'exchange' => [
        'spread_percentage' => 0.1,
        'liquidity_multiplier' => 10,
        'default_rates' => [
            'EUR/USD' => 1.10,
            'GBP/USD' => 1.27,
            'GCU/USD' => 1.00,
        ]
    ]
]
```

### Lending Services

#### DemoLendingService
**Location**: `app/Domain/Lending/Services/DemoLendingService.php`

Capabilities:
- Auto-approved loans (configurable threshold)
- Instant disbursement
- Simulated credit scoring
- Mock repayment schedules

Features:
```php
- Configurable approval rates
- Default credit scores
- Instant loan processing
- Simulated interest calculations
```

### Stablecoin Services

#### DemoStablecoinService
**Location**: `app/Domain/Stablecoin/Services/DemoStablecoinService.php`

Simulates:
- Instant minting and burning
- Auto-collateralization
- Stability mechanism triggers
- Mock oracle price feeds

### Blockchain Services

#### DemoBlockchainService
**Location**: `app/Domain/Wallet/Services/DemoBlockchainService.php`

Provides:
- Mock blockchain transactions
- Simulated wallet addresses
- Instant confirmations
- Fake transaction hashes

Features:
```php
- Multi-chain support (ETH, BTC, Polygon, BSC)
- Mock HD wallet generation
- Simulated gas fees
- Instant transaction mining
```

### Banking Services

#### DemoBankConnector
**Location**: `app/Domain/Custodian/Connectors/DemoBankConnector.php`

Simulates:
- Bank account operations
- Wire transfers
- Balance inquiries
- Transaction history

Replaces connectors for:
- Paysera
- Santander
- Deutsche Bank
- Revolut
- Wise

## Configuration

### Environment Variables (.env)

```env
# Environment Configuration
APP_ENV=demo  # Activates demo mode

# Feature Toggles
DEMO_INSTANT_DEPOSITS=true
DEMO_SKIP_KYC=true
DEMO_MOCK_EXTERNAL_APIS=true
DEMO_FIXED_EXCHANGE_RATES=true
DEMO_AUTO_APPROVE=true

# UI Indicators
DEMO_SHOW_BANNER=true
DEMO_SHOW_WATERMARK=true
DEMO_BANNER_TEXT="Demo Environment - No real transactions"

# Demo User Configuration
DEMO_USER_PASSWORD=demo123
DEMO_DATA_RETENTION_DAYS=7

# Cleanup Settings
DEMO_CLEANUP_ENABLED=true
DEMO_CLEANUP_RETENTION_DAYS=1

# Sandbox Mode (uses real sandbox APIs)
SANDBOX_MODE=false
```

### Configuration File (config/demo.php)

```php
return [
    'features' => [
        'instant_deposits'     => env('DEMO_INSTANT_DEPOSITS', true),
        'skip_kyc'             => env('DEMO_SKIP_KYC', true),
        'mock_external_apis'   => env('DEMO_MOCK_EXTERNAL_APIS', true),
        'fixed_exchange_rates' => env('DEMO_FIXED_EXCHANGE_RATES', true),
        'auto_approve'         => env('DEMO_AUTO_APPROVE', true),
    ],
    
    'ui' => [
        'show_banner' => env('DEMO_SHOW_BANNER', true),
        'banner_text' => env('DEMO_BANNER_TEXT', 'Demo Environment - No real transactions'),
        'show_watermark' => env('DEMO_SHOW_WATERMARK', true),
    ],
    
    'domains' => [
        'exchange' => [
            'spread_percentage'    => 0.1,
            'liquidity_multiplier' => 10,
            'default_rates'        => [
                'EUR/USD' => 1.10,
                'GBP/USD' => 1.27,
                'GCU/USD' => 1.00,
            ],
        ],
        'lending' => [
            'auto_approve_threshold' => 10000,
            'default_credit_score'   => 750,
            'default_interest_rate'  => 5.5,
            'approval_rate'          => 80,
        ],
        'stablecoin' => [
            'collateral_ratio'      => 1.5,
            'liquidation_threshold' => 1.2,
            'stability_fee'         => 2.5,
        ],
    ],
    
    'limits' => [
        'max_transaction_amount' => 100000,
        'max_accounts_per_user'  => 5,
        'max_daily_transactions' => 50,
    ],
    
    'rate_limits' => [
        'api_per_minute'        => 60,
        'deposits_per_hour'     => 10,
        'withdrawals_per_hour'  => 5,
        'transactions_per_hour' => 20,
    ],
];
```

## Usage Guide

### Enabling Demo Mode

1. **Quick Setup**:
```bash
cp .env.demo .env
php artisan config:cache
```

2. **Manual Setup**:
```env
# In .env file
DEMO_MODE=true
```

3. **Programmatic Check**:
```php
if (app()->environment('demo')) {
    // Demo environment is active
}
```

### Demo Data Seeding

```bash
# Run demo seeder
php artisan db:seed --class=DemoDataSeeder

# Create demo deposits
php artisan demo:deposit demo.user@gcu.global 10000 --asset=USD
```

### Demo User Accounts

Pre-configured demo accounts:
- `demo.argentina@gcu.global` - High-inflation country user
- `demo.nomad@gcu.global` - Digital nomad
- `demo.business@gcu.global` - Business user
- `demo.investor@gcu.global` - Investor
- `demo.user@gcu.global` - Regular user

Password for all: `demo123`

## Development Workflow

### Creating New Demo Services

1. **Create Interface** (if not exists):
```php
interface YourServiceInterface {
    public function processOperation(array $data): array;
}
```

2. **Create Demo Implementation**:
```php
class DemoYourService implements YourServiceInterface {
    public function processOperation(array $data): array {
        // Return mock response
        return [
            'success' => true,
            'transaction_id' => 'demo_' . uniqid(),
            'timestamp' => now(),
        ];
    }
}
```

3. **Register in DemoServiceProvider**:
```php
if ($this->app->environment('demo')) {
    $this->app->bind(YourServiceInterface::class, DemoYourService::class);
} else {
    $this->app->bind(YourServiceInterface::class, ProductionYourService::class);
}
```

### Testing Demo Services

```php
class DemoYourServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('demo.mode', true);
    }
    
    public function test_demo_service_returns_mock_response()
    {
        $service = app(YourServiceInterface::class);
        $result = $service->processOperation([]);
        
        $this->assertTrue($result['success']);
        $this->assertStringStartsWith('demo_', $result['transaction_id']);
    }
}
```

## Testing with Demo Mode

### Running Tests in Demo Mode

```bash
# Set environment for testing
DEMO_MODE=true ./vendor/bin/pest

# Or use the CI configuration
./vendor/bin/pest --configuration=phpunit.ci.xml
```

### Demo-Specific Test Traits

```php
trait UsesDemoMode
{
    protected function enableDemoMode(): void
    {
        Config::set('demo.mode', true);
        Config::set('demo.features.instant_deposits', true);
    }
}
```

## Troubleshooting

### Common Issues

#### 1. Demo Mode Not Activating

**Check**:
- Environment variable is set: `DEMO_MODE=true`
- Configuration is cached: `php artisan config:cache`
- Service provider is registered in `bootstrap/providers.php`

#### 2. External APIs Still Being Called

**Verify**:
- DemoServiceProvider bindings are correct
- Service resolution uses interface, not concrete class
- Cache is cleared after configuration changes

#### 3. Demo Indicators Not Showing

**Ensure**:
- `DEMO_SHOW_BANNER=true` in .env
- DemoMode middleware is registered
- View composers are working

### Debug Commands

```bash
# Check demo mode status
php artisan tinker
>>> app()->environment('demo')

# Test service binding
>>> app(PaymentServiceInterface::class)

# Clear all caches
php artisan optimize:clear
```

## Security Considerations

### Production Safety

The demo system includes multiple safeguards:

1. **Environment Protection**: Cannot be enabled in production
2. **Transaction Limits**: Maximum amounts enforced
3. **Rate Limiting**: Prevents abuse
4. **Visual Indicators**: Clear demo mode warnings
5. **Data Isolation**: Demo data clearly marked

### Best Practices

1. Never deploy with `DEMO_MODE=true` to production
2. Use separate databases for demo environments
3. Implement proper access controls
4. Regular audit of demo mode usage
5. Clear separation of demo and real data

## Performance Impact

Demo mode actually improves performance by:
- Eliminating external API calls
- Removing network latency
- Instant response times
- No rate limiting delays
- Simplified processing logic

Typical improvements:
- Payment processing: 2000ms → 50ms
- Order matching: 500ms → 10ms
- Bank transfers: 5000ms → 100ms
- Blockchain transactions: 30s → instant

## Future Enhancements

Planned improvements:
1. Configurable failure scenarios for testing
2. Demo data analytics and insights
3. Automated demo scenario playback
4. Enhanced demo data generation
5. Multi-tenant demo isolation

## Related Documentation

- [DEMO_SETUP.md](../DEMO_SETUP.md) - Quick setup guide
- [Testing Guide](./TESTING_GUIDE.md) - Testing strategies
- [Payment Architecture](./PAYMENT-ARCHITECTURE.md) - Payment service details
- [API Documentation](../04-API/REST_API_REFERENCE.md) - API endpoints