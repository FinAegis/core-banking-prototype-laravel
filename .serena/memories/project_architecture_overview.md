# FinAegis Core Banking Prototype - Architecture Overview

## Domain-Driven Design Structure

### Core Domains
```
app/Domain/
├── Account/              # Account management with event sourcing
│   ├── Aggregates/      # TransactionAggregate for balance tracking
│   ├── Models/          # Account, Transaction, TransactionProjection
│   └── Workflows/       # Account lifecycle workflows
├── Exchange/            # Trading & exchange engine
│   ├── Activities/      # Trading activities
│   ├── Services/        # OrderMatchingService, LiquidityService
│   └── Workflows/       # Order matching, liquidity management
├── Stablecoin/          # Stablecoin framework
│   ├── Aggregates/      # Stablecoin lifecycle aggregates
│   ├── Services/        # Minting, burning, collateral management
│   └── Workflows/       # Mint/burn workflows with saga pattern
├── Lending/             # P2P lending platform
│   ├── Activities/      # Loan processing activities
│   ├── Models/          # Loan, LoanApplication, Collateral
│   └── Workflows/       # Loan application workflow with compensation
├── Wallet/              # Blockchain wallet management
│   ├── Connectors/      # Blockchain-specific connectors
│   ├── Factories/       # BlockchainConnectorFactory
│   └── Services/        # Wallet management services
├── Payment/             # Payment processing
│   ├── Services/        # Interface-based payment services
│   ├── Activities/      # Payment processing activities
│   └── Workflows/       # Payment workflows
├── CGO/                 # Continuous Growth Offering
├── Governance/          # Voting & governance system
└── Compliance/          # KYC/AML & regulatory compliance
```

## Key Architectural Patterns

### 1. Event Sourcing
- All major domains use event sourcing with dedicated event tables
- Event projections for read models
- Aggregates for business logic encapsulation
- Example: `TransactionAggregate`, `StablecoinAggregate`

### 2. Workflow & Saga Pattern
- Laravel Workflow with Waterline for complex operations
- Saga pattern for distributed transactions with compensation
- Human task integration for approvals
- Example workflows:
  - `OrderMatchingWorkflow`
  - `LoanApplicationWorkflow`
  - `ProcessOpenBankingDepositWorkflow`

### 3. Service Layer Pattern
- Interface-based dependency injection
- Environment-specific implementations (Demo/Sandbox/Production)
- Service providers for automatic binding
- Example: `PaymentServiceInterface` with three implementations

### 4. Factory Pattern
- Used for creating blockchain connectors
- Supports multiple blockchains dynamically
- Example: `BlockchainConnectorFactory`

## Technology Stack

### Backend
- **PHP 8.4+** with strict typing
- **Laravel 12** framework
- **MySQL 8.0** for primary database
- **Redis** for caching and queues

### Event & Workflow
- **Spatie Event Sourcing** for event-driven architecture
- **Laravel Workflow** with Waterline for orchestration
- **Laravel Horizon** for queue management

### Admin & API
- **Filament 3.0** for admin panel
- **L5-Swagger** for OpenAPI documentation
- **Laravel Sanctum** for API authentication

### Testing
- **Pest PHP** with parallel testing support
- **PHPStan Level 5** for static analysis
- **PHP-CS-Fixer** for code standards
- **Mockery** for mocking

### CI/CD
- **GitHub Actions** for continuous integration
- **Docker** support for containerization
- **Environment-based deployment** (demo/staging/production)

## Multi-Asset Support
- Primary currencies: USD, EUR, GBP
- Custom token: GCU (Governance Currency Unit)
- GCU Basket composition for stability
- Real-time exchange rate management

## Security Features
- Defense in depth architecture
- Zero trust security model
- Comprehensive audit logging
- Role-based access control (RBAC)
- KYC/AML compliance built-in

## Performance Optimizations
- Database query optimization with eager loading
- Redis caching for frequently accessed data
- Queue-based processing for heavy operations
- Horizontal scaling support

## Current Development Focus
- **Phase 8.1**: FinAegis Exchange - Liquidity Pool Management
- Demo environment implementation (completed)
- Production readiness preparation
- Sandbox mode for third-party integrations