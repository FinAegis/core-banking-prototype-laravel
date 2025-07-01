# Demo Deposit Command

The `demo:deposit` command allows you to create test deposits for users in development and testing environments. This is useful for testing wallet functionality without going through the full deposit flow.

## Prerequisites

- The command is only available in `local`, `testing`, or `demo` environments
- Queue workers must be running for the event sourcing to process properly

## Usage

```bash
php artisan demo:deposit {email} {amount} [options]
```

### Arguments

- `email` - The email address of the user to deposit funds to
- `amount` - The amount to deposit (in standard units, e.g., 100 for $100)

### Options

- `--asset=USD` - The asset code to deposit (default: USD). Available options: USD, EUR, GBP, GCU
- `--description="Demo deposit"` - Description for the transaction (default: "Demo deposit")

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

## How it Works

1. The command verifies that the environment is appropriate for demo deposits
2. It finds the user by email address
3. If the user doesn't have an account, it creates one automatically
4. It verifies the asset exists and is active
5. It uses the event sourcing system to create a proper deposit:
   - Creates a `MoneyAdded` event via the `LedgerAggregate`
   - Processes the event queue to update balances
6. Shows the transaction ID and new balance

## Security

This command is restricted to non-production environments to prevent accidental creation of funds in production. The environment check ensures it can only be run in:
- `local` - Local development
- `testing` - Automated testing
- `demo` - Demo/staging environments

## Troubleshooting

### "User not found" error
Make sure the email address is correct and the user exists in the database.

### "Asset not found" error
Check available assets with:
```bash
php artisan tinker
>>> \App\Domain\Asset\Models\Asset::where('is_active', true)->pluck('code')
```

### Balance not updating
Ensure queue workers are running:
```bash
php artisan queue:work --queue=events,ledger,transactions
```

### Account creation fails
Check that the event sourcing system is properly configured and database migrations are up to date.