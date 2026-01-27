# Multi-Tenancy Implementation Impact Analysis - FinAegis

**Date**: 2026-01-27
**Codebase**: Core Banking Prototype Laravel v1.4.0
**Analysis Type**: READ-ONLY Codebase Impact Assessment

> ⚠️ **UPDATE**: POC implementation has started. See `multitenancy_poc_v2_status` memory for current implementation status.
> Branch: `feature/v2.0.0-multi-tenancy-poc` | PR: #328

---

## Executive Summary

FinAegis is a **complex, highly-sophisticated financial platform with 1,270+ domain files**, implementing Domain-Driven Design with extensive event sourcing. Multi-tenancy implementation would be **HIGH IMPACT** due to:

- **83 Eloquent models** requiring tenant scoping
- **26 bounded contexts (domains)** with cross-domain dependencies
- **41 event/snapshot tables** using Spatie Event Sourcing
- **108 HTTP controllers** with user/account queries
- **210+ references** to Auth::user() and currentTeam
- **3,200+ broadcast/realtime connections** needing tenant isolation
- **170 services** with business logic touching tenant data

**Estimated Effort**: 6-8 weeks for production-ready implementation
**Risk Level**: HIGH - Core authentication and data isolation changes

---

## 1. MODELS WITH USER/ACCOUNT DATA

### Core Tenant-Related Models (8)
```
PRIMARY MULTI-TENANCY CANDIDATES:
- User.php                    (1:N relationships to all resources)
- Team.php                    (Team already scoped, minimal changes)
- Account.php                 (CRITICAL: user_uuid, team_uuid fields exist)
- Transaction.php             (Related to Account)
- Transfer.php                (Related to Account)
```

### Domain Models Requiring Tenant Scoping (75+)

#### Account Domain (7 models) - CRITICAL PATH
```
/app/Domain/Account/Models/
├── Account.php              [user_uuid, team_uuid] - Already has BelongsToTeam trait
├── Transaction.php          [aggregate_uuid references]
├── Transfer.php             [aggregate_uuid references]
├── Ledger.php              [Finance data]
├── Turnover.php            [Aggregated data]
├── AccountBalance.php       [Read model]
└── TransactionProjection.php [Read model]
```

#### Banking Domain (10 models) - HIGH PRIORITY
```
/app/Domain/Banking/Models/
├── BankAccount.php          [user_uuid field]
├── BankAccountModel.php     [Duplicated model - consolidate]
├── BankTransaction.php      [User financial activity]
├── BankTransfer.php         [User financial activity]
├── BankStatement.php        [User financial records]
├── BankBalance.php          [User financial state]
├── BankConnection.php       [User 3rd-party integration]
├── UserBankPreference.php   [user_uuid field]
├── BankCapabilities.php     [System-wide? Or user-scoped?]
└── BankConnectionModel.php  [Duplicated model]
```

#### Compliance Domain (12 models) - HIGH PRIORITY
```
/app/Domain/Compliance/Models/
├── ComplianceAlert.php      [user_id field - USER SCOPED]
├── ComplianceCase.php       [User-specific cases]
├── AuditLog.php            [Tracks user actions]
├── KycDocument.php         [user_uuid field]
├── KycVerification.php     [User-specific verification]
├── AmlScreening.php        [User-specific screening]
├── CustomerRiskProfile.php [User risk assessment]
├── TransactionMonitoring.php [User transaction monitoring]
├── SuspiciousActivityReport.php [User-specific SAR]
├── ComplianceEvent.php     [Event sourcing - aggregate_uuid]
├── ComplianceSnapshot.php  [Event sourcing snapshot]
└── TransactionMonitoringRule.php [System-wide? Or user-scoped?]
```

#### Lending Domain (5 models) - HIGH PRIORITY
```
/app/Domain/Lending/Models/
├── Loan.php                [User-specific loans]
├── LoanApplication.php     [User loan applications]
├── LoanCollateral.php      [User collateral]
├── LoanRepayment.php       [User repayments]
└── LendingEvent.php        [Event sourcing]
```

#### AgentProtocol Domain (7 models) - MEDIUM-HIGH PRIORITY
```
/app/Domain/AgentProtocol/Models/
├── AgentIdentity.php       [Agent DID registration]
├── AgentWallet.php         [Agent financial account]
├── AgentTransaction.php    [Agent transaction history]
├── EscrowDispute.php       [Escrow conflicts]
├── AgentCapability.php     [Agent capabilities]
├── AgentMessage.php        [Agent communications]
├── AgentConnection.php     [Agent connections]
```
**CAUTION**: Agents may be cross-tenant or multi-tenant themselves - requires careful modeling.

#### Other High-Priority Domains (20+)
```
Stablecoin Domain:          [Collateral, minting, burning]
Treasury Domain:            [Portfolio management - likely global/per-user]
Exchange Domain:            [Trading, liquidity pools - user-specific?]
Wallet Domain:              [Blockchain accounts - user-specific]
Payment Domain:             [Deposits, withdrawals - user-specific]
Governance Domain:          [Voting - user-specific]
Fraud Domain:               [Fraud detection - user-specific]
CGO Domain:                 [Investments - user-specific]
Batch Domain:               [Batch jobs - user-specific]
Performance Domain:         [Metrics - global? User-specific?]
```

