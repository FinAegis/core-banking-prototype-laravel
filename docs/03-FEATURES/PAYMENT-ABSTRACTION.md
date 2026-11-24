# Payment Services Abstraction Layer

## Overview

The payment services abstraction is one interface with three implementations.
It lets the web wallet's deposit and withdrawal code run in production, in a provider sandbox or in the demo without changes.
The container picks the implementation from the environment.

The interface covers three operations.
They are a card deposit through Stripe, a withdrawal to a bank account and an Open Banking deposit.

*Named third-party providers: Integration adapters for third-party APIs; no partnership or endorsement implied.*

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

## Architecture

### Design

The layer uses the strategy pattern with dependency injection.

```
App\Domain\Payment\Contracts\PaymentServiceInterface
├── ProductionPaymentService (starts the payment workflows)
├── SandboxPaymentService    (same workflows, provider test keys)
└── DemoPaymentService       (records simulated payments directly)
```

All three classes live in `app/Domain/Payment/Services/`.

## Interface Definition

```php
namespace App\Domain\Payment\Contracts;

interface PaymentServiceInterface
{
    /**
     * Process a Stripe deposit. Returns a transaction or payment reference.
     *
     * @param array{account_uuid: string, amount: int, currency: string,
     *     reference: string, external_reference: string, payment_method: string,
     *     payment_method_type: string, metadata?: array} $data
     */
    public function processStripeDeposit(array $data): string;

    /**
     * Process a bank withdrawal.
     *
     * @param array{account_uuid: string, amount: int, currency: string,
     *     reference: string, bank_name: string, account_number: string,
     *     account_holder_name: string, routing_number?: string|null,
     *     iban?: string|null, swift?: string|null, metadata?: array} $data
     * @return array{reference: string, status: string}
     */
    public function processBankWithdrawal(array $data): array;

    /**
     * Process an Open Banking deposit.
     *
     * @param array{account_uuid: string, amount: int, currency: string,
     *     reference: string, bank_name: string, metadata?: array} $data
     */
    public function processOpenBankingDeposit(array $data): string;
}
```

Amounts are integers in minor units, for example cents.

## Implementation Classes

### 1. ProductionPaymentService

`ProductionPaymentService` starts a Laravel Workflow for each operation.

| Method | Workflow |
|--------|----------|
| `processStripeDeposit()` | `ProcessStripeDepositWorkflow` |
| `processBankWithdrawal()` | `ProcessBankWithdrawalWorkflow` |
| `processOpenBankingDeposit()` | `ProcessOpenBankingDepositWorkflow` |

The workflows run asynchronously, so the methods return a reference rather than a final result.
The bank payout step, `InitiateBankTransferActivity`, is a stub.
It logs the transfer and returns a generated id, but it does not call a bank.
A withdrawal therefore only debits the ledger.

### 2. SandboxPaymentService

`SandboxPaymentService` runs the same workflows as the production service.
It adds sandbox metadata and logs each call.
It is meant for testing against the providers' own test environments.

### 3. DemoPaymentService

`DemoPaymentService` does not call any provider.
It writes the deposit or withdrawal straight to the transaction aggregate and returns a demo reference.
The references start with `demo_pi_`, `demo_wd_` or `demo_ob_`.
A demo withdrawal always reports the status `completed`.
Its constructor throws a `RuntimeException` in production.

## Service Registration

`App\Providers\DemoServiceProvider` binds the interface.

```php
if ($this->app->environment('demo')) {
    $this->app->bind(PaymentServiceInterface::class, DemoPaymentService::class);
    $this->app->bind(PayseraDepositServiceInterface::class, DemoPayseraDepositService::class);
} elseif (config('demo.sandbox.enabled')) {
    $this->app->bind(PaymentServiceInterface::class, SandboxPaymentService::class);
    $this->app->bind(PayseraDepositServiceInterface::class, PayseraDepositService::class);
} else {
    $this->app->bind(PaymentServiceInterface::class, ProductionPaymentService::class);
    $this->app->bind(PayseraDepositServiceInterface::class, PayseraDepositService::class);
}
```

In the demo environment the provider also replaces `PaymentGatewayService` with `DemoPaymentGatewayService`.

## Where the interface is used

`App\Domain\Payment\Services\PaymentGatewayService` uses it for web card deposits and for bank withdrawals from the web wallet. `DepositController` and `WithdrawalController` call the gateway.
`App\Http\Controllers\OpenBankingDepositController` uses it for Open Banking deposits.
The Paysera deposit services use it to record a completed Paysera deposit.
`PaymentProcessingService` uses it to route a deposit or withdrawal by payment method.
`php artisan demo:deposit` uses it to add simulated deposits.

### HyperSwitch card deposits

HyperSwitch is an optional card payment orchestration path. It is off by default.
When `HYPERSWITCH_ENABLED=true`, `DepositController` sends card deposits through `HyperSwitchPaymentService::startDeposit()` instead of Stripe.
The HyperSwitch webhook then credits the account.

## Feature switches

The web wallet's money-moving pages have their own switches.
The web wallet withdrawals and the simulated, Open Banking, bank, manual and crypto deposit pages need `LEDGER_DIRECT_FUNDING_ENABLED`.
It is on by default only for `APP_ENV` `local`, `testing` and `demo`.
Web card deposits have their own switch, `WEB_CARD_DEPOSITS_ENABLED`.
When it is unset, card deposits are on for `local`, `testing` and `demo`, and elsewhere only with `HYPERSWITCH_ENABLED=true`.

## Testing

Unit tests cover each implementation:
- `tests/Unit/Domain/Payment/Services/DemoPaymentServiceTest.php`
- `tests/Unit/Domain/Payment/Services/SandboxPaymentServiceTest.php`

```php
// Swap the binding in a test
$this->app->bind(PaymentServiceInterface::class, DemoPaymentService::class);

$reference = app(PaymentServiceInterface::class)->processStripeDeposit([
    'account_uuid'        => $account->uuid,
    'amount'              => 10000, // 100.00 in cents
    'currency'            => 'USD',
    'reference'           => 'TEST-001',
    'external_reference'  => 'ext-001',
    'payment_method'      => 'card',
    'payment_method_type' => 'card',
]);

expect($reference)->toStartWith('demo_pi_');
```

## Configuration

```env
# Choose the implementation
APP_ENV=demo          # DemoPaymentService
SANDBOX_MODE=true     # SandboxPaymentService (when APP_ENV is not demo)

# Stripe
STRIPE_KEY=pk_...
STRIPE_SECRET=sk_...
STRIPE_WEBHOOK_SECRET=whsec_...

# HyperSwitch (optional, off by default)
HYPERSWITCH_ENABLED=false
```

## Security Considerations

The demo service refuses to run in production.
Keep production and test provider keys separate.
Verify every provider webhook signature before crediting an account.
Do money arithmetic in integer minor units or with `bcmath`, never with floats.
