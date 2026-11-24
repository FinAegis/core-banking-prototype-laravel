# Demo Environment Architecture

## Overview

The demo environment lets FinAegis run without its external services. It is meant for demonstrations, development and testing. It was introduced in PR #201 and PR #202. Demo services return instant, simulated responses in place of the external integrations. `APP_ENV=demo` switches it on.

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

1. Demo services replace the external API calls with simulated responses.
2. Responses are instant, without network delays.
3. Behaviour is predictable, so demonstrations are reliable.
4. The main flows work in demo mode. Features without a demo service still need their external service or stay unavailable.
5. One setting, `APP_ENV=demo`, switches between demo and production behaviour.

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

The provider binds the demo implementations when `APP_ENV` is `demo`. It also registers the demo bank connectors and the view composers for the demo indicators.

```php
// Automatic service switching based on environment
if ($this->app->environment('demo')) {
    $this->app->bind(PaymentServiceInterface::class, DemoPaymentService::class);
    $this->app->bind(ExchangeService::class, DemoExchangeService::class);
    $this->app->bind('lending.service', DemoLendingService::class);
    $this->app->bind('stablecoin.service', DemoStablecoinService::class);
    // ... other bindings
}
```

### 2. DemoMode Middleware

**Location**: `app/Http/Middleware/DemoMode.php`

The middleware adds the demo banner and watermark to the web pages and passes the demo configuration to the views. It also blocks sensitive operations, such as incoming webhooks. `bootstrap/app.php` adds it to the web group when `APP_ENV` is `demo`.

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

The service simulates Stripe deposits, bank withdrawals and Open Banking deposits with instant confirmation. It records the deposit through the transaction aggregate and returns mock payment intent IDs that start with `demo_pi_`.

#### DemoPaymentGatewayService
**Location**: `app/Domain/Payment/Services/DemoPaymentGatewayService.php`

The service returns mock Stripe payment intents with simulated client secrets and demo metadata, and confirms payments at once.

### Exchange Services

#### DemoExchangeService
**Location**: `app/Domain/Exchange/Services/DemoExchangeService.php`

The service matches orders at once and simulates market depth, spreads and trading pairs. Trading is off in production through `TRADING_ENABLED=false`.

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

The service approves loans up to a configurable threshold and disburses them at once. It simulates credit scores, interest and repayment schedules.

### Stablecoin Services

#### DemoStablecoinService
**Location**: `app/Domain/Stablecoin/Services/DemoStablecoinService.php`

The service simulates instant minting and burning, collateralisation, the stability mechanisms and oracle price feeds.

### Blockchain Services

#### DemoBlockchainService
**Location**: `app/Domain/Wallet/Services/DemoBlockchainService.php`

The service returns mock blockchain transactions, wallet addresses and transaction hashes for Ethereum, Bitcoin, Polygon and BSC. It simulates HD wallet generation and gas fees and confirms transactions at once. The non-custodial wallet send in the Zelta app does not use it.

### Banking Services

#### DemoBankConnector
**Location**: `app/Domain/Custodian/Connectors/DemoBankConnector.php`

The connector simulates bank account operations, wire transfers, balance inquiries and transaction history.

It replaces the bank connector adapters (e.g. Paysera, Santander, Deutsche Bank APIs). Integration adapters for third-party APIs; no partnership or endorsement implied.

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
APP_ENV=demo
```

There is no `DEMO_MODE` variable. See [DEMO-MODE-REFACTORING.md](DEMO-MODE-REFACTORING.md).

3. **Programmatic Check**:
```php
if (app()->environment('demo')) {
    // Demo environment is active
}
```

### Demo Data Seeding

```bash
# Run the standard seeders and all demo seeders
php artisan demo:populate

# Or run only the demo user seeder
php artisan db:seed --class=DemoDataSeeder

# Create demo deposits
php artisan demo:deposit demo.user@gcu.global 10000 --asset=USD
```

### Demo User Accounts

`php artisan migrate --seed` does not create these users. `php artisan demo:populate` does:
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
        // Tests run with APP_ENV=testing, so bind the demo service directly
        $this->app->bind(YourServiceInterface::class, DemoYourService::class);
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

Tests run with `APP_ENV=testing`, so `DemoServiceProvider` does not bind the demo services. Bind the demo service in the test, or test the demo class directly.

```bash
# Run the demo service tests
./vendor/bin/pest --filter=Demo

# Or use the CI configuration
./vendor/bin/pest --configuration=phpunit.ci.xml
```

### Demo-Specific Test Traits

```php
trait UsesDemoMode
{
    protected function enableDemoMode(): void
    {
        $this->app->bind(PaymentServiceInterface::class, DemoPaymentService::class);
        Config::set('demo.features.instant_deposits', true);
    }
}
```

## Troubleshooting

### Common Issues

#### 1. Demo Mode Not Activating

Check that `APP_ENV=demo` is set in `.env`. Then rebuild the configuration cache with `php artisan config:cache`. Also check that `DemoServiceProvider` is registered in `bootstrap/providers.php`.

#### 2. External APIs Still Being Called

Check the bindings in `DemoServiceProvider`. Make sure the code resolves the interface, not the concrete class. Clear the cache after configuration changes.

#### 3. Demo Indicators Not Showing

Check that `DEMO_SHOW_BANNER=true` is set in `.env`, that the `DemoMode` middleware is registered and that the view composers run.

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

The demo system has several safeguards:

1. Demo services are bound only when `APP_ENV=demo`. `ops:verify-env` fails a production deploy when `demo.mode` or other demo flags are on.
2. Demo amounts have maximum limits.
3. Rate limiting prevents abuse.
4. The banner and watermark make demo mode visible.
5. Demo data is clearly marked.

### Best Practices

1. Never deploy production with `APP_ENV=demo`.
2. Use separate databases for demo environments.
3. Set up proper access controls.
4. Audit the use of demo mode regularly.
5. Keep demo data and real data clearly apart.

## Performance Impact

Demo mode is faster than normal operation. It makes no external API calls, so there is no network latency and no rate limiting by the providers. The demo services also use simpler processing logic and answer at once.

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

- [DEMO_SETUP.md](../../DEMO_SETUP.md) is the quick setup guide.
- The [Testing Guide](./TESTING_GUIDE.md) explains the testing strategies.
- [Payment Abstraction](../03-FEATURES/PAYMENT-ABSTRACTION.md) explains the payment services.
- The [REST API Reference](../04-API/REST_API_REFERENCE.md) lists the API endpoints.
