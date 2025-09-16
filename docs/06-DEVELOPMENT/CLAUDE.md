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

### GCU Platform Management
```bash
# Seed GCU basket configuration
php artisan db:seed --class=GCUBasketSeeder

# Set up GCU voting polls
php artisan voting:setup                      # Create next month's poll
php artisan voting:setup --month=2024-09      # Create specific month
php artisan voting:setup --year=2024          # Create all polls for year

# Access admin dashboard
# http://localhost:8000/admin
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

### Email Configuration
For email setup and testing, see the comprehensive [Email Setup Guide](../EMAIL-SETUP.md).

Quick setup with Resend (recommended):
```bash
# 1. Get API key from https://resend.com/api-keys
# 2. Update .env:
MAIL_MAILER=resend
RESEND_KEY=re_your_api_key_here
MAIL_FROM_ADDRESS=noreply@yourdomain.com

# 3. Test email sending:
php artisan tinker
>>> Mail::raw('Test email', fn($m) => $m->to('test@example.com')->subject('Test'));
```

### Phase 8 Features Management

#### Exchange & Trading
```bash
# Process order matching
php artisan exchange:match-orders

# Update external exchange rates
php artisan exchange:sync-rates

# Generate market making orders
php artisan liquidity:generate-orders --pool=all

# Monitor arbitrage opportunities
php artisan exchange:arbitrage-monitor
```

#### P2P Lending
```bash
# Process loan applications
php artisan loans:process-applications

# Check for defaulted loans
php artisan loans:check-defaults

# Calculate daily interest
php artisan loans:calculate-interest

# Generate loan statements
php artisan loans:generate-statements --month=2024-09
```

#### Stablecoins
```bash
# Monitor collateral health
php artisan stablecoins:monitor-health

# Process liquidations
php artisan stablecoins:liquidate

# Update oracle prices
php artisan stablecoins:update-prices

# Calculate stability fees
php artisan stablecoins:calculate-fees
```

#### Liquidity Pools
```bash
# Distribute rewards to LPs
php artisan liquidity:distribute-rewards

# Rebalance pools
php artisan liquidity:rebalance --strategy=conservative

# Calculate LP positions
php artisan liquidity:calculate-positions

# Generate pool analytics
php artisan liquidity:analytics --period=daily
```

#### Blockchain Wallets
```bash
# Sync blockchain balances
php artisan wallets:sync-balances

# Process pending transactions
php artisan wallets:process-transactions

# Generate new addresses
php artisan wallets:generate-addresses --chain=ethereum

# Monitor gas prices
php artisan wallets:monitor-gas
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

### Custodian Management
```bash
# Synchronize balances with external custodians
php artisan custodian:sync-balances

# Sync specific account
php artisan custodian:sync-balances --account=uuid-here

# Sync specific custodian
php artisan custodian:sync-balances --custodian=paysera

# Force sync even if recently synchronized
php artisan custodian:sync-balances --force

# Schedule automatic synchronization (add to kernel.php)
# $schedule->command('custodian:sync-balances')->everyFiveMinutes();
```

### Compliance and Reporting
```bash
# Generate compliance reports
php artisan compliance:generate-ctr --date=2024-06-21
php artisan compliance:generate-sar --month=2024-09

# Process KYC documents
php artisan kyc:process-pending

# Data protection (GDPR)
php artisan gdpr:export-user-data user@example.com
php artisan gdpr:anonymize-user user-uuid-here

# Schedule compliance tasks (add to kernel.php)
# $schedule->command('compliance:generate-ctr')->daily();
# $schedule->command('compliance:generate-sar')->monthly();
```

### Liquidity Pool Management
```bash
# Create a new liquidity pool
php artisan tinker
>>> $poolService = app(\App\Domain\Exchange\Services\LiquidityPoolService::class);
>>> $poolId = $poolService->createPool('BTC', 'USD', '0.003');

# Distribute rewards to liquidity providers
php artisan liquidity:distribute-rewards
php artisan liquidity:distribute-rewards --pool=pool-uuid --dry-run

# Rebalance pools to maintain optimal ratios
php artisan liquidity:rebalance
php artisan liquidity:rebalance --pool=pool-uuid --strategy=aggressive

# Update automated market making orders
php artisan liquidity:update-market-making
php artisan liquidity:update-market-making --pool=pool-uuid --cancel-existing

# Scheduled tasks (already in console.php):
# - Rewards distribution: Hourly
# - Pool rebalancing: Every 30 minutes
# - Market making updates: Every 5 minutes
```

#### Enhanced Liquidity Pool Patterns

