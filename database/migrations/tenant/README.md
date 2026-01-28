# Tenant Migrations

This directory contains migrations that run **only in tenant databases**.

## Overview

FinAegis uses a multi-database tenancy approach where:
- **Central database**: Contains global data (users, teams, tenants, assets, etc.)
- **Tenant databases**: Contains tenant-specific data (accounts, transactions, etc.)

## Migration Commands

### Run tenant migrations for all tenants
```bash
php artisan tenants:migrate
```

### Run tenant migrations for specific tenant
```bash
php artisan tenants:migrate --tenants=tenant-uuid-here
```

### Rollback tenant migrations
```bash
php artisan tenants:rollback
```

### Fresh migrate (drop and recreate)
```bash
php artisan tenants:migrate-fresh
```

### Seed tenant databases
```bash
php artisan tenants:seed
```

## Migration File Naming

Tenant migrations follow the standard Laravel naming convention:
- `YYYY_MM_DD_HHMMSS_description.php`

For initial setup migrations, we use a special prefix:
- `0001_01_01_000001_create_tenant_accounts_table.php`
- `0001_01_01_000002_create_tenant_transactions_table.php`
- etc.

## Tables in Tenant Database

### Core Financial Tables
- `accounts` - Financial accounts
- `account_balances` - Balance tracking per currency
- `transactions` - Transaction records
- `transaction_projections` - Read model for transactions
- `transaction_snapshots` - Event sourcing snapshots
- `transfers` - Fund transfer records
- `transfer_snapshots` - Event sourcing snapshots
- `ledger_entries` - Detailed ledger records
- `ledger_snapshots` - Event sourcing snapshots

### Domain-Specific Tables (to be added)
- Banking tables (bank_accounts, bank_transfers, etc.)
- Lending tables (loans, loan_applications, etc.)
- Compliance tables (alerts, cases, kyc documents, etc.)
- Stablecoin tables (operations, collateral positions, etc.)
- Treasury tables (portfolios, investments, etc.)
- Exchange tables (orders, positions, etc.)
- Wallet tables (blockchain accounts, etc.)
- Payment tables (deposits, withdrawals, etc.)

## Tables in Central Database

These remain in `database/migrations/` (central):
- `users`, `teams`, `team_user`, `team_invitations`
- `tenants`, `domains`
- `personal_access_tokens`, `oauth_*` tables
- `assets`, `exchange_rates` (global market data)
- `roles`, `permissions` (Spatie Permission)
- `settings`, `feature_flags`
- Cache, jobs, workflows infrastructure

## Best Practices

1. **Never reference central tables** in tenant migrations using foreign keys
2. **Always include indexes** for commonly queried columns
3. **Use `uuid` columns** instead of auto-increment IDs for cross-database references
4. **Include soft deletes** for financial audit compliance
5. **Add proper indexes** for user_uuid/account_uuid lookups