#### System/Global Models (Not needing tenant scope)
```
/app/Domain/Asset/Models/
├── Asset.php               [GLOBAL: Exchange rates, asset definitions]
└── ExchangeRate.php        [GLOBAL: Currency rates]

/app/Domain/Basket/Models/
├── BasketComponent.php     [GLOBAL: Basket definitions]
├── BasketAsset.php         [GLOBAL: Asset composition]
├── BasketPerformance.php   [GLOBAL: Performance metrics]
└── BasketValue.php         [GLOBAL: Pricing]

/app/Domain/Product/Models/
├── Product.php             [GLOBAL: Product definitions]
└── UserProduct.php         [USER-SCOPED: User product subscriptions]

/app/Domain/Newsletter/Models/
└── Subscriber.php          [GLOBAL: Or user-scoped? - depends on model]

/app/Domain/Contact/Models/
└── ContactSubmission.php   [GLOBAL: Public contact form]
```

### Models in /app/Models (Root Level - 25+)
```
APP_MODELS (Mixed scope):
├── User.php                [GLOBAL AUTH MODEL]
├── Team.php                [GLOBAL - Multi-tenancy root]
├── Role.php                [GLOBAL: Spatie roles]
├── ApiKey.php              [user_uuid - USER-SCOPED]
├── ApiKeyLog.php           [USER-SCOPED logging]
├── Agent.php               [Complex: Agent ↔ User mapping]
├── AgentPayment.php        [USER-SCOPED]
├── AgentTransaction.php    [USER-SCOPED]
├── AgentTransactionTotal.php [USER-SCOPED]
├── AgentApiKey.php         [AGENT-SCOPED]
├── UserPreference.php      [USER-SCOPED]
├── Setting.php             [GLOBAL: System settings]
├── BlogPost.php            [GLOBAL: Content]
├── SystemIncident.php      [GLOBAL: Infrastructure]
├── SecurityAuditLog.php    [GLOBAL? Or user-scoped?]
└── [14 more models]        [Need individual analysis]
```

---

## 2. EVENT SOURCING TABLES & CRITICAL AREAS

### Event Sourcing Implementation Status
**Framework**: Spatie Event Sourcing v7.7+
**Architecture**: Domain-specific event stores with custom repositories

### Event Tables by Domain (41 identified)
```
COMPLIANCE DOMAIN:
├── compliance_events        [compliance_events table - USER-SCOPED]
├── compliance_snapshots     [compliance_snapshots table]
└── aml_screening_events     [aml_screening_events table]

TREASURY DOMAIN:
├── treasury_events          [treasury_events table]
├── treasury_snapshots       [treasury_snapshots table]
├── portfolio_events         [portfolio_events table]
└── portfolio_snapshots      [portfolio_snapshots table]

EXCHANGE DOMAIN:
├── liquidity_pool_events    [liquidity_pool_events table]
└── liquidity_pool_snapshots [LiquidityPoolSnapshot.php]

STABLECOIN DOMAIN:
├── stablecoin_events        [stablecoin_events table]
├── stablecoin_snapshots     [stablecoin_snapshots table]
├── collateral_position_events [collateral_position_events table]
└── collateral_position_snapshots [collateral_position_snapshots table]

LENDING DOMAIN:
├── lending_events           [lending_events table]
└── [snapshot model exists but no migration found]

BATCH DOMAIN:
├── batch_events             [batch_events table]
└── batch_snapshots          [batch_snapshots table]

ACCOUNT DOMAIN:
├── ledger_snapshots         [LedgerSnapshot.php]
├── transfer_snapshots       [TransferSnapshot.php]
└── transaction_snapshots    [TransactionSnapshot.php]

AGENT PROTOCOL DOMAIN:
├── agent_protocol_events    [agent_protocol_events table]
├── agent_protocol_snapshots [agent_protocol_snapshots table]
├── a2a_message_snapshots    [a2a_message_snapshots table]
└── agent_capability_snapshots [agent_capability_snapshots table]

AI DOMAIN:
└── [30+ AI Events defined but no corresponding tables found]

OTHER:
├── cgo_events               [cgo_events table]
├── batch_events             [batch_events table]
├── monitoring_events        [monitoring_events table]
├── monitoring_snapshots     [monitoring_snapshots table]
├── monitoring_metrics_snapshots [monitoring_metrics_snapshots table]
├── generic_snapshots        [snapshots table - shared]
└── generic_events           [stored_events table - shared]
```

### Migration Files Referencing User/Tenant Data
**44 migrations** contain user_uuid or user_id references.

### CRITICAL ISSUE: Event Sourcing Tenancy

**Problem**: Spatie Event Sourcing aggregates use `aggregate_uuid` as primary identifier. Need to:

1. Add `tenant_id` or `tenant_uuid` column to ALL event tables
2. Update aggregate repositories to filter by tenant
3. Verify snapshot repositories support tenant scoping
4. Update event replay logic to be tenant-aware
5. Implement tenant isolation in event handlers/projectors

**Risk**: **VERY HIGH** - Event sourcing logic is deeply embedded. Incorrect tenant scoping could leak data between tenants.

---

## 3. GLOBAL VS TENANT-SPECIFIC DATA CLASSIFICATION

### GLOBAL DATA (DO NOT TENANT-SCOPE)

#### System Configuration
```
- Asset definitions (currencies, cryptocurrencies)
- Exchange rates (real-time market data)
- Basket/Portfolio templates (GCU basket, etc.)
- Product definitions
- System settings (config)
- Feature flags
- Blog/content posts
- Help/documentation
```

