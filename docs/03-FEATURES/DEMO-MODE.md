# Demo Mode Feature

## Overview

The Demo Mode feature provides a fully functional demonstration environment for the FinAegis Core Banking Platform. It allows users to explore all platform features without connecting to real payment providers, blockchain networks, or financial institutions.

## Purpose

- **Product Demonstrations**: Showcase platform capabilities to potential clients
- **User Training**: Provide a safe environment for learning platform features
- **Development Testing**: Test integrations without using real services
- **Sales Presentations**: Enable interactive demonstrations during sales processes

## Architecture

### Service Layer Abstraction

The demo mode implements a service abstraction pattern that allows seamless switching between production, sandbox, and demo environments:

```php
interface PaymentServiceInterface
{
    public function createPaymentIntent(array $data): array;
    public function processPayment(string $paymentId): bool;
    public function getPaymentStatus(string $paymentId): string;
}

// Production implementation
class ProductionPaymentService implements PaymentServiceInterface
{
    // Real Stripe/payment provider integration
}

// Demo implementation
class DemoPaymentService implements PaymentServiceInterface
{
    // Simulated payments with predictable outcomes
}
```

### Environment Detection

```php
// config/services.php
'environment_mode' => env('APP_ENV_MODE', 'production'),

// Service provider registration
public function register()
{
    $this->app->bind(PaymentServiceInterface::class, function ($app) {
        return match (config('services.environment_mode')) {
            'demo' => new DemoPaymentService(),
            'sandbox' => new SandboxPaymentService(),
            default => new ProductionPaymentService(),
        };
    });
}
```

## Demo Services

### 1. Demo Payment Service

Simulates payment processing with configurable success/failure scenarios:

```php
class DemoPaymentService implements PaymentServiceInterface
{
    private array $simulatedPayments = [];
    
    public function createPaymentIntent(array $data): array
    {
        $paymentId = 'demo_pi_' . Str::random(16);
        
        $this->simulatedPayments[$paymentId] = [
            'id' => $paymentId,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'status' => 'pending',
            'created_at' => now(),
        ];
        
        return $this->simulatedPayments[$paymentId];
    }
    
    public function processPayment(string $paymentId): bool
    {
        // Simulate processing delay
        sleep(1);
        
        // 90% success rate for demo
        $success = rand(1, 10) <= 9;
        
        $this->simulatedPayments[$paymentId]['status'] = 
            $success ? 'succeeded' : 'failed';
            
        return $success;
    }
}
```

### 2. Demo Exchange Service

Provides realistic market data and trading simulations:

```php
class DemoExchangeService implements ExchangeServiceInterface
{
    public function getMarketData(string $pair): array
    {
        // Generate realistic price movements
        $basePrice = $this->getBasePrice($pair);
        $volatility = 0.02; // 2% volatility
        
        return [
            'pair' => $pair,
            'bid' => $basePrice * (1 - rand(0, $volatility * 100) / 10000),
            'ask' => $basePrice * (1 + rand(0, $volatility * 100) / 10000),
            'volume' => rand(1000000, 10000000),
            'change_24h' => rand(-500, 500) / 100,
        ];
    }
    
    public function executeOrder(Order $order): Trade
    {
        // Simulate order matching with slight slippage
        $executionPrice = $order->price * (1 + rand(-10, 10) / 10000);
        
        return Trade::create([
            'order_id' => $order->id,
            'execution_price' => $executionPrice,
            'executed_at' => now(),
            'status' => 'completed',
        ]);
    }
}
```

### 3. Demo Blockchain Service

Simulates blockchain operations without real network calls:

```php
class DemoBlockchainService implements BlockchainServiceInterface
{
    public function sendTransaction(array $params): string
    {
        // Generate demo transaction hash
        return '0xdemo' . hash('sha256', json_encode($params));
    }
    
    public function getTransactionStatus(string $txHash): string
    {
        // Simulate confirmation after delay
        $age = Cache::get("tx_age_{$txHash}", 0);
        
        return match(true) {
            $age < 10 => 'pending',
            $age < 30 => 'confirming',
            default => 'confirmed',
        };
    }
    
    public function getBalance(string $address): string
    {
        // Return demo balance based on address
        return Cache::remember("balance_{$address}", 300, function() {
            return (string) rand(1000, 1000000);
        });
    }
}
```

