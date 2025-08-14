# FinAegis Platform Architecture

**Version:** 8.0  
**Last Updated:** 2025-08-14  
**Status:** Unified Platform Architecture with Phase 8 DeFi Features

This document provides a comprehensive overview of the FinAegis Platform architecture, design patterns, and implementation details. The platform delivers the Global Currency Unit (GCU) as its flagship product alongside modular sub-products: Exchange, Lending, Stablecoins, and Treasury.

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
│  Web UI (Filament)  │  REST API  │  GraphQL (Planned)  │  CLI       │
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
│  Exchange  │  Lending   │ Stablecoin │  Treasury  │  Trading        │
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

1. **Domain-Driven Design (DDD)**: Clear domain boundaries and ubiquitous language
2. **Event Sourcing**: Complete audit trail and system state reconstruction
3. **CQRS Pattern**: Optimized read and write models
4. **Saga Pattern**: Distributed transaction coordination
5. **Clean Architecture**: Dependency inversion and separation of concerns

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
│   ├── Services/           # TransferService
│   └── Repositories/       # TransferRepository
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
│   └── Events/             # CustodianEvents
└── Cgo/
    ├── Models/             # CgoInvestment, CgoPricingRound
    ├── Events/             # InvestmentCreated, RefundRequested
    ├── Aggregates/         # CgoRefundAggregate
    ├── Projectors/         # RefundProjector
    ├── Repositories/       # CgoEventRepository
    └── Services/           # CgoKycService, InvestmentAgreementService
    ├── ValueObjects/       # TransactionReceipt
    └── Workflows/          # CustodianTransferWorkflow
```

### Bounded Contexts

#### Account Context
- **Purpose**: Core banking account management
- **Entities**: Account, User, Transaction, Turnover
- **Aggregates**: LedgerAggregate, TransactionAggregate
- **Services**: AccountService, BalanceService

#### Payment Context
- **Purpose**: Money movement and transfers
- **Entities**: Transfer, Transaction
- **Aggregates**: TransferAggregate
- **Services**: TransferService, PaymentValidator

#### Asset Context
- **Purpose**: Multi-asset support and exchange rates
- **Entities**: Asset, ExchangeRate, AccountBalance
- **Services**: ExchangeRateService, AssetService

#### Governance Context
- **Purpose**: Democratic decision making
- **Entities**: Poll, Vote, VotingPower
- **Services**: GovernanceService, VotingService

#### Custodian Context
- **Purpose**: External custodian integration
- **Interfaces**: ICustodianConnector
- **Services**: CustodianRegistry, CustodianService

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

The platform uses a complete CQRS infrastructure with Laravel implementations:

#### Command Bus (`app/Infrastructure/CQRS/LaravelCommandBus.php`)
- Synchronous command execution
- Asynchronous command dispatch via queues
- Transactional batch command execution
- Container-based handler resolution

#### Query Bus (`app/Infrastructure/CQRS/LaravelQueryBus.php`)
- Cached query execution with TTL support
- Batch query processing
- MD5-based cache key generation
- Container-based handler resolution

#### Domain Event Bus (`app/Infrastructure/Events/LaravelDomainEventBus.php`)
- Priority-based event handler execution
- Transaction support (record/dispatch/clear)
- Asynchronous event publishing
- Integration with Laravel's native event system

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

// Registration in DomainServiceProvider
$commandBus->register(
    CreateAccountCommand::class,
    CreateAccountHandler::class
);
        public readonly ?int $initialBalance = null
    ) {}
}
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

The FinAegis platform represents a modern, scalable approach to core banking systems with the following key architectural benefits:

### Strengths
- ✅ **Event Sourcing**: Complete audit trail and system recovery capabilities
- ✅ **CQRS Pattern**: Optimized read and write operations
- ✅ **Multi-Asset Support**: Native support for multiple asset types
- ✅ **Saga Pattern**: Reliable distributed transaction handling
- ✅ **Clean Architecture**: Clear separation of concerns and testability

### Scalability Features
- ✅ **Horizontal Scaling**: Stateless application design
- ✅ **Caching Strategy**: Multi-layer caching with Redis
- ✅ **Database Optimization**: Proper indexing and query optimization
- ✅ **Async Processing**: Queue-based background processing

### Security Features
- ✅ **Cryptographic Integrity**: SHA3-512 transaction hashing
- ✅ **API Security**: Bearer token authentication with Sanctum
- ✅ **Authorization**: Role and permission-based access control
- ✅ **Audit Logging**: Complete operation tracking

### Future Considerations
- 🔄 **Microservices Migration**: Domain boundaries enable service extraction
- 🔄 **GraphQL API**: Enhanced query capabilities
- 🔄 **Event Streaming**: Real-time event processing with Kafka
- 🔄 **Multi-Region Deployment**: Geographic distribution support

---

## Unified Platform Architecture

### Overview
FinAegis serves as a unified platform supporting multiple financial products:
- **Global Currency Unit (GCU)**: User-controlled currency with democratic governance
- **Litas Platform**: Crypto-fiat exchange and P2P lending marketplace

### Shared Components

#### Exchange Engine
```php
interface ExchangeEngine {
    // Supports both currency and crypto exchanges
    public function execute(Order $order): Trade;
    public function getOrderBook(AssetPair $pair): OrderBook;
    public function addLiquidity(LiquidityPool $pool): void;
}
```

#### Multi-Asset Ledger
- Supports fiat currencies (USD, EUR, GBP)
- Supports cryptocurrencies (BTC, ETH)
- Supports tokens (GCU, Stable LITAS, Crypto LITAS)
- Unified balance management across all asset types

#### Stablecoin Framework
```php
interface StablecoinManager {
    public function mint(Money $fiatAmount, string $token): TokenAmount;
    public function burn(TokenAmount $tokens): Money;
    public function getReserves(string $token): ReserveStatus;
}
```

### Product-Specific Domains

#### GCU-Specific
- Currency basket management
- Bank allocation (multi-bank distribution)
- Monthly voting on composition
- Basket rebalancing algorithms

#### Litas-Specific
- Crypto wallet infrastructure
- Blockchain integration layer
- P2P lending marketplace
- Loan tokenization (Crypto LITAS)

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
- **Bitcoin Integration**: Full node or API-based
- **Ethereum Integration**: Web3 provider connection
- **Smart Contracts**: Token contracts for Stable/Crypto LITAS
- **Transaction Monitoring**: Block confirmation tracking

#### External Services
- **Crypto Exchanges**: Binance, Kraken API integration
- **Credit Scoring**: Third-party risk assessment
- **KYC Providers**: Identity verification services
- **Market Data**: Real-time price feeds

### Security Architecture Extensions

#### Crypto Security
- **HD Wallets**: Hierarchical deterministic key generation
- **Multi-Signature**: M-of-N signature requirements
- **Cold Storage**: Offline key management
- **HSM Integration**: Hardware security modules

#### Compliance Extensions
- **VASP Registration**: Virtual Asset Service Provider
- **MiCA Compliance**: Markets in Crypto-Assets
- **ECSP License**: European Crowdfunding Service Provider

---

**Architecture Version**: 3.0  
**Implementation Status**: Core Complete, Extensions Planned  
**Last Updated**: 2025-08-14