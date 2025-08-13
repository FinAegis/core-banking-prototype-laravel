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
# Run PHPStan analysis (Level 5) - Xdebug disabled for performance
XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G

# Stricter analysis for new/modified files (Level 7)
XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse [files] --level=7 --memory-limit=2G

# Check for dead code and bleeding edge issues
XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse [files] --level=max --memory-limit=2G

# Fix code style issues (IMPORTANT: Run before committing!)
./vendor/bin/php-cs-fixer fix

# Check style without fixing (use this to verify)
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Quick validation before commit (one-liner - RECOMMENDED)
./vendor/bin/pest --parallel && XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G && ./vendor/bin/php-cs-fixer fix

# Full pre-commit validation (ensures all style issues are fixed)
./vendor/bin/php-cs-fixer fix && ./vendor/bin/pest --parallel && XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G
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

### Testing Requirements
- **Minimum Coverage**: 50% for all new code
- **Test Location**: Mirror source structure in `tests/`
- **Mocking**: Use PHPDoc for mock types
```php
/** @var ServiceClass&MockInterface */
protected $mockService;
```

### Import Order
1. App\Domain\...
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