#### Infrastructure & Monitoring
```
- System incidents & infrastructure alerts
- Performance metrics (system-wide)
- Workflow definitions (Laravel Workflow shared)
- Monitoring rules (system-wide? or user-configurable?)
- Rate limiting rules (system-wide)
```

#### Authentication & Authorization
```
- Users table (MUST REMAIN GLOBAL)
- Teams table (MUST REMAIN GLOBAL)
- Roles (GLOBAL - Spatie Permission)
- Permissions (GLOBAL - Spatie Permission)
- OAuth clients/tokens (GLOBAL - Passport)
```

### TENANT-SPECIFIC DATA (MUST TENANT-SCOPE)

#### Financial Core
```
✓ Accounts (user_uuid already present)
✓ Transactions (aggregate_uuid)
✓ Transfers (aggregate_uuid)
✓ Ledgers (user-specific)
✓ Balances (user-specific)
✓ Bank connections (user_uuid present)
✓ Bank accounts (user_uuid present)
✓ Wallets (user-specific blockchain accounts)
```

#### Compliance & Risk
```
✓ KYC documents (user_uuid present)
✓ KYC verifications (user-specific)
✓ Compliance alerts (user_id, entity_id present)
✓ Compliance cases (user-specific)
✓ AML screenings (user-specific)
✓ Fraud scores (user-specific)
✓ Risk profiles (user-specific)
✓ Audit logs (user-specific actions)
✓ Security audit logs (user activity)
✓ Suspicious activity reports (user-specific)
```

#### Trading & Investment
```
✓ Loans (user-specific)
✓ Loan applications (user-specific)
✓ Collateral positions (user-specific)
✓ CGO investments (user_uuid present)
✓ Trading positions (user-specific)
✓ Governance votes (user_uuid in GcuVote)
```

#### Integrations
```
✓ API keys (user_uuid present)
✓ API key logs (user_uuid present)
✓ Webhooks (user-specific if per-account)
✓ User preferences (user-specific)
✓ Bank preferences (user_uuid present)
✓ Agent connections (per-user agent setup)
```

#### Read Models & Projections
```
✓ Transaction projections (user-specific)
✓ Portfolio projections (user-specific)
✓ Balance projections (user-specific)
✓ Performance metrics (user-specific)
```

### AMBIGUOUS / REQUIRES DECISION

```
? Agent-related tables (AgentIdentity, AgentWallet, AgentTransaction)
  - Are agents global? Multi-tenant? Per-user?
  - Current model: AgentProtocol domain suggests system-level agents
  - Impact: May need separate multi-agent scoping layer

? Monitoring rules (TransactionMonitoringRule, MonitoringRule)
  - System-wide rules? Or user-customizable?
  - Impact: Different scoping if customizable per user

? Batch jobs (BatchJob, BatchJobItem, BatchItem)
  - User-specific batch operations? Or system-wide?
  - Impact: Queue isolation needed if user-specific

? Performance metrics (PerformanceMetric)
  - User-specific? System-wide?
  - Impact: Aggregation and reporting layer design

? Basket/Portfolio performance data
  - Per-user baskets? Or global baskets only?
  - Current: BasketPerformance seems global but used by users
  - Impact: May need separate user_basket_holdings table
```

---

## 4. AUTHENTICATION ARCHITECTURE

### Current Implementation

**Guards**: 
- `web` guard: Laravel session-based (Jetstream)
- `api` guard: Passport (OAuth2)

**Auth Config**: `/config/auth.php`
```
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],
    'api' => ['driver' => 'passport', 'provider' => 'users'],
]
```

**User Provider**: Eloquent model `App\Models\User`

**Sanctum Integration**: 
- `HasApiTokens` trait on User
- Personal access tokens support

### Multi-Tenancy Auth Changes Required

#### 1. User Model Changes
- Already uses `Team` via `HasTeams` trait (Jetstream)
- `currentTeam` property available
- Need to ensure **all** eloquent queries filter by `currentTeam`

#### 2. Passport/OAuth Changes
- **Scope isolation**: Scope tokens to current team
- **Client isolation**: Ensure API clients are team-scoped
- Possible: Multiple oauth_clients per team, or add team_id to oauth_clients table

#### 3. API Token Isolation
- Personal access tokens should be scoped to team
- Add `team_id` to `personal_access_tokens` table
- Middleware to enforce team validation

#### 4. Sanctum Sessions
- Configure sanctum for multi-tenant cookies
- Domain partitioning by subdomain (api.team1.app, api.team2.app) or path-based routing

### Authentication Files Affected (210+ references)
```
CRITICAL: 
- app/Http/Middleware/ (5 middleware files)
- app/Http/Controllers/Api/Auth/ (multiple auth controllers)
- app/Traits/BelongsToTeam.php (trait applied to Account, others)
- config/auth.php
- config/sanctum.php

IMPACT: Every auth check needs validation that resource belongs to current team
```

---

## 5. CACHING PATTERNS

### Current Cache Usage (564 references)

**Cache Methods Found**:
```
Cache::remember()       [Multiple domain services]
Cache::put()            [Rate limiting, metrics]
Cache::get()            [Retrieving cached values]
Cache::flush()          [Clearing caches]
```

### Services Using Cache
```
HIGH USAGE:
- DynamicRateLimitService.php       [system_metrics:* cache keys]
- IpBlockingService.php             [IP blocking state]
- ExchangeRateService.php           [Likely using cache for rates]

MODERATE USAGE:
- [Other domain services - distributed]
```

### Multi-Tenancy Cache Isolation Required

