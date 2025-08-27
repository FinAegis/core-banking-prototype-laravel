# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## CI/CD Troubleshooting Guide

### Pre-Commit Checks (ALWAYS RUN BEFORE PUSHING)
```bash
# Enhanced pre-commit check that mirrors GitHub Actions
./bin/pre-commit-check.sh          # Check modified files only
./bin/pre-commit-check.sh --fix    # Auto-fix issues where possible
./bin/pre-commit-check.sh --all    # Check entire codebase

# If pre-commit times out on PHPStan, run manually:
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
```

### Common CI Failures and Fixes

#### 1. PHPStan Errors
```bash
# Fix type errors and undefined methods
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G --level=5

# Common issues:
# - Cast return types: (int), (string), (float)
# - Undefined methods: Check trait usage and method names
# - Binary operations: Cast string variables to int/float
```

#### 2. Test Isolation Failures
```bash
# Security tests failing due to IP blocking or rate limiting?
# Add to test setUp():
\Illuminate\Support\Facades\Cache::flush();
\Illuminate\Support\Facades\DB::table('blocked_ips')->truncate();

# Or use the CleansUpSecurityState trait:
use Tests\Traits\CleansUpSecurityState;
```

#### 3. Code Style Violations
```bash
# Auto-fix most issues:
./vendor/bin/php-cs-fixer fix
./vendor/bin/phpcbf --standard=PSR12 app/

# Check without fixing:
./vendor/bin/php-cs-fixer fix --dry-run --diff
./vendor/bin/phpcs --standard=PSR12 app/
```

#### 4. GitHub Actions Status Check
```bash
# Check PR status
gh pr list --state open
gh pr checks <PR_NUMBER>

# View failed logs
gh run list --branch <BRANCH_NAME>
gh run view <RUN_ID> --log-failed
```

### Preventing CI Failures

1. **ALWAYS run pre-commit check before pushing:**
   ```bash
   ./bin/pre-commit-check.sh --fix
   ```

2. **Test locally with CI configuration:**
   ```bash
   # Run tests as CI does
   ./vendor/bin/pest --configuration=phpunit.ci.xml --parallel
   ```

3. **Fix common issues proactively:**
   - Clear test state in setUp() methods
   - Cast types for PHPStan compliance
   - Run code formatters before commit

## Essential Commands

### Testing
```bash
# Run all tests in parallel (RECOMMENDED)
./vendor/bin/pest --parallel

# Run specific test file
./vendor/bin/pest tests/Feature/Http/Controllers/Api/AccountControllerTest.php

# Run with coverage (minimum 50% required)
./vendor/bin/pest --parallel --coverage --min=50

# Run tests for CI environment
./vendor/bin/pest --configuration=phpunit.ci.xml --parallel --coverage --min=50

# Run specific test suites
./vendor/bin/pest tests/Domain/         # Domain layer tests
./vendor/bin/pest tests/Feature/        # Feature tests
./vendor/bin/pest tests/Unit/           # Unit tests
```

### Code Quality (ALWAYS RUN BEFORE COMMITTING)
```bash
# NEW: Comprehensive pre-commit check (RECOMMENDED)
./bin/pre-commit-check.sh          # Check modified files only
./bin/pre-commit-check.sh --fix    # Auto-fix issues where possible
./bin/pre-commit-check.sh --all    # Check entire codebase

# Individual Tools (if needed):

# 1. PHP CS Fixer - Fix code style issues
./vendor/bin/php-cs-fixer fix      # Fix issues
./vendor/bin/php-cs-fixer fix --dry-run --diff  # Check without fixing

# 2. PHP CodeSniffer (PHPCS) - Check PSR-12 compliance
./vendor/bin/phpcs --standard=PSR12 app/        # Check compliance
./vendor/bin/phpcbf --standard=PSR12 app/       # Auto-fix issues

# 3. PHPStan - Static analysis (Level 5)
XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G

# 4. Tests - Run in parallel
./vendor/bin/pest --parallel

# Quick one-liner (old method - use pre-commit-check.sh instead)
./vendor/bin/php-cs-fixer fix && ./vendor/bin/phpcbf --standard=PSR12 app/ && ./vendor/bin/pest --parallel && XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G

# IMPORTANT: The correct order is:
# 1. PHP CS Fixer (fixes most style issues)
# 2. PHPCS/PHPCBF (catches PSR-12 issues CS Fixer might miss)
# 3. PHPStan (static analysis)
# 4. Tests (verify functionality)
```

