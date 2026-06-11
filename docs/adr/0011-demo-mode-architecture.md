# ADR-005: Demo Mode Architecture

## Status

Accepted

## Context

The platform needs to support multiple operational modes:

1. **Demo Mode** - For demonstrations, learning, testing
2. **Sandbox Mode** - For integration testing with test APIs
3. **Production Mode** - Real transactions with real money

Challenges:
- External service dependencies (payment gateways, exchanges, KYC providers)
- Consistent behavior across environments
- Easy switching between modes
- Realistic demo experience without real transactions

## Decision

We will implement a **Service Switching Pattern** where each external integration has multiple implementations selected at runtime based on environment configuration.

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     Application Layer                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    Service Interface                         ││
│  │              PaymentGatewayInterface                        ││
│  └─────────────────────────────────────────────────────────────┘│
│                            │                                     │
│           ┌────────────────┼────────────────┐                   │
│           │                │                │                    │
│           ▼                ▼                ▼                    │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐             │
│  │    Demo     │  │   Sandbox   │  │ Production  │             │
│  │   Service   │  │   Service   │  │   Service   │             │
│  │  (Mocked)   │  │ (Test API)  │  │ (Real API)  │             │
│  └─────────────┘  └─────────────┘  └─────────────┘             │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Implementation

#### 1. Service Interface

```php
interface PaymentGatewayInterface
{
    public function charge(Money $amount, PaymentMethod $method): ChargeResult;
    public function refund(string $chargeId, Money $amount): RefundResult;
    public function getTransaction(string $transactionId): Transaction;
}
```

#### 2. Production Implementation

```php
class StripePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {}

    public function charge(Money $amount, PaymentMethod $method): ChargeResult
    {
        $charge = $this->stripe->charges->create([
            'amount' => $amount->getMinorAmount(),
            'currency' => $amount->getCurrency(),
            'source' => $method->token,
        ]);

        return new ChargeResult(
            id: $charge->id,
            status: $charge->status,
            amount: $amount,
        );
    }
}
```

#### 3. Demo Implementation

```php
class DemoPaymentGateway implements PaymentGatewayInterface
{
    public function charge(Money $amount, PaymentMethod $method): ChargeResult
    {
        // Simulate processing delay
        usleep(random_int(100000, 500000));

        // Demo card behaviors
        $result = match ($method->lastFour) {
            '0000' => $this->simulateDecline('insufficient_funds'),
            '0001' => $this->simulateDecline('card_expired'),
            '0002' => $this->simulateDecline('fraud_detected'),
            default => $this->simulateSuccess($amount),
        };

        // Log demo transaction
        DemoTransaction::create([
            'type' => 'charge',
            'amount' => $amount->getAmount(),
            'result' => $result->status,
        ]);

        return $result;
    }

    private function simulateSuccess(Money $amount): ChargeResult
    {
        return new ChargeResult(
            id: 'demo_' . Str::uuid(),
            status: 'succeeded',
            amount: $amount,
            isDemo: true,
        );
    }
}
```

#### 4. Service Provider Registration

```php
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return match (config('services.environment_mode')) {
                'demo' => new DemoPaymentGateway(),
                'sandbox' => new StripePaymentGateway(
                    new StripeClient(config('services.stripe.test_key'))
                ),
                'production' => new StripePaymentGateway(
                    new StripeClient(config('services.stripe.live_key'))
                ),
                default => throw new InvalidConfigurationException(
                    'Invalid environment mode'
                ),
            };
        });
    }
}
```

### Configuration

```php
// config/services.php
return [
    'environment_mode' => env('APP_ENV_MODE', 'demo'),

    'demo' => [
        'show_banner' => env('DEMO_SHOW_BANNER', true),
        'reset_schedule' => env('DEMO_RESET_SCHEDULE', 'daily'),
        'seed_accounts' => env('DEMO_SEED_ACCOUNTS', true),
    ],
];

// .env.demo
APP_ENV_MODE=demo
DEMO_SHOW_BANNER=true
DEMO_SEED_ACCOUNTS=true
```

### Demo Behaviors

| Service | Demo Behavior |
|---------|--------------|
| **Payment Gateway** | Always succeeds (except test cards) |
| **Exchange Connector** | Returns simulated prices |
| **KYC Provider** | Auto-approves (with delay) |
| **Email Service** | Logs instead of sending |
| **SMS Provider** | Logs instead of sending |
| **Blockchain** | In-memory transactions |

### Test Card Numbers

| Card Number | Behavior |
|-------------|----------|
| 4242 4242 4242 4242 | Success |
| 4000 0000 0000 0000 | Decline (insufficient funds) |
| 4000 0000 0000 0001 | Decline (expired) |
| 4000 0000 0000 0002 | Decline (fraud) |

### Demo Data Seeding

```php
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Demo accounts
        $accounts = [
            ['email' => 'demo.user@gcu.global', 'balance' => 10000],
            ['email' => 'demo.business@gcu.global', 'balance' => 50000],
            ['email' => 'demo.investor@gcu.global', 'balance' => 100000],
        ];

        foreach ($accounts as $account) {
            $user = User::factory()->create([
                'email' => $account['email'],
                'password' => bcrypt('demo123'),
                'is_demo' => true,
            ]);

            Account::factory()->create([
                'user_id' => $user->id,
                'balance' => $account['balance'],
            ]);
        }

        // Demo transactions
        Transaction::factory()
            ->count(100)
            ->demo()
            ->create();
    }
}
```

## Consequences

### Positive

- **Zero external dependencies** in demo mode
- **Consistent experience** across environments
- **Easy testing** without real transactions
- **Safe learning** environment
- **Predictable** demo behaviors

### Negative

- **Maintenance** of multiple implementations
- **Drift risk** between demo and production
- **Complexity** in service provider logic

### Mitigations

| Challenge | Mitigation |
|-----------|------------|
| Implementation drift | Shared interface tests |
| Maintenance burden | Code generation for demo services |
| Realistic behavior | Simulate delays and edge cases |

## Demo Mode Features

### 1. Visual Indicators

```blade
@if(config('services.demo.show_banner'))
<div class="demo-banner">
    This is a demo environment. No real transactions.
</div>
@endif
```

### 2. Demo Reset

```php
// Artisan command
php artisan demo:reset

class ResetDemoCommand extends Command
{
    public function handle(): void
    {
        // Clear demo data
        DB::table('transactions')->where('is_demo', true)->delete();

        // Re-seed
        $this->call('db:seed', ['--class' => 'DemoDataSeeder']);

        $this->info('Demo environment reset successfully');
    }
}
```

### 3. Demo Logging

```php
class DemoLogger
{
    public function log(string $service, string $action, array $data): void
    {
        DemoLog::create([
            'service' => $service,
            'action' => $action,
            'data' => $data,
            'timestamp' => now(),
        ]);
    }
}
```

## Alternatives Considered

### 1. Feature Flags Only

**Pros**: Simpler, single codebase
**Cons**: Production code cluttered with conditionals

**Rejected because**: Cleaner to have separate implementations

### 2. Separate Applications

**Pros**: Complete isolation
**Cons**: Code duplication, deployment complexity

**Rejected because**: Too much overhead for demonstration needs

### 3. Test Doubles Only

**Pros**: Standard testing approach
**Cons**: Not suitable for user-facing demos

**Rejected because**: Demo mode is for users, not just testing

## References

- [Dependency Injection](https://laravel.com/docs/container)
- [Service Providers](https://laravel.com/docs/providers)
- [Stripe Testing](https://stripe.com/docs/testing)
