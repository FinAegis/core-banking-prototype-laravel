# FinAegis Platform Architecture

**Version:** v7.18.1
**Last Updated:** 2026-11-24
**Status:** Open-source reference platform

This document describes the architecture of FinAegis and the design patterns it uses. FinAegis is an open-source reference platform, not a production bank. Zelta is the hosted non-custodial wallet product built on it.

The platform is split into 61 domain folders under `app/Domain/`. Some of them run the Zelta wallet in production, such as Wallet, Relayer, CardIssuance, Compliance and Subscription. Others are demo modules, such as the GCU demo (a reference implementation of a basket-referenced unit), Exchange trading, Lending, Stablecoin and Treasury. The production Zelta configuration disables the demo modules through `MODULES_DISABLED` and turns off the trading engine with `TRADING_ENABLED=false`.

The code samples in this document are simplified illustrations of the patterns. They are not copied from the codebase, so class and method names in them can differ from the real code.

## Table of Contents

- [System Overview](#system-overview)
- [Domain-Driven Design](#domain-driven-design)
- [Event Sourcing Architecture](#event-sourcing-architecture)
- [CQRS Implementation](#cqrs-implementation)
- [Multi-Asset Architecture](#multi-asset-architecture)
- [Workflow Orchestration](#workflow-orchestration)
- [Caching Strategy](#caching-strategy)
- [Security Architecture](#security-architecture)
- [API Design](#api-design)
- [Database Schema](#database-schema)
- [Performance Optimization](#performance-optimization)
- [Deployment Architecture](#deployment-architecture)

---

## System Overview

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                           Presentation Layer                        │
├─────────────────────────────────────────────────────────────────────┤
│  Web UI (Filament)  │  REST API  │  GraphQL (43 schemas)  │  CLI       │
├─────────────────────────────────────────────────────────────────────┤
│                          Application Layer                          │
├─────────────────────────────────────────────────────────────────────┤
│  Controllers  │  Commands  │  Queries  │  Event Handlers  │  Jobs   │
├─────────────────────────────────────────────────────────────────────┤
│                     Core Shared Domain Layer                       │
├─────────────────────────────────────────────────────────────────────┤
│  Account   │  Payment   │  Asset   │  Governance  │  Custodian      │
│  Domain    │  Domain    │ Domain   │   Domain     │   Domain        │
│            │            │          │              │  CGO Domain     │
│  Shared/CQRS │ Shared/Events │                                      │
├─────────────────────────────────────────────────────────────────────┤
│                    Sub-Product Domain Layer                         │
├─────────────────────────────────────────────────────────────────────┤
│  Exchange  │  Lending   │ Stablecoin │  Treasury  │  Basket         │
│  Domain    │  Domain    │   Domain   │   Domain   │   Domain        │
├─────────────────────────────────────────────────────────────────────┤
│                        Infrastructure Layer                         │
├─────────────────────────────────────────────────────────────────────┤
│  Database  │  Redis   │  Queue   │  Storage   │  External APIs      │
│  (MySQL)   │ (Cache)  │ System   │ (Files)    │ (Custodians)        │
│  CQRS/CommandBus │ CQRS/QueryBus │ Events/DomainEventBus           │
├─────────────────────────────────────────────────────────────────────┤
│ Blockchain │  Crypto  │  Market  │  Credit    │  Compliance         │
│   Nodes    │ Exchanges│  Data    │  Scoring   │  Services           │
└─────────────────────────────────────────────────────────────────────┘
```

### Core Principles

1. **Domain-Driven Design (DDD)** gives each domain clear boundaries and its own vocabulary.
2. **Event Sourcing** stores every change as an event. That gives a full audit trail and lets the system rebuild its state.
3. **CQRS** separates the write model from the read models. The command and query buses exist, but no domain routes its work through them yet.
4. **The saga pattern** coordinates steps that span several aggregates and undoes finished steps when a later one fails.
5. **Clean architecture** keeps dependencies pointing inward and separates concerns.

---

## Domain-Driven Design

### Domain Structure

```
app/Domain/
├── Account/
│   ├── Aggregates/         # LedgerAggregate, TransactionAggregate
│   ├── Events/             # AccountCreated, MoneyAdded, etc.
│   ├── Workflows/          # CreateAccount, Transfer, etc.
│   ├── Projectors/         # AccountProjector, TurnoverProjector
│   ├── Reactors/           # SnapshotReactors
│   └── Services/           # AccountService, CacheServices
├── Payment/
│   ├── Workflows/          # TransferWorkflow, BulkTransfer
│   ├── Services/           # TransferService, PaymentService
│   └── Repositories/
├── Asset/
│   ├── Models/             # Asset, ExchangeRate
│   ├── Aggregates/         # AssetTransactionAggregate
│   ├── Events/             # AssetBalanceAdded, AssetTransferred
│   ├── Services/           # ExchangeRateService
│   └── Workflows/          # AssetTransferWorkflow
├── Governance/
│   ├── Models/             # Poll, Vote
│   ├── Services/           # GovernanceService
│   ├── Enums/              # PollType, PollStatus
│   └── Strategies/         # VotingPowerStrategies
├── Custodian/
│   ├── Connectors/         # ICustodianConnector, MockBank
│   ├── Services/           # CustodianRegistry
│   ├── Events/             # CustodianEvents
│   ├── ValueObjects/       # TransactionReceipt
│   └── Workflows/          # CustodianTransferWorkflow
└── Cgo/
    ├── Models/             # CgoInvestment, CgoPricingRound
    ├── Events/             # InvestmentCreated, RefundRequested
    ├── Aggregates/         # RefundAggregate
    ├── Projectors/         # RefundProjector
    ├── Repositories/       # CgoEventRepository
    └── Services/           # CgoKycService, InvestmentAgreementService
```

### Bounded Contexts

#### Account Context
The Account context manages core banking accounts.
- **Entities**: Account, User, Transaction, Turnover
- **Aggregates**: LedgerAggregate, TransactionAggregate, TransferAggregate
- **Services**: AccountService

#### Payment Context
The Payment context moves money and handles deposits and withdrawals.
- **Entities**: Transfer, Transaction
- **Workflows**: TransferWorkflow, ProcessStripeDepositWorkflow, ProcessBankWithdrawalWorkflow
- **Services**: TransferService, PaymentService

#### Asset Context
The Asset context holds the asset list and the exchange rates for multi-asset balances.
- **Entities**: Asset, ExchangeRate, AccountBalance
- **Services**: ExchangeRateService

#### Governance Context
The Governance context runs polls and votes for the GCU demo. The votes are simulated.
- **Entities**: Poll, Vote
- **Services**: GovernanceService, VotingTemplateService

#### Custodian Context
The Custodian context connects external custodians through integration adapters; no partnership or endorsement implied.
- **Interfaces**: ICustodianConnector
- **Services**: CustodianRegistry, MultiCustodianTransferService, DailyReconciliationService

---

## Event Sourcing Architecture

### Event Store Design

```php
// Event Structure
interface ShouldBeStored
{
    public function getEventClass(): string;
    public function getEventAttributes(): array;
}

// Event with Hash Validation
interface HasHash
{
    public function getHash(): Hash;
    public function validateHash(): bool;
}

// Event with Money
interface HasMoney
{
    public function getMoney(): Money;
}
```

### Event Types

#### Core Banking Events
```php
class AccountCreated extends ShouldBeStored
{
    public function __construct(
        public readonly AccountUuid $accountUuid,
        public readonly string $name,
        public readonly Hash $hash
    ) {}
}

class MoneyAdded extends ShouldBeStored implements HasHash, HasMoney
{
    public function __construct(
        public readonly Money $money,
        public readonly Hash $hash
    ) {}
}

class MoneyTransferred extends ShouldBeStored implements HasHash
{
    public function __construct(
        public readonly AccountUuid $fromAccount,
        public readonly AccountUuid $toAccount,
        public readonly Money $money,
        public readonly Hash $hash
    ) {}
}
```

#### Multi-Asset Events
```php
class AssetBalanceAdded extends ShouldBeStored implements HasHash
{
    public function __construct(
        public readonly string $assetCode,
        public readonly int $amount,
        public readonly Hash $hash,
        public readonly ?array $metadata = []
    ) {}
}

class AssetTransferred extends ShouldBeStored implements HasHash
{
    public function __construct(
        public readonly AccountUuid $fromAccount,
        public readonly AccountUuid $toAccount,
        public readonly string $fromAsset,
        public readonly int $fromAmount,
        public readonly string $toAsset,
        public readonly int $toAmount,
        public readonly float $exchangeRate,
        public readonly Hash $hash
    ) {}
}
```

### Aggregate Implementation

```php
class LedgerAggregate extends AggregateRoot
{
    private AccountUuid $accountUuid;
    private int $balance = 0;
    private bool $frozen = false;
    
    public function credit(Money $money): self
    {
        $hash = Hash::make($this->accountUuid . $money->getAmount() . now());
        
        $this->recordThat(new MoneyAdded($money, $hash));
        
        return $this;
    }
    
    public function debit(Money $money): self
    {
        if ($this->balance < $money->getAmount()) {
            throw new InsufficientFundsException();
        }
        
        $hash = Hash::make($this->accountUuid . $money->getAmount() . now());
        
        $this->recordThat(new MoneySubtracted($money, $hash));
        
        return $this;
    }
    
    protected function applyMoneyAdded(MoneyAdded $event): void
    {
        $this->balance += $event->money->getAmount();
    }
    
    protected function applyMoneySubtracted(MoneySubtracted $event): void
    {
        $this->balance -= $event->money->getAmount();
    }
}
```

---

## CQRS Implementation

### Infrastructure Components

The platform has command, query and domain event buses with Laravel implementations. No command or query handler is registered in v7.18.1, so controllers still call services, aggregates and workflows directly. See [CQRS_IMPLEMENTATION.md](../04-API/CQRS_IMPLEMENTATION.md).

#### Command Bus (`app/Infrastructure/CQRS/LaravelCommandBus.php`)
- It runs a command synchronously or puts it on a queue.
- It can run several commands in one database transaction.
- It resolves handlers from the service container.

#### Query Bus (`app/Infrastructure/CQRS/LaravelQueryBus.php`)
- It can cache a query result for a set time.
- It can run several queries in one call.
- It builds cache keys from an MD5 hash of the query.
- It resolves handlers from the service container.

#### Domain Event Bus (`app/Infrastructure/Events/LaravelDomainEventBus.php`)
- It runs event handlers in order of priority.
- It can hold events during a transaction and dispatch or clear them afterwards.
- It can publish events asynchronously.
- It builds on Laravel's own event system.

### Command Side (Write Model)

```php
// Command Implementation
class CreateAccountCommand implements Command
{
    public function __construct(
        public readonly AccountUuid $accountUuid,
        public readonly string $name,
        public readonly string $userUuid,
    ) {}
}

// Command Handler
class CreateAccountHandler
{
    public function handle(CreateAccountCommand $command): void
    {
        $aggregate = LedgerAggregate::retrieve($command->accountUuid);
        $aggregate->create($command->name, $command->userUuid);
        $aggregate->persist();
    }
}

// Registration (illustration; DomainServiceProvider registers no handlers in v7.18.1)
$commandBus->register(
    CreateAccountCommand::class,
    CreateAccountHandler::class
);
```

### Query Side (Direct Event Store Queries)

```php
// Transaction History Service
class TransactionHistoryService
{
    public function getAccountHistory(string $accountUuid, array $filters = []): Collection
    {
        $eventClasses = [
            'App\Domain\Account\Events\MoneyAdded',
            'App\Domain\Account\Events\MoneySubtracted',
            'App\Domain\Account\Events\AssetBalanceAdded',
            'App\Domain\Account\Events\AssetTransferred',
        ];

        $events = DB::table('stored_events')
            ->where('aggregate_uuid', $accountUuid)
            ->whereIn('event_class', $eventClasses)
            ->orderBy('created_at', 'desc')
            ->get();

        return $events->map(function ($event) {
            return $this->transformEventToTransaction($event);
        });
    }

    private function transformEventToTransaction($event): array
    {
        $properties = json_decode($event->event_properties, true);
        
        return [
            'type' => $this->getTransactionType($event->event_class),
            'amount' => $properties['amount'] ?? $properties['money']['amount'],
            'asset_code' => $properties['assetCode'] ?? 'USD',
            'hash' => $properties['hash']['hash'],
            'created_at' => $event->created_at,
        ];
    }
}

// Selective Read Models (Only Where Aggregation is Needed)
class Turnover extends Model
{
    // Used for monthly/daily transaction summaries
    // This aggregates events, doesn't duplicate them
}
```

---

## Multi-Asset Architecture

### Asset Model Design

```php
class Asset extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    
    const TYPE_FIAT = 'fiat';
    const TYPE_CRYPTO = 'crypto';
    const TYPE_COMMODITY = 'commodity';
    
    protected $fillable = [
        'code',      // USD, EUR, BTC, XAU
        'name',      // US Dollar, Bitcoin, Gold
        'type',      // fiat, crypto, commodity
        'precision', // 2 for USD, 8 for BTC
        'is_active',
        'metadata'
    ];
}
```

### Multi-Asset Balance System

```php
class AccountBalance extends Model
{
    protected $fillable = [
        'account_uuid',
        'asset_code',
        'balance'
    ];
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_uuid', 'uuid');
    }
    
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_code', 'code');
    }
}

// Enhanced Account Model
class Account extends Model
{
    public function balances(): HasMany
    {
        return $this->hasMany(AccountBalance::class, 'account_uuid', 'uuid');
    }
    
    public function getBalance(string $assetCode): int
    {
        return $this->balances()
            ->where('asset_code', $assetCode)
            ->value('balance') ?? 0;
    }
    
    public function addBalance(string $assetCode, int $amount): void
    {
        $balance = $this->balances()->firstOrCreate(
            ['asset_code' => $assetCode],
            ['balance' => 0]
        );
        
        $balance->increment('balance', $amount);
    }
}
```

### Exchange Rate Service

```php
class ExchangeRateService
{
    public function getRate(string $from, string $to): ?ExchangeRate
    {
        return Cache::remember(
            "rate:{$from}:{$to}",
            300, // 5 minutes
            fn() => ExchangeRate::where('from_asset_code', $from)
                ->where('to_asset_code', $to)
                ->where('is_active', true)
                ->where('valid_at', '<=', now())
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->orderByDesc('valid_at')
                ->first()
        );
    }
    
    public function convertAmount(string $from, string $to, int $amount): int
    {
        if ($from === $to) {
            return $amount;
        }
        
        $rate = $this->getRate($from, $to);
        
        if (!$rate) {
            throw new ExchangeRateNotFoundException();
        }
        
        return (int) round($amount * $rate->rate);
    }
}
```

---

## Workflow Orchestration

### Saga Pattern Implementation

```php
class TransferWorkflow extends Workflow
{
    public function execute(
        AccountUuid $fromAccount,
        AccountUuid $toAccount,
        Money $money
    ): \Generator {
        try {
            // Step 1: Withdraw from source
            yield ChildWorkflowStub::make(
                WithdrawAccountWorkflow::class,
                $fromAccount,
                $money
            );
            
            // Compensation: Deposit back if later steps fail
            $this->addCompensation(fn() => 
                ChildWorkflowStub::make(
                    DepositAccountWorkflow::class,
                    $fromAccount,
                    $money
                )
            );
            
            // Step 2: Deposit to destination
            yield ChildWorkflowStub::make(
                DepositAccountWorkflow::class,
                $toAccount,
                $money
            );
            
        } catch (\Throwable $e) {
            // Execute compensations in reverse order
            yield from $this->compensate();
            throw $e;
        }
    }
}
```

### Multi-Asset Transfer Workflow

Asset transfers work since v7.18.1. Earlier releases stopped on a missing method before any balance moved (#1247).

```php
class AssetTransferWorkflow extends Workflow
{
    public function execute(
        AccountUuid $fromAccount,
        AccountUuid $toAccount,
        string $fromAsset,
        string $toAsset,
        int $amount
    ): \Generator {
        // Get exchange rate if cross-asset
        $exchangeRate = 1.0;
        $convertedAmount = $amount;
        
        if ($fromAsset !== $toAsset) {
            $rate = yield ActivityStub::make(
                GetExchangeRateActivity::class,
                $fromAsset,
                $toAsset
            );
            
            $exchangeRate = $rate;
            $convertedAmount = (int) round($amount * $rate);
        }
        
        // Execute the transfer
        yield ActivityStub::make(
            ExecuteAssetTransferActivity::class,
            $fromAccount,
            $toAccount,
            $fromAsset,
            $amount,
            $toAsset,
            $convertedAmount,
            $exchangeRate
        );
    }
}
```

---

## Caching Strategy

### Cache Layers

```php
// Account Cache Service
class AccountCacheService
{
    private const TTL_ACCOUNT = 3600;        // 1 hour
    private const TTL_BALANCE = 300;         // 5 minutes
    private const TTL_TRANSACTIONS = 1800;   // 30 minutes
    
    public function getAccount(string $uuid): ?Account
    {
        return Cache::remember(
            "account:{$uuid}",
            self::TTL_ACCOUNT,
            fn() => Account::with(['user', 'balances'])->find($uuid)
        );
    }
    
    public function getBalance(string $uuid, string $asset = 'USD'): int
    {
        return Cache::remember(
            "balance:{$uuid}:{$asset}",
            self::TTL_BALANCE,
            fn() => AccountBalance::where('account_uuid', $uuid)
                ->where('asset_code', $asset)
                ->value('balance') ?? 0
        );
    }
}
```

### Cache Invalidation

```php
class CacheManager
{
    public function invalidateAccount(string $accountUuid): void
    {
        $patterns = [
            "account:{$accountUuid}",
            "balance:{$accountUuid}:*",
            "transactions:{$accountUuid}:*",
            "turnover:{$accountUuid}:*"
        ];
        
        foreach ($patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                $this->forgetByPattern($pattern);
            } else {
                Cache::forget($pattern);
            }
        }
    }
    
    private function forgetByPattern(string $pattern): void
    {
        $keys = Redis::keys($pattern);
        if (!empty($keys)) {
            Redis::del($keys);
        }
    }
}
```

---

## Security Architecture

### Authentication & Authorization

```php
// API Authentication
class SanctumAuthenticationService
{
    public function authenticate(Request $request): ?User
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }
        
        return PersonalAccessToken::findToken($token)?->tokenable;
    }
}

// Authorization Policies
class AccountPolicy
{
    public function view(User $user, Account $account): bool
    {
        return $user->uuid === $account->user_uuid 
            || $user->hasRole('admin');
    }
    
    public function update(User $user, Account $account): bool
    {
        return $user->uuid === $account->user_uuid 
            || $user->hasPermission('accounts.update');
    }
}
```

### Cryptographic Security

```php
class Hash
{
    private string $value;
    
    public static function make(string $data): self
    {
        $hash = hash('sha3-512', $data . config('app.key'));
        return new self($hash);
    }
    
    public function toString(): string
    {
        return $this->value;
    }
    
    public function verify(string $data): bool
    {
        return hash_equals(
            $this->value,
            hash('sha3-512', $data . config('app.key'))
        );
    }
}
```

---

## API Design

### RESTful API Structure

```php
// Resource Controllers
class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = Account::query()
            ->when($request->user_uuid, fn($q, $uuid) => 
                $q->where('user_uuid', $uuid))
            ->paginate(15);
            
        return response()->json($accounts);
    }
    
    public function store(CreateAccountRequest $request): JsonResponse
    {
        $account = app(AccountService::class)->create(
            $request->validated()
        );
        
        return response()->json([
            'data' => $account,
            'message' => 'Account created successfully'
        ], 201);
    }
}
```

### API Versioning

```php
// Route Structure
Route::prefix('api/v1')->group(function () {
    Route::apiResource('accounts', AccountController::class);
    Route::apiResource('assets', AssetController::class);
    Route::apiResource('polls', PollController::class);
});

// Version Management
class ApiVersioningMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $version = $request->header('API-Version', 'v1');
        $request->merge(['api_version' => $version]);
        
        return $next($request);
    }
}
```

---

## Database Schema

### Core Tables

```sql
-- Accounts table with user relationships
CREATE TABLE accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    user_uuid CHAR(36) NOT NULL,
    name VARCHAR(255) NOT NULL,
    balance BIGINT NOT NULL DEFAULT 0, -- USD balance for compatibility
    frozen BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_user_uuid (user_uuid),
    INDEX idx_frozen (frozen)
);

-- Multi-asset balance support
CREATE TABLE account_balances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_uuid CHAR(36) NOT NULL,
    asset_code VARCHAR(10) NOT NULL,
    balance BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    UNIQUE KEY unique_account_asset (account_uuid, asset_code),
    FOREIGN KEY (account_uuid) REFERENCES accounts(uuid) ON DELETE CASCADE,
    FOREIGN KEY (asset_code) REFERENCES assets(code) ON DELETE RESTRICT,
    INDEX idx_account_asset (account_uuid, asset_code)
);