### Development Server
```bash
# Start Laravel server
php artisan serve

# Start Vite dev server
npm run dev

# Build production assets
npm run build

# Create admin user
php artisan make:filament-user
```

### Queue & Cache
```bash
# Start queue workers
php artisan queue:work --queue=events,ledger,transactions,transfers,webhooks

# Monitor with Horizon
php artisan horizon

# Warm up cache
php artisan cache:warmup

# Clear all caches
php artisan cache:clear
```

### Database
```bash
# Fresh migration with seeding
php artisan migrate:fresh --seed

# Seed GCU basket
php artisan db:seed --class=GCUBasketSeeder
```

### API Documentation
```bash
# Generate/update API docs
php artisan l5-swagger:generate
# Access at: http://localhost:8000/api/documentation
```

## Infrastructure Configuration

### CQRS & Domain Events

The platform uses a clean CQRS implementation with Domain Event Bus for decoupled communication:

#### For Demo/Development Environment
```php
// Infrastructure is enabled but handlers are optional
// Set in .env for demo site:
DOMAIN_ENABLE_HANDLERS=false  # Handlers not needed for demo
```

#### For Production Environment
```php
// Enable full infrastructure with handlers
DOMAIN_ENABLE_HANDLERS=true

// Register command/query/event handlers in DomainServiceProvider
// Example:
$commandBus->register(
    PlaceOrderCommand::class,
    PlaceOrderHandler::class
);
```

#### Infrastructure Components
- **CommandBus**: Handles commands with sync/async/transactional support
- **QueryBus**: Handles queries with caching support
- **DomainEventBus**: Bridges domain events with Laravel's event system
- **Sagas**: Multi-step workflows with compensation support

All infrastructure implementations are in `app/Infrastructure/` and are production-ready.

## Demo Mode Development Workflow

### Working with Demo Environment

The platform includes a comprehensive demo mode for development and testing without external dependencies.

#### Quick Demo Setup
```bash
# Use demo environment configuration
cp .env.demo .env
php artisan config:cache

# Or set manually in .env
APP_ENV_MODE=demo
DEMO_SHOW_BANNER=true
```

#### Demo Service Development

When developing new features, always implement the demo service layer:

```php
// 1. Create interface
interface YourServiceInterface
{
    public function performAction(array $data): Result;
}

// 2. Create production implementation
class ProductionYourService implements YourServiceInterface
{
    // Real implementation
}

// 3. Create demo implementation
class DemoYourService implements YourServiceInterface
{
    public function performAction(array $data): Result
    {
        // Simulated behavior
        return new Result(['success' => true, 'demo' => true]);
    }
}

// 4. Register in service provider
$this->app->bind(YourServiceInterface::class, function ($app) {
    return match (config('services.environment_mode')) {
        'demo' => new DemoYourService(),
        default => new ProductionYourService(),
    };
});
```

#### Testing in Demo Mode

```bash
# Run tests with demo services
APP_ENV_MODE=demo ./vendor/bin/pest

# Test specific demo scenarios
./vendor/bin/pest tests/Feature/Demo/
```

#### Demo Data Management

```bash
# Seed demo data
php artisan db:seed --class=DemoDataSeeder

# Reset demo environment
php artisan demo:reset

# Clean up old demo data
php artisan demo:cleanup
```

#### Demo Mode Best Practices

1. **Always implement demo services** for external integrations
2. **Use predictable test data** (e.g., card 4242... always succeeds)
3. **Add demo indicators** in UI (banners, badges)
4. **Document demo behaviors** in service classes
5. **Test both production and demo** implementations
6. **Keep demo data isolated** using scopes or flags

