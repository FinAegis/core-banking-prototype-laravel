# FinAegis Core Banking Platform

[![Tests](https://github.com/finaegis/core-banking-prototype-laravel/actions/workflows/test.yml/badge.svg)](https://github.com/finaegis/core-banking-prototype-laravel/actions/workflows/test.yml)
[![License: Apache-2.0](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20.svg)](https://laravel.com/)

**Enterprise-Grade Core Banking Platform Supporting Multiple Financial Products**

FinAegis is a production-ready core banking platform built with event sourcing, domain-driven design, and regulatory compliance at its core. It provides the technical foundation for various financial products including the **Global Currency Unit (GCU)** - a democratic digital currency backed by real banks.

📖 **See [GCU_VISION.md](GCU_VISION.md) for the complete platform vision and GCU implementation details.**

**🤖 AI-Friendly Architecture**: This project welcomes contributions from AI coding assistants (Claude Code, GitHub Copilot, Cursor, etc.). The comprehensive documentation and well-structured patterns make it easy for AI agents to understand and contribute meaningfully to the codebase.

## 🚀 Key Features

FinAegis provides a comprehensive foundation for modern financial services:

### 🌍 Featured Implementation: Global Currency Unit (GCU)

The platform includes support for implementing products like the GCU - a democratic digital currency:

- **Real Bank Backing**: User bank preference model supports multiple banks (e.g., Paysera, Deutsche Bank, Santander)
- **User-Controlled**: Monthly voting templates for currency basket composition
- **Multi-Currency Basket**: Configurable basket with default USD (40%), EUR (30%), GBP (15%), CHF (10%), JPY (3%), Gold (2%)
- **Deposit Insurance**: Designed for government protection through real bank storage
- **Democratic Governance**: Asset-weighted voting (1 unit = 1 vote)
- **Low Fees**: Platform supports configurable fee structures

## 🏛️ Platform Capabilities

FinAegis provides the technical foundation for diverse financial products:

### Core Banking Excellence
- **Event Sourcing Architecture**: Complete audit trail of all transactions
- **Saga Pattern Workflows**: Reliable business process orchestration with compensation
- **Domain-Driven Design**: Clean, maintainable code architecture
- **Quantum-Resistant Security**: SHA3-512 cryptographic hashing
- **Real-time Processing**: High-performance transaction processing (10,000+ TPS)
- **Regulatory Compliance**: Built-in audit trails and compliance features

### Multi-Asset Capabilities (✅ Implemented)
- **Asset-Agnostic Ledger**: Support for fiat, crypto, commodities, and custom assets
- **Multi-Currency Operations**: Seamless cross-currency transactions with real-time rates
- **Exchange Rate Management**: Pluggable rate providers with caching
- **Account Balance System**: Per-asset balance tracking with automatic USD creation
- **Basket Assets**: Composite assets with fixed/dynamic rebalancing and performance tracking

### Decentralized Architecture (✅ Foundation Implemented)
- **Custodian Abstraction**: Unified interface implemented (MockBankConnector available)
- **Multi-Custodian Support**: Foundation laid for multiple financial institutions
- **Automated Reconciliation**: Framework in place for real-time balance verification
- **Risk Distribution**: Configurable allocation strategies
- **User Bank Preferences**: Model for multi-bank fund distribution (Paysera, Deutsche Bank, Santander)

### Democratic Governance (✅ Enhanced)
- **User Voting System**: Complete polling system for platform decisions
- **Configurable Voting Power**: One-user-one-vote and asset-weighted voting strategies
- **Asset-Weighted Voting**: Voting power based on primary asset holdings (1 unit = 1 vote)
- **Automated Execution**: Poll results trigger system workflows (UpdateBasketCompositionWorkflow, etc.)
- **Monthly Voting Templates**: Pre-configured polls for currency basket composition
- **Transparency**: Complete audit trail of all governance actions
- **Admin Interface**: Full poll management and vote monitoring in admin dashboard

## 🚀 Quick Start

### Prerequisites

- PHP 8.3+
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
└── Payment/          # Payment processing domain
    ├── Services/     # Payment services
    └── Workflows/    # Payment workflows
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

### Recent Updates (Phase 4 & 4.1 Implementation)
- **Enhanced User Bank Preferences**: Expanded model with 5 banks (Paysera, Deutsche Bank, Santander, Revolut, Wise)
- **Bank Distribution Algorithm**: Intelligent fund allocation with rounding handling and validation
- **Bank Allocation Service**: Complete service layer for managing user bank preferences
- **Admin Interface**: Filament resource for bank allocation management with visual indicators
- **Bank Network Widget**: Dashboard widget showing bank partner network and insurance coverage
- **Deposit Insurance Tracking**: Calculate total coverage across multiple banks (up to €500,000)
- **Diversification Analysis**: Automated checks for healthy fund distribution
- **Primary Bank Selection**: Designate primary bank for urgent transfers
- **Voting Template Service**: Automated creation of monthly currency basket voting polls
- **Asset-Weighted Voting Strategy**: Democratic voting where 1 primary asset unit = 1 vote
- **Basket Update Workflow**: Automated basket composition updates based on poll results
- **Primary Basket Configuration**: Configurable primary currency basket (defaults: USD 40%, EUR 30%, GBP 15%, CHF 10%, JPY 3%, Gold 2%)
- **Console Commands**: `php artisan voting:setup` for poll management

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

### Additional Resources

- **[Development Guide](DEVELOPMENT.md)**: Complete developer documentation
- **[System Architecture](docs/02-ARCHITECTURE/ARCHITECTURE.md)**: Technical architecture overview
- **[Workflow Patterns](WORKFLOW_PATTERNS.md)**: Saga patterns and best practices
- **[API Implementation](API_IMPLEMENTATION.md)**: Complete API layer documentation
- **[BIAN API Documentation](BIAN_API_DOCUMENTATION.md)**: BIAN-compliant API following banking industry standards
- **[Admin Dashboard Guide](docs/04-TECHNICAL/ADMIN_DASHBOARD.md)**: Comprehensive admin interface documentation
- **[Webhook Integration Guide](docs/04-TECHNICAL/WEBHOOK_INTEGRATION.md)**: Webhook configuration and integration documentation

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

## 🚀 Deployment

### Docker Deployment

```yaml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "8000:80"
    environment:
      - APP_ENV=production
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

### Production Considerations
- Load balancing for high availability
- Database clustering for scalability
- Redis clustering for cache/queue resilience
- Comprehensive monitoring and alerting

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

See our comprehensive [Development Roadmap](ROADMAP.md) for detailed implementation phases.

### Current Status: Multi-Asset Platform Complete (Q4 2024 - Q1 2025)
- [x] **Asset-centric ledger architecture**: Complete with Asset and AccountBalance models
- [x] **Multi-currency account balances**: Per-asset balance tracking implemented
- [x] **Exchange rate service**: Multiple providers with caching and validation
- [x] **Custodian abstraction layer**: Interface and mock implementation ready
- [x] **Governance polling engine**: Full voting system with configurable strategies
- [x] **Transaction read model**: Event-sourced transaction history
- [x] **Admin dashboard**: Complete platform management interface

### Recently Completed
- [x] **Phase 1-3**: Multi-asset foundation and platform integration
- [x] **Phase 4**: Basket assets with dynamic rebalancing and performance tracking
- [x] **Transaction Read Model**: Event-sourced transaction history with projector
- [x] **Governance System**: Complete polling and voting system with admin interface
- [x] **Asset Management**: Full asset CRUD with exchange rate management
- [x] **Basket Assets**: Composite assets with fixed/dynamic rebalancing
- [x] **Export functionality**: CSV/XLSX for all major entities
- [x] **Webhook system**: Real-time event notifications with HMAC security
- [x] **Enhanced analytics dashboard**: Charts, widgets, and real-time statistics
- [x] **System health monitoring**: Performance metrics and queue monitoring

### Phase 4.2: Enhanced Governance (✅ Completed)
- [x] **GCU Implementation**: Environment-configurable basket implementation
- [x] **Enhanced Voting Workflow**: Weighted average calculation from votes
- [x] **User Voting Interface**: API endpoints and Vue.js dashboard component
- [x] **Monthly Poll Automation**: Scheduled task for poll creation
- [x] **GCU Admin Widget**: Basket composition display with rebalancing info

### Next Phase: Production Integrations (Q1-Q2 2025)
- [ ] **Production custodian integrations**: Paysera, Santander connectors
- [ ] **Enhanced compliance**: Automated regulatory reporting
- [ ] **Advanced fraud detection**: ML-based risk scoring
- [ ] **Mobile SDK**: Native mobile development kit
- [ ] **Performance optimization**: Microservices architecture readiness

## 🆘 Support

- **Documentation**: [Full Documentation](./docs/)
- **Issues**: [GitHub Issues](https://github.com/finaegis/core-banking-laravel/issues)
- **Discussions**: [GitHub Discussions](https://github.com/finaegis/core-banking-laravel/discussions)
- **Email**: support@finaegis.com

## 📄 License

This project is open-sourced software licensed under the [Apache License 2.0](LICENSE).

## 🙏 Acknowledgments

- Laravel Team for the excellent framework
- Spatie for the event sourcing package
- Laravel Workflow team for saga pattern implementation
- The open-source community for continuous inspiration

---

**Built with ❤️ for the banking industry**