**Event Sourcing with Custom Repositories:**
```php
// Custom repository usage in aggregate
protected function getStoredEventRepository(): LiquidityPoolEventRepository
{
    return app()->make(LiquidityPoolEventRepository::class);
}

protected function getSnapshotRepository(): LiquidityPoolSnapshotRepository
{
    return app()->make(LiquidityPoolSnapshotRepository::class);
}
```

**Value Objects for Type Safety:**
```php
use App\Domain\Exchange\LiquidityPool\ValueObjects\PoolId;
use App\Domain\Exchange\LiquidityPool\ValueObjects\LiquidityShare;
use App\Domain\Exchange\LiquidityPool\ValueObjects\PoolRatio;
use App\Domain\Exchange\LiquidityPool\ValueObjects\PoolFee;

// Usage
$poolId = PoolId::generate();
$share = new LiquidityShare($amount, $totalShares);
$ratio = new PoolRatio($baseReserve, $quoteReserve);
$fee = PoolFee::fromBasisPoints(30); // 0.3%
```

**Circuit Breaker for External Services:**
```php
$circuitBreaker = app(CircuitBreakerService::class);
$result = $circuitBreaker->call('external_exchange', function () {
    return $this->binanceConnector->getOrderBook('BTC/USD');
});
```

**Enhanced Workflow with Retry Policies:**
```php
use App\Domain\Exchange\Workflows\EnhancedLiquidityManagementWorkflow;
use App\Domain\Exchange\Workflows\Policies\LiquidityRetryPolicy;

// Workflow with retry policies
ActivityStub::make(ValidateLiquidityActivity::class)
    ->withRetryOptions(LiquidityRetryPolicy::standard())
    ->execute($input);
```

### SEO and Schema Markup
```bash
# Schema.org structured data is implemented for better SEO
# Available schema types via x-schema Blade component:
# - organization: Company information
# - website: Site search capabilities  
# - software: Platform/application details
# - gcu: Global Currency Unit product
# - faq: Frequently asked questions
# - breadcrumb: Navigation hierarchy
# - service: Service offerings
# - article: Blog/article content

# Usage in Blade templates:
# <x-schema type="organization" />
# <x-schema type="breadcrumb" :data="[['name' => 'Home', 'url' => '/']]" />
# <x-schema type="faq" :data="$faqArray" />

# Helper class: App\Helpers\SchemaHelper
# Generates JSON-LD structured data for search engines
```

## Architecture Overview

### Domain-Driven Design Structure
- **Account Domain** (`app/Domain/Account/`): Core banking account management with multi-asset support
- **Asset Domain** (`app/Domain/Asset/`): Multi-asset ledger, exchange rates, and asset management
- **Basket Domain** (`app/Domain/Basket/`): Basket asset management with rebalancing services
- **Exchange Domain** (`app/Domain/Exchange/`): Exchange rate providers and currency conversion
- **Custodian Domain** (`app/Domain/Custodian/`): External custodian integration with real bank connectors
- **Compliance Domain** (`app/Domain/Compliance/`): KYC, AML, and regulatory reporting
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

### Phase 7: Additional Platform Features ✅ Completed
- **Webhook System**: Full webhook management with retry logic and delivery tracking
- **Batch Processing**: High-volume transaction processing with validation
- **Transaction Reversal**: Complete reversal system with audit trails
- **Daily Reconciliation**: Automated reconciliation with external systems
- **Bank Alerting**: Real-time alerts for critical banking events
- **Regulatory Reporting**: CTR/SAR generation and compliance dashboards

### Phase 8: Unified Platform Features ✅ Completed

#### 8.1 Liquidity Pool Management
- **Automated Market Making**: Dynamic spread adjustment and order generation
- **Pool Types**: Constant product, weighted, stable, and concentrated liquidity
- **Incentive System**: Multi-factor rewards with performance multipliers
- **Rebalancing**: Conservative, aggressive, and adaptive strategies
- **Event Sourcing**: Full audit trail with PoolCreated, LiquidityAdded events

#### 8.2 P2P Lending Platform
- **Loan Lifecycle**: Application → Credit Scoring → Funding → Repayment
- **Risk Management**: Multi-factor credit scoring and collateral management
- **Interest Calculation**: Fixed and variable rate support with amortization
- **Default Handling**: Automated liquidation and recovery workflows
- **Event-Driven**: LoanApplicationCreated, LoanFunded, PaymentMade events

#### 8.3 Stablecoin Infrastructure
- **Multi-Collateral**: Support for crypto and fiat collateral types
- **Oracle Integration**: Multi-source price feeds with median calculation
- **Liquidation Engine**: Automated liquidation with keeper incentives
- **Stability Mechanisms**: DSR, emergency shutdown, rebalancing
- **Position Management**: Real-time health monitoring and alerts

