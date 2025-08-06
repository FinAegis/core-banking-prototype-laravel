# Payment Services Abstraction Layer

## Overview

The Payment Services Abstraction Layer provides a unified interface for payment processing across different environments (production, sandbox, demo) and payment providers (Stripe, Coinbase, bank transfers). This architecture ensures seamless switching between payment implementations without changing business logic.

## Architecture

### Core Design Pattern

The payment abstraction uses the Strategy pattern with dependency injection to provide environment-specific implementations:

```
PaymentServiceInterface
├── ProductionPaymentService (Real payment processing)
├── SandboxPaymentService (Test environment)
└── DemoPaymentService (Simulated payments)
```

## Interface Definition

### PaymentServiceInterface

```php
namespace App\Services\Payment;

interface PaymentServiceInterface
{
    /**
     * Create a payment intent
     */
    public function createPaymentIntent(array $data): PaymentIntent;
    
    /**
     * Process a payment
     */
    public function processPayment(string $paymentId, array $data): PaymentResult;
    
    /**
     * Capture a payment
     */
    public function capturePayment(string $paymentId): PaymentResult;
    
    /**
     * Refund a payment
     */
    public function refundPayment(string $paymentId, float $amount = null): RefundResult;
    
    /**
     * Get payment status
     */
    public function getPaymentStatus(string $paymentId): PaymentStatus;
    
    /**
     * Create a customer
     */
    public function createCustomer(array $data): Customer;
    
    /**
     * Create a subscription
     */
    public function createSubscription(array $data): Subscription;
    
    /**
     * Handle webhooks
     */
    public function handleWebhook(array $payload, string $signature): WebhookResult;
}
```

## Implementation Classes

### 1. ProductionPaymentService

Handles real payment processing with actual payment providers:

```php
namespace App\Services\Payment;

use Stripe\StripeClient;
use Coinbase\Commerce\Client as CoinbaseClient;

class ProductionPaymentService implements PaymentServiceInterface
{
    private StripeClient $stripe;
    private CoinbaseClient $coinbase;
    private BankTransferService $bankTransfer;
    
    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
        $this->coinbase = CoinbaseClient::init(config('services.coinbase.api_key'));
        $this->bankTransfer = new BankTransferService();
    }
    
    public function createPaymentIntent(array $data): PaymentIntent
    {
        $method = $data['method'] ?? 'card';
        
        return match($method) {
            'card' => $this->createStripePaymentIntent($data),
            'crypto' => $this->createCoinbaseCharge($data),
            'bank_transfer' => $this->createBankTransfer($data),
            default => throw new UnsupportedPaymentMethodException($method),
        };
    }
    
    private function createStripePaymentIntent(array $data): PaymentIntent
    {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $data['amount'] * 100, // Convert to cents
            'currency' => $data['currency'] ?? 'eur',
            'metadata' => $data['metadata'] ?? [],
            'capture_method' => $data['capture'] ?? 'automatic',
        ]);
        
        return new PaymentIntent([
            'id' => $intent->id,
            'amount' => $data['amount'],
            'currency' => $intent->currency,
            'status' => $intent->status,
            'client_secret' => $intent->client_secret,
            'provider' => 'stripe',
        ]);
    }
    
    private function createCoinbaseCharge(array $data): PaymentIntent
    {
        $charge = $this->coinbase->createCharge([
            'name' => $data['description'] ?? 'Payment',
            'description' => $data['description'] ?? '',
            'pricing_type' => 'fixed_price',
            'local_price' => [
                'amount' => $data['amount'],
                'currency' => strtoupper($data['currency'] ?? 'EUR'),
            ],
            'metadata' => $data['metadata'] ?? [],
        ]);
        
        return new PaymentIntent([
            'id' => $charge->id,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'status' => 'pending',
            'payment_url' => $charge->hosted_url,
            'provider' => 'coinbase',
        ]);
    }
    
    public function handleWebhook(array $payload, string $signature): WebhookResult
    {
        // Verify webhook signature
        $provider = $this->detectWebhookProvider($payload);
        
        return match($provider) {
            'stripe' => $this->handleStripeWebhook($payload, $signature),
            'coinbase' => $this->handleCoinbaseWebhook($payload, $signature),
            default => throw new UnknownWebhookException(),
        };
    }
}
```

### 2. SandboxPaymentService

Connects to payment provider sandbox/test environments:

```php
namespace App\Services\Payment;

class SandboxPaymentService implements PaymentServiceInterface
{
    private StripeClient $stripe;
    
    public function __construct()
    {
        // Use test keys
        $this->stripe = new StripeClient(config('services.stripe.test_secret'));
    }
    
    public function createPaymentIntent(array $data): PaymentIntent
    {
        // Use test mode with predictable test cards
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $data['amount'] * 100,
            'currency' => $data['currency'] ?? 'eur',
            'metadata' => array_merge($data['metadata'] ?? [], [
                'environment' => 'sandbox',
                'test_mode' => true,
            ]),
        ]);
        
        return new PaymentIntent([
            'id' => $intent->id,
            'amount' => $data['amount'],
            'currency' => $intent->currency,
            'status' => $intent->status,
            'client_secret' => $intent->client_secret,
            'provider' => 'stripe_sandbox',
            'test_mode' => true,
        ]);
    }
    
    public function processPayment(string $paymentId, array $data): PaymentResult
    {
        // Use test card behaviors
        $testCard = $data['card_number'] ?? '';
        
        // Stripe test cards with predictable outcomes
        $outcomes = [
            '4242424242424242' => 'success',
            '4000000000000002' => 'declined',
            '4000000000009995' => 'insufficient_funds',
            '4000000000009987' => 'lost_card',
        ];
        
        $outcome = $outcomes[$testCard] ?? 'success';
        
        if ($outcome === 'success') {
            return new PaymentResult([
                'success' => true,
                'payment_id' => $paymentId,
                'status' => 'succeeded',
            ]);
        }
        
        return new PaymentResult([
            'success' => false,
            'payment_id' => $paymentId,
            'status' => 'failed',
            'error' => $outcome,
        ]);
    }
}
```

### 3. DemoPaymentService

Provides fully simulated payments without external dependencies:

```php
namespace App\Services\Payment;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class DemoPaymentService implements PaymentServiceInterface
{
    private array $demoScenarios = [
        'success' => ['rate' => 0.9, 'delay' => 1],
        'decline' => ['rate' => 0.05, 'delay' => 1],
        'timeout' => ['rate' => 0.03, 'delay' => 10],
        'error' => ['rate' => 0.02, 'delay' => 0],
    ];
    
    public function createPaymentIntent(array $data): PaymentIntent
    {
        $intentId = 'demo_pi_' . Str::random(16);
        
        // Store in cache for demo persistence
        $intent = [
            'id' => $intentId,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'eur',
            'status' => 'requires_payment_method',
            'created_at' => now(),
            'metadata' => $data['metadata'] ?? [],
        ];
        
        Cache::put("demo_payment_{$intentId}", $intent, 3600);
        
        return new PaymentIntent(array_merge($intent, [
            'client_secret' => $intentId . '_secret_' . Str::random(16),
            'provider' => 'demo',
        ]));
    }
    
    public function processPayment(string $paymentId, array $data): PaymentResult
    {
        $intent = Cache::get("demo_payment_{$paymentId}");
        
        if (!$intent) {
            return new PaymentResult([
                'success' => false,
                'error' => 'Payment intent not found',
            ]);
        }
        
        // Simulate processing delay
        $scenario = $this->selectScenario($data);
        sleep($scenario['delay']);
        
        if ($scenario['type'] === 'success') {
            $intent['status'] = 'succeeded';
            $intent['paid_at'] = now();
            
            Cache::put("demo_payment_{$paymentId}", $intent, 3600);
            
            return new PaymentResult([
                'success' => true,
                'payment_id' => $paymentId,
                'status' => 'succeeded',
                'paid_at' => $intent['paid_at'],
            ]);
        }
        
        $intent['status'] = 'failed';
        $intent['error'] = $scenario['error'] ?? 'Payment declined';
        
        Cache::put("demo_payment_{$paymentId}", $intent, 3600);
        
        return new PaymentResult([
            'success' => false,
            'payment_id' => $paymentId,
            'status' => 'failed',
            'error' => $intent['error'],
        ]);
    }
    
    private function selectScenario(array $data): array
    {
        // Allow forcing specific scenarios for testing
        if (isset($data['demo_scenario'])) {
            return [
                'type' => $data['demo_scenario'],
                'delay' => $this->demoScenarios[$data['demo_scenario']]['delay'] ?? 1,
            ];
        }
        
        // Random scenario based on configured rates
        $random = mt_rand(1, 100) / 100;
        
        if ($random <= 0.9) {
            return ['type' => 'success', 'delay' => 1];
        } elseif ($random <= 0.95) {
            return ['type' => 'decline', 'delay' => 1, 'error' => 'Card declined'];
        } elseif ($random <= 0.98) {
            return ['type' => 'timeout', 'delay' => 10, 'error' => 'Processing timeout'];
        } else {
            return ['type' => 'error', 'delay' => 0, 'error' => 'System error'];
        }
    }
    
    public function refundPayment(string $paymentId, float $amount = null): RefundResult
    {
        $payment = Cache::get("demo_payment_{$paymentId}");
        
        if (!$payment || $payment['status'] !== 'succeeded') {
            return new RefundResult([
                'success' => false,
                'error' => 'Payment cannot be refunded',
            ]);
        }
        
        $refundAmount = $amount ?? $payment['amount'];
        $refundId = 'demo_rf_' . Str::random(16);
        
        $refund = [
            'id' => $refundId,
            'payment_id' => $paymentId,
            'amount' => $refundAmount,
            'status' => 'succeeded',
            'created_at' => now(),
        ];
        
        Cache::put("demo_refund_{$refundId}", $refund, 3600);
        
        return new RefundResult([
            'success' => true,
            'refund_id' => $refundId,
            'amount' => $refundAmount,
            'status' => 'succeeded',
        ]);
    }
    
    public function handleWebhook(array $payload, string $signature): WebhookResult
    {
        // Simulate webhook processing
        return new WebhookResult([
            'success' => true,
            'event_type' => $payload['type'] ?? 'demo.event',
            'processed' => true,
        ]);
    }
}
```

