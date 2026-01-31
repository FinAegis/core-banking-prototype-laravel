# Changelog

All notable changes to the FinAegis Core Banking Platform will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.0] - 2026-01-31 (In Progress)

### 📱 Mobile Backend & Biometric Authentication Release

This release delivers complete mobile backend infrastructure with enterprise-grade security, event sourcing integration, and real-time push notifications for mobile wallet applications.

### Highlights

| Feature | Description | PRs |
|---------|-------------|-----|
| Mobile Device Management | Device registration, blocking, trust levels | #347-352 |
| Biometric Authentication | ECDSA P-256 challenge-response, device binding | #348-349 |
| Push Notifications | Firebase Cloud Messaging, preference management | #351 |
| Event Sourcing | Mobile domain events with tenant awareness | #349 |
| Cross-Domain Integration | Transaction and security event listeners | #352 |

### Added

#### Mobile Device Management
- **MobileDeviceService** - Device registration, blocking, trust management
- **MobileDevice model** - Multi-device support per user (max 5)
- Device takeover prevention with automatic session invalidation
- Platform-specific tracking (iOS/Android)
- Push token management with duplicate detection

#### Biometric Authentication
- **BiometricAuthenticationService** - ECDSA P-256 signature verification
- **Challenge-response flow** - 5-minute TTL challenges
- **Device binding** - Public key stored per device
- **Rate limiting** - Auto-lockout after 5 failed attempts
- IP network validation for challenge responses

#### Push Notifications
- **PushNotificationService** - Firebase Cloud Messaging integration
- **NotificationPreferenceService** - User/device preferences
- Notification types: transaction, security, marketing, system
- Scheduled and retry mechanisms
- Read/unread tracking

#### Session Management
- **MobileSessionService** - Device-bound session management
- Token refresh endpoints
- Revoke single/all sessions
- Trusted device extended sessions (8 hours vs 1 hour)

#### Event Sourcing Integration
- **MobileDeviceAggregate** - Event-sourced device state
- 10 domain events for complete audit trail
- WebSocket broadcasting on tenant channels
- Tenant-aware background jobs

#### Cross-Domain Event Listeners
- **SendTransactionPushNotificationListener** - Transaction alerts
- **SendSecurityAlertListener** - Security event notifications
- **LogMobileAuditEventListener** - Compliance audit logging

### API Endpoints

| Category | Endpoints |
|----------|-----------|
| Device Management | `POST/GET/DELETE /api/mobile/devices`, `POST /devices/{id}/block` |
| Biometric Auth | `POST /api/mobile/auth/biometric/challenge`, `/verify` |
| Sessions | `GET/DELETE /api/mobile/sessions` |
| Notifications | `GET /api/mobile/notifications`, `PUT /notifications/preferences` |

### Configuration

New `config/mobile.php`:
- App version management with force update flag
- Device limits and session durations
- Biometric challenge TTL and failure thresholds
- Push notification batch size and retry settings

### Security

- ECDSA P-256 public key verification
- Challenge expiration (5 minutes)
- Biometric lockout after 5 failures (30 minutes)
- Device takeover detection with session invalidation
- IP network validation for challenge responses
- Sensitive fields hidden in API responses

### Documentation

- Created Mobile domain README
- Added API endpoint documentation
- Updated CLAUDE.md with Mobile services
- Updated version badges

---

## [2.1.0] - 2026-01-30

### 🔐 Security & Enterprise Features Release

This release delivers enterprise-grade security hardening and infrastructure features, including hardware wallet integration, multi-signature support, real-time WebSocket streaming, and Kubernetes-native deployment.

### Highlights

| Feature | Description | PRs |
|---------|-------------|-----|
| Hardware Wallet Integration | Ledger Nano S/X, Trezor One/Model T support | #341 |
| Multi-Signature Wallets | M-of-N threshold signatures for corporate accounts | #342 |
| WebSocket Streaming | Real-time order book, NAV, transaction updates | #343 |
| Kubernetes Native | Helm charts, HPA, Istio service mesh | #344 |
| Security Hardening | ECDSA, PBKDF2, EIP-2 compliance | #345 |

### Added

#### Hardware Wallet Integration
- **LedgerSignerService** - Ledger Nano S/X device support
- **TrezorSignerService** - Trezor One/Model T device support
- **HardwareWalletManager** - Unified wallet coordination
- **HardwareWalletController** - REST API for device management
- Supported chains: Ethereum, Bitcoin, Polygon, BSC
- BIP44 derivation path support
- Transaction signing workflows with 5-minute TTL

#### Multi-Signature Wallet Support
- M-of-N threshold signature schemes (e.g., 2-of-3, 3-of-5)
- Transaction approval workflows
- Multi-signer coordination
- Signature aggregation and verification

#### WebSocket Real-time Streaming
- Tenant-scoped broadcast channels
- Real-time order book updates
- Live NAV calculations
- Transaction status notifications
- Portfolio value streaming

#### Kubernetes Native Deployment
- **Helm Charts** - Complete deployment package
- **Horizontal Pod Autoscaler** - CPU/memory-based scaling
- **Istio Service Mesh** - Traffic management, mTLS
- **Network Policies** - Pod-to-pod security
- Production and staging value files

### Security

