# ADR-005: Demo Mode Architecture

## Status

Accepted

> **Implementation status (reviewed for v7.18.1).** The decision is built, but the names differ from the sketches below. The mode follows `APP_ENV`. `APP_ENV=demo` turns on demo mode, and `SANDBOX_MODE=true` turns on the sandbox settings. There is no `services.environment_mode` setting. `App\Providers\DemoServiceProvider` binds the demo implementations, such as `DemoPaymentService`, `DemoPaymentGatewayService`, `DemoExchangeService`, `DemoLendingService`, `DemoStablecoinService` and `DemoBankConnector`. The demo settings live in `config/demo.php`. There is no `demo:reset` command. `php artisan demo:populate` loads the demo data, and `php artisan demo:cleanup` removes old demo data every day at 03:00. The demo users are demo.argentina@, demo.nomad@, demo.business@, demo.investor@ and demo.user@gcu.global, all with the password `demo123`. The demo payment gateway does not simulate declines by card number; demo charges succeed.

## Context

FinAegis has to run in several modes:

1. **Demo mode** is for demonstrations, learning and testing.
2. **Sandbox mode** is for integration testing against providers' test APIs.
3. **Production mode** connects real providers. FinAegis is reference software, so a production deployment is the operator's own responsibility.

External services such as payment gateways, exchanges and identity check providers make this hard. The platform should behave the same way in every environment, and switching modes should be easy. The demo should feel realistic without moving real money.

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
// config/demo.php (excerpt)
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
    ],
    'cleanup' => [
        'enabled'        => env('DEMO_CLEANUP_ENABLED', true),
        'retention_days' => env('DEMO_CLEANUP_RETENTION_DAYS', 1),
    ],
    'sandbox' => [
        'enabled' => env('SANDBOX_MODE', false),
    ],
];

// .env for a demo deployment
APP_ENV=demo
DEMO_SHOW_BANNER=true
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

The decision planned test cards for declines. They are not built yet. The demo gateway returns a successful charge for every card and reports the card as `4242`.

| Card Number | Planned behavior |
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
        // Demo accounts (the real seeder also creates
        // demo.argentina@gcu.global and demo.nomad@gcu.global)
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

Demo mode needs no external services. The experience is the same in every environment. People can test and learn without real transactions, and the demo behaves predictably.

### Negative

Several implementations of each service must be maintained. Demo and production code can drift apart, and the service provider logic gets more complex.

### Mitigations

| Challenge | Mitigation |
|-----------|------------|
| Implementation drift | Shared interface tests |
| Maintenance burden | Code generation for demo services |
| Realistic behavior | Simulate delays and edge cases |

## Demo Mode Features

### 1. Visual Indicators

```blade
@if(config('demo.ui.show_banner'))
<div class="demo-banner">
    This is a demo environment. No real transactions.
</div>
@endif
```

### 2. Demo Reset

The planned `demo:reset` command was built as two commands. `php artisan demo:cleanup` removes old demo data, and `php artisan demo:populate` loads it again. The sketch shows the idea:

```php
// Artisan command (planned name)
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