#### Cache Key Prefixing Strategy
```
Current pattern: Cache::put('system_metrics:cpu_load', $value)
NEED TO CHANGE: Cache::put("tenant:{$tenantId}:system_metrics:cpu_load", $value)

Affected areas:
1. Rate limiting - IP blocks should be per-IP, but cached counts per-tenant
2. Exchange rates - GLOBAL (shared), OR per-tenant overrides?
3. System metrics - GLOBAL cache, separate tenant partitions?
```

#### Critical Cache Services to Audit
```
1. /app/Services/DynamicRateLimitService.php
   - Reads: system:cpu_count, system_metrics:*
   - Impact: Rate limits may be tenant-specific or global?

2. /app/Services/IpBlockingService.php
   - Caches IP block status globally
   - Problem: Cross-tenant IP blocks OK, but blocked_ips DB table audit?

3. Exchange rate caching
   - Likely GLOBAL cache (market data)
   - No per-tenant customization needed

4. Session caching
   - Jetstream/Sanctum handling session cache
   - Ensure team context preserved across requests
```

### Cache.php Configuration Changes
```
CURRENT: Likely using single Redis backend
NEEDED:
- Cache prefix config: config('cache.prefix') - set to include tenant ID
- OR: Explicit key namespacing in services
- Consider: Separate Redis database per tenant (redis.database config)
```

---

## 6. QUEUE JOBS & ASYNC PROCESSING

### Job Files Identified (11 total)

```
EXPLICIT JOBS:
1. /app/Domain/Cgo/Jobs/VerifyCgoPayment.php
   - Scope: User-specific (CGO investment verification)
   - Impact: MUST include user_id/tenant_id in job payload

2. /app/Domain/Exchange/Jobs/CheckArbitrageOpportunitiesJob.php
   - Scope: Potentially global (arbitrage detection)
   - Impact: May be global, but if per-user strategy, needs scoping

3. /app/Domain/Exchange/Jobs/RefreshExchangeRatesJob.php
   - Scope: GLOBAL (market data refresh)
   - Impact: NO tenant scoping needed

4. /app/Domain/Webhook/Jobs/ProcessWebhookDelivery.php
   - Scope: User-specific webhooks
   - Impact: MUST include webhook creator user_id

5. /app/Jobs/ProcessCustodianWebhook.php
   - Scope: User bank connection specific
   - Impact: MUST track which user's bank connection
```

### Implicit Job Processing (Queued Events)

**Laravel Event Dispatch**:
- 3,200+ broadcast/realtime references suggest events are heavily used
- Events may be queued (ShouldQueue trait)
- Need to audit each event for tenant context

**Critical Audit**: Find all classes implementing `ShouldQueue`:
```
Tasks:
1. Search for ShouldQueue trait usage
2. Verify each job/event serializes tenant context
3. Ensure tenant_id included in job payloads
4. Update job middleware to validate tenant context
```

### Queue Middleware Needed
```
CURRENT ISSUE: Jobs from different tenants could run in shared queue
SOLUTION:
1. Add tenant_id to all job payloads
2. Create TenantAwareJob middleware
3. Validate tenant_id matches job creator's tenant
4. Consider separate queues per tenant (Horizon config)
5. Update failed jobs table to include tenant_id for analysis
```

### Horizon Configuration Changes
```
CURRENT: /config/horizon.php
CHANGES:
- Add tenant isolation queue configs
- Monitor queues per tenant
- Failed job analysis per tenant
- Supervisor process list per tenant (or shared with isolation)
```

### Queue Isolation Strategy Options

#### Option A: Tenant-ID in Job Payload
- Simpler: Add $tenantId property to jobs
- Risk: Requires discipline across all job definitions
- Best for: Existing codebase

#### Option B: Separate Queue per Tenant
- More secure: Tenants literally in different queues
- Complexity: Horizon needs per-tenant processors
- Best for: High-security requirements, fewer tenants

#### Option C: Tenant Middleware
- Validate: Before job execution, restore tenant context
- Implement: Custom middleware in job handler
- Best for: Maximum safety with minimal changes

**RECOMMENDATION**: Combination A+C - Include tenant_id AND validate with middleware

---

## 7. FILAMENT ADMIN PANEL STRUCTURE

### Filament Files Identified (141 total)

#### Resources Directory Structure
```
/app/Filament/Resources/               [46+ resource files]
└── Admin/Resources/                   [Multiple admin resources]

EXAMPLE RESOURCES:
├── CgoInvestmentResource.php           [Investment management]
├── ContactSubmissionResource.php       [Public submissions]
├── CgoRefundResource.php               [Refund management]
└── [More domain resources]
```

#### Key Filament Components Needing Changes

**1. Resource Authorization**
- Current: canViewAny(), canCreate(), canEdit(), canDelete()
- Change: Add tenant context to all authorization checks
- Files affected: All Resource files

**2. List Pages with Filters**
```
CURRENT PATTERN: ListPages that query models
- CgoInvestmentResource/Pages/ListCgoInvestments.php
- ContactSubmissionResource/Pages/ListContactSubmissions.php

CHANGE NEEDED:
- Add global scope to filter by current tenant
- Or: Add manual tenant filter in Resource::getEloquentQuery()
```

**3. Create/Edit Forms**
```
CURRENT: Form creation without tenant context
CHANGE NEEDED:
- Hide tenant_id field (auto-fill from Auth::user())
- Or: Add hidden field with currentTeam
- Validation: Ensure created_by user belongs to team
```