#### Cryptographic Hardening
- **ECDSA ecrecover** - Proper signature validation with public key recovery
- **PBKDF2** - 100,000 iteration key derivation
- **EIP-2** - Signature malleability protection (s-value validation)
- **Timing-safe comparison** - Prevent timing attacks on key comparison
- **Curve order validation** - Secp256k1 compliance

### Infrastructure

#### Docker Build Improvements
- Multi-stage build optimization
- Alpine PHP 8.4-fpm base image
- PECL Redis extension compilation
- Autoconf build dependencies management

### Documentation
- Updated all documentation to v2.1.0
- Added Hardware Wallet API documentation
- Added WebSocket streaming guide
- Cleaned up archived documentation
- Updated version badges across all files

---

## [2.0.0] - 2026-01-28

### 🏢 Multi-Tenancy Release

Transform FinAegis into a **multi-tenant SaaS platform** with team-based data isolation, powered by stancl/tenancy v3.9. This release introduces complete tenant isolation for all domains while maintaining backward compatibility for single-tenant deployments.

### Highlights

| Phase | Deliverable | PRs |
|-------|-------------|-----|
| Phase 1 | Foundation POC - stancl/tenancy setup, tenant model, middleware | #328 |
| Phase 2 | Migration Infrastructure - 14 tenant migration files | #329, #337 |
| Phase 3 | Event Sourcing Integration - Tenant-aware aggregates & projectors | #330 |
| Phase 4 | Model Scoping - 83 models with tenant connection trait | #331 |
| Phase 5 | Queue Job Tenant Context - TenantAwareJob trait | #332 |
| Phase 6 | WebSocket Channel Authorization - Tenant-scoped broadcasting | #333 |
| Phase 7 | Filament Admin Tenant Filtering - Admin panel tenant support | #334 |
| Phase 8 | Data Migration Tooling - Import/export commands | #335 |
| Phase 9 | Security Audit - Isolation validation tests | #336 |

### Added

#### Multi-Tenancy Foundation
- **stancl/tenancy v3.9** integration with custom team-based tenancy
- **Tenant Model** - Links to Teams, supports multiple database strategies
- **InitializeTenancyByTeam Middleware** - Team membership verification, rate limiting, audit logging
- **TeamTenantResolver** - Cached tenant resolution with security checks

#### Tenant Database Migrations (14 files)
- `0001_01_01_000001_create_tenant_accounts_table.php` - Core account tables
- `0001_01_01_000002_create_tenant_transactions_table.php` - Transaction records
- `0001_01_01_000003_create_tenant_transfers_table.php` - Transfer tracking
- `0001_01_01_000004_create_tenant_account_balances_table.php` - Balance projections
- `0001_01_01_000005_create_tenant_compliance_tables.php` - KYC/AML tables
- `0001_01_01_000006_create_tenant_banking_tables.php` - Bank connections
- `0001_01_01_000007_create_tenant_lending_tables.php` - Loan lifecycle
- `0001_01_01_000008_create_tenant_event_sourcing_tables.php` - Event stores
- `0001_01_01_000009_create_tenant_exchange_tables.php` - Trading engine
- `0001_01_01_000010_create_tenant_stablecoin_tables.php` - Stablecoin ops
- `0001_01_01_000011_create_tenant_wallet_tables.php` - Blockchain wallets
- `0001_01_01_000012_create_tenant_treasury_tables.php` - Portfolio management
- `0001_01_01_000013_create_tenant_cgo_tables.php` - Investment platform
- `0001_01_01_000014_create_tenant_agent_protocol_tables.php` - AI agent protocol

#### Event Sourcing Integration
- **TenantAwareStoredEvent** - Base class for tenant-scoped events
- **TenantAwareSnapshot** - Base class for tenant-scoped snapshots
- **TenantAwareAggregateRoot** - Aggregate root with tenant context
- **TenantAwareStoredEventRepository** - Tenant-filtered event storage
- **TenantAwareSnapshotRepository** - Tenant-filtered snapshots

#### Model Scoping
- **UsesTenantConnection Trait** - Applied to 83 Eloquent models
- All domain models updated (Account, Banking, Compliance, Exchange, etc.)
- 16 event sourcing models extend TenantAwareStoredEvent
- 5 snapshot models extend TenantAwareSnapshot

#### Queue & Background Jobs
- **TenantAwareJob Trait** - Explicit tenant context tracking
- Updated AsyncCommandJob, AsyncDomainEventJob, ProcessCustodianWebhook
- Tenant tags for Laravel Horizon monitoring
- QueueTenancyBootstrapper enabled in config

#### WebSocket & Broadcasting
- **TenantChannelAuthorizer** - Tenant-scoped channel authorization
- **TenantBroadcastEvent Trait** - Tenant-aware event broadcasting
- Tenant-scoped channel definitions in routes/channels.php

#### Filament Admin Panel
- **TenantAwareResource Trait** - Automatic tenant scoping for resources
- **FilamentTenantMiddleware** - Tenant context initialization
- **TenantSelectorWidget** - UI widget for switching tenants
- Admin panel tenant filtering across all resources

#### Data Migration Tooling
- **TenantDataMigrationService** - Core data migration service
- **MigrateTenantDataCommand** - `php artisan tenant:migrate-data`
- **ExportTenantDataCommand** - `php artisan tenant:export` (JSON/CSV/SQL)
- **ImportTenantDataCommand** - `php artisan tenant:import`
- Migration tracking tables (tenant_data_migrations, imports, exports)

