# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## AI-Friendly Development

**FinAegis welcomes contributions from AI coding assistants!** This project is designed to be highly compatible with AI agents including Claude Code, GitHub Copilot, Cursor, and other vibe coding tools. The domain-driven design, comprehensive documentation, and well-structured patterns make it easy for AI agents to understand and contribute meaningfully to the codebase.

### Contribution Requirements for AI-Generated Code
All contributions (human or AI-generated) must include:
- **Full test coverage**: Every new feature, workflow, or significant change must have comprehensive tests
- **Complete documentation**: Update relevant documentation files and add inline documentation for complex logic
- **Code quality**: Follow existing patterns and maintain the established architecture principles
- **Always update or create new tests and update documentation whenever you're doing something**

## Development Commands

### Testing
```bash
# Run all tests
./vendor/bin/pest

# Run all tests in parallel (faster execution)
./vendor/bin/pest --parallel

# Run specific test suites
./vendor/bin/pest tests/Domain/         # Domain layer tests
./vendor/bin/pest tests/Feature/        # Feature tests
./vendor/bin/pest --coverage --min=50  # Run with coverage report (50% minimum)

# Run tests in parallel with coverage
./vendor/bin/pest --parallel --coverage --min=50

# Run single test file
./vendor/bin/pest tests/Domain/Account/Aggregates/LedgerAggregateTest.php

# Run tests with specific number of processes
./vendor/bin/pest --parallel --processes=4

# CI-specific test configuration
# Use phpunit.ci.xml for GitHub Actions
./vendor/bin/pest --configuration=phpunit.ci.xml --parallel --coverage --min=50
```

#### Test Coverage Requirements
- **Minimum Coverage**: 50% for all new code
- **Critical Areas**: API controllers, domain services, workflows
- **Coverage Reports**: Available in `coverage-html/` directory after running with --coverage
- **CI Integration**: Automated coverage checks on all PRs

### CI/CD and GitHub Actions
The project includes comprehensive GitHub Actions workflows:

```bash
# Workflows are triggered on:
# - Pull requests to main branch
# - Pushes to main branch

# Test Workflow (.github/workflows/test.yml):
# - Sets up PHP 8.3, MySQL 8.0, Redis 7, Node.js 20
# - Installs Composer and NPM dependencies
# - Builds frontend assets
# - Runs database migrations and seeders
# - Executes all tests in parallel with 50% coverage requirement
# - Uses self-hosted runners for improved performance
# - Uploads coverage reports to Codecov

# Security Workflow (.github/workflows/security.yml):
# - Uses Gitleaks to scan for exposed secrets
# - Scans entire git history for security vulnerabilities
# - Uses self-hosted runners for improved performance
# - Mandatory for all PRs to prevent secret leaks
```

### Building and Assets
```bash
# Build assets for production
npm run build

# Development with hot reloading
npm run dev

# Install dependencies
composer install
npm install
```

### API Documentation
```bash
# Generate/update API documentation
php artisan l5-swagger:generate

# Access documentation at:
# http://localhost:8000/api/documentation
# http://localhost:8000/docs/api-docs.json (raw OpenAPI spec)
```

### Database Operations
```bash
# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Fresh migration with seeding
php artisan migrate:fresh --seed
```

### Cache Management
```bash
# Warm up cache for all accounts
php artisan cache:warmup

# Warm up specific accounts
php artisan cache:warmup --account=uuid1 --account=uuid2

# Clear all caches
php artisan cache:clear

# Monitor cache performance (check headers)
# X-Cache-Hits: Number of cache hits
# X-Cache-Misses: Number of cache misses
# X-Cache-Hit-Rate: Percentage hit rate
```

### Queue Management
```bash
# Start queue workers for event processing
php artisan queue:work --queue=events,ledger,transactions,transfers,webhooks

# Monitor queues with Horizon
php artisan horizon

# Clear failed jobs
php artisan queue:clear

# Create admin user for Filament dashboard
php artisan make:filament-user
```

### Admin Dashboard Management
```bash
# Create admin user
php artisan make:filament-user

# Access dashboard at:
# http://localhost:8000/admin

# Clear Filament cache after resource changes
php artisan filament:clear-cached-components
php artisan view:clear
```

