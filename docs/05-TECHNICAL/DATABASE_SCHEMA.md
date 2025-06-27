# Database Schema Documentation

**Version:** 1.3  
**Last Updated:** 2025-06-27  
**Laravel Version:** 11.x  
**Database:** MySQL 8.0+

## Overview

FinAegis uses a sophisticated database schema designed to support multi-asset banking operations with event sourcing, comprehensive audit trails, and high-performance queries. The schema follows Domain-Driven Design principles with clear separation between different business domains.

## Core Banking Tables

### users
Primary user accounts for the banking platform.

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
Banking accounts owned by users. Each user can have multiple accounts.

```sql
CREATE TABLE `accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` bigint NOT NULL DEFAULT '0',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounts_uuid_unique` (`uuid`),
  KEY `accounts_user_uuid_index` (`user_uuid`),
  KEY `accounts_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `balance`: Legacy USD balance in cents (maintained for backward compatibility)
- `status`: 'active', 'frozen', 'closed'

**Indexes:**
- Primary key on `id`
- Unique index on `uuid`
- Index on `user_uuid` (foreign key reference)
- Index on `status` (for filtering)

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
  `from_asset` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_asset` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(20,8) NOT NULL,
  `source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `fetched_at` timestamp NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `exchange_rates_from_asset_to_asset_fetched_at_unique` (`from_asset`,`to_asset`,`fetched_at`),
  KEY `exchange_rates_from_asset_to_asset_index` (`from_asset`,`to_asset`),
  KEY `exchange_rates_fetched_at_index` (`fetched_at`),
  KEY `exchange_rates_is_active_index` (`is_active`),
  CONSTRAINT `exchange_rates_from_asset_foreign` FOREIGN KEY (`from_asset`) REFERENCES `assets` (`code`),
  CONSTRAINT `exchange_rates_to_asset_foreign` FOREIGN KEY (`to_asset`) REFERENCES `assets` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `rate`: Exchange rate with 8 decimal precision
- `source`: 'manual', 'api', 'oracle', 'market'
- `fetched_at`: When the rate was obtained
- `expires_at`: Rate expiration (NULL for permanent rates)

**Indexes:**
- Unique constraint on (from_asset, to_asset, fetched_at) for historical tracking
- Composite index on (from_asset, to_asset) for rate lookups
- Index on fetched_at for time-based queries

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
- Transfer events: `MoneyTransferred`, `TransferInitiated`, `TransferCompleted`

**Indexes:**
- Unique constraint on (aggregate_uuid, aggregate_version) for consistency
- Index on aggregate_uuid for aggregate reconstruction
- Index on event_class for event type queries

### snapshots
Event sourcing snapshots for performance optimization.

```sql
CREATE TABLE `snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `aggregate_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aggregate_version` int unsigned NOT NULL,
  `state` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `snapshots_aggregate_uuid_unique` (`aggregate_uuid`),
  KEY `snapshots_aggregate_version_index` (`aggregate_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Read Models & Projections

### turnovers
Turnover projections for account activity analysis.