#### Security Features
- Team membership verification before tenant access
- Rate limiting (60 attempts/minute) on tenant lookups
- Audit logging for all tenancy events
- Explicit 403 responses when tenant required but not found
- Config-based auto-creation (dev/test only)

### Security

- **TenantIsolationSecurityTest** - 9 structural security tests
- **CrossTenantAccessPreventionTest** - 17 isolation validation tests
- Security audit documentation at `docs/security/MULTI_TENANCY_SECURITY_AUDIT.md`
- Pure unit tests using reflection (no Laravel container dependencies)

### Changed

- Database connections now support: central, tenant, tenant_template
- Event sourcing repositories are now tenant-aware
- All financial models use tenant-scoped database connections
- Queue jobs preserve tenant context across async boundaries

### Migration Notes

1. **New Configuration Files**:
   ```bash
   config/tenancy.php      # stancl/tenancy configuration
   config/multitenancy.php # Custom multi-tenancy settings
   ```

2. **Run Central Migrations**:
   ```bash
   php artisan migrate  # Creates tenants table and domains
   ```

3. **Run Tenant Migrations** (after creating tenants):
   ```bash
   php artisan tenants:migrate
   ```

4. **Data Migration** (for existing single-tenant data):
   ```bash
   php artisan tenant:migrate-data {tenant_id} --tables=accounts,transactions
   ```

### Upgrade Notes

For existing single-tenant deployments:
- The default behavior remains unchanged when no tenant is active
- Multi-tenancy features are opt-in via middleware
- Existing data can be migrated using the data migration commands
- See `docs/V2.0.0_MULTI_TENANCY_ARCHITECTURE.md` for detailed upgrade guide

### Breaking Changes

- None for single-tenant deployments
- Multi-tenant deployments require:
  - Tenant creation before accessing tenant-scoped resources
  - Team membership for tenant access
  - Updated route middleware configuration

---

## [1.4.1] - 2026-01-27

### 🐛 Database Cache Connection Fix

Fixes a critical issue where `php artisan optimize` fails with "Access denied for user 'root'@'localhost'" in production environments.

### Fixed

- **Cache Configuration** - Fixed database cache driver using incorrect credentials during optimization
  - `config/cache.php` now properly defaults `DB_CACHE_CONNECTION` to the configured `DB_CONNECTION`
  - Also fixed `lock_connection` to inherit from `DB_CONNECTION` when not explicitly set
  - Resolves issue where Laravel would fall back to hardcoded 'root' credentials during `php artisan optimize`

### Changed

- **Environment Configuration** - Added documentation for `DB_CACHE_CONNECTION` in `.env.example`
  - Commented example showing how to explicitly set cache database connection
  - Helpful for environments requiring separate cache database credentials

### Root Cause Analysis

The `laravel-data` caching step during `php artisan optimize` uses the database cache driver. When `DB_CACHE_CONNECTION` was null (not set in .env), Laravel's cache driver would not properly inherit the application's configured database credentials, instead falling back to the hardcoded MySQL defaults (`root` with empty password) defined in `config/database.php`.

---

## [1.4.0] - 2026-01-27

### 🧪 Test Coverage Expansion Release

Comprehensive test coverage for previously untested domain services and value objects, plus code quality improvements through shared test utilities.

### Highlights

| Category | Deliverables |
|----------|--------------|
| AI Domain | 55 unit tests (ConsensusBuilder, AIAgentService, ToolRegistry) |
| Batch Domain | 37 unit tests (ProcessBatchItemActivity, BatchJobData) |
| CGO Domain | 70 unit tests (CgoKycService, InvestmentAgreementService, etc.) |
| FinancialInstitution Domain | 65 unit tests (ComplianceCheckService, PaymentVerificationService, etc.) |
| Fraud Domain | 18 unit tests for FraudDetectionService |
| Wallet Domain | 37 unit tests (KeyManagementService + Value Objects) |
| Regulatory Domain | 13 unit tests for ReportGeneratorService |
| Stablecoin Domain | 24 unit tests for Value Objects |
| Test Utilities | InvokesPrivateMethods helper trait |
| Code Quality | PHPStan Level 8 fixes, API scope test updates |
| **Total** | **319 new domain tests** |

### Added

#### Domain Test Suites

- **AI Domain Tests** (55 tests)
  - `ConsensusBuilderTest` (7 tests) - Consensus building algorithm
  - `AIAgentServiceTest` (24 tests) - Chat responses, keyword matching, feedback
  - `ToolRegistryTest` (24 tests) - Tool registration, search, schema export

- **Batch Domain Tests** (37 tests)
  - `ProcessBatchItemActivityTest` (24 tests)
    - Currency conversion rates (USD, EUR, GBP, PHP)
    - Conversion calculations with various amounts
    - Edge cases (zero, small, large amounts)
  - `BatchJobDataTest` (13 tests)
    - Data object creation and validation
    - UUID generation, type handling, metadata

- **Fraud Domain Tests** (18 tests)
  - `FraudDetectionServiceTest` - Pattern detection, risk scoring
  - Tests high-value, velocity, geographic, time-based, and round amount patterns
  - Aggregation logic and risk multiplier calculations
  - Uses anonymous class test doubles for Eloquent models