### 4. Demo Lending Service

Simulates loan lifecycle with automated approvals:

```php
class DemoLendingService implements LendingServiceInterface
{
    public function assessCreditScore(User $user): int
    {
        // Generate consistent demo credit score
        return 300 + (crc32($user->email) % 550);
    }
    
    public function approveLoan(LoanApplication $application): bool
    {
        $creditScore = $this->assessCreditScore($application->user);
        
        // Auto-approve based on credit score
        return $creditScore >= 650;
    }
    
    public function calculateInterestRate(LoanApplication $application): float
    {
        $creditScore = $this->assessCreditScore($application->user);
        
        // Base rate adjusted by credit score
        $baseRate = 5.0;
        $adjustment = (850 - $creditScore) / 100;
        
        return round($baseRate + $adjustment, 2);
    }
}
```

### 5. Demo Stablecoin Service

Provides stablecoin operations with simulated backing:

```php
class DemoStablecoinService implements StablecoinServiceInterface
{
    public function mintTokens(float $amount, string $currency): array
    {
        return [
            'transaction_id' => 'demo_mint_' . Str::random(16),
            'amount' => $amount,
            'currency' => $currency,
            'tokens_minted' => $amount,
            'backing_ratio' => 1.05, // 105% backed
            'timestamp' => now(),
        ];
    }
    
    public function getReserveStatus(): array
    {
        return [
            'total_supply' => 10000000,
            'total_reserves' => 10500000,
            'backing_ratio' => 1.05,
            'reserve_composition' => [
                'cash' => 0.40,
                'bonds' => 0.35,
                'commodities' => 0.25,
            ],
        ];
    }
}
```

## Demo Data Management

### Seeding Demo Data

```bash
# Seed demo data
php artisan db:seed --class=DemoDataSeeder

# Reset demo environment
php artisan demo:reset
```

### Demo Data Seeder

```php
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo users
        $this->createDemoUsers();
        
        // Create demo accounts
        $this->createDemoAccounts();
        
        // Create demo transactions
        $this->createDemoTransactions();
        
        // Create demo market data
        $this->createDemoMarketData();
    }
    
    private function createDemoUsers(): void
    {
        $demoUsers = [
            ['name' => 'John Demo', 'email' => 'john@demo.finaegis.com'],
            ['name' => 'Jane Test', 'email' => 'jane@demo.finaegis.com'],
            ['name' => 'Admin Demo', 'email' => 'admin@demo.finaegis.com'],
        ];
        
        foreach ($demoUsers as $userData) {
            User::factory()->create($userData);
        }
    }
}
```

## Configuration

### Environment Variables

```env
# Enable demo mode
APP_ENV_MODE=demo

# Demo service configuration
DEMO_AUTO_APPROVE=true
DEMO_SUCCESS_RATE=90
DEMO_PROCESSING_DELAY=1
DEMO_MARKET_VOLATILITY=0.02

# Demo data retention
DEMO_DATA_TTL=86400  # 24 hours
DEMO_AUTO_CLEANUP=true
```

### Configuration File

```php
// config/demo.php
return [
    'enabled' => env('APP_ENV_MODE') === 'demo',
    
    'features' => [
        'auto_approve_kyc' => true,
        'auto_approve_loans' => true,
        'instant_deposits' => true,
        'simulated_market_data' => true,
    ],
    
    'limits' => [
        'max_transaction_amount' => 100000,
        'max_loan_amount' => 50000,
        'max_trading_volume' => 1000000,
    ],
    
    'simulation' => [
        'success_rate' => 0.9,
        'processing_delay' => 1,
        'market_volatility' => 0.02,
    ],
];
```

## User Interface

### Demo Mode Indicators

```blade
{{-- resources/views/components/demo-banner.blade.php --}}
@if(config('demo.enabled'))
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
        <p class="font-bold">Demo Mode Active</p>
        <p>You are using a demonstration environment. No real transactions will be processed.</p>
    </div>
@endif
```

### Demo Controls