#### 8.4 Exchange & Trading Engine
- **Order Matching**: Event-sourced order book with saga pattern
- **External Integration**: Binance, Kraken, Coinbase connectors
- **Arbitrage Detection**: Real-time opportunity scanning
- **Market Making**: Automated liquidity provision
- **Price Synchronization**: Multi-exchange rate aggregation

#### 8.5 Blockchain Infrastructure
- **Multi-Chain Support**: Bitcoin, Ethereum, Polygon, BSC
- **HD Wallets**: BIP44 compliant hierarchical deterministic wallets
- **Transaction Management**: Gas optimization and batching
- **Key Security**: Hardware security module integration ready


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

### Phase 8 Development Patterns

#### Liquidity Pool Pattern
```php
// Creating a liquidity pool
$aggregate = LiquidityPoolAggregate::retrieve($poolUuid);
$aggregate->createPool($baseAsset, $quoteAsset, $feeRate);
$aggregate->persist();

// Adding liquidity
$aggregate->addLiquidity(
    providerId: $userId,
    baseAmount: BigDecimal::of('1000'),
    quoteAmount: BigDecimal::of('850'),
    minShares: BigDecimal::of('900')
);

// Automated Market Making
$ammService = app(AutomatedMarketMakerService::class);
$orders = $ammService->generateOrders($pool, [
    'depth' => 5,
    'spread_factor' => 1.0,
    'max_order_value' => BigDecimal::of('10000')
]);
```

#### P2P Lending Pattern
```php
// Loan application workflow
class LoanApplicationWorkflow extends Workflow
{
    public function execute(array $loanData): \Generator
    {
        // Step 1: Create application
        $application = yield ActivityStub::make(
            CreateLoanApplicationActivity::class,
            $loanData
        );
        
        // Step 2: Credit scoring
        $score = yield ActivityStub::make(
            CalculateCreditScoreActivity::class,
            $application->borrower_id
        );
        
        // Step 3: Risk assessment
        $risk = yield ActivityStub::make(
            AssessRiskActivity::class,
            $application,
            $score
        );
        
        // Step 4: Set interest rate
        yield ActivityStub::make(
            SetInterestRateActivity::class,
            $application,
            $risk
        );
        
        return $application;
    }
}
```

#### Stablecoin Minting Pattern
```php
// Mint stablecoins with collateral
$aggregate = StablecoinPositionAggregate::retrieve($positionId);
$aggregate->openPosition(
    ownerId: $userId,
    stablecoinCode: 'FUSD',
    collateral: [
        ['asset' => 'ETH', 'amount' => BigDecimal::of('5')]
    ]
);
$aggregate->mintStablecoins(BigDecimal::of('10000'));

// Monitor health with reactor
class CollateralHealthReactor extends Reactor
{
    public function onCollateralPriceUpdated(CollateralPriceUpdated $event): void
    {
        $position = StablecoinPosition::find($event->positionId);
        if ($position->getHealthRatio()->isLessThan('1.5')) {
            dispatch(new LiquidatePositionJob($position));
        }
    }
}
```

#### External Exchange Integration
```php
// Connector pattern for exchanges
interface ExchangeConnectorInterface
{
    public function getOrderBook(string $symbol): OrderBook;
    public function placeOrder(Order $order): OrderResult;
    public function getBalance(string $asset): BigDecimal;
    public function withdraw(string $asset, BigDecimal $amount, string $address): WithdrawResult;
}

// Arbitrage detection
class ArbitrageService
{
    public function findOpportunities(string $base, string $quote): Collection
    {
        $prices = collect($this->connectors)->map(fn($connector) => 
            $connector->getOrderBook("{$base}/{$quote}")->getBestBid()
        );
        
        $maxBid = $prices->max('price');
        $minAsk = $prices->min('price');
        
        if ($maxBid->isGreaterThan($minAsk)) {
            return new ArbitrageOpportunity($base, $quote, $minAsk, $maxBid);
        }
    }
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

## GCU Platform Development Patterns

### User Bank Preferences
```php
// Creating default bank preferences for a user
$user = User::factory()->create();
$preferences = UserBankPreference::getDefaultAllocations();

foreach ($preferences as $pref) {
    $user->bankPreferences()->create($pref);
}

// Validate allocations sum to 100%
$isValid = UserBankPreference::validateAllocations($user->uuid);

// Get user's primary bank
$primaryBank = $user->bankPreferences()
    ->where('is_primary', true)
    ->first();
```

### GCU Voting Polls
```php
// Create monthly voting poll
$votingService = app(GCUVotingTemplateService::class);
$poll = $votingService->createMonthlyBasketVotingPoll();

// Create emergency rebalancing poll
$emergencyPoll = $votingService->createEmergencyRebalancingPoll(
    'Major USD devaluation detected'
);