### Event Sourcing
```bash
# Replay events to projectors
php artisan event-sourcing:replay AccountProjector
php artisan event-sourcing:replay TurnoverProjector
php artisan event-sourcing:replay TransactionProjector
php artisan event-sourcing:replay AssetTransactionProjector
php artisan event-sourcing:replay AssetTransferProjector
php artisan event-sourcing:replay ExchangeRateProjector

# Create snapshots
php artisan snapshot:create

# Verify transaction hashes
php artisan verify:transaction-hashes
```

## Architecture Overview

### Domain-Driven Design Structure
- **Account Domain** (`app/Domain/Account/`): Core banking account management with multi-asset support
- **Asset Domain** (`app/Domain/Asset/`): Multi-asset ledger, exchange rates, and asset management
- **Exchange Domain** (`app/Domain/Exchange/`): Exchange rate providers and currency conversion
- **Custodian Domain** (`app/Domain/Custodian/`): External custodian integration framework
- **Governance Domain** (`app/Domain/Governance/`): Democratic governance and polling system
- **Payment Domain** (`app/Domain/Payment/`): Transfer and payment processing
- Each domain has Aggregates, Events, Workflows, Activities, Projectors, Reactors, and Services

### Caching Architecture
- **Redis-based caching** for high-performance data access
- **Cache Services**: `AccountCacheService`, `TransactionCacheService`, `TurnoverCacheService`
- **Cache Manager**: Centralized cache coordination with automatic invalidation
- **TTL Strategy**: Different cache durations based on data volatility
  - Accounts: 1 hour
  - Balances: 5 minutes
  - Transactions: 30 minutes
  - Turnovers: 2 hours
- **Schema Updates**: Turnover model now supports separate `debit` and `credit` fields for proper accounting

### Event Sourcing Architecture
- **Aggregates**: `LedgerAggregate`, `TransactionAggregate`, `TransferAggregate`, `AssetTransactionAggregate`, `AssetTransferAggregate`
- **Events**: `AccountCreated`, `MoneyAdded`, `MoneySubtracted`, `MoneyTransferred`, `AssetBalanceAdded`, `AssetBalanceSubtracted`, `AssetTransferred`, `AssetTransactionCreated`, `AssetTransferInitiated/Completed/Failed`, `ExchangeRateUpdated`
- **Projectors**: Build read models from events (`AccountProjector`, `TurnoverProjector`, `TransactionProjector`, `AssetTransactionProjector`, `AssetTransferProjector`, `ExchangeRateProjector`)
- **Reactors**: Handle side effects (`SnapshotTransactionsReactor`, `SnapshotTransfersReactor`)

### Workflow Orchestration (Saga Pattern)
- **Account Management**: `CreateAccountWorkflow`, `FreezeAccountWorkflow`, `UnfreezeAccountWorkflow`, `DestroyAccountWorkflow`
- **Transaction Processing**: `DepositAccountWorkflow`, `WithdrawAccountWorkflow`, `TransactionReversalWorkflow`
- **Multi-Asset Operations**: `AssetDepositWorkflow`, `AssetWithdrawWorkflow`, `AssetTransferWorkflow`
- **Transfer Operations**: `TransferWorkflow`, `BulkTransferWorkflow` (with compensation)
- **System Operations**: `BalanceInquiryWorkflow`, `AccountValidationWorkflow`, `BatchProcessingWorkflow`
- **Custodian Integration**: `CustodianTransferWorkflow` for external custodian operations
- **Governance Operations**: `AddAssetWorkflow`, `FeatureToggleWorkflow`, `UpdateConfigurationWorkflow`
- **Enhanced Validation**: Comprehensive KYC, address verification, identity checks, and compliance screening
- **Batch Processing**: Daily turnover calculation, statement generation, interest processing, compliance checks, regulatory reporting

### Queue Configuration
Events are processed through separate queues:
- `events`: General domain events
- `ledger`: Account lifecycle events
- `transactions`: Money movement events
- `transfers`: Transfer-specific events
- `webhooks`: Webhook delivery processing