-- Asset definitions
CREATE TABLE assets (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('fiat', 'crypto', 'commodity', 'custom') NOT NULL,
    precision TINYINT UNSIGNED NOT NULL DEFAULT 2,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_type (type),
    INDEX idx_active (is_active)
);

-- Exchange rates
CREATE TABLE exchange_rates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_asset_code VARCHAR(10) NOT NULL,
    to_asset_code VARCHAR(10) NOT NULL,
    rate DECIMAL(20, 10) NOT NULL,
    bid DECIMAL(20, 10) NULL,
    ask DECIMAL(20, 10) NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'manual',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    expires_at TIMESTAMP NULL,
    valid_at TIMESTAMP NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    UNIQUE KEY unique_rate_time (from_asset_code, to_asset_code, valid_at),
    FOREIGN KEY (from_asset_code) REFERENCES assets(code),
    FOREIGN KEY (to_asset_code) REFERENCES assets(code),
    INDEX idx_rate_pair (from_asset_code, to_asset_code),
    INDEX idx_valid_at (valid_at),
    INDEX idx_active (is_active)
);
```

### Event Store Schema

```sql
-- Event sourcing table
CREATE TABLE stored_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aggregate_uuid CHAR(36) NOT NULL,
    aggregate_version INT UNSIGNED NOT NULL,
    event_class VARCHAR(255) NOT NULL,
    event_data JSON NOT NULL,
    meta_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_aggregate (aggregate_uuid),
    INDEX idx_aggregate_version (aggregate_uuid, aggregate_version),
    INDEX idx_event_class (event_class),
    INDEX idx_created_at (created_at)
);
```

---

## Performance Optimization

### Database Optimization

```php
// Query Optimization
class OptimizedAccountRepository
{
    public function findWithBalances(string $uuid): ?Account
    {
        return Account::select([
                'accounts.*',
                'users.name as user_name',
                'users.email as user_email'
            ])
            ->join('users', 'accounts.user_uuid', '=', 'users.uuid')
            ->with(['balances.asset:code,name,precision'])
            ->where('accounts.uuid', $uuid)
            ->first();
    }
    
