# Demo Deposit Command

The `demo:deposit` command creates a test deposit for a user. It is meant for development and testing. You can use it to try wallet features without going through the full deposit flow.

## Prerequisites

- The command runs only in the `local`, `testing` and `demo` environments.
- Queue workers must be running so that the event-sourced deposit is processed.

## Usage

```bash
php artisan demo:deposit {email} {amount} [options]
```

### Arguments

- `email` is the email address of the user who receives the funds.
- `amount` is the amount in standard units. For example, `100` means $100.

### Options

- `--asset=USD` sets the asset code. The default is USD. The supported codes are USD, EUR, GBP and GCU.
- `--description="Demo deposit"` sets the transaction description. The default is "Demo deposit".
- `--instant` skips queue processing and applies the deposit at once. It works only in the `demo` environment. In other environments the command falls back to regular processing.

## Examples

### Basic USD deposit
```bash
php artisan demo:deposit user@example.com 100
```
This deposits $100 USD to the user's account.

### Deposit with different currency
```bash
php artisan demo:deposit user@example.com 50 --asset=EUR
```
This deposits €50 EUR to the user's account.

### Deposit with custom description
```bash
php artisan demo:deposit user@example.com 1000 --asset=GCU --description="Test GCU deposit"
```
This deposits 1000 GCU with a custom description.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

## How it works

1. The command checks that it runs in an allowed environment.
2. It finds the user by email address.
3. If the user has no account, it creates one.
4. It checks that the asset exists and is active.
5. It records the deposit through the event sourcing system. It calls `credit()` on the `AssetTransactionAggregate` and then processes the `events` and `ledger` queues so that the balance updates. With `--instant` in the `demo` environment, it uses `DemoPaymentService` instead.
6. It prints the transaction ID and the new balance.

## Security

The command refuses to run in production. This prevents anyone from creating funds there by accident. It runs only in these environments:
- `local` for local development
- `testing` for automated tests
- `demo` for demo and staging environments

## Troubleshooting

### "User not found" error
Check that the email address is correct and that the user exists in the database.

### "Asset not found" error
Check which assets are available:
```bash
php artisan tinker
>>> \App\Domain\Asset\Models\Asset::where('is_active', true)->pluck('code')
```

### Balance not updating
Make sure the queue workers are running:
```bash
php artisan queue:work --queue=default,events,ledger,transactions
```

### Account creation fails
Check that event sourcing is configured and that the database migrations are up to date.