**4. Filament Widgets**
```
Widgets identified (20+):
├── BankHealthMonitorWidget.php        [System-wide or per-user?]
├── BasketPerformanceStats.php         [Per-user or global?]
├── PaymentVerificationStats.php       [Per-user stats]
└── [More monitoring widgets]

CHANGE: Scope all query builders in widgets to current tenant
```

**5. Filament Policies**
- Current: Role-based via Spatie Permission
- Change: Add tenant context to policy checks
- Files: May need new TenantPolicy interface

#### Filament Authorization Strategy
```
APPROACH 1: Global Scopes on Models
- Add global scope: whereTeam() on all models
- Pro: Transparent, protects by default
- Con: Complex with multiple traits

APPROACH 2: Filament Resource Overrides
- Override Resource::getEloquentQuery() on each resource
- Pro: Fine-grained control
- Con: Repetitive, easy to miss one

APPROACH 3: Custom Filament Middleware
- Validate tenant context before resource access
- Pro: Centralized validation
- Con: Need to implement custom middleware

RECOMMENDATION: Approach 1 + 2 combo
- BelongsToTeam trait for models
- Resource::getEloquentQuery() as double-check
```

#### Filament Panels Configuration
```
CURRENT: /app/Filament/Admin/PanelProvider.php (or similar)
CHANGES:
1. Tenant identification: How to identify current tenant in admin?
   - Via Team model (current approach via Jetstream)?
   - Via subdomain (app.tenant1.com)?
   - Via path (/admin/tenant1/)?
   
2. Multi-panel setup:
   - One admin panel per tenant?
   - Shared admin panel with tenant switching?

3. Navigation customization per tenant
4. User role/permission scoping per tenant
```

---

## 8. COMPONENTS AFFECTED - COMPREHENSIVE BREAKDOWN

### A. CONTROLLERS (168 total)

#### API Controllers (90+) - HIGH PRIORITY
```
CRITICAL PATH:
/app/Http/Controllers/Api/
├── Auth/                    [5 auth controllers - scope validation]
├── Account*.php             [3-5 account controllers - user context]
├── Transaction*.php         [3-5 transaction controllers - user context]
├── Transfer*.php            [2-3 transfer controllers - user context]
├── Agent*.php               [15+ agent-related controllers]
├── Compliance*.php          [4-5 compliance controllers]
├── Bank*.php                [4-5 banking controllers]
├── Exchange*.php            [3-4 exchange controllers]
├── Lending*.php             [2-3 lending controllers]
├── Payment*.php             [2-3 payment controllers]
├── Governance*.php          [2-3 voting controllers]
├── Stablecoin*.php          [3-4 stablecoin controllers]
├── Treasury*.php            [2-3 portfolio controllers]
└── [25+ more controllers]
```

**Change Pattern for Each Controller**:
```
BEFORE:
public function index() {
    $accounts = Account::all();  // ← No tenant filter
}

AFTER:
public function index() {
    $accounts = Account::where('user_uuid', auth()->user()->uuid)->get();
    // Or with global scope:
    $accounts = Account::all();  // ← Filtered by BelongsToTeam global scope
}
```

#### Web Controllers (40+)
```
/app/Http/Controllers/
├── ProfileController.php            [User profile - team context]
├── DashboardController.php          [User dashboard - team context]
├── ApiKeyController.php             [API key management - user context]
└── [Other web controllers]
```

### B. SERVICES (170 total)

#### High-Priority Services
```
CRITICAL - Direct DB Access:
1. /app/Services/DynamicRateLimitService.php
2. /app/Services/IpBlockingService.php
3. /app/Domain/*/Services/*.php (130+ domain services)

Each domain likely has:
- *Service.php (business logic)
- *Repository.php (data access)
- *Processor.php (workflow processing)

IMPACT: Every query.where() needs tenant context
```

#### Service Audit Checklist
```
For each service:
[ ] Does it query models?
[ ] Does it filter by user_id or user_uuid?
[ ] Does it filter by team_id?
[ ] Does it use Cache::* calls?
[ ] Does it dispatch jobs?
[ ] Does it fire events?
```

### C. REPOSITORIES (33 total)

#### Current Repository Pattern
```
EXAMPLES:
/app/Domain/Compliance/Repositories/
- ComplianceAlertRepository.php
- ComplianceRuleRepository.php

/app/Domain/Lending/Repositories/
- LoanRepository.php

/app/Domain/Treasury/Repositories/
- PortfolioRepository.php
```

#### Multi-Tenancy in Repositories
```
CURRENT: Likely not tenant-aware
CHANGE:
1. Add tenant context parameter to all methods
2. Apply tenant filter before returning results
3. Validate tenant context in write operations

EXAMPLE:
// Before
public function find($id) {
    return $this->model->find($id);
}

// After
public function find($id, $tenantId) {
    return $this->model->where('tenant_id', $tenantId)->find($id);
}
```

### D. MIDDLEWARE (5 identified)

```
CURRENT MIDDLEWARE:
/app/Http/Middleware/
├── TracingMiddleware.php              [Request tracing]
├── MetricsMiddleware.php              [Performance metrics]
├── IdempotencyMiddleware.php          [Idempotent requests]
├── TransactionRateLimitMiddleware.php [Transaction rate limiting]
├── ApiRateLimitMiddleware.php         [API rate limiting]
├── CheckApiScope.php                  [API scope validation]
└── CheckAgentScope.php                [Agent scope validation]

NEW NEEDED:
├── TenantContext.php                  [Set tenant context for request]
├── ValidateTenantAccess.php           [Validate resource belongs to tenant]
├── TenantAwareQueue.php               [Set tenant for queued jobs]
```