// Calculate voting power
$strategy = new AssetWeightedVotingStrategy();
$votingPower = $strategy->calculatePower($user, $poll);
```

### GCU Basket Management
```php
// Access GCU basket configuration
$gcuBasket = BasketAsset::where('code', 'GCU')->first();

// Get current composition
$composition = $gcuBasket->components->map(function ($component) {
    return [
        'asset' => $component->asset_code,
        'weight' => $component->weight,
    ];
});

// Check if user has GCU
$hasGCU = $account->getBalance('GCU') > 0;
```

### Phase 4.2: Enhanced Governance Patterns

#### User Voting Interface
```php
// Get active polls for user
use App\Http\Controllers\Api\UserVotingController;

$controller = app(UserVotingController::class);
$activePolls = $controller->index($request);

// Submit basket allocation vote
$voteData = [
    'allocations' => [
        'USD' => 35,
        'EUR' => 30,
        'GBP' => 20,
        'CHF' => 10,
        'JPY' => 3,
        'XAU' => 2,
    ]
];

$controller->vote($poll->uuid, new Request($voteData));

// Get voting dashboard data
$dashboard = $controller->dashboard($request);
// Returns: stats, active_polls, voting_history, next_poll_date
```

#### Weighted Voting Calculation
```php
use App\Domain\Governance\Workflows\UpdateBasketCompositionWorkflow;

// The workflow automatically calculates weighted averages
$workflow = app(UpdateBasketCompositionWorkflow::class);
$workflow->execute($poll);

// Example of how weights are calculated:
// User A: 1000 GCU votes USD=40%, EUR=30%
// User B: 500 GCU votes USD=35%, EUR=35%
// Result: USD = (1000*40 + 500*35)/(1000+500) = 38.33%
```

#### GCU Configuration via Environment
```php
// In .env file:
GCU_ENABLED=true
GCU_BASKET_CODE=GCU
GCU_BASKET_NAME="Global Currency Unit"
GCU_BASKET_SYMBOL=Ǥ
GCU_BASKET_DESCRIPTION="Democratic global currency backed by real banks"

// Seeder uses configuration
php artisan db:seed --class=GCUBasketSeeder

// Access in code
$gcuEnabled = config('app.gcu_enabled', false);
$gcuCode = config('app.gcu_basket_code', 'GCU');
```

#### Frontend Integration Examples
```javascript
// Vue.js Component Usage
import GCUVotingDashboard from './components/GCUVotingDashboard.vue';

// React Integration
const VotingDashboard = () => {
    const [polls, setPolls] = useState([]);
    
    useEffect(() => {
        fetch('/api/voting/polls')
            .then(res => res.json())
            .then(data => setPolls(data.data));
    }, []);
    
    return <div>{/* Render polls */}</div>;
};

// Vanilla JavaScript
fetch('/api/voting/polls/' + pollId + '/vote', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
    },
    body: JSON.stringify({ allocations: {...} })
});
```

#### Testing Phase 4.2 Features
```php
// Test GCU basket creation
test('can create GCU basket from configuration', function () {
    config(['app.gcu_enabled' => true]);
    config(['app.gcu_basket_code' => 'TEST_GCU']);
    
    $this->artisan('db:seed', ['--class' => 'GCUBasketSeeder']);
    
    $basket = BasketAsset::where('code', 'TEST_GCU')->first();
    expect($basket)->not->toBeNull();
    expect($basket->type)->toBe('dynamic');
});

// Test weighted voting calculation
test('calculates weighted average correctly', function () {
    $votes = collect([
        ['voting_power' => 1000, 'allocations' => ['USD' => 40]],
        ['voting_power' => 500, 'allocations' => ['USD' => 35]],
    ]);
    
    $weightedAverage = $votes->sum(function ($vote) {
        return $vote['voting_power'] * $vote['allocations']['USD'];
    }) / $votes->sum('voting_power');
    
    expect($weightedAverage)->toBe(38.333333);
});
```

## Custodian Integration Patterns

### Real Bank Connectors
Working with production bank APIs:
```php
use App\Domain\Custodian\Services\CustodianRegistry;

// Get specific bank connector
$registry = app(CustodianRegistry::class);
$payseraConnector = $registry->getConnector('paysera');
$deutscheBankConnector = $registry->getConnector('deutsche_bank');
$santanderConnector = $registry->getConnector('santander');

// Check balance
$balance = $payseraConnector->getBalance('account-id', 'EUR');

// Initiate transfer
$request = new TransferRequest(
    fromAccount: 'source-account',
    toAccount: 'dest-account',
    amount: new Money(10000), // €100.00
    assetCode: 'EUR',
    reference: 'REF123',
    description: 'Payment'
);
$receipt = $connector->initiateTransfer($request);
```

### Multi-Bank Transfer Routing
```php
use App\Domain\Custodian\Services\MultiCustodianTransferService;

