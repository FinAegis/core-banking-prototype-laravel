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

### Banking Domain
- `bank_connections` - Bank API/OAuth connections
- `bank_accounts` - Linked bank accounts
- `bank_transfers` - Bank wire transfers
- `bank_statements` - Statement reconciliation

### Lending Domain
- `loans` - Loan records
- `loan_applications` - Loan applications
- `loan_collateral` - Collateral tracking
- `loan_repayments` - Repayment schedules

### Compliance Domain
- `compliance_alerts` - Compliance alerts
- `compliance_cases` - Investigation cases
- `kyc_documents` - KYC document uploads
- `kyc_verifications` - Verification records

### Exchange Domain (v2.0.0)
- `orders` - Trading orders
- `order_books` - Order book state
- `trades` - Executed trades
- `exchange_fees` - Fee configurations
- `exchange_matching_errors` - Error tracking

### Stablecoin Domain (v2.0.0)
- `stablecoins` - Stablecoin configurations
- `stablecoin_collateral_positions` - CDP positions
- `stablecoin_operations` - Mint/burn operations

### Wallet Domain (v2.0.0)
- `blockchain_wallets` - Blockchain wallets
- `wallet_addresses` - Derived addresses
- `blockchain_transactions` - On-chain transactions
- `wallet_seeds` - Encrypted seeds
- `token_balances` - Token balance tracking
- `wallet_backups` - Wallet backups

### Treasury Domain (v2.0.0)
- `asset_allocations` - Portfolio allocations
- `cash_allocations` - Cash management
- `yield_optimizations` - Yield tracking
- `portfolio_events/snapshots` - Event sourcing

### CGO Domain (v2.0.0)
- `cgo_pricing_rounds` - Investment rounds
- `cgo_investments` - Investment records
- `cgo_refunds` - Refund tracking
- `cgo_notifications` - Investor notifications
- `cgo_events/snapshots` - Event sourcing

### Agent Protocol Domain (v2.0.0)
- `agent_identities` - AI agent DIDs
- `agent_wallets` - Agent wallets
- `agent_transactions` - Agent transactions
- `escrows` - Escrow services
- `escrow_disputes` - Dispute resolution
- `agent_protocol_events/snapshots` - Event sourcing

### Event Sourcing Tables
All domains have event sourcing infrastructure:
- `stored_events` - Generic events
- `snapshots` - Generic snapshots
- `{domain}_events` - Domain-specific events
- `{domain}_snapshots` - Domain-specific snapshots

## Tables in Central Database

These remain in `database/migrations/` (central):
- `users`, `teams`, `team_user`, `team_invitations`
- `tenants`, `domains`
- `personal_access_tokens`, `oauth_*` tables
- `assets`, `exchange_rates` (global market data)
- `roles`, `permissions` (Spatie Permission)
- `settings`, `feature_flags`
- Cache, jobs, workflows infrastructure

## Security Guidelines

### Encrypted Fields
Sensitive data fields are marked with `_encrypted` suffix and require Laravel's `encrypted` cast:

```php
// In Model
protected $casts = [
    'access_data_encrypted' => 'encrypted:array',
    'beneficiary_details_encrypted' => 'encrypted:array',
    'iban_encrypted' => 'encrypted',
];
```

**Fields requiring encryption:**
- `bank_connections.access_data_encrypted` - OAuth/API tokens
- `bank_accounts.iban_encrypted`, `bic_swift_encrypted`, `routing_number_encrypted`
- `bank_transfers.beneficiary_details_encrypted`
- `kyc_documents.document_data_encrypted`

### Audit Trail Fields
All financial tables include audit fields:
- `created_by` - UUID of user who created the record
- `updated_by` - UUID of user who last modified
- `authorized_by` - UUID of user who authorized (for transfers)
- `processed_by` - UUID of user/system that processed

### AML/Compliance Fields
Financial tables include compliance tracking:
- `aml_status` - AML screening status (pending, cleared, flagged)
- `aml_screened_at` - When AML screening was performed
- `sanctions_status` - Sanctions check status
- `sanctions_checked_at` - When sanctions check was performed

## Best Practices

1. **Never reference central tables** in tenant migrations using foreign keys
2. **Always include indexes** for commonly queried columns
3. **Use `uuid` columns** instead of auto-increment IDs for cross-database references
4. **Include soft deletes** for financial audit compliance
5. **Add proper indexes** for user_uuid/account_uuid lookups
6. **Always encrypt sensitive data** using Laravel's encrypted casts
7. **Include audit fields** for all financial operations
8. **Add AML/compliance fields** where money movement occurs
9. **Use value_date and processing_date** for settlement tracking
