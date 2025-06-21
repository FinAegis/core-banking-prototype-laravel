# FinAegis Core Banking Platform - Developer Guide

## Overview

FinAegis is an open-source Core Banking as a Service platform built with Laravel 12, featuring event sourcing, domain-driven design, and workflow orchestration. This platform provides a robust foundation for banking operations with quantum-resistant security measures.

**🤖 AI-Friendly Development**: This project actively welcomes contributions from AI coding assistants including Claude Code, GitHub Copilot, Cursor, and other vibe coding tools. The well-structured architecture, comprehensive documentation, and clear patterns make it easy for AI agents to understand the codebase and contribute meaningfully.

## Architecture

### Domain-Driven Design (DDD)

The platform follows DDD principles with clear domain boundaries:

```
app/Domain/
├── Account/           # Account management domain
│   ├── Aggregates/    # Domain aggregates (LedgerAggregate, TransactionAggregate)
│   ├── Events/        # Domain events (AccountCreated, MoneyAdded, etc.)
│   ├── DataObjects/   # Value objects (Account, Money, Hash)
│   ├── Workflows/     # Business process workflows
│   ├── Activities/    # Individual workflow activities
│   ├── Projectors/    # Event sourcing projectors
│   ├── Reactors/      # Event sourcing reactors
│   └── Services/      # Domain services
└── Payment/           # Payment processing domain
    ├── Services/      # Payment services
    └── Workflows/     # Payment workflows
```

### Event Sourcing

The platform uses Spatie's Event Sourcing package to maintain a complete audit trail of all changes:

#### Key Components:

1. **Aggregates**: Business entities that generate events
   - `LedgerAggregate`: Manages account lifecycle
   - `TransactionAggregate`: Handles money movements
   - `TransferAggregate`: Manages transfers between accounts

2. **Events**: Domain events that capture state changes
   - `AccountCreated`, `AccountDeleted`, `AccountFrozen`, `AccountUnfrozen`
   - `MoneyAdded`, `MoneySubtracted`, `MoneyTransferred`
   - `TransactionThresholdReached`, `TransferThresholdReached`

3. **Projectors**: Build read models from events
   - `AccountProjector`: Maintains account state
   - `TurnoverProjector`: Calculates account turnovers

4. **Reactors**: Handle side effects
   - `SnapshotTransactionsReactor`: Creates snapshots when thresholds are reached
   - `SnapshotTransfersReactor`: Creates transfer snapshots

### Workflow Orchestration

The platform uses Laravel Workflow for saga pattern implementation:

#### Available Workflows:

1. **Account Management**
   - `CreateAccountWorkflow`: Account creation with validation
   - `DestroyAccountWorkflow`: Account closure with audit trail
   - `FreezeAccountWorkflow` / `UnfreezeAccountWorkflow`: Compliance operations

2. **Transaction Processing**
   - `DepositAccountWorkflow`: Deposit processing
   - `WithdrawAccountWorkflow`: Withdrawal processing
   - `TransactionReversalWorkflow`: Transaction reversal with compensation

3. **Transfer Operations**
   - `TransferWorkflow`: Money transfers with compensation patterns
   - `BulkTransferWorkflow`: Multiple transfers with rollback capabilities

4. **System Operations**
   - `BalanceInquiryWorkflow`: Balance inquiries with audit logging
   - `AccountValidationWorkflow`: KYC/compliance validation
   - `BatchProcessingWorkflow`: End-of-day batch operations

## Getting Started

### Prerequisites

- PHP 8.3+
- Laravel 12
- MySQL/PostgreSQL
- Redis (for queues)
- Node.js (for asset compilation)

### Installation

1. **Clone the repository**:
   ```bash
   git clone <repository-url>
   cd finaegis-core-banking
   ```

2. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Setup environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure database and run migrations**:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. **Build assets**:
   ```bash
   npm run build
   ```

### Testing

The platform includes comprehensive test coverage with support for parallel test execution:

```bash
# Run all tests
./vendor/bin/pest

# Run all tests in parallel (recommended for faster execution)
./vendor/bin/pest --parallel

# Run specific test suites
./vendor/bin/pest tests/Domain/
./vendor/bin/pest tests/Feature/

# Run with coverage
./vendor/bin/pest --coverage

# Run tests in parallel with coverage (used in CI/CD)
./vendor/bin/pest --parallel --coverage --min=50

# Control parallel processes
./vendor/bin/pest --parallel --processes=4
```

#### Parallel Testing

The test suite is configured for parallel execution to significantly reduce test run times:

1. **Automatic Database Isolation**: Each test process gets its own database (e.g., `test_db_1`, `test_db_2`)
2. **Redis/Cache Isolation**: Each test process uses unique prefixes for Redis and cache keys
3. **Event Sourcing Isolation**: Event storage is isolated per test process
4. **CI/CD Integration**: GitHub Actions runs tests in parallel by default

**Benefits**:
- 2-4x faster test execution on multi-core systems
- Improved CI/CD pipeline performance
- No test interference or shared state issues

**Requirements**:
- Sufficient database connections for parallel processes
- Adequate system memory for concurrent test execution
- Database user must have CREATE/DROP database permissions for test databases

**Note**: If you encounter database permission errors locally, ensure your database user has permission to create databases, or run tests without the `--parallel` flag.

## Core Concepts

### Account Management

Accounts are managed through event sourcing with complete audit trails:

```php
use App\Domain\Account\Services\AccountService;

$accountService = app(AccountService::class);

// Create account
$accountService->create([
    'name' => 'Customer Account',
    'user_uuid' => $userUuid,
]);

// Deposit money
$accountService->deposit($accountUuid, 1000);

// Withdraw money
$accountService->withdraw($accountUuid, 500);

// Close account
$accountService->destroy($accountUuid);
```

### Transfer Processing

Transfers use the saga pattern with automatic compensation:

```php
use App\Domain\Payment\Services\TransferService;

$transferService = app(TransferService::class);

// Single transfer
$transferService->transfer(
    from: $fromAccountUuid,
    to: $toAccountUuid,
    amount: 1000
);

// Bulk transfer with compensation
$bulkTransfer = WorkflowStub::make(BulkTransferWorkflow::class);
$bulkTransfer->start($fromAccount, $transfers);
```

### Event Handling

Events are automatically processed by projectors and reactors:

```php
// Events are recorded in aggregates
$aggregate->recordThat(new MoneyAdded($money, $hash));

// Projectors update read models
class AccountProjector extends Projector
{
    public function onMoneyAdded(MoneyAdded $event): void
    {
        app(CreditAccount::class)($event);
    }
}

// Reactors handle side effects
class SnapshotTransactionsReactor extends Reactor
{
    public function onTransactionThresholdReached(): void
    {
        // Create snapshot
    }
}
```

### Security Features

#### Quantum-Resistant Hashing

All transactions use SHA3-512 hashing with validation:

```php
use App\Domain\Account\Utils\ValidatesHash;

trait ValidatesHash
{
    private const string HASH_ALGORITHM = 'sha3-512';
    
    protected function generateHash(?Money $money = null): Hash
    {
        return hydrate(Hash::class, [
            'hash' => hash(
                self::HASH_ALGORITHM,
                $this->currentHash . ($money ? $money->getAmount() : 0)
            ),
        ]);
    }
}
```

#### Event Integrity

All events include cryptographic hashes to ensure data integrity:

```php
class MoneyAdded extends ShouldBeStored implements HasHash, HasMoney
{
    public function __construct(
        public readonly Money $money,
        public readonly Hash $hash
    ) {}
}
```

## API Usage

### Account Operations

```php
// Create account via service
$accountService = app(AccountService::class);
$accountService->create([
    'name' => 'Savings Account',
    'user_uuid' => auth()->user()->uuid,
]);

// Direct aggregate usage
$ledger = LedgerAggregate::retrieve($uuid);
$ledger->createAccount($account)->persist();
```

### Balance Inquiries

```php
$workflow = WorkflowStub::make(BalanceInquiryWorkflow::class);
$result = $workflow->start($accountUuid, $requestedBy);
// Returns: ['account_uuid', 'balance', 'account_name', 'status', 'inquired_at', 'inquired_by']
```

