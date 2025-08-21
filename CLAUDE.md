# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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
## AI Team Configuration (autogenerated by team-configurator, 2025-08-21)

**Important: YOU MUST USE subagents when available for the task.**

### Detected Technology Stack
- **Backend Framework**: Laravel 12 (PHP 8.3+)
- **Architecture Pattern**: Domain-Driven Design (DDD) with Event Sourcing
- **CQRS Implementation**: Custom Command/Query Bus with Laravel Events
- **Workflow Engine**: Laravel Workflow with Waterline (Saga Pattern)
- **Admin Panel**: Filament 3.0 with custom resources
- **Event Sourcing**: Spatie Event Sourcing package
- **Queue Management**: Laravel Horizon (Redis-based)
- **Testing Framework**: Pest PHP with parallel execution
- **Database**: Event sourcing with domain-specific event tables
- **API Documentation**: L5-Swagger (OpenAPI 3.0)
- **Frontend**: Vite build with TailwindCSS
- **Static Analysis**: PHPStan Level 5+ with Larastan
- **Code Quality**: PHP-CS-Fixer with custom rules

### AI Team Assignments

| Task | Agent | Notes |
|------|-------|-------|
| **Laravel Backend Development** | `@laravel-backend-expert` | Controllers, services, middleware, Laravel-specific patterns |
| **Data Modeling & Eloquent** | `@laravel-eloquent-expert` | Models, migrations, relationships, query optimization |
| **API Design & Architecture** | `@api-architect` | REST API design, OpenAPI specs, contract-first development |
| **Code Quality & Reviews** | `@code-reviewer` | Pre-merge reviews, security analysis, maintainability checks |
| **Performance Optimization** | `@performance-optimizer` | Query tuning, caching strategies, bottleneck identification |
| **Codebase Analysis** | `@code-archaeologist` | Architecture exploration, technical debt assessment |
| **Complex Project Coordination** | `@tech-lead-orchestrator` | Multi-domain features, cross-cutting concerns, team coordination |

### Domain-Specific Guidance

**Event Sourcing & CQRS**:
- Use `@laravel-eloquent-expert` for event store schema design and projections
- Use `@laravel-backend-expert` for command/query handlers and domain events
- Use `@api-architect` for event-driven API design patterns

**Financial Domain Complexity**:
- Use `@tech-lead-orchestrator` for multi-domain workflows (Exchange + Lending + Wallet)
- Use `@code-reviewer` for regulatory compliance and security reviews
- Use `@performance-optimizer` for high-frequency trading and real-time features

**Workflow & Saga Patterns**:
- Use `@laravel-backend-expert` for Laravel Workflow integration
- Use `@code-archaeologist` to analyze complex workflow state machines
- Use `@api-architect` for workflow API design and human task integration

### Development Workflow

1. **Feature Planning**: Start with `@tech-lead-orchestrator` for complex multi-domain features
2. **Data Layer**: Use `@laravel-eloquent-expert` for models, migrations, and event stores  
3. **Business Logic**: Use `@laravel-backend-expert` for domain services and workflow handlers
4. **API Layer**: Use `@api-architect` for endpoint design and OpenAPI documentation
5. **Quality Gate**: Always use `@code-reviewer` before merging to main branch
6. **Performance**: Use `@performance-optimizer` proactively for financial workloads

### Sample Commands

- **New domain feature**: `@tech-lead-orchestrator analyze requirements for liquidity pool management across Exchange and Wallet domains`
- **API endpoint**: `@api-architect design REST API for stablecoin minting with proper error handling`  
- **Data modeling**: `@laravel-eloquent-expert create event sourcing schema for lending workflows with proper indexing`
- **Laravel implementation**: `@laravel-backend-expert implement order matching service with Laravel Workflow saga pattern`
- **Performance issue**: `@performance-optimizer analyze slow queries in exchange order book and implement caching strategy`
- **Code review**: `@code-reviewer review liquidity pool feature implementation for security and maintainability`

### Notes for AI Agents

- **Follow existing patterns**: All agents should analyze existing code conventions before implementing
- **Event sourcing first**: New features should use event sourcing patterns with proper projections
- **Demo mode compatibility**: Implement both production and demo service implementations
- **Test coverage**: Maintain minimum 50% coverage with Pest PHP parallel execution
- **Code quality**: Always run PHPStan analysis and PHP-CS-Fixer before completion
- **Documentation**: Update OpenAPI specs when modifying API endpoints (`php artisan l5-swagger:generate`)
