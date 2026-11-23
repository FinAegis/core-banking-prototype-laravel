# Database Schema Documentation

**Version:** 1.4  
**Last Updated:** 2026-11-24 (reviewed for v7.18.1)  
**Laravel Version:** 12.x  
**Database:** MySQL 8.0+ (MariaDB is also supported)

## Overview

The FinAegis database supports multi-asset accounts, event sourcing and audit trails. The schema follows the Domain-Driven Design split of the code, so each business domain owns its own tables.

This page covers the core tables only. The full schema comes from about 380 migration files in `database/migrations/`, plus tenant migrations in `database/migrations/tenant/`. The migrations are the source of truth. The SQL blocks below are simplified, and column types can differ slightly from the migrations. The tables of most of the 61 domains, such as cards, ramp sessions, subscriptions and wallet addresses, are not described here.

## Core Banking Tables

### users
This table holds the user accounts. The block below shows only the core columns. Later migrations add many more, such as `last_login_at` (added in v7.18.0 and set on every sign-in), the Privy user id, two-factor fields, `kyc_status` and team fields.

```sql
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_uuid_unique` (`uuid`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Indexes:**
- Primary key on `id`
- Unique index on `uuid` (used for external references)
- Unique index on `email` (authentication)

### accounts
This table holds the accounts that users own. Each user can have several accounts.

```sql
CREATE TABLE `accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `uuid` char(36) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `user_uuid` char(36) NOT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `treasury_id` char(36) DEFAULT NULL,
  `balance` int NOT NULL DEFAULT '0',
  `available_balance` int NOT NULL DEFAULT '0',
  `frozen` tinyint(1) NOT NULL DEFAULT '0',
  `frozen_by_owner` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounts_uuid_unique` (`uuid`),
  KEY `accounts_frozen_index` (`frozen`),
  KEY `idx_account_frozen_status` (`uuid`,`frozen`),
  CONSTRAINT `accounts_user` FOREIGN KEY (`user_uuid`) REFERENCES `users` (`uuid`)
);
```

**Fields:**
- `balance`: Legacy USD balance in cents (maintained for backward compatibility)
- `frozen`: Whether the account is frozen
- `frozen_by_owner`: Records who placed the freeze. When it is true, the account holder froze the account and may lift the freeze. When it is false, staff or the system froze it, and only staff may lift it.
- `team_id`: The team that owns the account in a multi-tenant setup

**Indexes:**
- Primary key on `id`
- Unique index on `uuid`
- Foreign key from `user_uuid` to `users.uuid`
- Index on `frozen` (for filtering)

## Multi-Asset Support

### assets
Supported assets (currencies, cryptocurrencies, commodities).

```sql
CREATE TABLE `assets` (
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('fiat','crypto','commodity','custom') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `precision` tinyint NOT NULL DEFAULT '2',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`code`),
  KEY `assets_type_index` (`type`),
  KEY `assets_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `code`: Primary key (e.g., 'USD', 'EUR', 'BTC', 'XAU')
- `type`: Asset category for different handling logic
- `precision`: Decimal places for display (2 for fiat, 8 for crypto)
- `metadata`: JSON field for extensible asset properties

**Default Assets:**
- USD, EUR, GBP (fiat currencies)
- BTC, ETH (cryptocurrencies)
- XAU, XAG (precious metals)

### account_balances
Multi-asset balances for each account.

```sql
CREATE TABLE `account_balances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `asset_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_balances_account_uuid_asset_code_unique` (`account_uuid`,`asset_code`),
  KEY `account_balances_asset_code_foreign` (`asset_code`),
  CONSTRAINT `account_balances_account_uuid_foreign` FOREIGN KEY (`account_uuid`) REFERENCES `accounts` (`uuid`) ON DELETE CASCADE,
  CONSTRAINT `account_balances_asset_code_foreign` FOREIGN KEY (`asset_code`) REFERENCES `assets` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `balance`: Amount in smallest unit (cents for USD, satoshis for BTC)

**Constraints:**
- Unique constraint on (account_uuid, asset_code) - one balance per asset per account
- Foreign key to accounts table with CASCADE delete
- Foreign key to assets table

### exchange_rates
Exchange rates between different assets.

```sql
CREATE TABLE `exchange_rates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `from_asset_code` varchar(10) NOT NULL,
  `to_asset_code` varchar(10) NOT NULL,
  `rate` decimal(20,10) NOT NULL,
  `bid` decimal(20,10) DEFAULT NULL,
  `ask` decimal(20,10) DEFAULT NULL,
  `source` varchar(50) NOT NULL DEFAULT 'manual',
  `valid_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`from_asset_code`,`to_asset_code`,`valid_at`),
  KEY (`from_asset_code`,`to_asset_code`),
  KEY (`valid_at`,`expires_at`),
  KEY (`is_active`,`valid_at`),
  KEY (`source`),
  CONSTRAINT FOREIGN KEY (`from_asset_code`) REFERENCES `assets` (`code`),
  CONSTRAINT FOREIGN KEY (`to_asset_code`) REFERENCES `assets` (`code`)
);
```

**Fields:**
- `rate`: Exchange rate with 10 decimal places
- `bid`, `ask`: Optional bid and ask prices from the rate source
- `source`: Where the rate came from, for example 'manual' or a provider name
- `valid_at`: When the rate became valid
- `expires_at`: Rate expiration (NULL for permanent rates)

**Indexes:**
- Unique constraint on (from_asset_code, to_asset_code, valid_at) for historical tracking
- Composite index on (from_asset_code, to_asset_code) for rate lookups
- Indexes on (valid_at, expires_at) and (is_active, valid_at) for time-based queries

## Event Sourcing

### stored_events
Core event sourcing table storing all domain events.

```sql
CREATE TABLE `stored_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aggregate_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aggregate_version` int unsigned NOT NULL,
  `event_version` int unsigned NOT NULL DEFAULT '1',
  `event_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_properties` json NOT NULL,
  `meta_data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stored_events_aggregate_uuid_aggregate_version_unique` (`aggregate_uuid`,`aggregate_version`),
  KEY `stored_events_aggregate_uuid_index` (`aggregate_uuid`),
  KEY `stored_events_event_class_index` (`event_class`),
  KEY `stored_events_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Event Types:**
- Account events: `AccountCreated`, `MoneyAdded`, `MoneySubtracted`
- Asset events: `AssetBalanceAdded`, `AssetBalanceSubtracted`, `AssetTransferred`
- Transfer events: `MoneyTransferred`, plus `AssetTransferInitiated`, `AssetTransferCompleted` and `AssetTransferFailed` in the Asset domain

Many domains store their events in their own table instead, for example `exchange_events`, `lending_events`, `treasury_events`, `compliance_events`, `stablecoin_events` and `cgo_events`.

**Indexes:**
- Unique constraint on (aggregate_uuid, aggregate_version) for consistency
- Index on aggregate_uuid for aggregate reconstruction
- Index on event_class for event type queries

### snapshots
Event sourcing snapshots for performance optimization.

```sql
CREATE TABLE `snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aggregate_uuid` char(36) NOT NULL,
  `aggregate_version` bigint unsigned NOT NULL,
  `state` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `snapshots_aggregate_uuid_index` (`aggregate_uuid`)
);
```

## Read Models & Projections

### turnovers
Turnover projections for account activity analysis.

```sql
CREATE TABLE `turnovers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_uuid` char(36) NOT NULL,
  `date` date NOT NULL,
  `count` int NOT NULL DEFAULT '0',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_date` (`account_uuid`,`date`),
  CONSTRAINT `turnovers_account` FOREIGN KEY (`account_uuid`) REFERENCES `accounts` (`uuid`)
);
```

**Fields:**
- `date`: The day the row aggregates
- `count`: Number of transactions on that day
- `amount`: Net amount for the day
- `debit`, `credit`: Total debits and credits for the day

## Governance System

### polls
Polls for the Governance module. Governance is a demo module, the GCU votes in it are simulated, and the production Zelta configuration disables it.

```sql
CREATE TABLE `polls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `type` varchar(50) NOT NULL,
  `options` json NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `required_participation` int DEFAULT NULL,
  `voting_power_strategy` varchar(100) NOT NULL DEFAULT 'one_user_one_vote',
  `execution_workflow` varchar(255) DEFAULT NULL,
  `created_by` char(36) NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`uuid`),
  KEY (`status`,`start_date`,`end_date`),
  KEY (`created_by`),
  KEY (`voting_power_strategy`),
  CONSTRAINT FOREIGN KEY (`created_by`) REFERENCES `users` (`uuid`) ON DELETE CASCADE
);
```