    public function getBalanceSummary(string $uuid): array
    {
        return DB::table('account_balances')
            ->select([
                'asset_code',
                'balance',
                'assets.name',
                'assets.precision'
            ])
            ->join('assets', 'account_balances.asset_code', '=', 'assets.code')
            ->where('account_uuid', $uuid)
            ->where('assets.is_active', true)
            ->get()
            ->toArray();
    }
}
```

### Response Optimization

```php
// API Resource Optimization
class AccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'user' => new UserResource($this->whenLoaded('user')),
            'balances' => BalanceResource::collection(
                $this->whenLoaded('balances')
            ),
            'formatted_balance' => $this->getFormattedBalance(),
            'is_frozen' => $this->frozen,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
```

---

## Deployment Architecture

### Production Environment

The compose file below is an example layout for a self-hosted deployment. It is not the configuration of the hosted Zelta service.

```yaml
# Docker Compose Structure
version: '3.8'
services:
  app:
    image: finaegis/core-banking:latest
    environment:
      - APP_ENV=production
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis
      
  mysql:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=${DB_PASSWORD}
      - MYSQL_DATABASE=${DB_DATABASE}
    volumes:
      - mysql_data:/var/lib/mysql
      
  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
      
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
```

### Monitoring & Observability

```php
// Application Monitoring
class MetricsCollector
{
    public function recordTransaction(string $type, float $amount): void
    {
        app('metrics')->increment('transactions.count', [
            'type' => $type
        ]);
        
        app('metrics')->histogram('transactions.amount', $amount, [
            'type' => $type
        ]);
    }
    
