# Demo Mode Feature

## Overview

Demo mode is the environment that runs the public FinAegis demonstration. It is switched on by `APP_ENV=demo`. In this mode the platform replaces payment providers, bank connectors and several domain services with simulated versions. People can try the features without touching real payment providers, blockchain networks or banks.

All balances, tokens and transactions in the demo are simulated and have no real value.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

## Purpose

Demo mode exists for product walkthroughs, training and integration work.
It lets a developer or reviewer try the platform without credentials for third-party services.
It also keeps demo traffic away from any real provider.

## How the platform decides it is a demo

Demo mode is tied to the Laravel environment name, not to a separate flag.
`App\Providers\DemoServiceProvider` checks `app()->environment('demo')` and swaps the bindings below.
A second, optional mode is the sandbox mode. It is switched on with `SANDBOX_MODE=true`.
In sandbox mode payments go through `SandboxPaymentService`, which calls the providers' own test APIs.
Blockchain calls still use the simulated `DemoBlockchainService` in sandbox mode.

```php
// app/Providers/DemoServiceProvider.php (simplified)
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

The payment, payment gateway, exchange, lending and stablecoin demo services refuse to run in production.
Each of them throws a `RuntimeException` from its constructor when `app()->environment('production')` is true.
The demo bank connector is never registered in production.

## Demo services

| Service | Class | What it simulates |
|---------|-------|-------------------|
| Payments | `App\Domain\Payment\Services\DemoPaymentService` | Card deposits, bank withdrawals and Open Banking deposits, recorded through the transaction aggregate without calling a provider |
| Payment gateway | `App\Domain\Payment\Services\DemoPaymentGatewayService` | The web wallet's deposit and withdrawal gateway |
| Paysera deposits | `App\Domain\Payment\Services\DemoPayseraDepositService` | The Paysera deposit flow |
| Exchange | `App\Domain\Exchange\Services\DemoExchangeService` | Order placement, cancellation, order book and market data |
| Lending | `App\Domain\Lending\Services\DemoLendingService` | Loan applications, approval and repayments (bound as `lending.service`) |
| Stablecoin | `App\Domain\Stablecoin\Services\DemoStablecoinService` | Collateral positions, mint, burn and liquidation checks (bound as `stablecoin.service`) |
| Blockchain | `App\Domain\Wallet\Services\DemoBlockchainService` | Addresses, balances, gas estimates and transaction status without network calls (chosen by `BlockchainConnectorFactory` in the demo environment or sandbox mode) |
| Bank custodian | `App\Domain\Custodian\Connectors\DemoBankConnector` | A bank that accepts any account and always succeeds |

The demo bank connector is registered as `demo_bank`.
It is registered in the `demo` environment, and in `local` or `testing` when `SANDBOX_MODE=true`.
It is never registered in any other environment.
In the demo environment it also replaces the `paysera`, `santander` and `deutsche_bank` custodians while `demo.features.mock_external_apis` is on.

Other domains have their own demo adapters, for example `DemoCardIssuerAdapter`, `DemoBridgeAdapter`, `DemoSwapConnector`, `DemoHsmProvider`, `DemoZkProver` and the relayer's `DemoBundlerService` and `DemoPaymasterService`.
They are selected through each domain's own configuration.

## Demo data

`php artisan migrate --seed` creates the reference data but no demo users.
`php artisan demo:populate` runs the standard seeders, then `DemoDataSeeder`, `SmsDemoSeeder` and `MobileRewardsDemoSeeder`.

```bash
# Seed reference data and create the demo users
php artisan demo:populate

# Wipe the database first (asks for confirmation)
php artisan demo:populate --fresh

# Also create an admin user for the Filament panel
php artisan demo:populate --with-admin
```

`demo:populate` creates five demo users. They all use the password `demo123`.
`DemoDataSeeder` sets this password directly, so changing `DEMO_USER_PASSWORD` does not change it.

| Email | Profile |
|-------|---------|
| `demo.user@gcu.global` | Regular user |
| `demo.business@gcu.global` | Business user |
| `demo.investor@gcu.global` | Investor |
| `demo.argentina@gcu.global` | User in a high-inflation country |
| `demo.nomad@gcu.global` | Digital nomad |

Two more commands help with demo data.

```bash
# Add a simulated deposit to a user (local, testing and demo only; --instant applies only in demo)
php artisan demo:deposit demo.user@gcu.global 500 --asset=EUR --instant

# Delete demo data older than one day (demo environment only)
php artisan demo:cleanup --days=1 --dry-run
```

In the demo environment the scheduler runs `demo:cleanup` every day.
It keeps `demo.cleanup.retention_days` days of data, which `DEMO_CLEANUP_RETENTION_DAYS` sets.

## Configuration

### Environment

Copy `.env.demo` to `.env` to start a demo deployment.
`APP_ENV=demo` turns several demo-only features on by default. The file also sets them explicitly.
The mock bank custodian is the exception. It stays off in the demo environment unless `CUSTODIAN_MOCK_ENABLED=true` is set.

```env
APP_ENV=demo

