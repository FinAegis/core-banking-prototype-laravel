# CQRS (Command Query Responsibility Segregation) Implementation

## Overview

The FinAegis platform implements CQRS pattern to separate read and write operations, providing better scalability, performance, and maintainability. This document covers the implementation details, usage examples, and best practices.

## Architecture

### Core Components

```
app/
├── Domain/
│   ├── Shared/
│   │   ├── Commands/
│   │   │   └── Command.php              # Base command interface
│   │   ├── Queries/
│   │   │   └── Query.php                # Base query interface
│   │   └── Handlers/
│   │       ├── CommandHandler.php       # Base command handler
│   │       └── QueryHandler.php         # Base query handler
│   └── [Domain]/
│       ├── Commands/                    # Domain-specific commands
│       ├── Queries/                     # Domain-specific queries
│       └── Handlers/                    # Domain handlers
└── Infrastructure/
    └── CQRS/
        ├── CommandBus.php               # Command dispatcher
        ├── QueryBus.php                 # Query dispatcher
        └── Middleware/                  # Bus middleware
```

## Command Examples

### 1. Treasury Domain - Cash Allocation Command

```php
<?php

namespace App\Domain\Treasury\Commands;

use App\Domain\Shared\Commands\Command;

class AllocateCashCommand implements Command
{
    public function __construct(
        public readonly string $accountId,
        public readonly float $amount,
        public readonly string $strategy,
        public readonly array $constraints = []
    ) {}
}
```

**Handler Implementation:**

```php
<?php

namespace App\Domain\Treasury\Handlers;

use App\Domain\Shared\Handlers\CommandHandler;
use App\Domain\Treasury\Commands\AllocateCashCommand;
use App\Domain\Treasury\Aggregates\TreasuryAggregate;
use App\Domain\Treasury\ValueObjects\AllocationStrategy;
use Illuminate\Support\Str;

class AllocateCashHandler implements CommandHandler
{
    public function handle(AllocateCashCommand $command): void
    {
        $aggregate = TreasuryAggregate::retrieve($command->accountId);
        
        $strategy = new AllocationStrategy(
            $command->strategy,
            $command->constraints
        );
        
        $aggregate->allocateCash(
            Str::uuid()->toString(),
            $strategy,
            $command->amount,
            auth()->id() ?? 'system'
        );
        
        $aggregate->persist();
    }
}
```

**Usage in Controller:**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Domain\Treasury\Commands\AllocateCashCommand;
use App\Infrastructure\CQRS\CommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreasuryController extends Controller
{
    public function __construct(
        private readonly CommandBus $commandBus
    ) {}
    
    public function allocateCash(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'strategy' => 'required|in:conservative,balanced,aggressive,custom',
            'constraints' => 'nullable|array',
        ]);
        
        $command = new AllocateCashCommand(
            accountId: $validated['account_id'],
            amount: $validated['amount'],
            strategy: $validated['strategy'],
            constraints: $validated['constraints'] ?? []
        );
        
        $this->commandBus->dispatch($command);
        
        return response()->json([
            'success' => true,
            'message' => 'Cash allocation initiated',
        ], 202);
    }
}
```

### 2. Account Domain - Create Account Command

```php
<?php

namespace App\Domain\Account\Commands;

use App\Domain\Shared\Commands\Command;

class CreateAccountCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $accountType,
        public readonly string $currency,
        public readonly ?string $name = null,
        public readonly array $metadata = []
    ) {}
}
```

**Handler with Event Sourcing:**

```php
<?php

namespace App\Domain\Account\Handlers;

use App\Domain\Account\Aggregates\AccountAggregate;
use App\Domain\Account\Commands\CreateAccountCommand;
use App\Domain\Shared\Handlers\CommandHandler;
use Illuminate\Support\Str;

class CreateAccountHandler implements CommandHandler
{
    public function handle(CreateAccountCommand $command): string
    {
        $accountId = Str::uuid()->toString();
        
        $aggregate = AccountAggregate::retrieve($accountId);
        
        $aggregate->createAccount(
            accountId: $accountId,
            userId: $command->userId,
            accountType: $command->accountType,
            currency: $command->currency,
            name: $command->name ?? "Account {$command->accountType}",
            metadata: $command->metadata
        );
        
        $aggregate->persist();
        
        return $accountId;
    }
}
```

### 3. Exchange Domain - Place Order Command

```php
<?php

namespace App\Domain\Exchange\Commands;

use App\Domain\Shared\Commands\Command;

class PlaceOrderCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $orderType, // market, limit, stop
        public readonly string $side,      // buy, sell
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly float $amount,
        public readonly ?float $price = null,
        public readonly ?float $stopPrice = null
    ) {}
}
```

## Query Examples

### 1. Treasury Domain - Get Portfolio Query

```php
<?php

namespace App\Domain\Treasury\Queries;

use App\Domain\Shared\Queries\Query;

class GetPortfolioQuery implements Query
{
    public function __construct(
        public readonly string $accountId,
        public readonly ?string $asOf = null
    ) {}
}
```

**Handler Implementation:**

```php
<?php

namespace App\Domain\Treasury\Handlers;

use App\Domain\Shared\Handlers\QueryHandler;
use App\Domain\Treasury\Queries\GetPortfolioQuery;
use App\Domain\Treasury\ReadModels\PortfolioReadModel;
use Illuminate\Support\Collection;

class GetPortfolioHandler implements QueryHandler
{
    public function __construct(
        private readonly PortfolioReadModel $readModel
    ) {}
    
    public function handle(GetPortfolioQuery $query): array
    {
        $portfolio = $this->readModel->getPortfolio(
            $query->accountId,
            $query->asOf ? Carbon::parse($query->asOf) : now()
        );
        
        return [
            'account_id' => $query->accountId,
            'total_value' => $portfolio->totalValue,
            'allocations' => $portfolio->allocations->map(fn($a) => [
                'asset_type' => $a->assetType,
                'amount' => $a->amount,
                'percentage' => $a->percentage,
                'current_value' => $a->currentValue,
            ])->toArray(),
            'performance' => [
                'daily_return' => $portfolio->dailyReturn,
                'monthly_return' => $portfolio->monthlyReturn,
                'yearly_return' => $portfolio->yearlyReturn,
            ],
            'risk_metrics' => [
                'var_95' => $portfolio->valueAtRisk95,
                'sharpe_ratio' => $portfolio->sharpeRatio,
                'max_drawdown' => $portfolio->maxDrawdown,
            ],
        ];
    }
}
```

**Usage in Controller:**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Domain\Treasury\Queries\GetPortfolioQuery;
use App\Infrastructure\CQRS\QueryBus;
use Illuminate\Http\JsonResponse;

class TreasuryController extends Controller
{
    public function __construct(
        private readonly QueryBus $queryBus
    ) {}
    
    public function getPortfolio(string $accountId): JsonResponse
    {
        $query = new GetPortfolioQuery(
            accountId: $accountId,
            asOf: request('as_of')
        );
        
        $result = $this->queryBus->ask($query);
        
        return response()->json($result);
    }
}
```

### 2. Account Domain - Get Account Balance Query

```php
<?php

namespace App\Domain\Account\Queries;

use App\Domain\Shared\Queries\Query;

class GetAccountBalanceQuery implements Query
{
    public function __construct(
        public readonly string $accountId,
        public readonly ?string $assetCode = null
    ) {}
}
```

**Handler with Caching:**

```php
<?php

namespace App\Domain\Account\Handlers;

use App\Domain\Account\Queries\GetAccountBalanceQuery;
use App\Domain\Shared\Handlers\QueryHandler;
use Illuminate\Support\Facades\Cache;

class GetAccountBalanceHandler implements QueryHandler
{
    public function handle(GetAccountBalanceQuery $query): array
    {
        $cacheKey = "balance:{$query->accountId}:{$query->assetCode}";
        
        return Cache::remember($cacheKey, 60, function () use ($query) {
            $balances = AccountBalance::where('account_uuid', $query->accountId)
                ->when($query->assetCode, fn($q) => 
                    $q->where('asset_code', $query->assetCode)
                )
                ->get();
            
            return $balances->map(fn($b) => [
                'asset_code' => $b->asset_code,
                'balance' => $b->balance / 100, // Convert cents to dollars
                'available' => $b->available_balance / 100,
                'reserved' => $b->reserved_balance / 100,
                'updated_at' => $b->updated_at->toIso8601String(),
            ])->toArray();
        });
    }
}
```

### 3. Exchange Domain - Get Order Book Query

```php
<?php

namespace App\Domain\Exchange\Queries;

use App\Domain\Shared\Queries\Query;

class GetOrderBookQuery implements Query
{
    public function __construct(
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly int $depth = 20
    ) {}
}
```

## Command Bus Configuration

### Registration in Service Provider