**Poll Types:**
- 'single_choice': Select one option
- 'multiple_choice': Select multiple options
- 'weighted_choice': Weighted voting
- 'yes_no': Yes or no
- 'ranked_choice': Rank the options

**Voting Power Strategies:**
- 'one_user_one_vote': Equal voting power
- 'asset_weighted_vote': Voting power based on asset holdings

### votes
Individual votes cast on polls.

```sql
CREATE TABLE `votes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `poll_id` bigint unsigned NOT NULL,
  `user_uuid` char(36) NOT NULL,
  `selected_options` json NOT NULL,
  `voting_power` int NOT NULL DEFAULT '1',
  `voted_at` datetime NOT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_poll_vote` (`poll_id`,`user_uuid`),
  KEY (`poll_id`,`voted_at`),
  KEY (`user_uuid`),
  KEY (`voting_power`),
  CONSTRAINT FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE,
  CONSTRAINT FOREIGN KEY (`user_uuid`) REFERENCES `users` (`uuid`) ON DELETE CASCADE
);
```

**Constraints:**
- Unique constraint on (poll_id, user_uuid) - one vote per user per poll
- Foreign key to polls with CASCADE delete

## Webhook System

### webhooks
Webhook endpoints for external integrations.

```sql
CREATE TABLE `webhooks` (
  `uuid` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `url` varchar(255) NOT NULL,
  `events` json NOT NULL,
  `headers` json DEFAULT NULL,
  `secret` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `retry_attempts` int NOT NULL DEFAULT '3',
  `timeout_seconds` int NOT NULL DEFAULT '30',
  `last_triggered_at` datetime DEFAULT NULL,
  `last_success_at` datetime DEFAULT NULL,
  `last_failure_at` datetime DEFAULT NULL,
  `consecutive_failures` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `webhooks_is_active_index` (`is_active`)
);
```