## Service Registration

### Service Provider

```php
namespace App\Providers;

use App\Services\Payment\PaymentServiceInterface;
use App\Services\Payment\ProductionPaymentService;
use App\Services\Payment\SandboxPaymentService;
use App\Services\Payment\DemoPaymentService;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentServiceInterface::class, function ($app) {
            $mode = config('services.environment_mode', 'production');
            
            return match($mode) {
                'demo' => new DemoPaymentService(),
                'sandbox' => new SandboxPaymentService(),
                'production' => new ProductionPaymentService(),
                default => throw new InvalidEnvironmentException($mode),
            };
        });
    }
    
    public function boot(): void
    {
        // Register payment routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/payment.php');
        
        // Register payment views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views/payment', 'payment');
        
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/payment.php' => config_path('payment.php'),
        ], 'payment-config');
    }
}
```

## Usage Examples

### Controller Implementation

```php
namespace App\Http\Controllers\Api;

use App\Services\Payment\PaymentServiceInterface;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentServiceInterface $paymentService
    ) {}
    
    public function createPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'method' => 'required|in:card,crypto,bank_transfer',
            'description' => 'string|max:255',
        ]);
        
        try {
            $intent = $this->paymentService->createPaymentIntent($validated);
            
            return response()->json([
                'success' => true,
                'payment_intent' => $intent->toArray(),
            ]);
        } catch (PaymentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
    
    public function processPayment(string $paymentId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => 'required|string',
        ]);
        
        $result = $this->paymentService->processPayment($paymentId, $validated);
        
        if ($result->isSuccessful()) {
            // Update order status, send confirmation email, etc.
            event(new PaymentProcessed($result));
        }
        
        return response()->json($result->toArray());
    }
}
```

### Event Handling

```php
namespace App\Listeners;

use App\Events\PaymentProcessed;
use App\Services\Payment\PaymentServiceInterface;

class PaymentEventSubscriber
{
    public function __construct(
        private PaymentServiceInterface $paymentService
    ) {}
    
    public function handlePaymentProcessed(PaymentProcessed $event): void
    {
        $payment = $event->getPaymentResult();
        
        if ($payment->isSuccessful()) {
            // Update account balance
            $this->updateAccountBalance($payment);
            
            // Send confirmation
            $this->sendPaymentConfirmation($payment);
            
            // Log for audit
            $this->logPaymentSuccess($payment);
        } else {
            // Handle failure
            $this->handlePaymentFailure($payment);
        }
    }
}
```

## Testing

### Unit Tests