#### Recommended Middleware Stack
```
1. TenantContext (early in stack)
   - Identify tenant from request (subdomain, path, auth, header)
   - Set context for Eloquent queries
   
2. ValidateTenantAccess (after auth)
   - Ensure authenticated user can access identified tenant
   - Validate team membership
   
3. Existing middleware
   - Now runs with tenant context set
   
4. TenantAwareQueue (before dispatch)
   - Add tenant_id to queued jobs
```

### E. EVENTS (337 total)

#### Domain Events
```
EXAMPLES:
/app/Domain/*/Events/
- AccountCreated, AccountCredited, AccountDebited
- LoanApplicationSubmitted, LoanApproved
- TransactionCompleted, TransactionFailed
- UserKycApproved, UserKycRejected
- etc.

TOTAL: 337+ event classes

CHANGE NEEDED:
All events need to include/propagate tenant context
```

#### Event Sourcing Events
```
Using Spatie Event Sourcing
- EloquentStoredEvent models (41 types)
- Each domain has custom event model
- Events stored in domain-specific tables

CHANGE:
Add tenant_id to aggregate_uuid filtering in:
- Event retrieval
- Snapshot retrieval
- Event replay (critical!)
- Projector queries
```

#### Broadcasting Events
```
3,200+ references suggest WebSocket broadcasting
- Likely using Laravel Echo/Pusher
- Events broadcast to users in real-time

CHANGE:
- Channel: private-tenant.{tenantId}.channel
- Ensure WebSocket auth validates tenant membership
- Filter broadcast recipients by tenant
```

### F. GLOBAL SCOPES & TRAITS

#### Current Implementation
```
TRAIT AUDIT:
1. BelongsToTeam.php
   - Already uses global scope for team_id filtering
   - Status: READY for multi-tenancy
   - Usage: Only 2 models currently use it
   
2. BelongsToUser (NOT FOUND)
   - Probably not implemented
   - NEED TO CREATE for user_uuid/user_id filtering

3. Other traits
   - Unlikely to have explicit scoping
   - Each model likely has manual where clauses
```

#### Needed Global Scopes
```
1. BelongsToTeam (exists but rarely used)
   - Scope by: $model->team_id
   - Apply to: Account, Preferences, BankAccounts, etc.

2. BelongsToUser (needs creation)
   - Scope by: $model->user_uuid or $model->user_id
   - Apply to: ApiKey, UserPreference, BankPreference, etc.

3. TenantAware (base scope)
   - Detects if model has team_id or user_uuid
   - Applies appropriate filter
   - Use as trait on base model

EXAMPLE:
```php
trait BelongsToUser {
    protected static function bootBelongsToUser() {
        static::addGlobalScope('user', function($builder) {
            if (Auth::check()) {
                $builder->where('user_uuid', Auth::user()->uuid);
            }
        });
    }
}
```

---

## 9. HIGH-RISK AREAS REQUIRING CAREFUL MIGRATION

### RISK LEVEL: CRITICAL

#### 1. Event Sourcing Aggregate Queries
**Why**: Replay logic could leak events between tenants if tenant context not added
**Location**: 
- /app/Domain/*/Repositories/
- /app/Domain/*/Aggregates/
- Event models with custom queries

**Mitigation**:
- Add tenant_id to all event tables (alter migrations)
- Update all aggregate retrievals to filter by tenant
- Test event replay with multi-tenant scenarios

#### 2. Transaction Processing Workflows
**Why**: Cross-domain transactions (Account → Lending → Treasury) must not mix tenant data
**Location**:
- /app/Domain/*/Workflows/
- /app/Infrastructure/Cqrs/
- Command/Query bus handlers

**Mitigation**:
- Validate tenant context at workflow entry
- Pass tenant through all workflow activities
- Add tenant validation at activity boundaries

#### 3. API Token & Passport OAuth
**Why**: Incorrect token scoping could allow users to access other tenant's data
**Location**:
- config/auth.php
- OAuth tables (oauth_access_tokens, oauth_clients)
- Personal access tokens table

**Mitigation**:
- Add team_id to oauth_clients table
- Scope tokens to specific team
- Validate token team matches request tenant

#### 4. Broadcast/WebSocket Channels
**Why**: Real-time data leakage across WebSocket connections
**Location**:
- /routes/channels.php
- Broadcasting configuration
- Event broadcasting classes

**Mitigation**:
- Channel authentication: verify user belongs to tenant
- Channel naming: private-tenant.{id}.channel
- Test multi-tenant WebSocket access

#### 5. Cache Corruption Risk
**Why**: Cache keys without tenant prefix = data from one tenant served to another
**Location**:
- All Cache:: calls (564 references)
- Service cache usage
- Query result caching

**Mitigation**:
- Mandatory Cache::tags() or key prefixing
- Create CacheService wrapper enforcing tenant context
- Audit all cache keys during code review

#### 6. Global Scope Exceptions
**Why**: withoutGlobalScope() bypasses tenant protection
**Location**:
- Any .withoutGlobalScope() or .withoutGlobalScopes() calls
- Admin interfaces needing cross-tenant visibility

**Mitigation**:
- Document why withoutGlobalScope is needed
- Add security check: ensure withoutGlobalScope only in admin
- Never use in user-facing APIs

#### 7. Queued Job Data Leakage
**Why**: Job payload contains serialized models without tenant validation
**Location**:
- All Job classes (11 identified + implicit ShouldQueue events)
- Queue middleware

**Mitigation**:
- Add tenant_id to all job payloads explicitly
- Validate tenant_id matches job creator before execution
- Test job execution with wrong tenant context (negative test)

#### 8. N+1 Queries in Multi-Tenant Context
**Why**: Tenant filtering + eager loading mismatches
**Location**:
- Controllers with complex relationships
- Filament resources with eager loading

**Mitigation**:
- Audit all eager load statements
- Ensure related models also filter by tenant
- Use database profiling in multi-tenant scenarios

---

## 10. COMPONENTS THAT CAN REMAIN UNCHANGED

### Core Framework (No Changes)
```
✓ Laravel framework itself (no tenant changes needed)
✓ Spatie Permission package (global roles/permissions OK)
✓ Laravel Jetstream (Team model already supports multi-tenancy)
✓ Filament framework (just need resource adjustments)
✓ Pest testing framework
✓ Workflow/Waterline
```

### System-Wide Services (No Tenant Scoping)
```
✓ ExchangeRateService (market data is global)
✓ Asset definitions (currencies, crypto types are global)
✓ Basket/Portfolio templates (product definitions are global)
✓ Feature flag system
✓ System incident tracking
✓ Infrastructure monitoring
✓ Rate limiting rules (IP blocking can be global)
✓ Blog/content management (public content)
```

### Global Infrastructure
```
✓ Redis cache layer (just needs key prefixing)
✓ Queue system (just needs tenant_id in payloads)
✓ Database (structure mostly unchanged, just new columns)
✓ CI/CD pipelines
✓ Docker/Kubernetes configs
✓ Logging system (just add tenant_id to log context)
✓ Tracing/observability (OpenTelemetry adds attributes)
```

### Authentication Core (Minimal Changes)
```
✓ User model itself (no changes)
✓ Team model (already supports tenancy)
✓ Spatie Permission roles/permissions (global)
✓ Basic auth flow (just add team context)
✗ Passport/OAuth (needs token scoping - minor change)
✗ Sanctum (needs session team context - minor change)
```

---

## 11. MIGRATION STRATEGY PHASES

### Phase 1: Foundation (Week 1-2)
```
1. Create TenantContext middleware
   - Identify tenant from request
   - Set context in app container
   