## Architecture Overview

### Domain-Driven Design Structure
```
app/
├── Domain/                    # Business logic (DDD)
│   ├── Account/              # Account management domain
│   ├── Exchange/             # Trading & exchange engine
│   ├── Stablecoin/          # Stablecoin framework
│   ├── Lending/             # P2P lending platform
│   ├── Wallet/              # Blockchain wallet management
│   ├── CGO/                 # Continuous Growth Offering
│   ├── Governance/          # Voting & governance
│   ├── Compliance/          # KYC/AML & regulatory
│   └── Shared/               # Shared domain interfaces (CQRS, Events)
├── Infrastructure/           # Infrastructure implementations
│   ├── CQRS/                # Command & Query Bus implementations
│   └── Events/              # Domain Event Bus implementation
├── Http/Controllers/Api/     # REST API endpoints
├── Models/                   # Eloquent models
├── Services/                 # Application services
└── Filament/Admin/Resources/ # Admin panel resources
```

### Event Sourcing Pattern
All major domains use event sourcing with dedicated event tables:
- `exchange_events` - Trading events
- `stablecoin_events` - Token lifecycle events
- `lending_events` - Loan lifecycle events
- `wallet_events` - Blockchain operations
- `cgo_events` - Investment events

### Workflow & Saga Pattern
Complex operations use Laravel Workflow with saga pattern for:
- Multi-step transactions with compensation
- Cross-domain coordination
- Automatic rollback on failures
- Human task integration

Example saga locations:
- `app/Domain/Exchange/Workflows/OrderMatchingWorkflow.php`
- `app/Domain/Lending/Workflows/LoanApplicationWorkflow.php`
- `app/Domain/Wallet/Workflows/WithdrawalWorkflow.php`

### Key Technologies
- **Backend**: PHP 8.4+, Laravel 12
- **Event Sourcing**: Spatie Event Sourcing
- **Workflow Engine**: Laravel Workflow with Waterline
- **CQRS Pattern**: Command & Query Bus with Laravel implementation
- **Domain Events**: Custom Event Bus bridging with Laravel Events
- **Admin Panel**: Filament 3.0
- **Queue Management**: Laravel Horizon
- **Testing**: Pest PHP (parallel support)
- **API Docs**: L5-Swagger (OpenAPI)

## Code Conventions

### PHP Standards
```php
<?php
declare(strict_types=1);

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Models\Order;
use Illuminate\Support\Collection;

class OrderMatchingService
{
    public function __construct(
        private readonly OrderRepository $repository
    ) {}
    
    public function matchOrders(Order $order): Collection
    {
        // Implementation
    }
}
```

## Task Completion Checklist

Before marking any task complete:

1. **Run comprehensive pre-commit check**: `./bin/pre-commit-check.sh --fix`
2. **Or run individual tools in correct order**:
   - Fix code style: `./vendor/bin/php-cs-fixer fix`
   - Fix PSR-12 issues: `./vendor/bin/phpcbf --standard=PSR12 app/`
   - Check static analysis: `XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G`
   - Run tests: `./vendor/bin/pest --parallel`
3. **Update documentation** if needed
4. **Verify coverage** for new features: `./vendor/bin/pest --parallel --coverage --min=50`
5. **Update API docs** if endpoints changed: `php artisan l5-swagger:generate`
2. App\Http\...
3. App\Models\...
4. App\Services\...
5. Illuminate\...
6. Third-party packages

### Commit Messages
Use conventional commits:
```
feat: Add liquidity pool management
fix: Resolve order matching race condition
test: Add coverage for wallet workflows
```