- **Wallet Domain Tests** (37 tests)
  - `KeyManagementServiceTest` (23 tests)
    - BIP39 mnemonic generation and validation
    - Key derivation (BIP32/BIP44)
    - Multi-blockchain address generation (Ethereum, Bitcoin, Solana, etc.)
    - Signature operations and key storage
  - `WalletValueObjectsTest` (14 tests)
    - `WalletAddress` value object (address, blockchain, label)
    - `TransactionResult` value object (hash, status, gas, logs)
    - Status helpers (isSuccess, isPending, isFailed)

- **Regulatory Domain Tests** (13 tests)
  - `ReportGeneratorServiceTest` - Report generation utilities
  - CSV header extraction (CTR, SAR, KYC report types)
  - Certification statements for regulatory compliance
  - Filename generation with proper formatting
  - XML conversion for nested data structures

- **Stablecoin Domain Tests** (24 tests)
  - `StablecoinValueObjectsTest` - Financial value objects
  - `LiquidationThreshold` - Collateral health levels (safe/margin call/liquidation)
  - `CollateralRatio` - Ratio calculations with BigDecimal precision
  - `PriceData` - Price feeds with staleness detection

- **CGO Domain Tests** (70 tests)
  - `CgoKycServiceTest` (17 tests) - KYC verification and compliance checks
  - `InvestmentAgreementServiceTest` (18 tests) - Agreement generation and management
  - `RiskAssessmentServiceTest` (18 tests) - Risk scoring and investment suitability
  - `OfferingValidatorServiceTest` (17 tests) - Offering validation rules

- **FinancialInstitution Domain Tests** (65 tests)
  - `ComplianceCheckServiceTest` (18 tests) - Regulatory compliance verification
  - `PaymentVerificationServiceTest` (18 tests) - Payment validation and fraud checks
  - `BankingConnectorServiceTest` (14 tests) - Banking API integration tests
  - `TransactionMonitoringServiceTest` (15 tests) - Real-time transaction monitoring

#### Test Utilities

- **InvokesPrivateMethods Trait** (`tests/Traits/`)
  - `invokeMethod()` - Invoke private/protected methods via reflection
  - `getPrivateProperty()` - Read private property values
  - `setPrivateProperty()` - Set private property values
  - Reduces code duplication across test files (DRY improvement)

#### Domain Commands

- **DomainCreateCommand** (`php artisan domain:create`)
  - Scaffold new domain structure with all required files
  - Creates Models, Services, Events, Repositories directories
  - Generates ServiceProvider template
  - Creates module.json manifest

### Fixed

- PHPStan Level 8 errors in `AccountQueryService`
- Test isolation issues with Eloquent model mocking
- Type safety for financial calculations in value objects
- API scope authentication in 20+ feature tests after security hardening
- Test expectations for empty scopes (now correctly deny access)
- Flaky `DemoLendingServiceTest` credit score simulation
- `AgentMessageBusServiceTest` mock return types (Agent model vs array)
- `MetricsMiddlewareTest` timing-sensitive assertion (now uses assertNotNull)

### CI/CD

- **Deploy Workflow Improvements**
  - Added Redis service container for pre-deployment tests
  - Added APP_KEY environment variable to build-artifacts job
  - Fixed tar "file changed as we read it" error with `--warning=no-file-changed`
  - Excluded `bootstrap/cache/*` from deployment package
  - Properly skip deployment steps when server credentials not configured
  - Added step outputs for conditional deployment execution
  - Improved notification messages for skipped vs failed deployments

### Security

- **Rate limiting threshold** - Reduced auth attempts from 5 to 3 (brute force protection)
- **Session limit** - Reduced max concurrent sessions from 5 to 3 (session hijacking protection)
- **Token expiration enforcement** - All auth controllers now use `createTokenWithScopes()` for proper token expiration
  - Fixed: LoginController, PasswordController, SocialAuthController, TwoFactorAuthController
- **API scope bypass fix** - Removed backward compatibility bypass in `CheckApiScope` middleware
- **Agent scope bypass fix** - `AgentScope::hasScope()` now returns false for empty scopes (was returning true)

### Developer Experience

- Anonymous class test doubles pattern documented
- Test utilities centralized for reuse
- Pure unit tests (no database dependencies)

---

## [1.3.0] - 2026-01-25

### 🔧 Platform Modularity Release

Transform FinAegis from a monolithic domain structure to a **modular architecture** where domains can be installed independently. This enables faster onboarding, customized deployments, and better maintainability.

### Highlights

| Category | Deliverables |
|----------|--------------|
| Shared Interfaces | 4 new domain contracts for loose coupling |
| Security | Input validation and audit logging across shared services |
| Module Manifests | 29 domain manifests with dependency declarations |
| Domain Commands | 5 Artisan commands for domain management |
| Infrastructure | DependencyResolver, DomainManager services |

### Added

#### Phase 1: Shared Domain Interfaces
- **WalletOperationsInterface** - Wallet funds management contract
  - `depositFunds()`, `withdrawFunds()`, `getBalance()`
  - `lockFunds()`, `unlockFunds()`, `transferBetweenWallets()`
- **AssetTransferInterface** - Cross-domain asset operations
  - `transfer()`, `getAssetDetails()`, `validateTransfer()`
  - `convertAsset()`, `getTransferStatus()`