```php
namespace Tests\Unit\Services\Payment;

use Tests\TestCase;
use App\Services\Payment\DemoPaymentService;
use App\Services\Payment\PaymentServiceInterface;

class PaymentServiceTest extends TestCase
{
    public function test_payment_service_interface_binding()
    {
        config(['services.environment_mode' => 'demo']);
        
        $service = app(PaymentServiceInterface::class);
        
        $this->assertInstanceOf(DemoPaymentService::class, $service);
    }
    
    public function test_create_payment_intent()
    {
        $service = new DemoPaymentService();
        
        $intent = $service->createPaymentIntent([
            'amount' => 100.50,
            'currency' => 'eur',
            'method' => 'card',
        ]);
        
        $this->assertNotNull($intent->id);
        $this->assertEquals(100.50, $intent->amount);
        $this->assertEquals('eur', $intent->currency);
        $this->assertEquals('requires_payment_method', $intent->status);
    }
    
    public function test_process_payment_success()
    {
        $service = new DemoPaymentService();
        
        $intent = $service->createPaymentIntent(['amount' => 100]);
        
        $result = $service->processPayment($intent->id, [
            'demo_scenario' => 'success',
        ]);
        
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('succeeded', $result->status);
    }
}
```

### Integration Tests

```php
namespace Tests\Feature\Payment;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_complete_payment_flow()
    {
        config(['services.environment_mode' => 'demo']);
        
        $user = User::factory()->create();
        
        // Create payment intent
        $response = $this->actingAs($user)
            ->postJson('/api/payments', [
                'amount' => 100,
                'currency' => 'eur',
                'method' => 'card',
            ]);
        
        $response->assertSuccessful();
        $paymentId = $response->json('payment_intent.id');
        
        // Process payment
        $response = $this->actingAs($user)
            ->postJson("/api/payments/{$paymentId}/process", [
                'payment_method' => 'pm_card_visa',
            ]);
        
        $response->assertSuccessful();
        $this->assertEquals('succeeded', $response->json('status'));
    }
}
```

## Configuration

### Environment Configuration

```env
# Payment environment mode
APP_ENV_MODE=production  # production|sandbox|demo

# Stripe configuration
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_TEST_KEY=pk_test_...
STRIPE_TEST_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Coinbase Commerce
COINBASE_API_KEY=...
COINBASE_WEBHOOK_SECRET=...

# Demo configuration
DEMO_PAYMENT_SUCCESS_RATE=0.9
DEMO_PAYMENT_DELAY=1
```

### Configuration File

```php
// config/payment.php
return [
    'default' => env('PAYMENT_GATEWAY', 'stripe'),
    
    'environment_mode' => env('APP_ENV_MODE', 'production'),
    
    'gateways' => [
        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'api_version' => '2023-10-16',
        ],
        
        'coinbase' => [
            'api_key' => env('COINBASE_API_KEY'),
            'webhook_secret' => env('COINBASE_WEBHOOK_SECRET'),
        ],
    ],
    
    'demo' => [
        'success_rate' => env('DEMO_PAYMENT_SUCCESS_RATE', 0.9),
        'processing_delay' => env('DEMO_PAYMENT_DELAY', 1),
        'available_methods' => ['card', 'crypto', 'bank_transfer'],
    ],
    
    'webhooks' => [
        'route' => 'api/webhooks/payment',
        'middleware' => ['api'],
    ],
];
```

## Security Considerations

1. **Webhook Verification**: Always verify webhook signatures
2. **PCI Compliance**: Never store card details directly
3. **API Key Security**: Use environment variables for sensitive keys
4. **Rate Limiting**: Implement rate limiting for payment endpoints
5. **Idempotency**: Use idempotency keys to prevent duplicate charges
6. **Audit Logging**: Log all payment operations for compliance
7. **Encryption**: Encrypt sensitive payment data at rest

## Migration Guide

### Migrating from Direct Implementation

```php
// Before: Direct Stripe usage
$stripe = new \Stripe\StripeClient($key);
$intent = $stripe->paymentIntents->create([...]);

// After: Using abstraction
$paymentService = app(PaymentServiceInterface::class);
$intent = $paymentService->createPaymentIntent([...]);
```

### Environment Switching

```php
// Dynamically switch environments
app()->bind(PaymentServiceInterface::class, function () {
    $mode = request()->header('X-Payment-Mode', 'production');
    
    return match($mode) {
        'demo' => new DemoPaymentService(),
        'sandbox' => new SandboxPaymentService(),
        default => new ProductionPaymentService(),
    };
});
```