### Security Features
- **Quantum-resistant hashing**: SHA3-512 for all transactions
- **Event integrity**: Cryptographic validation using `Hash` value objects
- **Audit trails**: Complete event history for all operations
- **Enhanced error logging**: Comprehensive error tracking with context for hash validation failures
- **Compliance monitoring**: Automated detection of suspicious patterns, sanctions screening, and regulatory compliance

### Admin Dashboard (Filament)
- **Account Management**: Full CRUD operations with real-time multi-asset balance updates
- **Transaction History**: Enhanced event-sourced transaction API with direct event store querying
- **Asset Management**: Asset CRUD with type filtering, precision validation, and metadata management
- **Exchange Rate Monitoring**: Real-time rate tracking with age indicators and bulk operations
- **Governance Interface**: Poll management, vote tracking, and governance analytics
- **Bulk Operations**: Freeze/unfreeze accounts, update exchange rates, activate/deactivate assets
- **Real-time Statistics**: 
  - **Account Widgets**: Balance trends, growth metrics, and account overview
  - **Transaction Widgets**: Daily volume, transaction type distribution, net cash flow
  - **Asset Widgets**: Asset distribution, exchange rate statistics
  - **System Health**: Real-time monitoring of services, cache, queues, and system performance
- **Advanced Analytics**:
  - **Balance Trends**: Track total and average balance over time with configurable periods
  - **Transaction Volume**: Analyze deposits, withdrawals, and transfers with interactive charts
  - **Cash Flow Analysis**: Monitor debit/credit flows with net calculations
  - **Growth Metrics**: Track new account creation and cumulative growth
  - **Governance Analytics**: Poll activity, voting patterns, and governance statistics
- **Export Functionality**: Export accounts, transactions, assets, exchange rates, and users to CSV/XLSX formats
- **Webhook Management**: Configure and monitor webhook endpoints for real-time event notifications
- **Search & Filtering**: Advanced search across all entities with multiple filter criteria

## Implementation Phases

### Phase 4: Basket Assets ✅ Completed

- **Basket Asset Models**:
  - Created `BasketAsset` model for defining composite assets with fixed/dynamic types
  - Implemented `BasketComponent` model for weighted asset composition  
  - Added `BasketValue` model for historical value tracking
  - Integrated with existing Asset model via `is_basket` flag

- **Services**:
  - `BasketValueCalculationService`: Calculates basket values based on component weights and current exchange rates
  - `BasketRebalancingService`: Handles dynamic basket rebalancing with configurable frequencies
  - Caching support for performance optimization with 5-minute TTL

- **Event Sourcing**:
  - Created `BasketCreated`, `BasketRebalanced`, `BasketDecomposed` events
  - Full audit trail for all basket operations

- **Database Schema**:
  - `basket_assets`: Main basket definitions with rebalancing configuration
  - `basket_components`: Asset composition with weights and min/max bounds
  - `basket_values`: Historical value tracking with component breakdowns

- **Testing**:
  - Comprehensive test coverage for models and services
  - Factory support for generating test basket data
  - Performance and edge case testing

### Phase 1: Multi-Asset Foundation Implementation ✅ Completed
- **Asset Domain Structure**:
  - Created comprehensive Asset model supporting fiat, crypto, and commodity types
  - Implemented asset metadata storage for extensibility
  - Added precision handling for different asset types (2 decimals for fiat, 8 for crypto)
  
- **Database Schema Evolution**:
  - Added `assets` table with initial seed data (USD, EUR, GBP, BTC, ETH, XAU)
  - Created `account_balances` table for multi-asset support
  - Implemented backward-compatible migration preserving existing USD balances
  - Maintained accounts.balance field for legacy compatibility

- **Multi-Asset Account Support**:
  - Enhanced Account model with balances relationship
  - Created AccountBalance model with credit/debit operations
  - Implemented getBalance() method defaulting to USD for backward compatibility
  - Added helper methods: addBalance(), subtractBalance(), hasBalance()

- **Event Sourcing Updates**:
  - Created asset-aware events: AssetBalanceAdded, AssetBalanceSubtracted, AssetTransferred
  - Implemented AssetTransactionAggregate for multi-asset transaction handling
  - Created AssetTransferAggregate for asset transfers between accounts
  - Updated event class map configuration