**Supported Events:**
- Account: `account.created`, `account.updated`, `account.frozen`, `account.unfrozen`, `account.closed`
- Transaction: `transaction.created`, `transaction.reversed`
- Transfer: `transfer.created`, `transfer.completed`, `transfer.failed`
- Balance: `balance.low`, `balance.negative`

The `webhook_endpoints` table is a different table. It stores the signing keys of incoming provider webhooks, such as Alchemy.

### webhook_deliveries
Webhook delivery tracking and retry logic.

```sql
CREATE TABLE `webhook_deliveries` (
  `uuid` char(36) NOT NULL,
  `webhook_uuid` char(36) NOT NULL,
  `event_type` varchar(255) NOT NULL,
  `payload` json NOT NULL,
  `attempt_number` int NOT NULL DEFAULT '1',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `response_status` int DEFAULT NULL,
  `response_body` text,
  `response_headers` json DEFAULT NULL,
  `duration_ms` int DEFAULT NULL,
  `error_message` text,
  `delivered_at` datetime DEFAULT NULL,
  `next_retry_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`uuid`),
  KEY `webhook_deliveries_webhook_uuid_status_index` (`webhook_uuid`,`status`),
  KEY `webhook_deliveries_event_type_index` (`event_type`),
  KEY `webhook_deliveries_created_at_index` (`created_at`),
  CONSTRAINT `webhook_deliveries_webhook_uuid_foreign` FOREIGN KEY (`webhook_uuid`) REFERENCES `webhooks` (`uuid`) ON DELETE CASCADE
);
```

**Delivery Status:**
- 'pending': Awaiting delivery or the next retry
- 'delivered': Successfully delivered
- 'failed': All retry attempts exhausted

## Platform Configuration

### settings
Platform-wide configuration settings with encryption support. The admin Settings page edits them.

```sql
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group` varchar(100) NOT NULL DEFAULT 'general',
  `key` varchar(255) NOT NULL,
  `value` json NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'string',
  `label` varchar(255) NOT NULL,
  `description` text,
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `validation_rules` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`key`),
  KEY (`group`),
  KEY (`is_public`),
  KEY (`group`,`key`)
);
```