When using AI assistance, include:
```
🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

## Task Completion Checklist

Before marking any task complete:

1. **Run tests**: `./vendor/bin/pest --parallel`
2. **Check code quality**: `TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G`
3. **Fix code style**: `./vendor/bin/php-cs-fixer fix`
4. **Update documentation** if needed
5. **Verify coverage** for new features: `./vendor/bin/pest --parallel --coverage --min=50`
6. **Update API docs** if endpoints changed: `php artisan l5-swagger:generate`

## Important Files

### Configuration
- `.env.example` - Environment template
- `phpunit.xml` - Test configuration
- `phpunit.ci.xml` - CI test configuration
- `.php-cs-fixer.php` - Code style rules
- `phpstan.neon` - Static analysis config

### Documentation
- `docs/` - Comprehensive documentation
- `TODO.md` - Project tasks (gitignored, session continuity)
- `README.md` - Project overview

### CI/CD
- `.github/workflows/ci-pipeline.yml` - Main CI workflow
- `.github/workflows/test.yml` - Test workflow
- `.github/workflows/security.yml` - Security scanning

## Current Development Focus

**Phase 8.1**: FinAegis Exchange - Liquidity Pool Management
- Build liquidity pool event sourcing
- Create market maker workflows
- Implement spread management saga
- Design inventory balancing events

See `TODO.md` for complete task list and priorities.

## Notes

- Always work in feature branches
- Create pull requests for all changes
- Ensure GitHub Actions pass before merging
- Read `TODO.md` at session start for continuity
- Never create documentation files unless explicitly requested
- Always prefer editing existing files over creating new ones
## AI Team Configuration (autogenerated by team-configurator, 2025-08-27)

**Important: YOU MUST USE subagents when available for the task.**

### Detected Technology Stack

**Core Framework & Architecture:**
- **Backend Framework**: Laravel 12 (PHP 8.4+) with strict type declarations
- **Architecture Pattern**: Domain-Driven Design (DDD) with comprehensive Event Sourcing
- **Event Sourcing Engine**: Spatie Event Sourcing v7.7+ with domain-specific event tables
- **CQRS Implementation**: Custom Command/Query Bus with Laravel Events bridge
- **Workflow Engine**: Laravel Workflow with Waterline (Saga Pattern with compensation)
- **Infrastructure Layer**: Custom CQRS and Event Bus implementations in `app/Infrastructure/`

**Domain Architecture:**
- **Bounded Contexts**: 15+ domains (Account, Exchange, Lending, Treasury, Wallet, Stablecoin, CGO, Governance, Compliance, AI, Banking, Fraud, Monitoring, Payment, Performance)
- **Event Tables**: Domain-specific event stores (`exchange_events`, `lending_events`, `wallet_events`, etc.)
- **Aggregates & Projections**: Full CQRS with read/write model separation
- **Cross-Domain Coordination**: Saga patterns for multi-domain workflows
- **Value Objects**: Immutable domain objects with validation

**Testing & Quality Assurance:**
- **Testing Framework**: Pest PHP v3.0+ with parallel execution and Laravel plugins
- **Test Coverage**: Minimum 50% with comprehensive domain/feature/unit test separation
- **Static Analysis**: PHPStan Level 5+ with Larastan and custom baselines
- **Code Style**: PHP-CS-Fixer with PSR-12 + custom rules, PHPCS for compliance
- **Behavioral Testing**: Behat with Chrome extension for E2E scenarios
- **Performance Testing**: Custom benchmark commands and load testing

**API & Documentation:**
- **API Documentation**: L5-Swagger (OpenAPI 3.0) with automatic generation
- **REST Architecture**: Resource-based API design with versioning support
- **Authentication**: Laravel Passport (OAuth2) + Sanctum with API key management
- **Rate Limiting**: Multi-tier rate limiting with IP blocking and transaction limits

**Frontend & Build Tools:**
- **Build System**: Vite 6.3+ with Laravel plugin integration
- **CSS Framework**: TailwindCSS v3.4+ with forms and typography plugins
- **UI Components**: Livewire v3.6+ for reactive components
- **Admin Panel**: Filament 3.0 with custom resources and advanced features

**Infrastructure & Operations:**
- **Queue Management**: Laravel Horizon v5.27+ with Redis backend
- **Caching**: Redis with Predis client for distributed caching
- **Monitoring**: Custom metrics with Prometheus, OpenTelemetry integration
- **Search**: Laravel Scout with Meilisearch for full-text search
- **File Storage**: AWS SDK integration for S3 and CloudFront

**Security & Compliance:**
- **Authentication**: Multi-factor with Jetstream, biometric verification
- **Authorization**: Spatie Permission with role-based access control
- **Security Scanning**: Continuous vulnerability assessments
- **Compliance**: GDPR, AML, KYC compliance with audit trails
- **Fraud Detection**: ML-based fraud detection with behavioral analysis

**Payment & Financial Integration:**
- **Payment Gateways**: Stripe, Coinbase Commerce with webhook processing
- **Banking Integration**: Open Banking connectors (Deutsche Bank, Santander, Paysera)
- **Blockchain**: Ethereum wallet management with elliptic curve cryptography
- **Exchange Connectors**: Binance, Kraken API integration with rate limiting

**Development & CI/CD:**
- **Version Control**: Git with GitHub Actions (15+ workflow files)
- **CI Pipeline**: Multi-stage pipeline (code quality, security, testing, performance, build)
- **Pre-commit Hooks**: Automated quality checks with `./bin/pre-commit-check.sh`
- **Environment Management**: Docker support with Laravel Sail
- **Code Generation**: Custom Artisan commands for scaffolding

### AI Team Assignments

| Task Category | Primary Agent | Secondary Agent | Specialized Use Cases |
|---------------|---------------|-----------------|----------------------|
| **Laravel Backend Development** | `@laravel-backend-expert` | `@laravel-eloquent-expert` | Controllers, services, middleware, command/query handlers, Laravel-specific patterns |
| **Data Architecture & Modeling** | `@laravel-eloquent-expert` | `@performance-optimizer` | Event sourcing schemas, migrations, projections, relationships, query optimization |
| **API Design & Contracts** | `@api-architect` | `@laravel-backend-expert` | REST API design, OpenAPI specs, resource models, authentication flows |
| **Complex Project Coordination** | `@tech-lead-orchestrator` | `@code-archaeologist` | Multi-domain features, architecture decisions, workflow orchestration |
| **Code Quality & Security** | `@code-reviewer` | `@performance-optimizer` | Pre-merge reviews, security analysis, compliance checks, technical debt |
| **Performance & Optimization** | `@performance-optimizer` | `@laravel-eloquent-expert` | Query tuning, caching strategies, bottleneck identification, load testing |
| **Codebase Analysis & Documentation** | `@code-archaeologist` | `@documentation-specialist` | Architecture exploration, legacy analysis, technical documentation |

### Domain-Specific Routing Rules

**Event Sourcing & CQRS Architecture:**
- `@laravel-eloquent-expert` → Event store schema design, aggregate persistence, projection optimization
- `@laravel-backend-expert` → Command/query handlers, domain event implementation, CQRS bus integration
- `@api-architect` → Event-driven API patterns, webhook design, async processing contracts
- `@performance-optimizer` → Event store performance, projection rebuilding, aggregate snapshotting

**Financial Domain Complexity:**
- `@tech-lead-orchestrator` → Multi-domain workflows (Exchange + Lending + Wallet + Treasury)
- `@code-reviewer` → Regulatory compliance (GDPR, AML, KYC), security reviews, audit trail validation  
- `@performance-optimizer` → High-frequency trading, real-time order matching, latency optimization
- `@laravel-backend-expert` → Financial calculations, transaction processing, settlement workflows

**Workflow & Saga Patterns:**
- `@laravel-backend-expert` → Laravel Workflow implementation, activity definitions, saga orchestration
- `@code-archaeologist` → Complex workflow state machines, saga recovery, compensation analysis
- `@api-architect` → Workflow API design, human task integration, async operation patterns
- `@tech-lead-orchestrator` → Cross-domain saga coordination, workflow dependencies

**AI & Machine Learning Integration:**
- `@laravel-backend-expert` → AI service integration, LLM providers (Claude, OpenAI), MCP implementations
- `@api-architect` → AI API contracts, conversation flows, tool integration patterns
- `@performance-optimizer` → Vector database optimization, ML inference caching, model performance
- `@code-reviewer` → AI safety, bias detection, responsible AI practices

**Blockchain & Cryptocurrency:**
- `@laravel-backend-expert` → Blockchain connectors, wallet management, cryptographic operations
- `@performance-optimizer` → Transaction throughput, gas optimization, network latency
- `@code-reviewer` → Security audits, private key management, smart contract integration
- `@api-architect` → Blockchain API design, webhook processing, event synchronization

**Banking & Payment Integration:**
- `@laravel-backend-expert` → Banking connectors, payment gateway integration, Open Banking APIs
- `@code-reviewer` → Payment security, PCI compliance, fraud detection integration
- `@performance-optimizer` → Payment processing optimization, settlement performance
- `@api-architect` → Payment API contracts, webhook validation, reconciliation flows

### Development Workflow

**1. Feature Planning & Analysis**
- **Complex Features**: `@tech-lead-orchestrator` → Analyze requirements, break down tasks, coordinate domains
- **Simple Features**: `@laravel-backend-expert` → Direct implementation with pattern analysis
- **Performance Features**: `@performance-optimizer` → Bottleneck analysis, optimization strategy

**2. Architecture & Design**
- **System Design**: `@tech-lead-orchestrator` → Multi-domain architecture, workflow coordination
- **Data Architecture**: `@laravel-eloquent-expert` → Event sourcing design, projection strategy
- **API Design**: `@api-architect` → Contract specification, resource modeling, documentation

**3. Implementation**
- **Domain Logic**: `@laravel-backend-expert` → Service implementation, aggregate logic, workflows
- **Data Layer**: `@laravel-eloquent-expert` → Models, migrations, repositories, factories
- **API Layer**: `@api-architect` OR `@laravel-backend-expert` → Controllers, resources, validation

**4. Quality Assurance**
- **Code Review**: `@code-reviewer` → Security analysis, maintainability, compliance
- **Performance Review**: `@performance-optimizer` → Query analysis, caching, optimization
- **Documentation**: `@documentation-specialist` → Technical docs, API specs, guides

**5. Optimization & Maintenance**
- **Performance Issues**: `@performance-optimizer` → Profiling, bottleneck resolution, scaling
- **Architecture Evolution**: `@code-archaeologist` → Legacy analysis, refactoring strategy
- **Complex Debugging**: `@tech-lead-orchestrator` → Multi-domain issue analysis

### Specialized Command Examples

**Multi-Domain Features:**
```
@tech-lead-orchestrator design liquidity pool management system across Exchange, Treasury, and Wallet domains with event sourcing and saga coordination