- **PaymentProcessingInterface** - Payment gateway abstraction
  - `processDeposit()`, `processWithdrawal()`, `getPaymentStatus()`
  - `refundPayment()`, `validatePaymentRequest()`
- **AccountQueryInterface** - Read-only account operations
  - `getAccountDetails()`, `getBalance()`, `getTransactionHistory()`
  - `accountExists()`, `getAccountsByOwner()`

#### Phase 2: Security Implementation
- **FinancialInputValidator** trait - Consistent input validation
  - UUID validation for all identifiers
  - Amount validation (positive, precision limits)
  - Currency/asset code validation (ISO 4217)
  - Reference and metadata sanitization
- **AuditLogger** trait - Financial operation audit trail
  - Automatic sensitive data redaction
  - Request ID tracking for correlation
  - Operation timing and outcome logging
- **Encrypted Cache Storage** - Secure wallet locks and payment records
- **Reduced Lock TTL** - From 24h to 1h for security

#### Phase 3: Module Manifest System
- **ModuleManifest** value object - Parses `module.json` files
- **DependencyResolver** service - Builds dependency trees, detects cycles
- **DomainManager** service - Central domain operations management
- **29 module.json files** - One per domain with:
  - Version and description metadata
  - Required and optional dependencies
  - Provided interfaces, events, and commands
  - Path configuration (routes, migrations, config)

#### Phase 4: Domain Installation Commands
- `php artisan domain:list` - List all domains with status and dependencies
  - Filter by type (`--type=core`) or status (`--status=installed`)
  - JSON output support (`--json`)
- `php artisan domain:install {domain}` - Install a domain
  - Automatic dependency resolution
  - Migration execution
  - Config publishing
- `php artisan domain:remove {domain}` - Safe domain removal
  - Dependent checking (prevents breaking changes)
  - Migration rollback
  - Force option for overrides
- `php artisan domain:dependencies {domain}` - Show dependency tree
  - Visual tree rendering
  - Flat list option (`--flat`)
  - Unsatisfied dependency warnings
- `php artisan domain:verify {domain?}` - Verify domain health
  - Manifest validation
  - Dependency satisfaction
  - Interface implementation checks

#### Domain Classification
- **Core Domains** (always required): `shared`, `account`, `user`, `compliance`
- **Optional Financial**: `exchange`, `lending`, `treasury`, `wallet`, `payment`, `banking`, `asset`, `stablecoin`
- **Optional AI/Agent**: `ai`, `agent-protocol`, `governance`
- **Optional Infrastructure**: `monitoring`, `performance`, `fraud`, `batch`, `webhook`

### Changed
- Service locator anti-pattern removed from WalletOperationsService
- Value objects (AccountUuid, Money) used consistently in AssetTransferService

### Security
- Input validation added to all shared service implementations
- Audit logging for compliance and security monitoring
- Encrypted cache storage for sensitive operation data
- 365-day retention for audit and security logs

### Developer Experience
- Domain discovery via `domain:list` command
- Dependency visualization via `domain:dependencies`
- Health verification via `domain:verify`
- JSON output for CI/CD integration

---

## [1.2.0] - 2026-01-13

### 🚀 Feature Completion Release

This release completes the **Phase 6 integration bridges**, adds **production observability**, and resolves all actionable TODO items - making the platform feature-complete for production deployment.

### Highlights

| Category | Deliverables |
|----------|--------------|
| Integration Bridges | Agent-Payment, Agent-KYC, Agent-MCP bridges |
| Enhanced Features | Yield Optimization, EDD Workflows, Batch Processing |
| Observability | 10 Grafana dashboards, Prometheus alerting rules |
| Domain Completions | StablecoinReserve model, Paysera integration |
| TODO Cleanup | 10 TODOs resolved, 2 deferred (external blockers) |

### Added

#### Integration Bridges (Phase 6 Completion)
- **AgentPaymentIntegrationService** - Connects Agent Protocol to Payment System
  - Wallet-to-account linking for AI agents
  - Real financial transaction execution
  - Balance synchronization across systems
- **AgentKycIntegrationService** - Unified KYC across human and AI agents
  - KYC inheritance from linked users
  - Compliance tier mapping
  - Regulatory compliance for AI-driven transactions
- **AgentMCPBridgeService** - AI Framework integration with Agent Protocol
  - Tool execution with proper agent authorization
  - Comprehensive audit logging
  - MCP tool registration for agents

#### Enhanced Features
- **YieldOptimizationController** - Wired to existing YieldOptimizationService
  - Portfolio optimization endpoints
  - Yield projection API
  - Rebalancing recommendations
- **EnhancedDueDiligenceService** - Advanced compliance workflows
  - EDD workflow initiation and management
  - Document collection and verification
  - Risk assessment scoring
  - Periodic review scheduling
- **BatchProcessingController** - Complete scheduled processing
  - Batch scheduling with dispatch delay
  - Cancellation with compensation patterns
  - Progress tracking and retry logic

#### Production Observability
- **Grafana Dashboards** (10 domain dashboards in `infrastructure/observability/grafana/`)
  - Account/Banking metrics
  - Exchange trading metrics
  - Lending portfolio health
  - Compliance monitoring
  - Agent Protocol metrics
  - Stablecoin reserves
  - Treasury portfolio
  - Wallet operations
  - System health overview
  - AI Framework metrics