- **API Backward Compatibility**:
  - Updated all controllers to use getBalance('USD') for existing endpoints
  - Modified cache services to work with multi-asset balances
  - Fixed account factory to create USD balances automatically
  - All existing tests pass without modification

### Phase 2: Exchange Rates and Multi-Asset Transactions ✅ Completed
- **Exchange Rate Management**: ExchangeRate model with validation, provider support, and caching
- **Multi-Asset Transaction Engine**: Asset-aware events, aggregates, and projectors
- **Enhanced Workflow System**: AssetDepositWorkflow, AssetWithdrawWorkflow, AssetTransferWorkflow with compensation
- **API Layer**: REST APIs for exchange rates, currency conversion, and asset transfers
- **Database Schema**: exchange_rates table with comprehensive indexing and constraints
- **Testing**: Complete test coverage for all asset and exchange rate features

### Phase 3: Platform Integration with Admin Dashboard and REST APIs ✅ Completed
- **Filament Admin Resources**: Complete asset and exchange rate management interfaces
- **REST API Layer**: Production-ready APIs for external platform integration
- **Enhanced Admin Dashboard**: Asset management, exchange rate monitoring, dashboard widgets
- **API Documentation**: OpenAPI/Swagger documentation for all endpoints
- **Authentication**: Sanctum-based API authentication for protected endpoints

### Phase 4: Enhanced Transaction Architecture ✅ Completed
- **Event-First Transaction History**: Direct querying of stored events for transaction data
- **Multi-Asset Event Support**: Proper handling of AssetBalanceAdded, AssetTransferred events
- **Filament Transaction Interface**: Transaction history with analytics, filtering, and export
- **Multi-Asset Support**: Exchange rate tracking and cross-asset transaction support

### Phase 5: Governance & Polling Engine ✅ Completed
- **Core Governance System**: Poll and Vote models with configurable voting strategies
- **Workflow Integration**: Automated execution of governance decisions (AddAssetWorkflow, etc.)
- **Admin Interface**: Complete poll management and vote tracking with analytics
- **Security & Integrity**: Vote signatures, double voting prevention, audit logging

### Phase 6: Documentation and Testing Overhaul ✅ Completed
- **Comprehensive Documentation Review**: Updated all documentation to match current implementation
- **Test Coverage Enhancement**: Adding missing tests and fixing existing test gaps
- **API Documentation**: Complete OpenAPI specification with all endpoints documented


## Key Development Patterns

### Creating Workflows
Workflows follow the saga pattern with compensation logic:
```php
// Simple workflow
class DepositAccountWorkflow extends Workflow
{
    public function execute(AccountUuid $uuid, Money $money): \Generator
    {
        return yield ActivityStub::make(DepositAccountActivity::class, $uuid, $money);
    }
}

// Compensatable workflow
class TransferWorkflow extends Workflow
{
    public function execute(AccountUuid $from, AccountUuid $to, Money $money): \Generator
    {
        try {
            yield ChildWorkflowStub::make(WithdrawAccountWorkflow::class, $from, $money);
            $this->addCompensation(fn() => ChildWorkflowStub::make(DepositAccountWorkflow::class, $from, $money));
            
            yield ChildWorkflowStub::make(DepositAccountWorkflow::class, $to, $money);
        } catch (\Throwable $th) {
            yield from $this->compensate();
            throw $th;
        }
    }
}
```

### Event Handling
Events are recorded in aggregates and processed by projectors/reactors:
```php
// Recording events in aggregates
$aggregate->recordThat(new MoneyAdded($money, $hash));

// Processing in projectors
class AccountProjector extends Projector
{
    public function onMoneyAdded(MoneyAdded $event): void
    {
        app(CreditAccount::class)($event);
    }
}
```

### Security Implementation
All financial events include quantum-resistant hashes:
```php
class MoneyAdded extends ShouldBeStored implements HasHash, HasMoney
{
    public function __construct(
        public readonly Money $money,
        public readonly Hash $hash
    ) {}
}
```

## Testing Patterns

