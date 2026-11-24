# FinAegis Core Banking Platform - Developer Guide

## Overview

FinAegis is an open-source reference platform for banking and payments software. It is built with Laravel 12 and PHP 8.4. It uses event sourcing, domain-driven design and workflow orchestration. It is a reference implementation and a learning resource, not a production bank. Zelta is the hosted non-custodial wallet product built on it. The current version is v7.18.1.

**AI-assisted development.** The project accepts contributions written with AI coding assistants such as Claude Code, GitHub Copilot and Cursor. The domains follow consistent patterns, which makes the code easier for these tools to read. `CLAUDE.md` and `AGENTS.md` files hold instructions for them. AI-generated code must meet the same standards as any other code.

## Architecture

### Domain-Driven Design (DDD)

The platform has 61 domains in `app/Domain/`. Each domain is a bounded context. The Account and Payment domains show the typical layout:

```
app/Domain/
├── Account/           # Account management domain
│   ├── Aggregates/    # Domain aggregates (LedgerAggregate, TransactionAggregate)
│   ├── Events/        # Domain events (AccountCreated, MoneyAdded, etc.)
│   ├── DataObjects/   # Value objects (Account, Money, Hash)
│   ├── Workflows/     # Business process workflows and their activities
│   ├── Projectors/    # Event sourcing projectors
│   ├── Reactors/      # Event sourcing reactors
│   └── Services/      # Domain services
└── Payment/           # Payment processing domain
    ├── Services/      # Payment services
    └── Workflows/     # Payment workflows
```

### Event Sourcing

The platform uses `spatie/laravel-event-sourcing` (7.15). Events are stored, so every change has an audit trail.

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

The platform uses the `durable-workflow/workflow` package (formerly Laravel Workflow) for sagas. The Waterline dashboard shows the running workflows.

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

- PHP 8.4+
- Composer
- MySQL 8 or MariaDB
- Redis (for queues, cache and many tests)
- Node.js (for asset compilation)

### Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/FinAegis/core-banking-prototype-laravel.git
   cd core-banking-prototype-laravel
   ```

2. **Install dependencies**:
   ```bash
   composer install
   npm ci
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

   `db:seed` does not create demo users. Run `php artisan demo:populate` for the demo personas (password `demo123`). Create an admin with `php artisan user:create --admin`.

5. **Build assets**:
   ```bash
   npm run build
   ```

### Testing

The repository has about 1,300 test files, and they can run in parallel:

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

# Stop at the first failure
./vendor/bin/pest --parallel --stop-on-failure

# Control parallel processes
./vendor/bin/pest --parallel --processes=4
```

#### Parallel Testing

Parallel runs are much faster on machines with several cores. The suite keeps the processes apart:

1. `phpunit.xml` uses SQLite in memory, so each process has its own database. With MySQL, Laravel creates one test database per process, such as `testing_test_1`.
2. Each process uses its own prefix for Redis and cache keys.
3. Event storage is isolated per process.
4. CI runs the unit tests with two parallel processes. The feature tests run without parallel processes, because they share MySQL state.

With MySQL, the database user needs permission to create and drop the test databases. If you get permission errors, run without `--parallel`. See [PARALLEL-TESTING.md](PARALLEL-TESTING.md).

## Core Concepts

### Account Management

Accounts are event-sourced, so every change is recorded:

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

Transfers use the saga pattern. When a step fails, the workflow runs compensating steps:

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

Projectors and reactors process the stored events:

```php
// Events are recorded in aggregates
$aggregate->recordThat(new MoneyAdded($money, $hash));

// Projectors update read models
class TurnoverProjector extends Projector
{
    public function onMoneyAdded(MoneyAdded $event): void
    {
        // Update the turnover read model
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

#### SHA3-512 Transaction Hashes

Each transaction event carries a SHA3-512 hash that chains it to the previous one. The `ValidatesHash` trait creates and checks these hashes:

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

Money events carry these hashes, so tampering with stored events can be detected:

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

1. **Event sourcing.** Keep events immutable and give them descriptive names. Put all the data a projector needs into the event. Validate event integrity with the hashes.
2. **Workflows.** Design for failure and compensation, and keep activities idempotent. Log the important steps and set timeouts for long-running operations.
3. **Money.** Use bcmath (`bcadd`, `bcsub`, `bcmul`, `bcdiv`) for amounts, never floats. Wrap multi-table financial writes in `DB::transaction()` and use `lockForUpdate()` for balance checks. Do not wrap flows that touch tenant-connection models in `DB::transaction()`.
4. **Security.** Always validate hashes and log security-relevant events. Check authorization properly and keep audit trails.
5. **Testing.** Test aggregates with fake events and workflows with `WorkflowStub::fake()`. Cover both positive and negative cases and the compensation logic. Pass `['read', 'write', 'delete']` abilities to `Sanctum::actingAs()` in API tests.

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

### All Contributors (Human and AI)
1. Every new feature, workflow or significant change needs tests.
2. Update the relevant documentation, and document complex logic inline.
3. Follow the PSR-12 coding standard.
4. Follow the existing DDD, event sourcing and saga patterns.
5. Use conventional commit messages (`feat:`, `fix:`, `test:`, `refactor:`).
6. Run the quality checks before you open a pull request:
   ```bash
   ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
   ./vendor/bin/phpcs
   XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
   ./vendor/bin/pest --parallel --stop-on-failure
   ```
   See [code-quality-workflow.md](QUALITY/code-quality-workflow.md).

### AI Coding Assistant Contributions
AI-generated code must meet the same quality standards as code written by people. The instructions for AI agents are in `CLAUDE.md` at the project root and in [CLAUDE.md](CLAUDE.md) in this folder.

## Support

For technical support and questions:
- GitHub Issues: [Repository Issues](https://github.com/FinAegis/core-banking-prototype-laravel/issues)
- Documentation: [Full Documentation](../README.md)
- Email: support@finaegis.org