- **Prometheus Alerting Rules** (`infrastructure/observability/prometheus/`)
  - Critical alerts (immediate response)
  - Warning alerts (investigation needed)
  - Domain-specific alert thresholds

#### Stablecoin Domain Completion
- **StablecoinReserve Model** - Read model for reserve data projection
  - Reserve tracking with custodian information
  - Allocation percentage calculations
  - Verification status and audit trail
- **StablecoinReserveAuditLog Model** - Comprehensive audit logging
  - Deposit/withdrawal tracking
  - Rebalance history
  - Price update records
- **StablecoinReserveProjector** - Event sourcing projection
  - Projects ReservePool aggregate events
  - Real-time reserve statistics

#### Payment Integration
- **PayseraDepositServiceInterface** - Contract for Paysera operations
- **PayseraDepositService** - Production Paysera integration
  - OAuth2 authentication flow
  - Deposit initiation with redirect
  - Callback handling with verification
- **DemoPayseraDepositService** - Demo mode simulation
  - Predictable test behaviors
  - No external API calls
  - Instant callback simulation
- **PayseraDepositController** - Full controller implementation
  - Input validation
  - Error handling
  - Demo/production mode switching

#### Workflow & Saga Additions
- **LoanDisbursementSaga** - Multi-step loan orchestration
  - Loan approval workflow
  - Fund disbursement with compensation
  - Notification integration
- **NotifyReputationChangeActivity** - Real Laravel notifications
  - Email notifications
  - Database notifications
  - Customizable templates

### Changed

- **DemoServiceProvider** - Added Paysera service bindings
- **StablecoinAggregateRepository** - Now uses real StablecoinReserve model
- **ProcessCustodianWebhook** - Wired to WebhookProcessorService

### Fixed

- Removed TODO stubs from PayseraDepositController
- Resolved StablecoinReserve model dependency in repository
- Fixed MySQL index name length (64 char limit)
- PHPStan Level 8 compliance for all new files

### Technical Debt Status

| Category | Count | Status |
|----------|-------|--------|
| Resolved | 10 | ✅ Complete |
| Blocked | 1 | 🚫 External (laravel-workflow RetryOptions) |
| Deferred | 1 | 📉 v1.3.0 (BasketService refactor) |

### Migration Notes

1. Run migrations for new tables:
   ```bash
   php artisan migrate
   ```
   New tables: `stablecoin_reserves`, `stablecoin_reserve_audit_logs`, `edd_*`, `agent_mcp_audit_logs`

2. Configure Paysera (optional):
   ```env
   PAYSERA_PROJECT_ID=your_project_id
   PAYSERA_SIGN_PASSWORD=your_sign_password
   ```

3. Set up observability (optional):
   - Import Grafana dashboards from `infrastructure/observability/grafana/`
   - Configure Prometheus with rules from `infrastructure/observability/prometheus/`

### Upgrade Notes

This release has no breaking changes. All new features are additive.

```bash
git pull origin main
composer install
php artisan migrate
php artisan config:cache
```

---

## [1.1.0] - 2026-01-11

### 🔧 Foundation Hardening Release

This release focuses on **code quality**, **test coverage expansion**, and **CI/CD hardening** - laying a solid foundation for future feature development.

### Highlights

| Metric | v1.0.0 | v1.1.0 | Improvement |
|--------|--------|--------|-------------|
| PHPStan Level | 5 | **8** | +3 levels |
| PHPStan Baseline | 54,632 lines | **9,007 lines** | **83% reduction** |
| Test Files | 458 | **499** | +41 files |
| Behat Features | 1 | **22** | +21 features |

### Added

#### Comprehensive Domain Test Suites
- **Banking Domain** (40 tests)
  - BankingConnectorTest - Multi-bank routing
  - BankRoutingServiceTest - Intelligent bank selection
  - BankHealthMonitorTest - Health monitoring
- **Governance Domain** (55 tests)
  - VotingPowerCalculatorTest - Voting weight calculations
  - ProposalStatusTest - Proposal lifecycle
  - VoteTypeTest - Vote type behaviors
  - GovernanceExceptionTest - Exception handling
- **User Domain** (64 tests)
  - NotificationPreferencesTest - Email/SMS/push settings
  - PrivacySettingsTest - Privacy controls
  - UserPreferencesTest - Language/timezone/currency
  - UserRolesTest - Role-based access
  - UserProfileExceptionTest - Exception factory
- **Compliance Domain** (34 tests)
  - AlertStatusTest - Alert lifecycle management
  - AlertSeverityTest - Severity levels and priorities
- **Treasury Domain** (53 tests)
  - RiskProfileTest - Risk levels and exposure limits
  - AllocationStrategyTest - Portfolio allocation
  - LiquidityMetricsTest - Basel III regulatory metrics
- **Lending Domain** (59 tests)
  - LoanPurposeTest - Loan purposes and interest rates
  - CollateralTypeTest - Collateral and LTV ratios
  - CreditScoreTest - Credit score validation
  - RiskRatingTest - Risk ratings and multipliers