### Workflow Testing
```php
it('can execute transfer workflow', function () {
    WorkflowStub::fake();
    
    $workflow = WorkflowStub::make(TransferWorkflow::class);
    $workflow->start($fromAccount, $toAccount, $money);
    
    WorkflowStub::assertDispatched(WithdrawAccountActivity::class);
    WorkflowStub::assertDispatched(DepositAccountActivity::class);
});
```

### Aggregate Testing
```php
it('can record money added event', function () {
    $aggregate = TransactionAggregate::retrieve($uuid);
    $aggregate->credit($money);
    
    expect($aggregate->getStoredEvents())->toHaveCount(1);
    expect($aggregate->getStoredEvents()[0])->toBeInstanceOf(MoneyAdded::class);
});
```

### Model Factory Testing
```php
// AccountFactory provides realistic test data
it('can create account with factory', function () {
    $account = Account::factory()->create();
    
    expect($account->uuid)->toBeString();
    expect($account->user_uuid)->toBeString();
    expect($account->balance)->toBeInt();
});

// Factory states for specific scenarios
$zeroAccount = Account::factory()->zeroBalance()->create();
$richAccount = Account::factory()->withBalance(100000)->create();
$userAccount = Account::factory()->forUser($user)->create();
```

### Filament Resource Testing
```php
// Test admin dashboard resources
it('can list accounts in admin panel', function () {
    $accounts = Account::factory()->count(5)->create();
    
    livewire(AccountResource\Pages\ListAccounts::class)
        ->assertCanSeeTableRecords($accounts);
});

// Test account operations
it('can deposit money through admin panel', function () {
    $account = Account::factory()->create();
    
    livewire(AccountResource\Pages\ListAccounts::class)
        ->callTableAction('deposit', $account, data: [
            'amount' => 50.00,
        ])
        ->assertHasNoTableActionErrors();
});
```

### Admin Dashboard Development
When working with Filament resources:
```php
// Creating custom actions
Tables\Actions\Action::make('custom_action')
    ->label('Custom Action')
    ->icon('heroicon-o-star')
    ->action(function (Model $record): void {
        // Action logic here
    })
    ->visible(fn (Model $record): bool => $record->canPerformAction());

// Adding bulk operations
Tables\Actions\BulkAction::make('bulk_process')
    ->action(function (Collection $records): void {
        foreach ($records as $record) {
            // Process each record
        }
    })
    ->requiresConfirmation();

// Custom widgets
class CustomWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Label', 'Value')
                ->description('Description')
                ->color('success'),
        ];
    }
}

// Export functionality
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\ExportColumn;

// Add export to table header actions
Actions\ExportAction::make()
    ->exporter(AccountExporter::class)
    ->label('Export Accounts')
    ->icon('heroicon-o-arrow-down-tray')
    ->color('success');

// Create exporter class
class AccountExporter extends Exporter
{
    protected static ?string $model = Account::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uuid')->label('Account ID'),
            ExportColumn::make('balance')
                ->label('Balance (USD)')
                ->formatStateUsing(fn ($state) => number_format($state / 100, 2)),
        ];
    }
}
```

### Basket Asset Implementation
Working with basket assets and complex financial instruments:
```php
// Creating a basket asset
$basket = BasketAsset::create([
    'code' => 'STABLE_BASKET',
    'name' => 'Stable Currency Basket',
    'type' => 'fixed', // or 'dynamic'
    'rebalance_frequency' => 'monthly',
    'is_active' => true,
]);

// Adding components with weights
$basket->components()->createMany([
    ['asset_code' => 'USD', 'weight' => 40.0],
    ['asset_code' => 'EUR', 'weight' => 35.0],
    ['asset_code' => 'GBP', 'weight' => 25.0],
]);

// For dynamic baskets, add weight ranges
$basket->components()->create([
    'asset_code' => 'USD',
    'weight' => 40.0,
    'min_weight' => 35.0,
    'max_weight' => 45.0,
]);

// Create as tradeable asset
$asset = $basket->toAsset();

// Calculate current value
$valueService = app(BasketValueCalculationService::class);
$value = $valueService->calculateValue($basket);
echo "Current value: \${$value->value}";

// Rebalance dynamic basket
$rebalancingService = app(BasketRebalancingService::class);
if ($basket->needsRebalancing()) {
    $result = $rebalancingService->rebalance($basket);
    echo "Rebalanced {$result['adjustments_count']} components";
}

// Account operations
$accountService = app(BasketAccountService::class);

// Decompose basket into components
$decomposition = $accountService->decomposeBasket($account, 'STABLE_BASKET', 10000);
// Results in individual asset balances

// Compose basket from components
$composition = $accountService->composeBasket($account, 'STABLE_BASKET', 10000);
// Results in basket asset balance

// Get basket holdings value
$holdings = $accountService->getBasketHoldingsValue($account);
```