$transferService = app(MultiCustodianTransferService::class);

// Service automatically finds optimal route
$receipt = $transferService->transfer(
    fromAccount: $account1,
    toAccount: $account2,
    amount: new Money(50000),
    assetCode: 'EUR',
    reference: 'TRANSFER123'
);

// Routes can be:
// - Internal (same bank)
// - External (direct bank-to-bank)
// - Bridge (through intermediate bank)
```

### Settlement Processing
```php
use App\Domain\Custodian\Services\SettlementService;

$settlementService = app(SettlementService::class);

// Process pending settlements (scheduled task)
$results = $settlementService->processPendingSettlements();

// Settlement types:
// - Realtime: Immediate settlement
// - Batch: Grouped by time interval
// - Net: Offset debits/credits for efficiency

// Get settlement statistics
$stats = $settlementService->getSettlementStatistics();
```

### Compliance Workflows
```php
// Enhanced KYC workflow
$workflow = WorkflowStub::make(EnhancedKYCWorkflow::class);
$result = $workflow->start($userId, $documents);

// Regulatory reporting
$ctrReport = app(CTRReportService::class)->generateDailyReport(today());
$sarReport = app(SARReportService::class)->generateMonthlyReport();

// GDPR compliance
$gdprService = app(GDPRService::class);
$userData = $gdprService->exportUserData($userId);
$gdprService->anonymizeUser($userId);
```

## Phase 5.2 Resilience Patterns

### Circuit Breaker Implementation
```php
use App\Domain\Custodian\Services\CircuitBreakerService;

// Configure circuit breaker
$circuitBreaker = app(CircuitBreakerService::class);
$circuitBreaker->configure('paysera', [
    'failure_threshold' => 5,      // Open after 5 failures
    'success_threshold' => 2,      // Close after 2 successes
    'timeout' => 60,               // Reset timeout in seconds
    'recovery_timeout' => 120,     // Half-open state duration
]);

// Use circuit breaker in connector
class PayseraConnector extends BaseCustodianConnector
{
    protected function executeWithCircuitBreaker(string $operation, callable $callback)
    {
        $circuitBreaker = app(CircuitBreakerService::class);
        
        return $circuitBreaker->call($this->getName(), $operation, $callback);
    }
    
    public function getBalance(string $accountId, string $assetCode): Money
    {
        return $this->executeWithCircuitBreaker('getBalance', function () use ($accountId, $assetCode) {
            // Actual API call
            return $this->apiRequest('GET', "/accounts/{$accountId}/balance");
        });
    }
}

// Check circuit state
$state = $circuitBreaker->getState('paysera'); // 'closed', 'open', 'half_open'
$stats = $circuitBreaker->getStatistics('paysera');
```

### Retry Service with Exponential Backoff
```php
use App\Domain\Custodian\Services\RetryService;

$retryService = app(RetryService::class);

// Configure retry policy
$result = $retryService->execute(function () use ($connector, $request) {
    return $connector->initiateTransfer($request);
}, [
    'max_attempts' => 3,
    'initial_delay' => 1000,      // 1 second
    'max_delay' => 30000,         // 30 seconds
    'multiplier' => 2,            // Double delay each retry
    'jitter' => true,             // Add randomness to prevent thundering herd
    'retryable_exceptions' => [
        \Illuminate\Http\Client\ConnectionException::class,
        \Illuminate\Http\Client\RequestException::class,
    ],
]);

// Custom retry logic for specific operations
class CustodianTransferWorkflow extends Workflow
{
    public function execute(TransferRequest $request): \Generator
    {
        $retryService = app(RetryService::class);
        
        try {
            $receipt = yield $retryService->executeAsync(
                fn() => ActivityStub::make(InitiateTransferActivity::class, $request),
                [
                    'max_attempts' => 5,
                    'on_retry' => function ($attempt, $exception) {
                        Log::warning("Transfer retry attempt {$attempt}", [
                            'exception' => $exception->getMessage(),
                        ]);
                    },
                ]
            );
            
            return $receipt;
        } catch (\Exception $e) {
            // All retries exhausted
            yield ActivityStub::make(NotifyTransferFailureActivity::class, $request, $e);
            throw $e;
        }
    }
}
```

### Fallback Service for Graceful Degradation
```php
use App\Domain\Custodian\Services\FallbackService;

$fallbackService = app(FallbackService::class);

// Configure fallback chains
$fallbackService->registerChain('balance_check', [
    // Primary: Real-time API
    fn($accountId) => $connector->getBalance($accountId, 'EUR'),
    
    // Fallback 1: Cached balance
    fn($accountId) => Cache::get("balance:{$accountId}:EUR"),
    
    // Fallback 2: Last known balance from database
    fn($accountId) => CustodianAccount::where('account_id', $accountId)
        ->value('last_known_balance'),
    
    // Fallback 3: Return zero with warning
    fn($accountId) => (function() use ($accountId) {
        Log::error("All balance check methods failed for account: {$accountId}");
        return new Money(0);
    })(),
]);