```blade
{{-- Demo control panel for testing scenarios --}}
@if(config('demo.enabled'))
    <div class="demo-controls">
        <h3>Demo Controls</h3>
        
        <button onclick="simulatePaymentSuccess()">
            Simulate Successful Payment
        </button>
        
        <button onclick="simulatePaymentFailure()">
            Simulate Failed Payment
        </button>
        
        <button onclick="generateMarketVolatility()">
            Generate Market Volatility
        </button>
        
        <button onclick="resetDemoData()">
            Reset Demo Data
        </button>
    </div>
@endif
```

## API Endpoints

### Demo-Specific Endpoints

```php
// routes/api.php
Route::group(['prefix' => 'demo', 'middleware' => 'demo.mode'], function () {
    Route::post('/reset', [DemoController::class, 'reset']);
    Route::post('/simulate/payment', [DemoController::class, 'simulatePayment']);
    Route::post('/simulate/market', [DemoController::class, 'simulateMarket']);
    Route::get('/scenarios', [DemoController::class, 'getScenarios']);
});
```

### Demo Controller

```php
class DemoController extends Controller
{
    public function reset(): JsonResponse
    {
        if (!config('demo.enabled')) {
            abort(403, 'Demo mode not enabled');
        }
        
        Artisan::call('demo:reset');
        
        return response()->json([
            'message' => 'Demo environment reset successfully',
        ]);
    }
    
    public function simulatePayment(Request $request): JsonResponse
    {
        $scenario = $request->input('scenario', 'success');
        
        $result = app(DemoPaymentService::class)
            ->simulateScenario($scenario);
            
        return response()->json($result);
    }
}
```

## Security Considerations

### Access Control

```php
class DemoModeMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!config('demo.enabled')) {
            abort(403, 'Demo mode not available');
        }
        
        // Add demo headers
        $response = $next($request);
        $response->headers->set('X-Demo-Mode', 'true');
        
        return $response;
    }
}
```

### Data Isolation

```php
class DemoDataScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (config('demo.enabled')) {
            $builder->where('is_demo', true);
        }
    }
}
```

## Testing

### Demo Mode Tests

```php
class DemoModeTest extends TestCase
{
    public function test_demo_payment_service_returns_predictable_results()
    {
        config(['services.environment_mode' => 'demo']);
        
        $service = app(PaymentServiceInterface::class);
        $payment = $service->createPaymentIntent(['amount' => 100]);
        
        $this->assertStringStartsWith('demo_pi_', $payment['id']);
        $this->assertEquals('pending', $payment['status']);
    }
    
    public function test_demo_mode_prevents_real_transactions()
    {
        config(['demo.enabled' => true]);
        
        $response = $this->post('/api/payments', [
            'amount' => 100,
            'method' => 'card',
        ]);
        
        $response->assertHeader('X-Demo-Mode', 'true');
    }
}
```

## Monitoring

### Demo Usage Analytics

```php
class DemoAnalytics
{
    public function trackUsage(string $feature, User $user = null): void
    {
        DemoUsage::create([
            'feature' => $feature,
            'user_id' => $user?->id,
            'session_id' => session()->getId(),
            'timestamp' => now(),
        ]);
    }
    
    public function getUsageReport(): array
    {
        return [
            'total_sessions' => DemoUsage::distinct('session_id')->count(),
            'popular_features' => DemoUsage::groupBy('feature')
                ->selectRaw('feature, count(*) as usage_count')
                ->orderByDesc('usage_count')
                ->limit(10)
                ->get(),
            'active_users' => DemoUsage::where('timestamp', '>', now()->subHour())
                ->distinct('session_id')
                ->count(),
        ];
    }
}
```

## Best Practices

1. **Clear Indication**: Always clearly indicate when demo mode is active
2. **Realistic Data**: Generate realistic but clearly fake data
3. **Predictable Behavior**: Ensure demo behavior is consistent and predictable
4. **Time Limits**: Implement automatic cleanup of old demo data
5. **Rate Limiting**: Apply rate limits to prevent demo abuse
6. **Documentation**: Provide clear documentation for demo scenarios
7. **Isolation**: Keep demo data completely isolated from production

## Troubleshooting

### Common Issues

1. **Demo services not loading**
   - Check `APP_ENV_MODE` environment variable
   - Verify service provider registration
   - Clear configuration cache

2. **Real services being called**
   - Verify environment detection logic
   - Check service binding in container
   - Review middleware configuration

3. **Demo data persistence**
   - Check cleanup schedules
   - Verify data isolation scopes
   - Review cache configuration