### Basket Value Calculation
Understanding basket valuation mechanics:
```php
// Manual calculation example
$basket = BasketAsset::with('components.asset')->find('STABLE_BASKET');
$totalValue = 0;

foreach ($basket->components as $component) {
    if (!$component->is_active) continue;
    
    // Get exchange rate to USD
    $rate = $component->asset_code === 'USD' 
        ? 1.0 
        : ExchangeRate::getRate($component->asset_code, 'USD');
    
    // Calculate weighted contribution
    $weightedValue = $rate * ($component->weight / 100);
    $totalValue += $weightedValue;
}

// Automated calculation with caching
$valueService = app(BasketValueCalculationService::class);
$value = $valueService->calculateValue($basket, true); // Use cache

// Get historical performance
$performance = $valueService->calculatePerformance(
    $basket,
    now()->subMonth(),
    now()
);

echo "30-day return: {$performance['percentage_change']}%";
```

### Dynamic Rebalancing Logic
Implementing rebalancing strategies:
```php
class BasketRebalancingService
{
    public function rebalance(BasketAsset $basket): array
    {
        if ($basket->type !== 'dynamic') {
            throw new \Exception('Only dynamic baskets can be rebalanced');
        }
        
        $adjustments = [];
        $activeComponents = $basket->components()->where('is_active', true)->get();
        
        foreach ($activeComponents as $component) {
            $currentWeight = $component->weight;
            $targetWeight = $this->calculateTargetWeight($component);
            
            // Check if adjustment needed
            if (abs($currentWeight - $targetWeight) > 0.01) {
                $adjustments[] = [
                    'asset_code' => $component->asset_code,
                    'old_weight' => $currentWeight,
                    'new_weight' => $targetWeight,
                    'adjustment' => $targetWeight - $currentWeight,
                ];
                
                $component->update(['weight' => $targetWeight]);
            }
        }
        
        // Normalize weights to sum to 100%
        $this->normalizeWeights($activeComponents);
        
        // Update rebalancing timestamp
        $basket->update(['last_rebalanced_at' => now()]);
        
        // Fire event
        event(new BasketRebalanced($basket->code, $adjustments));
        
        return [
            'status' => 'completed',
            'basket' => $basket->code,
            'adjustments' => $adjustments,
            'adjustments_count' => count($adjustments),
        ];
    }
    
    private function calculateTargetWeight(BasketComponent $component): float
    {
        // Clamp to min/max range
        $target = $component->weight;
        
        if ($component->min_weight && $target < $component->min_weight) {
            $target = $component->min_weight;
        }
        
        if ($component->max_weight && $target > $component->max_weight) {
            $target = $component->max_weight;
        }
        
        return $target;
    }
}
```