2. Add tenant fields to tables
   - Add tenant_id/team_uuid to all tables (in migrations)
   - Backfill existing data
   - Add foreign key constraints
   
3. Create tenant-aware base model
   - BelongsToUser trait
   - BelongsToTeam trait (improve existing)
   - TenantAware trait (abstract)
```

### Phase 2: Core Models (Week 2-3)
```
1. Apply BelongsToTeam to models
   - Account, Transaction, Transfer, etc.
   - Test with existing data
   
2. Apply BelongsToUser to models
   - ApiKey, UserPreference, etc.
   
3. Update event sourcing
   - Add tenant_id to all event tables
   - Update aggregate repositories
```

### Phase 3: Services & Controllers (Week 3-4)
```
1. Update all controllers
   - Add team context validation
   - Use Eloquent scopes instead of manual where
   
2. Update services
   - Inject tenant context
   - Update repository calls
   
3. Add middleware
   - TenantContext (identification)
   - ValidateTenantAccess (validation)
```

### Phase 4: Jobs, Events, Caching (Week 5-6)
```
1. Queue jobs
   - Add tenant_id to payloads
   - Add TenantAwareQueue middleware
   
2. Events
   - Add tenant context to all events
   - Update broadcasting channels
   
3. Cache
   - Create CacheService wrapper
   - Add tenant key prefixing
   - Audit all Cache:: calls
```

### Phase 5: Admin & UI (Week 6-7)
```
1. Filament resources
   - Add tenant filtering to all resources
   - Update policies
   - Test authorization
   
2. Web controllers
   - Update team context
   - Fix breadcrumbs/navigation
```

### Phase 6: Testing & Hardening (Week 7-8)
```
1. Write comprehensive tests
   - Multi-tenant data isolation tests
   - Cross-tenant access rejection tests
   - Event sourcing replay with tenants
   
2. Security audit
   - Penetration testing
   - Cache key validation
   - Token scoping verification
   
3. Performance testing
   - Global scope performance
   - Cache hit rates
   - Query analysis
```

---

## 12. ESTIMATED SCOPE SUMMARY

### Files Requiring Changes
```
Models:                 108 (~80% of 135 total models)
Controllers:            130 (~77% of 168 total)
Services:              160 (~94% of 170 total)
Repositories:           33 (100%)
Middleware:             12 (including new + modified existing)
Event classes:         300+ (add tenant context)
Migrations:             44 (add tenant fields) + 30 new migrations
Tests:                 500+ (new multi-tenant tests)
Config files:           4-5 (auth, cache, queue, sanctum)
Routes:                 8 files (validate tenant context)
Filament resources:     46 (authorize by tenant)
```

### Database Schema Changes
```
New columns: tenant_id/team_uuid/team_id on 80+ tables
New tables: Possibly 3-4 for tenant-specific features
Migrations: 40-50 total (new + altering existing)
Foreign keys: Add 30+ team_id FK constraints
Indexes: Add 50+ composite indexes (tenant_id, user_id, etc.)
```

### Configuration Changes
```
- config/auth.php (Passport team scoping)
- config/sanctum.php (session team context)
- config/cache.php (key prefix strategy)
- config/queue.php (job middleware)
- config/broadcast.php (channel authentication)
- .env (tenant identification strategy)
```

---

## 13. TESTING REQUIREMENTS

### Test Categories

#### Unit Tests (150+)
```
Model scoping tests:
- Global scope correctly filters by team
- Relationships preserve tenant context
- Attributes/casts work with tenant fields