```sql
CREATE TABLE `turnovers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `period` date NOT NULL,
  `debit` bigint NOT NULL DEFAULT '0',
  `credit` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `turnovers_account_uuid_period_unique` (`account_uuid`,`period`),
  KEY `turnovers_period_index` (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `debit`: Total debit amount for the period (in cents)
- `credit`: Total credit amount for the period (in cents)
- `period`: Date for daily aggregation

## Governance System

### polls
Democratic governance polls for platform decisions.

```sql
CREATE TABLE `polls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` json NOT NULL,
  `start_date` timestamp NOT NULL,
  `end_date` timestamp NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `required_participation` int DEFAULT NULL,
  `voting_power_strategy` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'one_user_one_vote',
  `voting_power_config` json DEFAULT NULL,
  `execution_workflow` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `polls_uuid_unique` (`uuid`),
  KEY `polls_status_index` (`status`),
  KEY `polls_start_date_end_date_index` (`start_date`,`end_date`),
  KEY `polls_created_by_index` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Poll Types:**
- 'single_choice': Select one option
- 'multiple_choice': Select multiple options
- 'weighted': Weighted voting based on holdings

**Voting Power Strategies:**
- 'one_user_one_vote': Equal voting power
- 'asset_weighted': Voting power based on asset holdings
- 'sqrt_weighted': Square root of asset holdings

### votes
Individual votes cast on polls.

```sql
CREATE TABLE `votes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `poll_id` bigint unsigned NOT NULL,
  `user_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `selected_options` json NOT NULL,
  `voting_power` int NOT NULL DEFAULT '1',
  `voted_at` timestamp NOT NULL,
  `signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `votes_poll_id_user_uuid_unique` (`poll_id`,`user_uuid`),
  KEY `votes_user_uuid_index` (`user_uuid`),
  KEY `votes_voted_at_index` (`voted_at`),
  CONSTRAINT `votes_poll_id_foreign` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Constraints:**
- Unique constraint on (poll_id, user_uuid) - one vote per user per poll
- Foreign key to polls with CASCADE delete

## Webhook System

### webhooks
Webhook endpoints for external integrations.

```sql
CREATE TABLE `webhooks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `events` json NOT NULL,
  `headers` json DEFAULT NULL,
  `secret` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `timeout_seconds` int NOT NULL DEFAULT '30',
  `retry_attempts` int NOT NULL DEFAULT '3',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webhooks_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Supported Events:**
- Account: created, updated, frozen, unfrozen, closed
- Transaction: created, reversed
- Transfer: created, completed, failed
- Balance: low_balance, negative_balance

### webhook_deliveries
Webhook delivery tracking and retry logic.

```sql
CREATE TABLE `webhook_deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `webhook_id` bigint unsigned NOT NULL,
  `event_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `response_code` int DEFAULT NULL,
  `response_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attempts` int NOT NULL DEFAULT '0',
  `delivered_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `webhook_deliveries_webhook_id_foreign` (`webhook_id`),
  KEY `webhook_deliveries_status_index` (`status`),
  KEY `webhook_deliveries_next_retry_at_index` (`next_retry_at`),
  CONSTRAINT `webhook_deliveries_webhook_id_foreign` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Delivery Status:**
- 'pending': Awaiting delivery
- 'success': Successfully delivered
- 'failed': All retry attempts exhausted
- 'retrying': Waiting for next retry

## Platform Configuration

### settings
Platform-wide configuration settings with encryption support.

```sql
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`),
  KEY `settings_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fields:**
- `key`: Unique setting identifier (e.g., 'platform.name', 'features.lending.enabled')
- `value`: Setting value (encrypted if sensitive)
- `type`: Data type (string, boolean, integer, json, array)
- `is_encrypted`: Whether the value is encrypted
- `description`: Human-readable description of the setting

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

1. **Primary Keys**: All tables use `bigint unsigned AUTO_INCREMENT`
2. **UUIDs**: Indexed for external references and API lookups
3. **Foreign Keys**: Proper indexing for joins and cascading operations
4. **Time-based Queries**: Indexes on created_at, fetched_at, etc.
5. **Composite Indexes**: Multi-column indexes for complex queries

### Query Optimization

```sql
-- Efficient account balance retrieval
SELECT ab.asset_code, ab.balance, a.name, a.precision
FROM account_balances ab
JOIN assets a ON ab.asset_code = a.code
WHERE ab.account_uuid = ? AND ab.balance > 0;

-- Exchange rate lookup with fallback
SELECT rate FROM exchange_rates 
WHERE from_asset = ? AND to_asset = ? 
AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())
ORDER BY fetched_at DESC LIMIT 1;

-- Account transaction history
SELECT event_class, event_properties, created_at
FROM stored_events 
WHERE aggregate_uuid = ? 
AND event_class IN (?)
ORDER BY aggregate_version DESC;
```

### Partitioning Strategy

For high-volume deployments, consider partitioning:

1. **stored_events**: Partition by created_at (monthly)
2. **webhook_deliveries**: Partition by created_at (weekly)
3. **turnovers**: Partition by period (yearly)

## Data Integrity

### Constraints and Validations

1. **Foreign Key Constraints**: Enforce referential integrity
2. **Unique Constraints**: Prevent duplicate records
3. **Check Constraints**: Validate data ranges and formats
4. **JSON Schema Validation**: Validate JSON field structures

### Backup Strategy

1. **Daily Backups**: Full database backup with 30-day retention
2. **Point-in-Time Recovery**: Binary log enabled for recovery
3. **Testing**: Regular restore testing on staging environment

## Security Considerations

### Data Protection

1. **Encryption at Rest**: Database-level encryption for sensitive data
2. **Access Control**: Role-based database user permissions
3. **Audit Logging**: Enable MySQL audit plugin for compliance
4. **Connection Security**: TLS encryption for all connections

### Sensitive Data Handling

- **Passwords**: Bcrypt hashed in users table
- **API Tokens**: Hashed in personal_access_tokens
- **Webhook Secrets**: Encrypted before storage
- **PII Data**: Minimal storage, encryption where required

---

This schema supports FinAegis's evolution from single-currency to multi-asset platform while maintaining ACID compliance, performance, and auditability requirements.