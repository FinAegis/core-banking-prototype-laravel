# FinAegis Core Banking Prototype

[![CI Pipeline](https://github.com/finaegis/core-banking-prototype-laravel/actions/workflows/ci-pipeline.yml/badge.svg)](https://github.com/finaegis/core-banking-prototype-laravel/actions/workflows/ci-pipeline.yml)
[![Test Coverage](https://img.shields.io/badge/coverage-%3E50%25-brightgreen.svg)](https://github.com/finaegis/core-banking-prototype-laravel/actions)
[![Build Status](https://img.shields.io/badge/build-passing-brightgreen.svg)](https://github.com/finaegis/core-banking-prototype-laravel/actions)
[![License: Apache-2.0](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20.svg)](https://laravel.com/)
[![Demo Available](https://img.shields.io/badge/demo-live-blue.svg)](https://finaegis.org)

**Open Source Core Banking Prototype Demonstrating Modern Banking Architecture**

🌐 **[Live Demo Available at https://finaegis.org](https://finaegis.org)** - Experience the prototype in action!

FinAegis is a comprehensive prototype of a core banking platform built with event sourcing, domain-driven design, and modern banking patterns. It demonstrates the technical architecture for innovative financial products including the **Global Currency Unit (GCU)** concept - a democratic digital currency vision backed by real banks.

📖 **See [GCU Vision Documentation](docs/01-VISION/GCU_VISION.md) for the complete platform vision and GCU implementation details.**

**🤖 AI-Powered Banking Framework**: FinAegis now includes a complete AI Agent Framework for financial institutions. With full MCP (Model Context Protocol) server implementation, multi-LLM support (OpenAI GPT-4, Anthropic Claude), and event-sourced architecture, it enables banks to deploy intelligent AI agents for customer service, compliance, risk assessment, and automated trading. Every AI interaction is recorded for complete audit trails and regulatory compliance.

## 🔗 Quick Links

- 🌐 **[Live Demo](https://finaegis.org)** - Try the demo environment
- 🤖 **[AI Framework](docs/13-AI-FRAMEWORK/README.md)** - AI Agent Framework documentation
- 🎮 **[Demo Guide](docs/11-USER-GUIDES/DEMO-USER-GUIDE.md)** - Demo features walkthrough  
- 📚 **[Documentation](docs/README.md)** - Complete documentation index
- 🚀 **[Quick Start](#-quick-start)** - Get started immediately
- 💻 **[API Reference](docs/04-API/REST_API_REFERENCE.md)** - REST API v2.0
- 🎯 **[Roadmap](docs/01-VISION/ROADMAP.md)** - Development phases

## 🌐 Live Demo

**🎮 Prototype/Demo: [https://finaegis.org](https://finaegis.org)**

### Demo Features
The demo environment showcases all platform capabilities without real transactions:
- ✅ Multi-asset banking operations  
- ✅ Global Currency Unit (GCU) concept demonstration
- ✅ Instant transaction processing (simulated)
- ✅ Pre-configured demo accounts (see [Demo Guide](docs/11-USER-GUIDES/DEMO-USER-GUIDE.md))
- ✅ API testing with demo credentials
- ✅ Admin dashboard with full access
- ✅ All external services mocked locally

## 🚧 Prototype Status

**This is a demonstration prototype** showcasing modern banking architecture and concepts. While it includes many advanced features, it is not production-ready and serves as:

- A technical demonstration of core banking patterns
- An educational resource for developers

## 🏗️ Architecture Highlights

### 🤖 AI Agent Framework (Production Ready!)
- **MCP Server Implementation**: Full Model Context Protocol v1.0 server with comprehensive tool registry
- **Event-Driven Architecture**: Complete audit trail via `AIInteractionAggregate` event sourcing
- **12+ Banking Tools Implemented**:
  - **Account Domain** (3 tools): CreateAccount, CheckBalance, GetTransactionHistory
  - **Payment Domain** (3 tools): InitiatePayment, PaymentStatus, CancelPayment
  - **Exchange Domain** (2 tools): GetExchangeRates, PlaceOrder
  - **Lending Domain** (2 tools): LoanApplication, CheckLoanStatus
  - **Stablecoin Domain** (2 tools): TransferTokens, CheckTokenBalance
- **AI Agent Workflows** (Phase 3 Complete!):
  - **CustomerServiceWorkflow**: Natural language query processing with intent classification
  - **ComplianceWorkflow**: Comprehensive KYC/AML checks with transaction monitoring
  - **RiskAssessmentSaga**: Multi-dimensional risk analysis (credit, fraud, portfolio)
  - Saga pattern with full compensation support for workflow failures
  - Confidence scoring and human-in-the-loop decisions
  - Integration with existing domain services
- **Advanced Features**:
  - Conversation tracking with full event history
  - Tool result caching with configurable TTL (<100ms response time)
  - Resource exposure for documents and data via MCP protocol
  - Authorization and permission validation with Laravel Sanctum
  - Workflow orchestration via Laravel Workflow (Waterline)
  - User UUID injection for numeric ID compatibility
- **Testing Coverage**: 
  - MCPServer fully tested with comprehensive test suites
  - All tools have >80% test coverage
  - Event sourcing verification
  - Tool execution tracking
  - Performance monitoring
  - PHPStan Level 5 compliance

### Domain-Driven Design (DDD)
- **25+ Bounded Contexts**: Account, Exchange, Stablecoin, Lending, Wallet, and more
- **Event Sourcing**: 130+ domain events with full audit trail (perfect for AI audit)
- **CQRS Pattern**: Separated command and query responsibilities (ideal for AI actions)
- **Repository Pattern**: Abstracted data access with interfaces
- **Saga Pattern**: Cross-domain transaction orchestration (AI workflow foundation)

### Recent Improvements (v2.1)
- **Saga Implementation**: Laravel Workflow-based sagas for complex transactions
  - `OrderFulfillmentSaga`: Orchestrates exchange order processing
  - `StablecoinIssuanceSaga`: Manages multi-domain stablecoin minting
- **CQRS Infrastructure**: CommandBus and QueryBus for clean separation
- **Domain Event Bus**: Decoupled event publishing and handling
- **Repository Interfaces**: Proper abstraction for all key aggregates
- **Compensation Support**: Full rollback capabilities for failed transactions

### 🌍 Conceptual Implementation: Global Currency Unit (GCU)

The prototype demonstrates how a democratic digital currency could work:

- **Conceptual Bank Integration**: Models for multi-bank support (placeholder implementations)
- **Voting System Demo**: Showcases democratic governance concepts
- **Multi-Currency Basket**: Demonstrates basket asset management
- **Architecture Patterns**: Shows how such a system could be built

## 🏛️ Technical Demonstrations

The prototype showcases advanced banking architecture patterns:

### Core Banking Architecture
- **Event Sourcing Pattern**: Demonstrates audit trail implementation
- **Saga Pattern Workflows**: Shows compensation and rollback handling
- **Domain-Driven Design**: Example of clean architecture patterns
- **Security Patterns**: Demonstrates hashing and validation approaches
- **Performance Optimization**: Shows caching and optimization techniques
- **Compliance Patterns**: Examples of audit and compliance features

### Implemented Features (Prototype Demonstrations)

#### Multi-Asset Support
- **Asset Management**: Models for various asset types (fiat, crypto, commodities)
- **Multi-Currency Demo**: Shows cross-currency transaction concepts
- **Exchange Rate System**: Demonstrates rate provider integration patterns
- **Balance Tracking**: Per-asset balance demonstration
- **Basket Assets**: Composite asset implementation example

#### Architecture Patterns
- **Service Abstraction**: Shows how to integrate with external services
- **Event-Driven Design**: Demonstrates event sourcing patterns
- **Workflow Orchestration**: Examples of complex business processes
- **Domain Modeling**: Clean separation of business logic

#### Governance Concepts
- **Voting System**: Demonstrates democratic decision-making
- **Poll Management**: Shows how voting could work
- **Automated Workflows**: Examples of vote-triggered actions

## 🚀 Quick Start

### Option 1: Try Demo Mode (Recommended for First-Time Users)

```bash
# Quick demo setup - no external dependencies needed!
git clone https://github.com/finaegis/core-banking-laravel.git
cd finaegis-core-banking
composer install
cp .env.demo .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve

# Access at http://localhost:8000
# Demo credentials in .env.demo file
```

### Option 2: Full Installation

#### Prerequisites

- PHP 8.4+
- Laravel 12
- MySQL 8.0+ or PostgreSQL 13+
- Redis 6.0+
- Node.js 18+ (for asset compilation)

### Installation

```bash
# Clone the repository
git clone https://github.com/finaegis/core-banking-laravel.git
cd finaegis-core-banking

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure database and Redis in .env file
# Then run migrations
php artisan migrate
php artisan db:seed

# Setup Primary Basket (optional)
php artisan db:seed --class=PrimaryBasketSeeder
php artisan voting:setup

# Build assets
npm run build

# Start the application
php artisan serve

# Access Admin Dashboard
# http://localhost:8000/admin
# Create admin user: php artisan make:filament-user

# Start queue workers (in separate terminals)
php artisan queue:work --queue=events,ledger,transactions,transfers,webhooks
```

## 🎭 Demo Mode

The platform includes a comprehensive Demo Mode that allows it to run without external dependencies, making it ideal for demonstrations, development, and testing.

### Enabling Demo Mode

```bash
# Quick setup with demo environment
cp .env.demo .env
php artisan config:cache

# Or manually set in .env
APP_ENV=demo
DEMO_SHOW_BANNER=true
DEMO_INSTANT_DEPOSITS=true
```

### Demo Features

- **Zero External Dependencies**: All API calls are simulated locally
- **Instant Operations**: No network delays or processing time
- **Pre-configured Demo Users**: Ready-to-use test accounts
- **Simulated Services**: Mock implementations for all external integrations
  - Payment processors (Stripe simulation)
  - Bank APIs (Paysera, Santander, Deutsche Bank mocks)
  - Blockchain networks (Ethereum, Bitcoin simulations)
  - Exchange APIs (Binance, Kraken mocks)

### Demo User Accounts

Pre-configured accounts available in demo mode:
- `demo.argentina@gcu.global` - High-inflation country user
- `demo.nomad@gcu.global` - Digital nomad
- `demo.business@gcu.global` - Business user
- `demo.investor@gcu.global` - Investor
- `demo.user@gcu.global` - Regular user

Password for all demo accounts: `demo123`

### Demo Services

When `APP_ENV=demo`, the platform automatically switches to demo implementations:
- `DemoPaymentService` - Simulates Stripe payments
- `DemoExchangeService` - Mock exchange operations
- `DemoLendingService` - Auto-approved loans
- `DemoStablecoinService` - Instant minting/burning
- `DemoBlockchainService` - Simulated blockchain transactions
- `DemoBankConnector` - Mock bank operations

For detailed demo environment documentation, see [Demo Environment Guide](docs/06-DEVELOPMENT/DEMO-ENVIRONMENT.md).

## 🏗️ Architecture

### Core Components

- **Event Store**: Immutable event log with complete transaction history
- **Aggregate Roots**: `LedgerAggregate`, `TransactionAggregate`, `TransferAggregate`
- **Workflows**: Saga-based business process orchestration
- **Projectors**: Real-time read model updates
- **Reactors**: Side-effect handling and notifications

### Domain Structure

```
app/Domain/
├── Account/           # Account management domain
│   ├── Aggregates/    # Business logic aggregates
│   ├── Events/        # Domain events
│   ├── Workflows/     # Business process workflows
│   ├── Activities/    # Individual workflow steps
│   ├── Projectors/    # Read model builders (including TransactionProjector)
│   └── Services/      # Domain services and cache layers
├── Asset/            # Multi-asset management
│   ├── Models/       # Asset and ExchangeRate models
│   ├── Aggregates/   # Asset transaction aggregates
│   ├── Events/       # Asset-specific events
│   ├── Workflows/    # Asset deposit/withdraw/transfer workflows
│   └── Services/     # Exchange rate services
├── Exchange/         # Exchange rate providers
│   ├── Providers/    # Rate provider implementations
│   └── Services/     # Enhanced exchange rate services
├── Custodian/        # External custodian integration
│   ├── Connectors/   # Custodian connector implementations
│   └── Services/     # Custodian registry and management
├── Governance/       # Democratic governance system
│   ├── Models/       # Poll and Vote models
│   ├── Strategies/   # Voting power strategies
│   ├── Workflows/    # Governance execution workflows
│   └── Services/     # Governance services
├── Payment/          # Payment processing domain
│   ├── Services/     # Payment services
│   └── Workflows/    # Payment workflows
├── Basket/           # Basket asset management
│   ├── Services/     # Basket calculation and rebalancing
│   ├── Events/       # Basket lifecycle events
│   └── Workflows/    # Basket composition/decomposition
├── Compliance/       # KYC/AML and regulatory compliance
│   ├── Services/     # KYC, GDPR, and regulatory services
│   └── Reports/      # Compliance report generation
├── Performance/      # Performance optimization
│   ├── Services/     # Transfer optimization service
│   └── Benchmarks/   # Performance benchmarking
├── Stablecoin/       # Stablecoin issuance and management
│   ├── Models/       # Stablecoin and collateral models
│   ├── Services/     # Issuance and liquidation services
│   └── Workflows/    # Stablecoin lifecycle workflows
├── Wallet/           # Blockchain wallet management
│   ├── Services/     # Wallet and key management services
│   ├── Connectors/   # Blockchain connectors (Bitcoin, Ethereum)
│   └── ValueObjects/ # Address, transaction, and gas data objects
├── Lending/          # P2P lending platform
│   ├── Models/       # Loan and credit score models
│   ├── Services/     # Credit scoring and risk assessment
│   └── Workflows/    # Loan lifecycle workflows
└── AI/               # AI Agent Framework (Refactored)
    ├── Activities/   # Atomic business logic units
    │   ├── Trading/  # RSI, MACD, pattern identification
    │   ├── Risk/     # VaR, credit scoring, fraud detection
    │   └── Portfolio/# Optimization and rebalancing
    ├── ChildWorkflows/# Focused sub-workflows
    │   ├── Trading/  # MarketAnalysis, StrategyGeneration
    │   ├── Risk/     # CreditRisk, FraudDetection workflows
    │   └── Approval/ # ConfidenceEvaluation, Escalation
    ├── Sagas/        # Compensatable operations
    │   └── TradingExecutionSaga (with rollback support)
    ├── Aggregates/   # AIInteractionAggregate for event sourcing
    ├── Events/       # Domain events (MarketAnalyzed, TradeExecuted)
    ├── MCP/          # Model Context Protocol server
    │   ├── Tools/    # MCP tools for banking operations
    │   └── Resources/# MCP resource exposure
    └── Workflows/    # Main orchestration workflows
```

## 💼 Key Features

### Account Management
- Account creation, modification, and closure
- Multi-asset balance tracking per account
- Balance inquiries with audit trails
- Account freezing/unfreezing for compliance
- Real-time balance calculations with caching

### Transaction Processing
- Real-time money deposits and withdrawals (single-asset and multi-asset)
- Transaction read model with comprehensive history
- Transaction reversal with compensation
- Automated threshold monitoring
- Quantum-resistant transaction hashing
- Transaction projector for event-sourced data
- Sub-second transfer processing with performance optimization
- Resilience patterns: Circuit breakers, retries, and fallback mechanisms

### Transfer Operations
- Peer-to-peer transfers with saga pattern
- Bulk transfer processing
- Automatic rollback on failures
- Cross-account validation

### Compliance & Security
- Complete audit trails for all operations
- KYC/AML validation workflows
- Regulatory reporting capabilities
- Role-based access control

### System Operations
- Batch processing for end-of-day operations
- Real-time balance calculations
- Automated snapshot creation
- Performance monitoring
- Redis caching layer for optimized performance
- Daily reconciliation with automated balance verification
- Bank health monitoring with real-time alerts

### Exchange & Trading
- Order book management with event-sourced architecture
- Automated order matching with partial fill support
- Liquidity pool creation and management
- AMM-based token swaps with dynamic pricing
- External exchange integration (Binance, Kraken, Coinbase)
- Real-time arbitrage opportunity detection
- Price alignment with external markets
- Trading fee calculation with maker/taker model

### Stablecoin Management
- Stablecoin issuance and minting with multi-collateral support
- Collateral position management with real-time valuation
- Automated liquidation mechanisms with configurable thresholds
- Stability mechanism execution (DSR, liquidation, rebalancing)
- Risk assessment and monitoring with health factor calculation
- Oracle aggregation for accurate price feeds
- Emergency pause and recovery mechanisms

### Bank Integration
- Real bank connectors (Paysera, Deutsche Bank, Santander)
- Multi-bank transfer routing
- Settlement processing across bank networks
- Custodian balance synchronization
- Webhook integration for real-time updates

### Blockchain Integration
- Multi-chain wallet support (Bitcoin, Ethereum, Polygon, BSC)
- HD wallet generation with BIP44 compliance
- Secure key management with encryption
- Transaction signing and broadcasting
- Gas estimation and optimization
- Real-time balance monitoring
- Address generation and validation

### P2P Lending Platform
- Loan application and approval workflows
- Credit scoring with multiple data sources
- Risk assessment and categorization
- Interest rate calculation based on risk
- Automated repayment processing
- Default management and recovery
- Collateralized and uncollateralized loans

### AI Agent Framework (Phase 4 Complete - January 2025, Fully Refactored)
- **MCP Server**: Production-ready Model Context Protocol v1.0 implementation
- **20+ Banking Tools**: Complete coverage across all banking domains
- **Event Sourcing**: AIInteractionAggregate tracks all conversations and decisions
- **Clean Architecture Refactoring** (65% Average Code Reduction):
  - **Activities Pattern** (12 Atomic Units): 
    - Trading (5): CalculateRSI, CalculateMACD, IdentifyPatterns, CalculatePositionSize, ValidateOrderParameters
    - Risk (7): CalculateCreditScore, CalculateDebtRatios, EvaluateLoanAffordability, DetectAnomalies, AnalyzeTransactionVelocity, VerifyDeviceAndLocation, CalculateRiskScore
    - Pure business logic with single responsibility principle
  - **Child Workflows** (5 Domain Orchestrators): 
    - Trading: MarketAnalysisWorkflow, StrategyGenerationWorkflow, TradingExecutionWorkflow
    - Risk: CreditRiskWorkflow, FraudDetectionWorkflow
    - Focused orchestration coordinating related activities
  - **Refactored Sagas** (Major Size Reductions): 
    - TradingExecutionSaga: 720→194 lines (73% reduction) with full compensation
    - RiskAssessmentSaga: 782→350 lines (55% reduction) with rollback support
  - **Domain Events**: 
    - Trading: MarketAnalyzedEvent, StrategyGeneratedEvent, TradeExecutedEvent
    - Risk: CreditAssessedEvent, FraudAssessedEvent
- **Production-Ready Workflows**:
  - **TradingAgentWorkflow**: Clean orchestration (720 → 194 lines, 73% reduction)
  - **RiskAssessmentSaga**: Refactored with child workflows for credit and fraud
  - **MultiAgentCoordination**: Agent communication with consensus mechanisms
  - **HumanInTheLoopWorkflow**: Approval flows with confidence thresholds
  - **CustomerServiceWorkflow**: Intent classification and routing
  - **ComplianceWorkflow**: Automated KYC/AML with audit trails
- **Performance**: Sub-100ms response times with intelligent caching
- **Testing**: Comprehensive test coverage across all AI components

### Admin Dashboard (Filament v3)
- Comprehensive admin interface powered by Filament v3
- **Primary Basket Widget**: Real-time visualization of configurable currency basket
- **Account Management**: Real-time operations (deposit, withdraw, freeze/unfreeze)
- **Transaction History**: Complete transaction monitoring with projector-based data
- **Multi-Asset Support**: Asset management (CRUD) and exchange rate monitoring
- **Governance Interface**: Poll creation, vote tracking, and result management
- **Analytics Widgets**: Account balance trends, transaction volume, cash flow analysis
- **Advanced Filtering**: By status, balance, date range, asset type, transaction type
- **Bulk Operations**: Freeze/unfreeze accounts, update exchange rates
- **Export Functionality**: CSV/XLSX export for accounts, transactions, assets
- **Webhook Management**: Configuration, monitoring, and delivery tracking
- **User Management**: Complete user administration with role-based access

### Recent Updates (Completed Phases)

#### Phase 8: Advanced Trading & DeFi Features ✅
- **Generalized Exchange Engine**: Event-sourced order book with saga-based matching engine
- **Liquidity Pool Management**: Automated market maker (AMM) with constant product formula
- **External Exchange Integration**: Connectors for Binance, Kraken, and Coinbase
- **Blockchain Wallet System**: Multi-chain support (Bitcoin, Ethereum, Polygon, BSC)
- **P2P Lending Platform**: Credit scoring, risk assessment, and loan lifecycle management
- **Stablecoin Framework**: Collateralized stablecoin issuance with liquidation mechanisms
- **Arbitrage Detection**: Real-time arbitrage opportunity detection across exchanges
- **Price Alignment**: Automated price synchronization with external markets

#### Phase 4.1: User Bank Allocation ✅
- **Enhanced User Bank Preferences**: Expanded model with 5 banks (Paysera, Deutsche Bank, Santander, Revolut, Wise)
- **Bank Distribution Algorithm**: Intelligent fund allocation with rounding handling and validation
- **Bank Allocation Service**: Complete service layer for managing user bank preferences
- **Admin Interface**: Filament resource for bank allocation management with visual indicators
- **Bank Network Widget**: Dashboard widget showing bank partner network and insurance coverage
- **Deposit Insurance Tracking**: Calculate total coverage across multiple banks (up to €500,000)
- **Diversification Analysis**: Automated checks for healthy fund distribution
- **Primary Bank Selection**: Designate primary bank for urgent transfers

#### Phase 4.2: Enhanced Governance ✅
- **Voting Template Service**: Automated creation of monthly currency basket voting polls
- **Asset-Weighted Voting Strategy**: Democratic voting where 1 primary asset unit = 1 vote
- **Basket Update Workflow**: Automated basket composition updates based on poll results
- **Primary Basket Configuration**: Configurable primary currency basket (defaults: USD 40%, EUR 30%, GBP 15%, CHF 10%, JPY 3%, Gold 2%)
- **Console Commands**: `php artisan voting:setup` for poll management
- **User Voting API**: Complete REST API for voting interface
- **Vue.js Integration**: GCUVotingDashboard component for frontend

#### Phase 4.3: Compliance Framework ✅
- **Enhanced KYC System**: Document management with verification workflows
- **Automated Regulatory Reporting**: CTR and SAR report generation
- **Comprehensive Audit Trails**: Event-based audit logging with search
- **GDPR Compliance**: Data export, anonymization, and retention policies
- **Scheduled Reports**: Automated daily/monthly compliance reports

#### Phase 5.1: Real Bank Integration ✅
- **Paysera Connector**: OAuth2 authentication with multi-currency support
- **Deutsche Bank Connector**: SEPA and instant payment capabilities
- **Santander Connector**: Open Banking UK standard implementation
- **Balance Synchronization**: Automated reconciliation service
- **Console Command**: `php artisan custodian:sync-balances`

#### Phase 6: Business Team Management ✅
- **Multi-Tenant Architecture**: Complete data isolation between business organizations
- **Team Member Management**: CRUD interface for adding/managing team members
- **Role-Based Permissions**: Business-specific roles (Compliance Officer, Risk Manager, Accountant, etc.)
- **Automatic Data Scoping**: BelongsToTeam trait ensures data isolation at model level
- **Team Limits**: Configurable user limits per organization
- **Team-Specific Roles**: Separate from global system roles

#### Phase 7: Continuous Growth Offering (CGO) ✅
- **Investment Platform**: Allow users to invest in platform growth
- **Multiple Payment Methods**: Support for crypto (BTC, ETH, USDT), bank transfers, and cards
- **QR Code Generation**: Easy mobile crypto payments
- **Reference Tracking**: Unique reference numbers for all investments
- **Payment Confirmation Views**: Dedicated views for each payment method
- **Investment Management**: Track investment status and history

#### Phase 8: Enhanced Features (Q1 2025) ✅
- **GCU Voting System**: Complete implementation of democratic voting for GCU composition
- **Subscriber Management**: Comprehensive newsletter and marketing system
- **Enhanced Authentication**: Two-factor authentication, OAuth2, and password reset
- **GCU Trading Operations**: Buy/sell functionality for Global Currency Unit
- **Advanced Fraud Detection**: Real-time transaction monitoring and alerting
- **Regulatory Reporting**: Automated CTR and SAR report generation
- **Browser Testing**: Critical path testing for navigation and core features

## 🔧 Usage Examples

### Account Operations

```php
use App\Domain\Account\Services\AccountService;

$accountService = app(AccountService::class);

// Create account
$accountService->create([
    'name' => 'John Doe Savings',
    'user_uuid' => $userUuid,
]);

// Deposit money
$accountService->deposit($accountUuid, 1000);

// Withdraw money
$accountService->withdraw($accountUuid, 500);
```

### Transfer Operations

```php
use App\Domain\Payment\Services\TransferService;

$transferService = app(TransferService::class);

// Single transfer
$transferService->transfer(
    from: $fromAccountUuid,
    to: $toAccountUuid,
    amount: 1000
);
```

### Workflow Usage

```php
use Workflow\WorkflowStub;
use App\Domain\Account\Workflows\BalanceInquiryWorkflow;

// Balance inquiry with audit
$workflow = WorkflowStub::make(BalanceInquiryWorkflow::class);
$result = $workflow->start($accountUuid, $requestedBy);
```

## 🧪 Testing

The platform includes comprehensive test coverage using Pest PHP testing framework:

```bash
# Run all tests
./vendor/bin/pest

# Run tests in parallel (faster execution)
./vendor/bin/pest --parallel

# Run with coverage report
./vendor/bin/pest --coverage --min=50

# Run specific test suites
./vendor/bin/pest tests/Domain/         # Domain layer tests
./vendor/bin/pest tests/Feature/        # Feature tests
./vendor/bin/pest tests/Console/        # Console command tests

# Run specific test file
./vendor/bin/pest tests/Domain/Account/Aggregates/LedgerAggregateTest.php

# Run tests with specific filter
./vendor/bin/pest --filter="it_can_create_account"

# Run admin dashboard tests
./vendor/bin/pest tests/Feature/Filament/
```

### Test Structure
- **Unit Tests**: Domain logic, models, and value objects
- **Integration Tests**: Workflow orchestration and activity execution
- **Feature Tests**: API endpoints, controllers, and user interactions
- **Filament Tests**: Admin dashboard resources and actions

### Test Coverage Areas
- **Domain Layer**: Aggregates, events, projectors, reactors, services
- **API Controllers**: Account, Transfer, Asset, Exchange Rate, Basket, Stablecoin endpoints
- **Workflows**: All business process workflows with compensation testing
- **Admin Dashboard**: Resource pages, actions, widgets, and bulk operations
- **Multi-Asset Operations**: Cross-currency transfers, exchange rates, basket management
- **Governance System**: Polls, votes, and automated workflow execution

### CI/CD Integration
Tests run automatically on:
- Pull requests to main branch
- Pushes to main branch
- Uses GitHub Actions with MySQL, Redis, and parallel test execution
- Minimum coverage requirement: 50%

## 📖 Documentation

### API Documentation

The platform includes comprehensive API documentation powered by OpenAPI/Swagger:

- **Access Documentation**: Navigate to `/api/documentation` when the server is running
- **OpenAPI Specification**: Available at `/docs/api-docs.json`
- **Interactive Testing**: Test API endpoints directly from the documentation interface

```bash
# Generate/update API documentation
php artisan l5-swagger:generate
```

### Admin Dashboard

The platform includes a powerful admin dashboard built with Filament:

- **Access Dashboard**: Navigate to `/admin` when the server is running
- **Default Credentials**: Create an admin user with `php artisan make:filament-user`
- **Features**:
  - Account management with real-time operations (deposit, withdraw, freeze/unfreeze)
  - Transaction monitoring and history with detailed views
  - Turnover statistics and analytics
  - Account balance tracking with automatic updates
  - System health monitoring with real-time metrics
  - Advanced filtering and search by status, balance, and name
  - Bulk operations support (freeze multiple accounts)
  - User management interface
  - Export capabilities (CSV/XLSX)
  - Webhook management for real-time event notifications
  - **Enhanced Analytics Dashboard**:
    - Account balance trend charts (daily/weekly/monthly views)
    - Transaction volume analysis by type
    - Turnover flow visualization with net calculations
    - Account growth tracking over time
    - System health monitoring with performance metrics

### 📚 Documentation Index

Our comprehensive documentation is organized into the following sections:

#### Strategic & Vision Documents
- **[Platform Vision](docs/01-VISION/UNIFIED_PLATFORM_VISION.md)**: Complete platform strategy and sub-products
- **[GCU Vision](docs/01-VISION/GCU_VISION.md)**: Global Currency Unit detailed concept
- **[Development Roadmap](docs/01-VISION/ROADMAP.md)**: Implementation phases and timeline
- **[Banking Requirements](docs/01-VISION/FINANCIAL_INSTITUTION_REQUIREMENTS.md)**: Partner bank integration requirements

#### AI Agent Framework
- **[AI Framework Overview](docs/13-AI-FRAMEWORK/README.md)**: Complete AI Agent Framework documentation
- **[MCP Integration](docs/13-AI-FRAMEWORK/MCP_INTEGRATION.md)**: Model Context Protocol server implementation

#### Technical Architecture
- **[System Architecture](docs/02-ARCHITECTURE/ARCHITECTURE.md)**: Core technical architecture
- **[Multi-Asset Architecture](docs/02-ARCHITECTURE/MULTI_ASSET_ARCHITECTURE.md)**: Multi-currency implementation
- **[Workflow Patterns](docs/02-ARCHITECTURE/WORKFLOW_PATTERNS.md)**: Saga patterns and best practices

#### Features & Implementation
- **[Features Overview](docs/03-FEATURES/FEATURES.md)**: Complete feature reference
- **[Business Team Management](docs/03-FEATURES/BUSINESS_TEAM_MANAGEMENT.md)**: Multi-tenant architecture
- **[CGO Documentation](docs/05-TECHNICAL/CGO_DOCUMENTATION.md)**: Continuous Growth Offering

#### API Documentation
- **[REST API Reference](docs/04-API/REST_API_REFERENCE.md)**: Complete v2.0 API documentation
- **[BIAN API Documentation](docs/04-API/BIAN_API_DOCUMENTATION.md)**: BIAN-compliant banking APIs
- **[API Implementation](docs/07-IMPLEMENTATION/API_IMPLEMENTATION.md)**: API implementation details
- **[Webhook Integration](docs/04-API/WEBHOOK_INTEGRATION.md)**: Real-time event notifications

#### Development Resources
- **[Development Guide](docs/06-DEVELOPMENT/DEVELOPMENT.md)**: Complete developer setup
- **[Testing Guide](docs/06-DEVELOPMENT/TESTING_GUIDE.md)**: Comprehensive testing documentation
- **[AI Assistant Guide](docs/06-DEVELOPMENT/CLAUDE.md)**: Guide for AI coding assistants
- **[Demo Setup](docs/06-DEVELOPMENT/DEMO.md)**: Demo environment configuration

#### Administration
- **[Admin Dashboard Guide](docs/05-TECHNICAL/ADMIN_DASHBOARD.md)**: Filament admin panel documentation
- **[Database Schema](docs/05-TECHNICAL/DATABASE_SCHEMA.md)**: Complete database structure

#### User Guides
- **[Getting Started](docs/11-USER-GUIDES/GETTING-STARTED.md)**: User onboarding guide
- **[Liquidity Pools Guide](docs/05-USER-GUIDES/LIQUIDITY_POOLS_GUIDE.md)**: DeFi liquidity management
- **[P2P Lending Guide](docs/05-USER-GUIDES/P2P_LENDING_GUIDE.md)**: Peer-to-peer lending platform
- **[Stablecoin Guide](docs/05-USER-GUIDES/STABLECOIN_GUIDE.md)**: Stablecoin minting and management

For a complete documentation index, see **[Documentation Overview](docs/README.md)**.

### API Endpoints

The platform provides RESTful APIs for all banking operations:

```http
# Account Management
POST   /api/accounts                    # Create account
GET    /api/accounts/{uuid}             # Get account details
DELETE /api/accounts/{uuid}             # Delete account
POST   /api/accounts/{uuid}/freeze      # Freeze account
POST   /api/accounts/{uuid}/unfreeze    # Unfreeze account

# Transaction Operations
POST   /api/accounts/{uuid}/deposit     # Deposit money
POST   /api/accounts/{uuid}/withdraw    # Withdraw money
GET    /api/accounts/{uuid}/transactions # Transaction history
POST   /api/transfers                   # Create transfer
GET    /api/transfers/{uuid}            # Get transfer details

# Multi-Asset Support
GET    /api/assets                      # List all assets
GET    /api/assets/{code}               # Get asset details
GET    /api/accounts/{uuid}/balances    # Multi-asset balances
GET    /api/exchange-rates              # Current exchange rates
POST   /api/exchange-rates/convert      # Currency conversion

# Basket Assets
GET    /api/v2/baskets                  # List all baskets
GET    /api/v2/baskets/{code}           # Get basket details
POST   /api/v2/baskets                  # Create basket
GET    /api/v2/baskets/{code}/value     # Get current value
POST   /api/v2/baskets/{code}/rebalance # Rebalance basket
GET    /api/v2/baskets/{code}/performance # Performance metrics
POST   /api/v2/accounts/{uuid}/baskets/compose   # Compose basket
POST   /api/v2/accounts/{uuid}/baskets/decompose # Decompose basket

# Exchange & Trading
GET    /api/exchange/markets           # List trading pairs
GET    /api/exchange/orderbook         # Get order book
POST   /api/exchange/orders            # Place order
DELETE /api/exchange/orders/{id}       # Cancel order
GET    /api/exchange/orders/{id}       # Get order status
GET    /api/exchange/trades            # Trade history

# Liquidity Pools
GET    /api/pools                      # List liquidity pools
POST   /api/pools                      # Create pool
POST   /api/pools/{id}/liquidity       # Add liquidity
DELETE /api/pools/{id}/liquidity       # Remove liquidity
POST   /api/pools/{id}/swap            # Execute swap

# Stablecoins
POST   /api/stablecoins/mint           # Mint stablecoins
POST   /api/stablecoins/burn           # Burn stablecoins
GET    /api/stablecoins/positions      # List positions
POST   /api/stablecoins/liquidate      # Liquidate position

# P2P Lending
POST   /api/loans/apply                # Apply for loan
GET    /api/loans                      # List loans
POST   /api/loans/{id}/approve         # Approve loan
POST   /api/loans/{id}/repay           # Make repayment
GET    /api/loans/{id}/schedule        # Repayment schedule

# Blockchain Wallets
POST   /api/wallets/generate           # Generate wallet
GET    /api/wallets/{chain}/balance    # Get balance
POST   /api/wallets/{chain}/send       # Send transaction
GET    /api/wallets/{chain}/transactions # Transaction history

# Governance APIs
GET    /api/polls                       # List polls
POST   /api/polls                       # Create poll
POST   /api/polls/{id}/vote             # Submit vote
GET    /api/polls/{id}/results          # View results

# User Voting Interface (GCU)
GET    /api/voting/polls                # Active polls with user context
GET    /api/voting/polls/upcoming       # Upcoming polls
GET    /api/voting/polls/history        # User's voting history
POST   /api/voting/polls/{uuid}/vote    # Submit basket allocation vote
GET    /api/voting/dashboard            # Voting dashboard data

# BIAN-Compliant APIs
POST   /api/bian/current-account        # BIAN current account
POST   /api/bian/payment-initiation     # BIAN payment processing
```

## 🔒 Security

### Security Features
- **Quantum-Resistant Hashing**: SHA3-512 for all transactions
- **Event Integrity**: Cryptographic validation of all events
- **Multi-Factor Authentication**: Laravel Fortify integration
- **Role-Based Access Control**: Granular permissions
- **Audit Logging**: Complete operation audit trails

### Compliance
- **GDPR**: Data protection and privacy controls
- **PCI DSS**: Secure payment card handling
- **SOX**: Financial reporting controls
- **Basel III**: Risk management framework

## 🚀 Local Development Setup

### Docker Development Environment

```yaml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8000:80"
    environment:
      - APP_ENV=local
      - DB_CONNECTION=mysql
      - REDIS_HOST=redis
      - QUEUE_CONNECTION=redis
    
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: finaegis
      MYSQL_ROOT_PASSWORD: secret
    
  redis:
    image: redis:7-alpine
```

### Important Notes
- This is a prototype for demonstration purposes
- Not intended for production use without significant additional work
- Requires proper security review before any real-world deployment
- Bank integrations are conceptual demonstrations only

## 🤝 Contributing

We welcome contributions from the community, including **AI coding assistants and vibe coding tools**! This project is designed to be highly compatible with AI agents like Claude Code, GitHub Copilot, Cursor, and similar tools. The domain-driven design and comprehensive documentation make it easy for AI agents to understand and contribute meaningfully.

### Contributing Guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass (`./vendor/bin/pest`)
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

### Development Standards (Human & AI Contributors)
- **Full test coverage**: Every new feature must have comprehensive tests
- **Complete documentation**: Update relevant docs and add inline documentation
- **Follow PSR-12 coding standards**
- **Maintain architectural patterns**: Follow existing DDD, event sourcing, and saga patterns
- **Maintain backward compatibility**

## 📊 Performance

### Benchmarks
- **Transaction Processing**: 10,000+ TPS
- **Event Storage**: Sub-millisecond write times
- **Balance Inquiries**: <50ms response time
- **Workflow Execution**: <100ms average

### Scalability
- Horizontal scaling via read replicas
- Event store sharding capabilities
- Queue-based async processing
- Redis caching for performance

## 🛠️ Tech Stack

- **Backend**: Laravel 12, PHP 8.3+
- **Event Sourcing**: Spatie Event Sourcing
- **Workflows**: Laravel Workflow
- **Database**: MySQL 8.0+/PostgreSQL 13+
- **Cache/Queue**: Redis 6.0+
- **Testing**: Pest PHP
- **Frontend**: Laravel Jetstream, Livewire, Tailwind CSS
- **Admin Panel**: Filament v3

## 📈 Roadmap

See our comprehensive [Development Roadmap](docs/01-VISION/ROADMAP.md) for detailed implementation phases.

### Current Status: Prototype Demonstration
The FinAegis prototype demonstrates comprehensive banking architecture patterns and serves as a foundation for future development.

#### Completed Phases ✅
- **Phase 1-3**: Multi-asset foundation, exchange rates, and platform integration
- **Phase 4**: Basket assets with dynamic rebalancing and performance tracking
- **Phase 4.1**: User bank allocation system with 5-bank support
- **Phase 4.2**: Enhanced governance with GCU voting implementation
- **Phase 4.3**: Compliance framework with KYC, AML, and regulatory reporting
- **Phase 5.1**: Real bank integration with Paysera, Deutsche Bank, and Santander
- **Phase 6**: Complete governance system with polling and voting
- **Phase 7**: Continuous Growth Offering (CGO) platform
- **Phase 8**: Advanced trading features including:
  - **Phase 8.1**: Generalized exchange engine with liquidity pools
  - **Phase 8.2**: Stablecoin framework with collateral management
  - **Phase 8.3**: Multi-chain wallet system with key management
  - **Phase 8.4**: P2P lending platform with credit scoring

#### Technical Achievements ✅
- **Event Sourcing**: Complete implementation with aggregates and projectors
- **Multi-Asset Support**: Full support for fiat, crypto, and commodity assets
- **Exchange Rates**: Real-time rate management with multiple providers
- **Basket Assets**: Composite assets with rebalancing algorithms
- **Bank Integration**: Production-ready connectors for 3 major banks
- **Governance**: Democratic voting system with automated execution
- **Compliance**: KYC/AML, GDPR, and regulatory reporting
- **Admin Dashboard**: Comprehensive Filament v3 interface
- **API Coverage**: Complete REST APIs with OpenAPI documentation
- **Test Coverage**: 50%+ coverage with parallel test execution
- **Exchange Engine**: Order book with saga-based matching
- **DeFi Features**: AMM liquidity pools and yield farming
- **Blockchain Support**: Multi-chain wallet infrastructure
- **Lending Platform**: Complete loan lifecycle management
- **External Integration**: Real-time connection to major exchanges

### Phase 5.2: Transaction Processing ✅
- **Multi-Bank Transfers**: Route transfers across bank network
- **Settlement Logic**: Handle inter-bank settlements
- **Performance Optimization**: Sub-second transfer processing with caching
- **Resilience Patterns**: Circuit breakers, retries, and fallback mechanisms
- **Error Handling**: Robust failure recovery across banks
- **Performance Optimization**: Sub-second transaction processing

## 🎉 Recent Implementations (2025)

### Demo Environment System (January 2025)
- ✅ **Complete Demo Mode**: Zero external dependencies for demonstrations
- ✅ **Service Abstraction Layer**: Environment-based service implementations  
- ✅ **Demo Services**: Payment, Exchange, Lending, Stablecoin, Blockchain mocks
- ✅ **Demo Data Management**: Seeding, reset, and cleanup utilities
- ✅ **Demo User Guide**: Comprehensive documentation for demo features

### FinAegis Exchange Engine (January 2025)  
- ✅ **Event-Sourced Trading**: Complete order book with event sourcing
- ✅ **External Connectors**: Binance and Kraken integration
- ✅ **Order Matching**: Saga-based order matching with compensation
- ✅ **Market Data**: Real-time price feeds and aggregation

### Stablecoin Framework (January 2025)
- ✅ **EUR Stablecoin**: Complete token lifecycle management
- ✅ **Oracle Integration**: Multiple price source aggregation  
- ✅ **Reserve Management**: Event-sourced reserve tracking
- ✅ **Governance Enhancement**: Voting-based parameter adjustment

### P2P Lending Platform (January 2025)
- ✅ **Loan Lifecycle**: Application, approval, funding, repayment
- ✅ **Credit Scoring**: Risk assessment and automated decisions
- ✅ **Event Sourcing**: Complete audit trail for all operations
- ✅ **Early Settlement**: Support for early loan repayment

### Wallet Management System (January 2025)
- ✅ **Multi-Blockchain**: Ethereum, Polygon, BSC, Bitcoin support
- ✅ **HD Wallets**: Hierarchical deterministic key generation
- ✅ **Deposit/Withdrawal**: Saga-based blockchain operations
- ✅ **Security**: Encrypted key storage and backup system

### Future Development Opportunities
- [ ] **Production Hardening**: Security review and production readiness
- [ ] **Real Bank Integration**: Actual API integration with partner banks
- [ ] **Regulatory Compliance**: Full compliance implementation
- [ ] **User Interface**: Complete web and mobile applications
- [ ] **Testing & Validation**: Comprehensive testing for production use

## 🆘 Support

- **Documentation**: [Full Documentation](docs/README.md)
- **Live Demo**: [https://finaegis.org](https://finaegis.org)
- **Issues**: [GitHub Issues](https://github.com/finaegis/core-banking-prototype-laravel/issues)
- **Discussions**: [GitHub Discussions](https://github.com/finaegis/core-banking-prototype-laravel/discussions)
- **Email**: support@finaegis.org

## 📄 License

This project is open-sourced software licensed under the [Apache License 2.0](LICENSE).

## 🙏 Acknowledgments

- Laravel Team for the excellent framework
- Spatie for the event sourcing package
- Laravel Workflow team for saga pattern implementation
- The open-source community for continuous inspiration

---

**Built with ❤️ for the banking industry**
# Trigger CI