@laravel-backend-expert implement market maker workflow with Laravel Workflow, including inventory balancing and spread management activities

@laravel-eloquent-expert create event sourcing schema for liquidity pools with optimized projections for real-time price calculations
```

**API & Integration:**
```
@api-architect design REST API for stablecoin minting with proper error handling, rate limiting, and OpenAPI documentation

@laravel-backend-expert integrate Binance API connector with circuit breaker pattern and comprehensive error handling

@performance-optimizer optimize exchange order book queries and implement Redis caching for sub-millisecond response times
```

**Security & Compliance:**
```
@code-reviewer audit Treasury portfolio management feature for security vulnerabilities, compliance gaps, and regulatory requirements

@laravel-backend-expert implement KYC verification workflow with biometric verification and document analysis services

@tech-lead-orchestrator coordinate fraud detection across Payment, Account, and Compliance domains with ML integration
```

**Performance & Optimization:**
```
@performance-optimizer analyze slow queries in exchange order matching and implement database sharding strategy

@laravel-eloquent-expert optimize event sourcing projections for high-throughput trading scenarios with batch processing

@laravel-backend-expert implement horizontal scaling for payment processing with queue partitioning
```

**Architecture & Analysis:**
```
@code-archaeologist analyze existing Treasury domain architecture and provide refactoring recommendations for better maintainability