### Compliance Operations

```php
// Freeze account
$workflow = WorkflowStub::make(FreezeAccountWorkflow::class);
$workflow->start($accountUuid, 'Suspicious activity', $authorizedBy);

// Validate account
$workflow = WorkflowStub::make(AccountValidationWorkflow::class);
$workflow->start($accountUuid, [
    'kyc_document_verification',
    'address_verification',
    'compliance_screening'
], $validatedBy);
```

## Development Guidelines

### Adding New Workflows

1. **Create Workflow Class**:
   ```php
   class MyCustomWorkflow extends Workflow
   {
       public function execute($param1, $param2): \Generator
       {
           return yield ActivityStub::make(
               MyCustomActivity::class,
               $param1,
               $param2
           );
       }
   }
   ```

2. **Create Activity Class**:
   ```php
   class MyCustomActivity extends Activity
   {
       public function execute($param1, $param2, Dependencies $deps): mixed
       {
           // Business logic here
           return $result;
       }
   }
   ```

3. **Add Tests**:
   ```php
   it('can execute my custom workflow', function () {
       WorkflowStub::fake();
       
       $workflow = WorkflowStub::make(MyCustomWorkflow::class);
       $workflow->start($param1, $param2);
       
       expect(true)->toBeTrue();
   });
   ```

### Adding New Events

1. **Create Event Class**:
   ```php
   class MyCustomEvent extends ShouldBeStored
   {
       public string $queue = EventQueues::CUSTOM->value;
       
       public function __construct(
           public readonly MyDataObject $data
       ) {}
   }
   ```

2. **Update Event Class Map**:
   ```php
   // config/event-sourcing.php
   'event_class_map' => [
       'my_custom_event' => App\Domain\MyDomain\Events\MyCustomEvent::class,
   ],
   ```

3. **Create Projector/Reactor**:
   ```php
   class MyCustomProjector extends Projector
   {
       public function onMyCustomEvent(MyCustomEvent $event): void
       {
           // Update read model
       }
   }
   ```

### Best Practices

1. **Event Sourcing**:
   - Keep events immutable
   - Use descriptive event names
   - Include all necessary data in events
   - Validate event integrity with hashes

2. **Workflows**:
   - Design for failure and compensation
   - Keep activities idempotent
   - Log important workflow steps
   - Use timeouts for long-running operations

3. **Security**:
   - Always validate hashes
   - Log security-relevant events
   - Use proper authorization checks
   - Implement audit trails

4. **Testing**:
   - Test aggregates with fake events
   - Test workflows with WorkflowStub::fake()
   - Include both positive and negative test cases
   - Test compensation logic

## Monitoring and Debugging

### Event Store Debugging

```php
// View stored events
$events = StoredEvent::where('aggregate_uuid', $uuid)->get();

// Replay events to specific projector
php artisan event-sourcing:replay MyProjector
```

### Workflow Monitoring

```php
// Check workflow status
$workflow = StoredWorkflow::find($workflowId);
echo $workflow->status; // running, completed, failed

// View workflow logs
$logs = $workflow->logs;
```

### Queue Monitoring

```php
// Monitor event processing queues
php artisan horizon:status
php artisan queue:work --queue=events,ledger,transactions,transfers
```

## Contributing

### All Contributors (Human & AI)
1. **Full test coverage required**: Every new feature, workflow, or significant change must include comprehensive tests
2. **Complete documentation**: Update relevant documentation files and add inline documentation for complex logic
3. **Follow PSR-12 coding standards**
4. **Maintain architectural patterns**: Follow existing DDD, event sourcing, and saga patterns
5. **Use conventional commit messages**
6. **Ensure all tests pass before submitting PRs** (`./vendor/bin/pest`)

### AI Coding Assistant Contributions
This project is specifically designed to work well with AI coding tools. AI agents typically understand the codebase structure very well due to the clear patterns and comprehensive documentation. All AI-generated code must meet the same quality standards as human-written code.

## Support

For technical support and questions:
- GitHub Issues: [Repository Issues](https://github.com/your-repo/issues)
- Documentation: [Full Documentation](./docs/)
- Email: support@finaegis.com