```php
<?php

namespace App\Providers;

use App\Infrastructure\CQRS\CommandBus;
use App\Infrastructure\CQRS\QueryBus;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommandBus::class);
        $this->app->singleton(QueryBus::class);
    }
    
    public function boot(): void
    {
        $this->registerCommandHandlers();
        $this->registerQueryHandlers();
    }
    
    private function registerCommandHandlers(): void
    {
        $commandBus = $this->app->make(CommandBus::class);
        
        // Treasury Commands
        $commandBus->register(
            AllocateCashCommand::class,
            AllocateCashHandler::class
        );
        
        $commandBus->register(
            OptimizeYieldCommand::class,
            OptimizeYieldHandler::class
        );
        
        // Account Commands
        $commandBus->register(
            CreateAccountCommand::class,
            CreateAccountHandler::class
        );
        
        $commandBus->register(
            TransferFundsCommand::class,
            TransferFundsHandler::class
        );
        
        // Exchange Commands
        $commandBus->register(
            PlaceOrderCommand::class,
            PlaceOrderHandler::class
        );
    }
    
    private function registerQueryHandlers(): void
    {
        $queryBus = $this->app->make(QueryBus::class);
        
        // Treasury Queries
        $queryBus->register(
            GetPortfolioQuery::class,
            GetPortfolioHandler::class
        );
        
        // Account Queries
        $queryBus->register(
            GetAccountBalanceQuery::class,
            GetAccountBalanceHandler::class
        );
        
        // Exchange Queries
        $queryBus->register(
            GetOrderBookQuery::class,
            GetOrderBookHandler::class
        );
    }
}
```

## Middleware Support

### Transaction Middleware

```php
<?php

namespace App\Infrastructure\CQRS\Middleware;

use Illuminate\Support\Facades\DB;

class TransactionMiddleware
{
    public function handle($command, $next)
    {
        return DB::transaction(function () use ($command, $next) {
            return $next($command);
        });
    }
}
```

### Logging Middleware

```php
<?php

namespace App\Infrastructure\CQRS\Middleware;

use Illuminate\Support\Facades\Log;

class LoggingMiddleware
{
    public function handle($command, $next)
    {
        Log::info('Command dispatched', [
            'command' => get_class($command),
            'data' => $command,
            'user' => auth()->id(),
        ]);
        
        $result = $next($command);
        
        Log::info('Command completed', [
            'command' => get_class($command),
        ]);
        
        return $result;
    }
}
```

### Validation Middleware

```php
<?php

namespace App\Infrastructure\CQRS\Middleware;

use Illuminate\Support\Facades\Validator;

class ValidationMiddleware
{
    public function handle($command, $next)
    {
        if (method_exists($command, 'rules')) {
            $validator = Validator::make(
                (array) $command,
                $command->rules()
            );
            
            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        }
        
        return $next($command);
    }
}
```

## API Endpoints with CQRS

### Treasury Management Endpoints

```http
POST /api/treasury/allocate
Content-Type: application/json

{
    "account_id": "uuid",
    "amount": 1000000,
    "strategy": "balanced",
    "constraints": {
        "min_liquidity": 0.2,
        "max_risk": 0.5
    }
}
```

**Response:**
```json
{
    "success": true,
    "message": "Cash allocation initiated",
    "command_id": "uuid"
}
```

```http
GET /api/treasury/{accountId}/portfolio
```

**Response:**
```json
{
    "account_id": "uuid",
    "total_value": 1000000,
    "allocations": [
        {
            "asset_type": "cash",
            "amount": 200000,
            "percentage": 20,
            "current_value": 200000
        },
        {
            "asset_type": "bonds",
            "amount": 400000,
            "percentage": 40,
            "current_value": 410000
        }
    ],
    "performance": {
        "daily_return": 0.15,
        "monthly_return": 3.2,
        "yearly_return": 8.5
    }
}
```

### Account Management Endpoints

```http
POST /api/accounts
Content-Type: application/json

{
    "user_id": "uuid",
    "account_type": "savings",
    "currency": "USD",
    "name": "Main Savings Account"
}
```

**Response:**
```json
{
    "success": true,
    "account_id": "uuid",
    "message": "Account created successfully"
}
```

```http
GET /api/accounts/{accountId}/balance
```

**Response:**
```json
{
    "balances": [
        {
            "asset_code": "USD",
            "balance": 50000.00,
            "available": 45000.00,
            "reserved": 5000.00,
            "updated_at": "2024-09-15T10:30:00Z"
        }
    ]
}
```