### Basket API Integration
Working with basket APIs:
```php
// List all baskets
$response = Http::get('/api/v2/baskets', [
    'type' => 'dynamic',
    'active' => true,
]);

// Get basket details
$basket = Http::get('/api/v2/baskets/STABLE_BASKET');

// Create new basket
$newBasket = Http::post('/api/v2/baskets', [
    'code' => 'CRYPTO_BASKET',
    'name' => 'Cryptocurrency Basket',
    'type' => 'dynamic',
    'rebalance_frequency' => 'daily',
    'components' => [
        ['asset_code' => 'BTC', 'weight' => 60.0, 'min_weight' => 50.0, 'max_weight' => 70.0],
        ['asset_code' => 'ETH', 'weight' => 40.0, 'min_weight' => 30.0, 'max_weight' => 50.0],
    ],
]);

// Get current value
$value = Http::get('/api/v2/baskets/STABLE_BASKET/value');

// Rebalance basket
$rebalance = Http::post('/api/v2/baskets/STABLE_BASKET/rebalance');

// Account operations
$decompose = Http::post("/api/v2/accounts/{$accountUuid}/baskets/decompose", [
    'basket_code' => 'STABLE_BASKET',
    'amount' => 10000,
]);

$compose = Http::post("/api/v2/accounts/{$accountUuid}/baskets/compose", [
    'basket_code' => 'STABLE_BASKET',
    'amount' => 10000,
]);

// Get account basket holdings
$holdings = Http::get("/api/v2/accounts/{$accountUuid}/baskets");
```

### Basket Admin Interface
Managing baskets through Filament:
```php
// Custom basket action in Filament resource
Tables\Actions\Action::make('rebalance')
    ->label('Rebalance')
    ->icon('heroicon-m-scale')
    ->color('warning')
    ->visible(fn (BasketAsset $record) => $record->type === 'dynamic')
    ->action(function (BasketAsset $record) {
        $service = app(BasketRebalancingService::class);
        $result = $service->rebalance($record);
        
        Notification::make()
            ->success()
            ->title('Basket Rebalanced')
            ->body("Adjusted {$result['adjustments_count']} components")
            ->send();
    });

// Basket value widget
class BasketValueChart extends ChartWidget
{
    public function getData(): array
    {
        $values = BasketValue::where('basket_code', $this->record->code)
            ->where('calculated_at', '>=', now()->subDays(30))
            ->orderBy('calculated_at')
            ->get();
            
        return [
            'datasets' => [[
                'label' => 'Value (USD)',
                'data' => $values->pluck('value'),
                'borderColor' => 'rgb(75, 192, 192)',
            ]],
            'labels' => $values->pluck('calculated_at')->map(fn($date) => $date->format('M j')),
        ];
    }
}

// Component weight validation
Forms\Components\Repeater::make('components')
    ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
        $components = $get('components') ?? [];
        $totalWeight = collect($components)->sum('weight');
        
        if (abs($totalWeight - 100) > 0.01) {
            Notification::make()
                ->warning()
                ->title('Weight Warning')
                ->body("Total weight is {$totalWeight}%. Must sum to 100%.")
                ->send();
        }
    });
```

## Multi-Asset Development Patterns

### Asset Management
When implementing multi-asset support:
```php
// Creating Asset entity
namespace App\Domain\Asset\Models;

class Asset extends Model
{
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'code',      // 'USD', 'EUR', 'BTC', 'XAU'
        'name',      // 'US Dollar', 'Euro', 'Bitcoin', 'Gold'
        'type',      // 'fiat', 'crypto', 'commodity'
        'precision', // Decimal places (2 for USD, 8 for BTC)
        'is_active',
    ];
}

// Account with multi-asset balances
class Account extends Model
{
    public function balances(): HasMany
    {
        return $this->hasMany(AccountBalance::class, 'account_uuid', 'uuid');
    }
    
    public function getBalance(string $asset_code): int
    {
        return $this->balances()
            ->where('asset_code', $asset_code)
            ->value('balance') ?? 0;
    }
    
    public function addBalance(string $asset_code, int $amount): void
    {
        $balance = $this->balances()->firstOrCreate(
            ['asset_code' => $asset_code],
            ['balance' => 0]
        );
        
        $balance->increment('balance', $amount);
    }
}
```

### Multi-Asset Events
Update events to include asset information:
```php
// Multi-asset event example
class AssetBalanceAdded extends ShouldBeStored
{
    public function __construct(
        public readonly string $asset_code,
        public readonly int $amount,
        public readonly Hash $hash,
        public readonly ?array $metadata = []
    ) {}
}
```