# Demo-only ledger and showcase features (default on under APP_ENV=demo)
LEDGER_DIRECT_FUNDING_ENABLED=true
TRADING_ENABLED=true
REGISTRATION_API_ENABLED=true

# Mock bank custodian (default off under APP_ENV=demo, so set it explicitly)
CUSTODIAN_MOCK_ENABLED=true
```

### `config/demo.php`

`config/demo.php` defines these settings.

```env
# Demo features
DEMO_INSTANT_DEPOSITS=true
DEMO_SKIP_KYC=true
DEMO_MOCK_EXTERNAL_APIS=true
DEMO_FIXED_EXCHANGE_RATES=true
DEMO_AUTO_APPROVE=true

# Banner and watermark
DEMO_SHOW_BANNER=true
DEMO_BANNER_TEXT="Demo Environment - No real transactions"
DEMO_SHOW_WATERMARK=true

# Demo users and data retention
DEMO_USER_PASSWORD=demo123
DEMO_DATA_RETENTION_DAYS=7
DEMO_CLEANUP_ENABLED=true
DEMO_CLEANUP_RETENTION_DAYS=1

# Use real provider sandboxes instead of simulations
SANDBOX_MODE=false
```

The code reads only some of them.
`DEMO_MOCK_EXTERNAL_APIS` decides whether the demo bank replaces the named custodians.
`DEMO_AUTO_APPROVE` and `DEMO_INSTANT_DEPOSITS` are read by the demo exchange, lending and blockchain services.
`DEMO_SHOW_BANNER` and `DEMO_BANNER_TEXT` control the banner, `DEMO_CLEANUP_RETENTION_DAYS` sets the scheduled cleanup, and `SANDBOX_MODE` turns on sandbox mode.
No code reads `DEMO_SKIP_KYC`, `DEMO_FIXED_EXCHANGE_RATES`, `DEMO_SHOW_WATERMARK`, `DEMO_USER_PASSWORD`, `DEMO_DATA_RETENTION_DAYS` or `DEMO_CLEANUP_ENABLED` at present.

The same file holds fixed demo exchange rates, lending and stablecoin defaults, the demo user list and demo limits.
The demo limits, such as `max_transaction_amount` and `max_daily_transactions`, are not enforced by any code at present.

## User interface

When `DEMO_SHOW_BANNER` is on, `DemoServiceProvider` shares `isDemoMode` and `demoMessage` with every view.
The `<x-demo-banner>` component (`resources/views/components/demo-banner.blade.php`) shows a notice on pages such as the crypto, manual and Open Banking deposit pages.
It only renders when the environment is `demo`.

## Middleware

`App\Http\Middleware\DemoMode` is added to the `web` group when `APP_ENV=demo`. It is also available under the `demo` alias.
It is written to block a short list of routes, such as external webhook processing, with HTTP 403.
It is also written to add the `X-Demo-Mode: true` and `X-Demo-Features` headers to other responses.
At present it only acts when `SANDBOX_MODE=true`. It checks a `demo.mode` setting that `config/demo.php` does not define, so under a plain `APP_ENV=demo` it passes every request through unchanged.

## Production safeguards

The payment, payment gateway, exchange, lending and stablecoin demo services throw an exception when they are built in production.
`ops:verify-env` fails a production deploy while `LEDGER_DIRECT_FUNDING_ENABLED` is on, unless `LEDGER_DIRECT_FUNDING_ACKNOWLEDGED=true` confirms a production-mode demo.
The production templates `.env.production.example` and `.env.zelta.example` keep `TRADING_ENABLED=false` and `SHOW_PROMO_PAGES=false`.

## Best practices

Always show clearly that the demo is a demo.
Use data that looks realistic but is obviously fake.
Keep the cleanup schedule on so old demo data does not pile up.
Keep demo data in a separate database from any real deployment.

## Troubleshooting

### Demo services are not used

Check that `APP_ENV` is exactly `demo`.
Then clear the configuration cache with `php artisan config:clear`.

### Real provider calls happen in the demo

Check that `SANDBOX_MODE` is not set to `true`.
Check that `DEMO_MOCK_EXTERNAL_APIS` is on.

### Demo users are missing

Run `php artisan demo:populate`. A plain `php artisan migrate --seed` does not create them.

### Old demo data is not removed

Check that the scheduler runs and that `APP_ENV` is `demo`, because the cleanup is only scheduled in the demo environment.
Run `php artisan demo:cleanup --dry-run` to see what would be deleted.