**Fields:**
- `group`: Setting group, for example 'general' or 'features'
- `key`: Unique setting identifier
- `value`: Setting value as JSON (encrypted if sensitive)
- `type`: Data type (string, boolean, integer, json, array)
- `label`, `description`: Human-readable name and description
- `is_public`: Whether the value may be shown outside the admin panel
- `is_encrypted`: Whether the value is encrypted

**Usage:**
- Platform configuration (name, URL, timezone)
- Feature toggles for sub-products
- API rate limits and thresholds
- Email templates and notifications
- Third-party service credentials (encrypted)

## Laravel Framework Tables

### migrations
Laravel migration tracking.

```sql
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### failed_jobs
Failed queue jobs for retry and debugging.

```sql
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### cache
Cache table for database-backed caching.

```sql
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### personal_access_tokens
Laravel Sanctum API tokens.

```sql
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Performance Considerations

### Indexing Strategy

1. Most tables use a `bigint unsigned AUTO_INCREMENT` primary key. Some, such as `webhooks` and `webhook_deliveries`, use a UUID as the primary key.
2. UUID columns are indexed, because external references and API lookups use them.
3. Foreign keys are indexed for joins and cascading deletes.
4. Columns used in time-based queries, such as `created_at` and `valid_at`, are indexed.
5. Multi-column indexes support the more complex queries.

MariaDB accepts only RFC 4122 UUIDs (version 4 nibble and variant bits set) in UUID columns. Raw hashes are rejected.

### Query Optimization

```sql
-- Efficient account balance retrieval
SELECT ab.asset_code, ab.balance, a.name, a.precision
FROM account_balances ab
JOIN assets a ON ab.asset_code = a.code
WHERE ab.account_uuid = ? AND ab.balance > 0;

-- Exchange rate lookup with fallback
SELECT rate FROM exchange_rates 
WHERE from_asset_code = ? AND to_asset_code = ? 
AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())
ORDER BY valid_at DESC LIMIT 1;

-- Account transaction history
SELECT event_class, event_properties, created_at
FROM stored_events 
WHERE aggregate_uuid = ? 
AND event_class IN (?)
ORDER BY aggregate_version DESC;
```

### Partitioning Strategy

For high-volume deployments, consider partitioning:

1. Partition `stored_events` by `created_at`, monthly.
2. Partition `webhook_deliveries` by `created_at`, weekly.
3. Partition `turnovers` by `date`, yearly.

## Data Integrity

### Constraints and Validations

Foreign key constraints enforce referential integrity, and unique constraints prevent duplicate records. Value ranges, formats and the structure of JSON fields are validated in the application before data is written.

### Backup Strategy

Production runs a database-only backup every night at 01:30 UTC with `spatie/laravel-backup` (`backup:run --only-db`), followed by `backup:clean`. By default all backups are kept for 7 days, daily backups for 16 days and weekly backups for 8 weeks (`BACKUP_KEEP_*` settings). Point-in-time recovery needs the MySQL binary log, which the database host must enable. Restores should be tested regularly on a staging environment.

## Security Considerations

### Data Protection

These are recommendations for the database host. Enable encryption at rest and give database users only the permissions they need. Enable the MySQL audit plugin if your audit needs require it, and use TLS for all connections.

### Sensitive Data Handling

- Passwords are hashed with bcrypt in the `users` table.
- API tokens are stored as hashes in `personal_access_tokens`.
- Webhook secrets use Laravel's `encrypted` cast.
- Personal data is kept to a minimum and encrypted where required.

---

This schema supports FinAegis's evolution from single-currency to multi-asset platform while maintaining ACID compliance, performance, and auditability requirements.