    public function recordApiCall(string $endpoint, int $responseTime): void
    {
        app('metrics')->histogram('api.response_time', $responseTime, [
            'endpoint' => $endpoint
        ]);
    }
}
```

---

## Conclusion

FinAegis combines event sourcing, sagas and domain-driven design in one Laravel application. The sections below sum up what that gives.

### Strengths
- Event sourcing keeps a full audit trail and lets the system rebuild its state.
- Command and query buses are in place for separating writes from reads, although no domain uses them yet.
- Balances can be held in several asset types.
- Sagas undo finished steps when a multi-step operation fails.
- Clear domain boundaries keep concerns apart and make the code easier to test.

### Scalability Features
- The application is stateless, so it can run on several servers.
- Redis caches data on several layers.
- Tables are indexed for the main queries.
- Heavy work runs in queued background jobs.

### Security Features
- Ledger events carry a SHA3-512 hash, so a changed event can be detected.
- API requests are authenticated with Sanctum bearer tokens.
- Access is controlled by roles and permissions.
- Operations are recorded in audit logs.

### Added in v4.0.0 to v5.0.0
- The GraphQL API uses Lighthouse PHP. `graphql/schema.graphql` imports 43 domain schemas plus the shared directives and subscriptions, with DataLoaders for batching.
- Event streaming publishes and consumes events through Redis Streams (v5.0.0).
- A plugin manager runs plugins in a sandbox and scans them for security problems (v4.0.0).
- The API gateway middleware adds request IDs, timing headers and version handling (v5.0.0).

### Future Considerations
- The domain boundaries would allow single domains to move into separate services later.
- Multi-region deployment has groundwork from v3.5.0 but is not complete.

---

## Unified Platform Architecture

### Overview
FinAegis is one code base that can carry several financial products:
- **Zelta** is the hosted non-custodial wallet built on FinAegis. It is the product that runs in production.
- **The GCU demo** is a reference implementation of a basket-referenced unit built with FinAegis. Its balances, conversions and basket votes are simulated.
- **Litas** is a design concept for a crypto-fiat exchange and P2P lending marketplace. It is not offered.

### Shared Components

#### Exchange Engine
```php
interface ExchangeEngine {
    // Supports both currency and crypto exchanges
    public function execute(Order $order): Trade;
    public function getOrderBook(AssetPair $pair): OrderBook;
}
```

The exchange engine is a demo. It is on by default only in the local, testing and demo environments, and an explicit `TRADING_ENABLED` value overrides that. Its liquidity pools support pool creation, swaps, positions, rewards and impermanent loss protection. The add, remove and rebalance liquidity flow was removed in v7.18.0, together with `PoolRebalancingService` and the `RebalancePoolsCommand`.

#### Multi-Asset Ledger
- The ledger holds fiat currencies such as USD, EUR and GBP.
- It holds cryptocurrencies such as BTC and ETH.
- It holds token asset types, for example the GCU demo unit.
- One balance model covers all asset types.

#### Stablecoin Framework
```php
interface StablecoinManager {
    public function mint(Money $fiatAmount, string $token): TokenAmount;
    public function burn(TokenAmount $tokens): Money;
    public function getReserves(string $token): ReserveStatus;
}
```

### Product-Specific Domains

#### GCU demo-specific

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

- The demo manages a currency basket.
- It models how funds would be spread across banks. The allocation is simulated.
- It runs basket composition votes. The votes are simulated.
- It rebalances the basket by algorithm.

#### Litas-specific (design concept, not offered)
- The concept covers crypto wallet infrastructure.
- It covers a blockchain integration layer.
- It covers a P2P lending marketplace.
- It covers loan tokenisation (Crypto LITAS).

### Configuration-Driven Architecture

```php
// config/platform.php
return [
    'features' => [
        'gcu' => [
            'basket_management' => true,
            'bank_allocation' => true,
            'currency_voting' => true,
        ],
        'litas' => [
            'crypto_wallets' => true,
            'p2p_lending' => true,
            'token_trading' => true,
        ],
    ],
];
```

### Integration Points

#### Blockchain Layer
- Bitcoin can be reached through a full node or an API.
- EVM chains and Solana are reached through RPC providers. The Zelta wallet sends on Polygon, Base, Arbitrum and Solana.
- Token contract integration points are a design concept.
- Inbound transactions are tracked through Helius webhooks for Solana and Alchemy webhooks for EVM chains.

#### External Services
- Crypto exchange adapters read prices from exchange APIs such as Binance and Kraken. They are integration adapters for third-party APIs; no partnership or endorsement implied.
- Credit scoring can call a third-party risk service.
- Identity checks run through KYC providers such as Ondato, Bridge and the card provider.
- Market data comes from price feeds.

### Security Architecture Extensions

#### Crypto Security
- The demo blockchain wallets use hierarchical deterministic (HD) key generation.
- Multi-signature wallets require M of N signatures.
- Hardware wallets (Ledger and Trezor) keep keys offline.
- Hardware security modules (HSM) can hold platform keys.
- The Zelta wallet is non-custodial. Privy manages the user's keys and the device signs every transaction. The backend never sees private key material.

#### Regulatory status
FinAegis is a software project and does not hold any banking, e-money, payment-institution, crypto-asset service provider or token-issuer licence or authorisation. Licensing and regulatory compliance are the responsibility of the operator of each deployment.

---

**Architecture Version**: v7.18.1
**Implementation Status**: 61 domain folders, 43 GraphQL domain schemas
**Last Updated**: 2026-11-24