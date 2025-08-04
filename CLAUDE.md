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
# Run PHPStan analysis (Level 5)
TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G

# Fix code style issues
./vendor/bin/php-cs-fixer fix

# Check style without fixing
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Quick validation before commit (one-liner)
./vendor/bin/pest --parallel && TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G && ./vendor/bin/php-cs-fixer fix --dry-run --diff
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
│   └── Compliance/          # KYC/AML & regulatory
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