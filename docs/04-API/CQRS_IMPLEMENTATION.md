# CQRS (Command Query Responsibility Segregation) Implementation

## Overview

CQRS means that writes go through commands and reads go through queries, and each has its own handler. FinAegis has the building blocks for this pattern. They are a command bus, a query bus and the interfaces that commands and queries implement. This document describes those building blocks and shows how a domain would use them.

## Status in v7.18.1

The buses are in place, but no domain uses them yet. No command or query handler is registered in v7.18.1, and no API endpoint dispatches through the command bus. Controllers call domain services, aggregates and workflows directly.

The command and query classes in the examples below show the intended pattern. They are not classes in the codebase. The same is true for the handler registrations, the middleware and the endpoints shown later in this document.

## Architecture

### Core Components

These files exist in v7.18.1:

```
app/
├── Domain/
│   └── Shared/
│       └── CQRS/
│           ├── Command.php              # Command interface
│           ├── CommandBus.php           # Command bus interface
│           ├── Query.php                # Query interface
│           └── QueryBus.php             # Query bus interface
├── Infrastructure/
│   └── CQRS/
│       ├── LaravelCommandBus.php        # Command bus implementation
│       ├── LaravelQueryBus.php          # Query bus implementation (with cache)
│       └── AsyncCommandJob.php          # Queued command dispatch
└── Providers/
    └── DomainServiceProvider.php        # Binds the buses as singletons
```

The command bus offers `dispatch()`, `register()`, `dispatchAsync()` and `dispatchTransaction()`. `dispatchTransaction()` runs several commands inside one database transaction. `DomainServiceProvider` has empty `registerCommandHandlers()` and `registerQueryHandlers()` methods where handlers would be registered.

## Command Examples

The examples in this section and the next one illustrate the pattern. The classes do not exist in the codebase.

### 1. Treasury Domain - Cash Allocation Command

```php
<?php

namespace App\Domain\Treasury\Commands;

use App\Domain\Shared\CQRS\Command;

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
use App\Domain\Shared\CQRS\CommandBus;
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

use App\Domain\Shared\CQRS\Command;

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

use App\Domain\Shared\CQRS\Command;

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

use App\Domain\Shared\CQRS\Query;

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
use App\Domain\Shared\CQRS\QueryBus;
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

use App\Domain\Shared\CQRS\Query;

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

use App\Domain\Shared\CQRS\Query;

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

The example below shows how handlers would be registered. In v7.18.1 the real `DomainServiceProvider` binds `App\Domain\Shared\CQRS\CommandBus` to `LaravelCommandBus` and `QueryBus` to `LaravelQueryBus`, and it registers no handlers.

```php
<?php

namespace App\Providers;

use App\Domain\Shared\CQRS\CommandBus;
use App\Domain\Shared\CQRS\QueryBus;
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

`LaravelCommandBus` has no middleware pipeline in v7.18.1. The classes below are examples of middleware that could be added.

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

No API endpoint goes through the command or query bus in v7.18.1. The account endpoints are described in [REST_API_REFERENCE.md](REST_API_REFERENCE.md#account-management). The Treasury demo module serves its portfolio endpoints under `/api/treasury/portfolios/...` and calls its services directly. Production Zelta disables the Treasury module.

## Testing CQRS Implementation

These tests show how the example handlers would be tested.

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

A command handler that changes an aggregate would record domain events through the aggregate. The example shows the idea:

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

The CQRS building blocks in FinAegis are a command bus, a query bus and their interfaces. They are ready for use, but no domain uses them in v7.18.1. When a domain adopts them, it separates writes from reads. That lets each side be tested, cached and scaled on its own, and it fits the event-sourced aggregates the platform already uses.

For more information, see:
- [Event Sourcing Best Practices](../12-PATTERNS/EVENT_SOURCING_BEST_PRACTICES.md)
- [Architecture](../02-ARCHITECTURE/ARCHITECTURE.md)
- [ADR 0008: CQRS pattern](../adr/0008-cqrs-pattern.md)