// Execute with fallback
$balance = $fallbackService->execute('balance_check', $accountId);

// Fallback for transfer routing
$fallbackService->registerChain('transfer_route', [
    // Primary: Direct transfer
    fn($req) => $this->directTransfer($req),
    
    // Fallback 1: Bridge through another bank
    fn($req) => $this->bridgeTransfer($req),
    
    // Fallback 2: Queue for manual processing
    fn($req) => $this->queueForManualProcessing($req),
]);
```

### Health Monitoring Integration
```php
use App\Domain\Custodian\Services\CustodianHealthMonitor;

$healthMonitor = app(CustodianHealthMonitor::class);

// Register health checks
$healthMonitor->registerCheck('paysera', function () {
    $connector = app(CustodianRegistry::class)->getConnector('paysera');
    return $connector->isAvailable();
});

// Monitor with callbacks
$healthMonitor->monitor('paysera', [
    'interval' => 30,  // Check every 30 seconds
    'on_healthy' => function ($custodian) {
        Cache::put("custodian:{$custodian}:status", 'healthy', 300);
    },
    'on_unhealthy' => function ($custodian, $error) {
        // Trigger alerts
        Notification::send(
            User::whereAdmin()->get(),
            new CustodianDownNotification($custodian, $error)
        );
        
        // Update circuit breaker
        app(CircuitBreakerService::class)->recordFailure($custodian);
    },
]);

// Get health status
$health = $healthMonitor->getHealth('paysera');
// Returns: ['status' => 'healthy', 'last_check' => '2024-06-21 10:30:00', 'uptime' => 99.95]

// Dashboard widget
class CustodianHealthWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $monitor = app(CustodianHealthMonitor::class);
        $custodians = ['paysera', 'deutsche_bank', 'santander'];
        
        return collect($custodians)->map(function ($custodian) use ($monitor) {
            $health = $monitor->getHealth($custodian);
            return Stat::make($custodian, $health['uptime'] . '%')
                ->description($health['status'])
                ->color($health['status'] === 'healthy' ? 'success' : 'danger');
        })->toArray();
    }
}
```

### Resilient Transfer Processing
```php
// Complete resilient transfer implementation
class ResilientTransferService
{
    public function transfer(TransferRequest $request): TransactionReceipt
    {
        $circuitBreaker = app(CircuitBreakerService::class);
        $retryService = app(RetryService::class);
        $fallbackService = app(FallbackService::class);
        $healthMonitor = app(CustodianHealthMonitor::class);
        
        // 1. Check health first
        $custodian = $this->determineCustodian($request);
        if (!$healthMonitor->isHealthy($custodian)) {
            return $fallbackService->execute('transfer_route', $request);
        }
        
        // 2. Execute with circuit breaker
        try {
            return $circuitBreaker->call($custodian, 'transfer', function () use ($request, $retryService) {
                // 3. Execute with retry
                return $retryService->execute(function () use ($request) {
                    $connector = $this->getConnector($request->fromAccount);
                    return $connector->initiateTransfer($request);
                }, [
                    'max_attempts' => 3,
                    'initial_delay' => 1000,
                ]);
            });
        } catch (CircuitOpenException $e) {
            // 4. Circuit is open, use fallback
            Log::warning("Circuit open for {$custodian}, using fallback");
            return $fallbackService->execute('transfer_route', $request);
        }
    }
}
```

### Testing Resilience Patterns
```php
// Test circuit breaker
test('circuit breaker opens after threshold failures', function () {
    $circuitBreaker = app(CircuitBreakerService::class);
    $circuitBreaker->configure('test_service', [
        'failure_threshold' => 3,
        'timeout' => 60,
    ]);
    
    // Simulate failures
    for ($i = 0; $i < 3; $i++) {
        try {
            $circuitBreaker->call('test_service', 'operation', function () {
                throw new \Exception('Service unavailable');
            });
        } catch (\Exception $e) {
            // Expected
        }
    }
    
    expect($circuitBreaker->getState('test_service'))->toBe('open');
    
    // Subsequent calls should fail fast
    expect(fn() => $circuitBreaker->call('test_service', 'operation', fn() => 'success'))
        ->toThrow(CircuitOpenException::class);
});