@tech-lead-orchestrator evaluate feasibility of implementing cross-chain bridge integration across Wallet and Exchange domains

@laravel-backend-expert refactor legacy payment processing to use modern Laravel patterns with proper error handling
```

### Agent-Specific Guidelines

**For `@laravel-backend-expert`:**
- Always analyze existing Laravel patterns and service provider registrations
- Implement demo service variants for development/testing environments
- Follow event sourcing patterns with proper aggregate design
- Use Laravel Workflow for complex multi-step operations
- Implement proper error handling with Laravel exception patterns

**For `@laravel-eloquent-expert`:**
- Design event sourcing schemas with proper indexing strategies
- Create efficient projections for read model optimization  
- Implement proper factory patterns for testing data generation
- Use Laravel's advanced Eloquent features (casts, attributes, relationships)
- Optimize queries for financial high-frequency operations

**For `@api-architect`:**
- Design contracts following OpenAPI 3.0 specifications
- Implement proper versioning strategies for financial APIs
- Create comprehensive error response structures
- Design webhook patterns for asynchronous operations
- Focus on contract-first development with clear documentation

**For `@code-reviewer`:**
- Enforce security best practices for financial data handling
- Validate compliance with banking regulations (PCI, GDPR, AML)
- Review event sourcing implementation for data consistency
- Check performance implications of architectural decisions
- Ensure proper error handling and audit trail implementation

**For `@performance-optimizer`:**
- Focus on financial workload performance (sub-second trading operations)
- Optimize event sourcing queries and projection rebuilding
- Implement proper caching strategies for real-time data
- Analyze and optimize Laravel Horizon queue performance
- Design scaling strategies for high-throughput scenarios

**For `@tech-lead-orchestrator`:**
- Coordinate complex multi-domain feature implementations
- Make architectural decisions considering event sourcing implications
- Plan saga coordination for cross-domain operations
- Evaluate trade-offs between consistency and performance
- Design integration strategies for external financial services

**For `@code-archaeologist`:**
- Analyze complex domain relationships and dependencies
- Document event sourcing patterns and aggregate boundaries
- Identify technical debt in financial calculation logic
- Map complex workflow state machines and saga patterns
- Provide refactoring strategies for legacy financial components

### Quality Standards & Pre-Commit Requirements

**Mandatory Quality Checks:**
1. **Code Style**: PHP-CS-Fixer + PHPCS (PSR-12 compliance)
2. **Static Analysis**: PHPStan Level 5+ with zero errors
3. **Testing**: Pest PHP with minimum 50% coverage
4. **Security**: No security vulnerabilities in dependencies
5. **Performance**: No N+1 queries or inefficient database operations

**Pre-Commit Command:**
```bash
./bin/pre-commit-check.sh --fix  # ALWAYS run before pushing
```

**Agent Responsibilities:**
- All agents MUST run quality checks before marking tasks complete
- Event sourcing implementations require extra validation for data consistency
- Financial calculations need comprehensive test coverage (>80%)
- API changes require OpenAPI documentation updates
- Performance-critical code needs benchmark validation

### Technology Integration Notes

**Event Sourcing Specific:**
- All state changes must go through domain events
- Projections must be rebuildable from event streams
- Aggregate boundaries align with business invariants
- Event versioning strategy for long-term compatibility

**Laravel Workflow Integration:**
- Use Activities for external service calls
- Implement proper compensation for failed workflows
- Design workflows for long-running operations
- Handle workflow retry and error scenarios

**Demo Mode Architecture:**
- Implement both production and demo service implementations
- Use service container binding for environment-specific behavior
- Maintain demo data isolation with proper scoping
- Document demo service behaviors and limitations

**Multi-Domain Coordination:**
- Use sagas for cross-domain consistency requirements
- Design proper event choreography vs orchestration
- Implement idempotent operations for reliability
- Handle eventual consistency in distributed scenarios

This configuration optimizes the AI team for the complex financial domain with its sophisticated event sourcing architecture, comprehensive compliance requirements, and high-performance trading operations.