### Exchange Rate Service
Working with exchange rates:
```php
// Usage in workflows
class CrossAssetTransferWorkflow extends Workflow
{
    public function execute(
        AccountUuid $from,
        AccountUuid $to,
        string $from_asset,
        string $to_asset,
        int $amount
    ): \Generator {
        // Get exchange rate
        $rate = yield ActivityStub::make(
            GetExchangeRateActivity::class,
            $from_asset,
            $to_asset
        );
        
        // Calculate converted amount
        $converted_amount = (int) round($amount * $rate);
        
        // Perform transfers
        yield from $this->executeTransfers(
            $from,
            $to,
            $from_asset,
            $to_asset,
            $amount,
            $converted_amount
        );
    }
}

// Caching exchange rates
class ExchangeRateService
{
    public function getRate(string $from, string $to): float
    {
        return Cache::remember(
            "rate:{$from}:{$to}",
            300, // 5 minutes
            fn() => $this->fetchRateFromProvider($from, $to)
        );
    }
}
```

### Governance Implementation
Building polling system:
```php
// Creating a poll
$poll = Poll::create([
    'title' => 'Add support for Japanese Yen?',
    'type' => 'single_choice',
    'options' => [
        ['id' => 'yes', 'label' => 'Yes, add JPY support'],
        ['id' => 'no', 'label' => 'No, not needed'],
    ],
    'start_date' => now(),
    'end_date' => now()->addDays(7),
    'voting_power_strategy' => OneUserOneVoteStrategy::class,
    'execution_workflow' => AddAssetWorkflow::class,
]);

// Voting
class VoteController extends Controller
{
    public function store(Poll $poll, Request $request)
    {
        $validated = $request->validate([
            'option_id' => 'required|string',
        ]);
        
        $votingPower = app($poll->voting_power_strategy)
            ->calculatePower($request->user(), $poll);
        
        Vote::create([
            'poll_id' => $poll->id,
            'user_uuid' => $request->user()->uuid,
            'selected_options' => [$validated['option_id']],
            'voting_power' => $votingPower,
        ]);
        
        return response()->json(['message' => 'Vote recorded']);
    }
}
```

### Testing Multi-Asset Features
```php
// Test multi-asset account
test('account can hold multiple asset balances', function () {
    $account = Account::factory()->create();
    
    $account->addBalance('USD', 10000);    // $100.00
    $account->addBalance('EUR', 5000);     // €50.00
    $account->addBalance('BTC', 100000000); // 1 BTC
    
    expect($account->getBalance('USD'))->toBe(10000);
    expect($account->getBalance('EUR'))->toBe(5000);
    expect($account->getBalance('BTC'))->toBe(100000000);
    expect($account->balances)->toHaveCount(3);
});
```

## Important Files and Locations
- Event configuration: `config/event-sourcing.php`
- Workflow configuration: `config/workflows.php`
- Queue configuration: `config/queue.php`
- Cache configuration: `config/domain-cache.php`
- Domain models: `app/Models/`
- Domain events: `app/Domain/*/Events/`
- Aggregates: `app/Domain/*/Aggregates/`
- Workflows: `app/Domain/*/Workflows/`
- Activities: `app/Domain/*/Workflows/*Activity.php`
- Cache services: `app/Domain/*/Services/Cache/`
- Filament resources: `app/Filament/Admin/Resources/`
- Filament tests: `tests/Feature/Filament/`

### New Multi-Asset Locations
- Asset domain: `app/Domain/Asset/`
- Exchange rate providers: `app/Domain/Exchange/Providers/`
- Governance domain: `app/Domain/Governance/`
- Multi-asset migrations: `database/migrations/`

### Basket Asset Locations
- Basket models: `app/Models/BasketAsset.php`, `app/Models/BasketComponent.php`, `app/Models/BasketValue.php`
- Basket services: `app/Domain/Basket/Services/`
- Basket events: `app/Domain/Basket/Events/`
- Basket workflows: `app/Domain/Basket/Workflows/`
- Basket API controllers: `app/Http/Controllers/Api/BasketController.php`, `app/Http/Controllers/Api/BasketAccountController.php`
- Basket Filament resources: `app/Filament/Admin/Resources/BasketAssetResource/`
- Basket tests: `tests/Feature/Basket/`
- Basket migration: `database/migrations/2025_06_16_232847_create_basket_assets_tables.php`
- Basket documentation: `docs/BASKET_ASSETS_DESIGN.md`