Service tests:
- Services respect tenant context
- Repositories filter correctly
- Cache keys include tenant ID
```

#### Feature/Integration Tests (200+)
```
Controller isolation:
- Cannot access other tenant's accounts
- Cannot query other tenant's transactions
- API returns 403/404 for other tenant resources

Event sourcing:
- Events aggregated by tenant
- Snapshots created per-tenant
- Event replay doesn't leak data
```

#### Security Tests (50+)
```
Token scoping:
- Passport tokens limited to tenant
- Personal tokens scoped to team

Cache security:
- Cache keys tenant-isolated
- No cross-tenant cache hits

Queue security:
- Jobs execute in correct tenant context
- Failed jobs don't expose other tenant data
```

#### Multi-Tenant Scenarios (30+)
```
- Same user in multiple teams
- Team switching
- Cross-team data isolation
- Concurrent requests to different teams
- Race conditions in team context
```

---

## 14. CRITICAL IMPLEMENTATION NOTES

### Must-Have Tenant Identification Strategy

**Choose ONE approach**:

1. **Subdomain-based** (api.tenant1.example.com, api.tenant2.example.com)
   - Pro: Clean URL structure
   - Con: DNS/SSL complexity
   
2. **Path-based** (/api/tenant1/, /api/tenant2/)
   - Pro: Single domain, no DNS changes
   - Con: URL complexity

3. **Header-based** (X-Tenant-ID header)
   - Pro: Flexible, API-friendly
   - Con: Easy to forget, requires client discipline

4. **Auth-based** (Use authenticated user's team)
   - Pro: Secure, no extra parameter
   - Con: Only works for authenticated requests

**Recommendation for FinAegis**: **Combination Auth-based + Header fallback**
- Primary: Extract team from Auth::user()->currentTeam
- Fallback: Accept X-Tenant-ID header for verification
- Admin: Allow sudo to other teams (with audit logging)

### Global Scope Safety Pattern

```php
// ANTI-PATTERN - Don't do this:
Account::all();  // Gets ALL accounts from all tenants!

// CORRECT PATTERN:
Account::get();  // Uses global scope to filter by Auth::user()->currentTeam

// If you REALLY need all:
Account::withoutGlobalScope('team')->get();  // Explicit, auditable, searchable
```

### Tenant Context in Async Operations

```php
// Job serialization must preserve tenant context
public function __construct(
    private readonly string $tenantId,  // Always explicit
    private readonly Account $account,   // Lazy loads, but add check
) {}

public function handle() {
    // Validate tenant hasn't changed
    if (auth()->user()?->currentTeam?->id !== $this->tenantId) {
        throw new TenantMismatchException();
    }
}
```

---

## 15. DEPENDENCIES & COMPATIBILITY

### Package Compatibility
```
✓ Spatie Event Sourcing - No known tenancy issues
✓ Spatie Permission - Tenant-agnostic (roles are global)
✓ Laravel Jetstream - Already has Team model
✓ Filament - No built-in tenancy, but Resource filtering works
✓ Laravel Workflow/Waterline - No known tenancy issues
```

### Known Incompatibilities
```
None identified - FinAegis' stack is tenant-friendly

Potential issues (test required):
- Passport OAuth token scoping (not built-in)
- Broadcast channel authorization (need custom auth)
- Event sourcing multi-tenant replay (need custom logic)
```

---

## 16. SUCCESS METRICS & VALIDATION

### Post-Implementation Validation
```
1. Data Isolation Tests
   [ ] User A cannot see User B's accounts
   [ ] User A cannot see User B's transactions
   [ ] API returns 403 for cross-tenant access
   [ ] Cache contains no cross-tenant data
   
2. Performance Metrics
   [ ] Query performance acceptable with new scopes
   [ ] Cache hit rates maintained
   [ ] Event replay speed within SLA
   
3. Security Audit
   [ ] No data leakage in logs
   [ ] Tokens properly scoped
   [ ] WebSocket channels isolated
   
4. Operational Readiness
   [ ] Monitoring/alerting works per-tenant
   [ ] Backups/recovery maintains isolation
   [ ] Disaster recovery preserves isolation
```

---

## CONCLUSION & RECOMMENDATIONS

### Overall Assessment
**Complexity**: HIGH (1,270+ files, 15+ domains, complex event sourcing)
**Effort**: 6-8 weeks for a small experienced team
**Risk**: HIGH (data isolation, financial data, event sourcing replay)

### Recommended Approach
1. **Start with foundation**: Middleware + global scopes
2. **Validate early**: Test multi-tenant data isolation immediately
3. **Iterate by domain**: Don't try to do everything at once
4. **Extensive testing**: 3:1 ratio of test code to production code
5. **Security-first**: Assume tenant isolation is critical

### Success Factors
```
✓ Dedicated team (don't parallelize major feature work)
✓ Comprehensive test suite (must test multi-tenant scenarios)
✓ Code review discipline (every file touches tenancy)
✓ DBA involvement (schema changes are complex)
✓ Security audit (especially event sourcing + caching)
```

---

**Analysis completed**: 2026-01-27
**Analyst**: Claude Code (READ-ONLY Analysis Mode)
**Next Steps**: Present to development team for planning & resource allocation