## Testing CQRS Implementation

### Command Handler Test

```php
<?php

namespace Tests\Feature\Domain\Treasury;

use App\Domain\Treasury\Commands\AllocateCashCommand;
use App\Domain\Treasury\Handlers\AllocateCashHandler;
use App\Domain\Treasury\Aggregates\TreasuryAggregate;

it('handles cash allocation command', function () {
    $command = new AllocateCashCommand(
        accountId: 'test-account',
        amount: 100000,
        strategy: 'balanced',
        constraints: ['min_liquidity' => 0.2]
    );
    
    $handler = app(AllocateCashHandler::class);
    $handler->handle($command);
    
    $aggregate = TreasuryAggregate::retrieve('test-account');
    
    expect($aggregate->getRecordedEvents())
        ->toHaveCount(1)
        ->first()->toBeInstanceOf(CashAllocated::class);
});
```

### Query Handler Test

```php
<?php

namespace Tests\Feature\Domain\Treasury;

use App\Domain\Treasury\Queries\GetPortfolioQuery;
use App\Domain\Treasury\Handlers\GetPortfolioHandler;

it('retrieves portfolio information', function () {
    // Arrange
    $accountId = 'test-account';
    createTestPortfolio($accountId);
    
    // Act
    $query = new GetPortfolioQuery($accountId);
    $handler = app(GetPortfolioHandler::class);
    $result = $handler->handle($query);
    
    // Assert
    expect($result)
        ->toHaveKey('account_id', $accountId)
        ->toHaveKey('total_value')
        ->toHaveKey('allocations')
        ->toHaveKey('performance');
});
```

## Best Practices

### 1. Command Design
- Commands should be immutable value objects
- Use readonly properties in PHP 8.1+
- Include all necessary data for the operation
- Commands should represent business intentions

### 2. Query Design
- Queries should be simple data requests
- Include filtering and pagination parameters
- Return DTOs or arrays, not domain models
- Consider caching strategies for expensive queries

### 3. Handler Implementation
- Keep handlers focused on a single responsibility
- Use dependency injection for services
- Wrap operations in database transactions
- Emit domain events for important state changes

### 4. Error Handling
- Use domain-specific exceptions
- Implement compensation logic for failed commands
- Log all command failures with context
- Return meaningful error messages to API consumers

### 5. Performance Optimization
- Use read models for complex queries
- Implement query result caching
- Consider async command processing for heavy operations
- Use database projections for reporting

## Integration with Event Sourcing

Commands that modify aggregates automatically generate domain events:

```php
class TreasuryAggregate extends AggregateRoot
{
    public function allocateCash(
        string $allocationId,
        AllocationStrategy $strategy,
        float $amount,
        string $allocatedBy
    ): void {
        // Business logic validation
        $this->guardSufficientBalance($amount);
        
        // Record domain event
        $this->recordThat(new CashAllocated(
            accountId: $this->uuid,
            allocationId: $allocationId,
            strategy: $strategy->getValue(),
            amount: $amount,
            allocations: $strategy->getAllocations(),
            allocatedBy: $allocatedBy,
            allocatedAt: now()
        ));
    }
}
```

## Monitoring and Observability

### Command Metrics
- Command execution count
- Average execution time
- Failure rate
- Queue depth (for async commands)

### Query Metrics
- Query execution count
- Cache hit rate
- Average response time
- Slow query detection

### Example Monitoring Implementation

```php
class MetricsMiddleware
{
    public function handle($command, $next)
    {
        $startTime = microtime(true);
        $commandName = class_basename($command);
        
        try {
            $result = $next($command);
            
            Metrics::increment("command.{$commandName}.success");
            
            return $result;
        } catch (\Exception $e) {
            Metrics::increment("command.{$commandName}.failure");
            throw $e;
        } finally {
            $duration = microtime(true) - $startTime;
            Metrics::histogram(
                "command.{$commandName}.duration",
                $duration * 1000
            );
        }
    }
}
```

## Conclusion

The CQRS implementation in FinAegis provides:
- Clear separation of read and write operations
- Better scalability through independent optimization
- Simplified testing and maintenance
- Natural integration with event sourcing
- Enhanced performance through targeted caching

For more information, see:
- [Event Sourcing Guide](../05-PATTERNS/EVENT_SOURCING.md)
- [Domain-Driven Design](../02-ARCHITECTURE/DDD.md)
- [API Reference](./REST_API_REFERENCE.md)