// Test retry with exponential backoff
test('retry service uses exponential backoff', function () {
    $retryService = app(RetryService::class);
    $attempts = 0;
    $delays = [];
    
    try {
        $retryService->execute(function () use (&$attempts, &$delays) {
            $attempts++;
            $delays[] = microtime(true);
            throw new \Exception('Temporary failure');
        }, [
            'max_attempts' => 3,
            'initial_delay' => 100,
            'multiplier' => 2,
            'jitter' => false,
        ]);
    } catch (\Exception $e) {
        // Expected
    }
    
    expect($attempts)->toBe(3);
    
    // Check delays approximately match exponential backoff
    $delay1 = ($delays[1] - $delays[0]) * 1000; // Convert to ms
    $delay2 = ($delays[2] - $delays[1]) * 1000;
    
    expect($delay1)->toBeGreaterThan(90)->toBeLessThan(110);   // ~100ms
    expect($delay2)->toBeGreaterThan(190)->toBeLessThan(210);  // ~200ms
});

// Test fallback chain
test('fallback service executes chain in order', function () {
    $fallbackService = app(FallbackService::class);
    $executed = [];
    
    $fallbackService->registerChain('test_chain', [
        function () use (&$executed) {
            $executed[] = 'primary';
            throw new \Exception('Primary failed');
        },
        function () use (&$executed) {
            $executed[] = 'secondary';
            throw new \Exception('Secondary failed');
        },
        function () use (&$executed) {
            $executed[] = 'tertiary';
            return 'success';
        },
    ]);
    
    $result = $fallbackService->execute('test_chain');
    
    expect($executed)->toBe(['primary', 'secondary', 'tertiary']);
    expect($result)->toBe('success');
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

### GCU Platform Locations
- User bank preferences: `app/Models/UserBankPreference.php`
- GCU voting templates: `app/Domain/Governance/Services/GCUVotingTemplateService.php`
- Voting strategies: `app/Domain/Governance/Strategies/`
- GCU basket widget: `app/Filament/Admin/Widgets/GCUBasketWidget.php`
- GCU commands: `app/Console/Commands/SetupGCUVotingPolls.php`
- GCU seeders: `database/seeders/GCUBasketSeeder.php`
- GCU tests: `tests/Feature/Governance/GCUVotingTemplateTest.php`

### Phase 4.2 Specific Locations
- User voting controller: `app/Http/Controllers/Api/UserVotingController.php`
- User voting resource: `app/Http/Resources/UserVotingPollResource.php`
- Vue voting dashboard: `resources/js/components/GCUVotingDashboard.vue`
- Integration examples: `resources/js/components/voting-integration-example.js`
- GCU widget blade: `resources/views/filament/admin/widgets/gcu-basket-widget.blade.php`
- User voting tests: `tests/Feature/Api/UserVotingControllerTest.php`
- Basket composition workflow: `app/Domain/Governance/Workflows/UpdateBasketCompositionWorkflow.php`
- Monthly poll schedule: `routes/console.php` (line 20)

### Phase 4.3 Compliance Locations
- KYC models: `app/Models/KYCDocument.php`, `app/Models/KYCVerification.php`
- Compliance services: `app/Domain/Compliance/Services/`
- Regulatory reports: `app/Domain/Compliance/Reports/`
- GDPR service: `app/Domain/Compliance/Services/GDPRService.php`
- Compliance commands: `app/Console/Commands/Generate*Report.php`
- Compliance tests: `tests/Feature/Compliance/`

### Phase 5 Bank Integration Locations
- Custodian connectors: `app/Domain/Custodian/Connectors/`
- Balance sync service: `app/Domain/Custodian/Services/BalanceSynchronizationService.php`
- Multi-bank transfer: `app/Domain/Custodian/Services/MultiCustodianTransferService.php`
- Settlement service: `app/Domain/Custodian/Services/SettlementService.php`
- Custodian models: `app/Models/CustodianAccount.php`
- Custodian config: `config/custodians.php`
- Integration docs: `docs/CUSTODIAN_INTEGRATION.md`

### Phase 5.2 Resilience Services Locations
- Circuit breaker: `app/Domain/Custodian/Services/CircuitBreakerService.php`
- Retry service: `app/Domain/Custodian/Services/RetryService.php`
- Fallback service: `app/Domain/Custodian/Services/FallbackService.php`
- Health monitor: `app/Domain/Custodian/Services/CustodianHealthMonitor.php`
- Resilience tests: `tests/Feature/Custodian/ResilienceServicesTest.php`
- Circuit breaker tests: `tests/Unit/Custodian/CircuitBreakerServiceTest.php`
- Retry service tests: `tests/Unit/Custodian/RetryServiceTest.php`

### Phase 6 - Business Team Management ✅ Completed
- Team management: `app/Http/Controllers/TeamMemberController.php`
- Business organization trait: `app/Traits/BelongsToTeam.php`
- Team member views: `resources/views/teams/members/`
- Team role management: `database/migrations/2025_06_30_143734_enhance_teams_for_business_organizations.php`
- Multi-tenant tests: `tests/Feature/BusinessTeamManagementTest.php`

#### Business Team Management Usage
```bash
# Create a business organization user
php artisan tinker
$user = User::factory()->create(['name' => 'Business Owner']);
$team = Team::factory()->create([
    'user_id' => $user->id,
    'name' => 'My Business',
    'is_business_organization' => true,
    'max_users' => 10,
    'allowed_roles' => ['compliance_officer', 'accountant']
]);
$user->assignRole('customer_business');
```

**Key Features:**
- **Data Isolation**: All models using `BelongsToTeam` trait automatically scope to current team
- **Team Roles**: Members can have team-specific roles (compliance_officer, accountant, etc.)
- **User Limits**: Teams have configurable user limits
- **Super Admin Access**: Super admins can view all teams' data using `Model::allTeams()`

### Phase 7 - CGO (Continuous Growth Offering) ✅ Completed
- CGO controller: `app/Http/Controllers/CgoController.php`
- CGO views: `resources/views/cgo/`
- Payment confirmation views: `resources/views/cgo/crypto-payment.blade.php`, `bank-transfer.blade.php`, `card-payment.blade.php`
- CGO routes: Added to authenticated navigation in `resources/views/navigation-menu.blade.php`
- **Payment Integration**: 
  - Stripe payments: `app/Services/Cgo/StripePaymentService.php`
  - Coinbase Commerce: `app/Services/Cgo/CoinbaseCommerceService.php`
  - Payment verification: `app/Services/Cgo/PaymentVerificationService.php`
- **KYC/AML Implementation**:
  - KYC service: `app/Services/Cgo/CgoKycService.php`
  - KYC controller: `app/Http/Controllers/CgoKycController.php`
  - Documentation: `docs/06-DEVELOPMENT/CGO_KYC_AML.md`
  - Tiered KYC levels: Basic ($1k), Enhanced ($10k), Full ($50k+)
  - AML checks: PEP screening, sanctions screening, transaction patterns
- **Database Tables**:
  - `cgo_investments`: Investment records with KYC/AML fields
  - `cgo_pricing_rounds`: Pricing round management
  - KYC status tracking in users and investments tables

### Frontend Interfaces - Fund Flow Visualization ✅ Completed
- Fund flow controller: `app/Http/Controllers/FundFlowController.php`
- Main visualization component: `resources/js/Pages/FundFlow/Visualization.vue`
- Account detail component: `resources/js/Pages/FundFlow/AccountDetail.vue`
- Feature tests: `tests/Feature/FundFlowControllerTest.php`
- Feature documentation: `docs/06-DEVELOPMENT/features/fund-flow-visualization.md`
- Routes: `/fund-flow`, `/fund-flow/account/{uuid}`, `/fund-flow/data`

**Key Features:**
- **Visual Analytics**: Line charts for daily flows, D3.js network visualization
- **Advanced Filtering**: By time period (24h/7d/30d/90d), account, and flow type
- **Real-time Statistics**: Total inflow/outflow, net flow, active days
- **Account Network**: Interactive visualization of fund movements between accounts and external entities
- **Export Functionality**: Download flow data as CSV for external analysis

## Important File Locations

### Core Domains
- Account Management: `app/Domain/Account/`
- Transaction Processing: `app/Domain/Transaction/`
- Asset Management: `app/Domain/Asset/`
- Exchange & Trading: `app/Domain/Exchange/`
- P2P Lending: `app/Domain/Lending/`
- Stablecoins: `app/Domain/Stablecoin/`
- Liquidity Pools: `app/Domain/Exchange/LiquidityPool/`
- Wallet Management: `app/Domain/Wallet/`

### Infrastructure
- Event Sourcing: `app/Infrastructure/EventSourcing/`
- Workflow Engine: `app/Infrastructure/Workflow/`
- External Connectors: `app/Infrastructure/Exchange/Connectors/`
- Blockchain Services: `app/Infrastructure/Blockchain/`

### Frontend
- Vue Components: `resources/js/Components/`
- Page Components: `resources/js/Pages/`
- Shared Utilities: `resources/js/utils/`
- API Client: `resources/js/api/`

### Configuration
- Event Sourcing: `config/event-sourcing.php`
- Workflow: `config/workflow.php`
- Exchange: `config/exchange.php`
- Blockchain: `config/blockchain.php`

### Testing
- Unit Tests: `tests/Unit/`
- Feature Tests: `tests/Feature/`
- Domain Tests: `tests/Domain/`
- API Tests: `tests/Feature/Api/`

---

**Last Updated**: 2024-09-07  
**Version**: 8.0  
**Status**: Production Ready - All Phase 8 Features Implemented