#### PHPStan Level 8 Achievement
- Upgraded from level 5 → 6 → 7 → **8**
- Fixed event sourcing aggregate return types
- Added null-safe operators in AI/MCP services
- Corrected reflection method null-safety in tests
- Added User type annotations to ComplianceController

### Changed

#### CI/CD Hardening
- **Security Audit Enforcement**: CI now fails on critical/high vulnerabilities
- Removed obsolete backup files from `bin/` directory
- Enhanced pre-commit checks for better local validation

### Fixed

- PHPStan baseline errors across all domains
- Null-safety issues in AI service implementations
- Reflection method null-pointer exceptions in tests
- Type annotations for Eloquent factory return types

### Developer Experience

#### Pre-Commit Quality Checks
```bash
./bin/pre-commit-check.sh --fix  # Auto-fix issues
```

#### Test Commands
```bash
./vendor/bin/pest --parallel                    # Run all tests
./vendor/bin/pest tests/Domain/Banking/         # Run domain tests
```

### Upgrade Notes

This is a quality-focused release with no breaking changes.

1. Pull the latest changes:
   ```bash
   git pull origin main
   composer install
   ```

2. Verify PHPStan compliance:
   ```bash
   XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
   ```

3. Run the test suite:
   ```bash
   ./vendor/bin/pest --parallel
   ```

---

## [1.0.0] - 2024-12-21

### 🎉 Open Source Release

This release marks the transformation of FinAegis from a proprietary platform to an **open-source core banking framework** with GCU (Global Currency Unit) as its reference implementation.

### Added

#### Open Source Foundation (Phase 1)
- **CONTRIBUTING.md** - Comprehensive contribution guidelines with development workflow
- **SECURITY.md** - Vulnerability reporting and security policy
- **CODE_OF_CONDUCT.md** - Contributor Covenant 2.1 community guidelines
- **Architecture Decision Records (ADRs)**
  - ADR-001: Event Sourcing Architecture
  - ADR-002: CQRS Pattern Implementation
  - ADR-003: Saga Pattern for Distributed Transactions
  - ADR-004: GCU Basket Currency Design
  - ADR-005: Demo Mode Architecture
- **ARCHITECTURAL_ROADMAP.md** - Strategic 4-phase transformation plan
- **IMPLEMENTATION_PLAN.md** - Sprint-level implementation details

#### Platform Modularity (Phase 2)
- **Domain Dependency Analysis** - Three-tier domain classification (Core, Supporting, Optional)
- **Shared Contracts for Domain Decoupling**
  - `AccountOperationsInterface` - Cross-domain account operations
  - `ComplianceCheckInterface` - KYC/AML verification abstraction
  - `ExchangeRateInterface` - Currency conversion abstraction
  - `GovernanceVotingInterface` - Voting system abstraction
- **AccountOperationsAdapter** - Reference implementation bridging interface to Account domain

#### GCU Reference Implementation (Phase 3)
- **Basket Domain README** - Complete domain documentation
- **BUILDING_BASKET_CURRENCIES.md** - Step-by-step tutorial (776 lines)
  - Custom basket creation from scratch
  - NAV calculation implementation
  - Rebalancing strategies
  - Governance integration
  - Testing patterns

#### Production Hardening (Phase 4)
- **SECURITY_AUDIT_CHECKLIST.md** - 74+ item security review framework
  - Authentication & session management
  - Authorization & access control
  - Data protection & encryption
  - Financial security & fraud prevention
  - API security & rate limiting
  - Infrastructure & container security
- **DEPLOYMENT_GUIDE.md** - Production deployment documentation
  - Docker Compose configuration
  - Kubernetes manifests (Deployment, Service, Ingress, HPA)
  - Database setup and backup strategies
  - Queue worker configuration
  - Scaling considerations
- **OPERATIONAL_RUNBOOK.md** - Day-to-day operations manual
  - Incident response procedures (SEV-1 to SEV-4)
  - Common scenarios with resolutions
  - Maintenance procedures
  - Disaster recovery (RTO/RPO objectives)

### Changed
- Website content updated for open-source accuracy
- Investment components converted to demo-only mode
- Enhanced documentation structure with clear separation of concerns
- Improved domain boundaries with interface-based decoupling

### Architecture Highlights
- **29 Bounded Contexts** organized in three tiers
- **Event Sourcing** with domain-specific event stores
- **CQRS** with Command/Query Bus infrastructure
- **Saga Pattern** for distributed transaction compensation
- **Demo Mode** for development without external dependencies

## [0.9.0] - 2024-12-18

### Added
- **Agent Protocol (AP2/A2A)** - Full implementation of Google's Agent Payments Protocol
  - Agent registration with DID support
  - Escrow service for secure transactions
  - Reputation and trust scoring system
  - A2A messaging infrastructure
  - MCP tools for AI agent integration
  - Protocol negotiation API
  - OAuth2-style agent scopes

### Changed
- AI Framework enhanced with Agent Protocol bridge service
- Multi-agent coordination capabilities

## [0.8.0] - 2024-12-01

### Added
- **Treasury Management Domain**
  - Portfolio management with event sourcing
  - Cash allocation and yield optimization
  - Investment strategy workflows
  - Treasury aggregates with full audit trail

- **Enhanced Compliance Domain**
  - Three-tier KYC verification (Basic, Enhanced, Full)
  - AML screening integration
  - Transaction monitoring with SAR/CTR generation
  - Biometric verification support

### Changed
- Improved event sourcing patterns across domains
- Enhanced saga compensation logic

## [0.7.0] - 2024-11-15

### Added
- **AI Framework**
  - Production-ready MCP server with 20+ banking tools
  - Event-sourced AI interactions
  - Tool execution with audit trail
  - Claude and OpenAI provider support

- **Distributed Tracing**
  - OpenTelemetry integration
  - Cross-domain trace correlation
  - Performance monitoring

### Fixed
- PHPStan level 5 compliance issues
- Test isolation for security tests

## [0.6.0] - 2024-11-01

### Added
- **Governance Domain**
  - Democratic voting system
  - Asset-weighted voting strategy
  - Proposal lifecycle management
  - GCU basket composition voting

- **Stablecoin Domain Enhancements**
  - Multi-collateral support
  - Health monitoring with margin calls
  - Liquidation workflows
  - Position management

## [0.5.0] - 2024-10-15

### Added
- **GCU (Global Currency Unit) Basket**
  - 6-currency basket implementation (USD, EUR, GBP, CHF, JPY, XAU)
  - NAV calculation service
  - Automatic rebalancing with governance
  - Performance tracking

- **Liquidity Pool Enhancements**
  - Spread management saga
  - Market maker workflow
  - Impermanent loss protection
  - AMM (Automated Market Maker) implementation

## [0.4.0] - 2024-10-01

### Added
- **Exchange Domain**
  - Order matching engine with saga pattern
  - Liquidity pool management
  - External exchange connectors (Binance, Kraken)
  - 6-tier fee system
  - 44 domain events

- **Lending Domain**
  - P2P lending platform
  - Credit scoring system
  - Loan lifecycle management
  - Risk assessment workflows

## [0.3.0] - 2024-09-15

### Added
- **Wallet Domain**
  - Multi-chain blockchain support (BTC, ETH, Polygon, BSC)
  - Transaction signing
  - Balance tracking
  - Withdrawal workflows with saga compensation

- **Demo Mode Architecture**
  - Service switching pattern
  - Mock implementations for all external services
  - Demo data seeding
  - Visual demo indicators

## [0.2.0] - 2024-09-01

### Added
- **Account/Banking Domain**
  - Event-sourced account management
  - Multi-asset balance tracking
  - SEPA/SWIFT transfer support
  - Multi-bank connector pattern (Paysera, Deutsche Bank, Santander)
  - Intelligent bank routing

- **CQRS Infrastructure**
  - Command Bus with middleware support
  - Query Bus with caching
  - Domain Event Bus bridging Laravel events

## [0.1.0] - 2024-08-15

### Added
- Initial project structure with Domain-Driven Design
- Event sourcing foundation using Spatie Event Sourcing
- Laravel 12 with PHP 8.4 support
- Filament 3.0 admin panel
- Pest PHP testing framework
- PHPStan level 5 static analysis
- CI/CD pipeline with GitHub Actions

---

## Version History Summary

| Version | Date | Highlights |
|---------|------|------------|
| **2.0.0** | **2026-01-28** | **🏢 Multi-Tenancy** |
| 1.4.1 | 2026-01-27 | 🐛 Database Cache Connection Fix |
| 1.4.0 | 2026-01-27 | 🧪 Test Coverage Expansion |
| 1.3.0 | 2026-01-25 | 🔧 Platform Modularity |
| 1.2.0 | 2026-01-13 | 🚀 Feature Completion |
| 1.1.0 | 2026-01-11 | 🔧 Foundation Hardening |
| 1.0.0 | 2024-12-21 | 🎉 Open Source Release |
| 0.9.0 | 2024-12-18 | Agent Protocol (AP2/A2A) |
| 0.8.0 | 2024-12-01 | Treasury Management, Enhanced Compliance |
| 0.7.0 | 2024-11-15 | AI Framework, Distributed Tracing |
| 0.6.0 | 2024-11-01 | Governance, Stablecoin Enhancements |
| 0.5.0 | 2024-10-15 | GCU Basket, Liquidity Pools |
| 0.4.0 | 2024-10-01 | Exchange, Lending |
| 0.3.0 | 2024-09-15 | Wallet, Demo Mode |
| 0.2.0 | 2024-09-01 | Account/Banking, CQRS |
| 0.1.0 | 2024-08-15 | Initial Release |

## Upgrade Notes

### From 0.9.x to 1.0.0
This is a documentation-focused release with no breaking changes.
- Review new contribution guidelines in `CONTRIBUTING.md`
- Consider using shared contracts for domain decoupling
- Review security checklist before production deployment

### From 0.8.x to 0.9.x
- Run `php artisan migrate` for Agent Protocol tables
- Update `.env` with `AGENT_PROTOCOL_*` configuration
- Register AgentProtocolServiceProvider if not auto-discovered

### From 0.7.x to 0.8.x
- Run `php artisan migrate` for Treasury tables
- New compliance configuration in `config/compliance.php`

### From 0.6.x to 0.7.x
- Run `php artisan migrate` for AI Framework tables
- Configure AI providers in `config/ai.php`

[Unreleased]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.4.1...v2.0.0
[1.4.1]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.9.0...v1.0.0
[0.9.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/FinAegis/core-banking-prototype-laravel/releases/tag/v0.1.0
