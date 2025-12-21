# Contributing to FinAegis

Thank you for your interest in contributing to FinAegis! This document provides guidelines and workflows for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing Requirements](#testing-requirements)
- [Pull Request Process](#pull-request-process)
- [Commit Message Guidelines](#commit-message-guidelines)
- [Domain Development](#domain-development)
- [Documentation](#documentation)
- [Getting Help](#getting-help)

---

## Code of Conduct

This project adheres to a [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code. Please report unacceptable behavior to the maintainers.

---

## Getting Started

### Prerequisites

- PHP 8.4+
- Composer 2.x
- MySQL 8.0+ or PostgreSQL 13+
- Redis 6.0+
- Node.js 18+
- Git

### Setting Up Your Development Environment

1. **Fork the repository** on GitHub

2. **Clone your fork**:
   ```bash
   git clone https://github.com/YOUR_USERNAME/core-banking-prototype-laravel.git
   cd core-banking-prototype-laravel
   ```

3. **Add upstream remote**:
   ```bash
   git remote add upstream https://github.com/FinAegis/core-banking-prototype-laravel.git
   ```

4. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```

5. **Set up environment**:
   ```bash
   cp .env.demo .env
   php artisan key:generate
   php artisan migrate --seed
   npm run build
   ```

6. **Verify setup**:
   ```bash
   ./bin/pre-commit-check.sh
   ```

### Development Tools

We recommend installing these tools globally:
- [PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) - Code style
- [PHPStan](https://phpstan.org/) - Static analysis
- [Pest](https://pestphp.com/) - Testing framework

---

## Development Workflow

### Branch Naming

Use descriptive branch names following this pattern:

| Type | Pattern | Example |
|------|---------|---------|
| Feature | `feature/short-description` | `feature/add-wire-transfer` |
| Bug fix | `fix/issue-description` | `fix/balance-calculation-error` |
| Documentation | `docs/topic` | `docs/api-authentication` |
| Refactor | `refactor/area` | `refactor/exchange-service` |
| Test | `test/coverage-area` | `test/lending-domain` |

### Workflow Steps

1. **Sync with upstream**:
   ```bash
   git fetch upstream
   git checkout main
   git merge upstream/main
   ```

2. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

3. **Make your changes** with tests

4. **Run quality checks**:
   ```bash
   ./bin/pre-commit-check.sh --fix
   ```

5. **Commit your changes** (see [Commit Message Guidelines](#commit-message-guidelines))

6. **Push to your fork**:
   ```bash
   git push origin feature/your-feature-name
   ```

7. **Create a Pull Request** against `main`

---

## Coding Standards

### PHP Code Style

We follow PSR-12 with additional rules defined in `.php-cs-fixer.php`.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Models\Order;
use Illuminate\Support\Collection;

final class OrderMatchingService
{
    public function __construct(
        private readonly OrderRepository $repository,
        private readonly EventDispatcher $eventDispatcher,
    ) {}

    public function matchOrders(Order $order): Collection
    {
        // Implementation
    }
}
```

**Key requirements:**
- `declare(strict_types=1)` in all PHP files
- `final` classes by default (unless designed for extension)
- Constructor property promotion
- Return type declarations on all methods
- Use `readonly` for immutable properties

### Static Analysis

We use PHPStan at level 5. Run before committing:

```bash
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
```

Common fixes:
- Cast return values: `(int)`, `(string)`, `(float)`
- Add proper type hints to method parameters
- Use null-safe operators: `$object?->method()`

### Import Order

Organize imports in this order:
1. `App\Domain\...` - Domain layer
2. `App\Http\...` - HTTP layer
3. `App\Models\...` - Eloquent models
4. `App\Services\...` - Application services
5. `Illuminate\...` - Laravel framework
6. Third-party packages
7. PHP built-in classes

---

## Testing Requirements

### Test Coverage

- **Minimum**: 50% overall coverage
- **Financial calculations**: 80%+ coverage required
- **Domain aggregates**: Comprehensive event sourcing tests

### Running Tests

```bash
# All tests
./vendor/bin/pest --parallel

# Specific domain
./vendor/bin/pest tests/Domain/Exchange/

# With coverage
./vendor/bin/pest --coverage --min=50

# Single test file
./vendor/bin/pest tests/Feature/Http/Controllers/Api/AccountControllerTest.php
```

### Writing Tests

Use Pest PHP syntax:

```php
<?php

use App\Domain\Exchange\Services\OrderMatchingService;
use App\Domain\Exchange\Models\Order;

describe('OrderMatchingService', function () {
    beforeEach(function () {
        $this->service = app(OrderMatchingService::class);
    });

    it('matches buy orders with sell orders at same price', function () {
        $buyOrder = Order::factory()->buy()->create(['price' => '100.00']);
        $sellOrder = Order::factory()->sell()->create(['price' => '100.00']);

        $matches = $this->service->matchOrders($buyOrder);

        expect($matches)->toHaveCount(1)
            ->and($matches->first()->sellOrderId)->toBe($sellOrder->id);
    });

    it('rejects orders below minimum amount', function () {
        $order = Order::factory()->create(['amount' => '0.001']);

        expect(fn() => $this->service->matchOrders($order))
            ->toThrow(MinimumAmountException::class);
    });
});
```

### Test Categories

| Type | Location | Purpose |
|------|----------|---------|
| Unit | `tests/Unit/` | Individual class behavior |
| Feature | `tests/Feature/` | HTTP endpoints, integrations |
| Domain | `tests/Domain/` | Domain logic, aggregates, events |

---

## Pull Request Process

### Before Submitting

1. **Run all quality checks**:
   ```bash
   ./bin/pre-commit-check.sh --fix
   ```

2. **Ensure tests pass**:
   ```bash
   ./vendor/bin/pest --parallel
   ```

3. **Update documentation** if needed

4. **Add tests** for new functionality

### PR Template

Your PR description should include:

```markdown
## Summary
Brief description of changes

## Changes
- Change 1
- Change 2

## Test Plan
- [ ] Unit tests added/updated
- [ ] Feature tests added/updated
- [ ] Manual testing performed

## Documentation
- [ ] Code comments added where needed
- [ ] README updated (if applicable)
- [ ] API docs updated (if applicable)
```

### Review Process

1. **Automated checks** must pass (CI pipeline)
2. **Code review** by at least one maintainer
3. **Address feedback** promptly
4. **Squash merge** into main

### Review Criteria

- Code follows project standards
- Tests cover new functionality
- No security vulnerabilities introduced
- Performance impact considered
- Documentation updated

---

## Commit Message Guidelines

We use [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

### Types

| Type | Description |
|------|-------------|
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `style` | Code style (formatting, etc.) |
| `refactor` | Code refactoring |
| `perf` | Performance improvement |
| `test` | Adding/updating tests |
| `chore` | Maintenance tasks |

### Examples

```bash
feat(exchange): add limit order support

fix(lending): correct interest calculation for leap years

docs(api): update authentication examples

refactor(compliance): extract KYC verification to service

test(wallet): add coverage for multi-chain transfers
```

### Scope

Use domain names as scope: `account`, `exchange`, `lending`, `compliance`, `wallet`, `governance`, `stablecoin`, `ai`, `api`, `docs`

---

## Domain Development

### Domain Structure

Each domain follows this structure:

```
app/Domain/YourDomain/
├── Aggregates/           # Event-sourced aggregates
├── Commands/             # Command objects (CQRS)
├── Contracts/            # Interfaces
├── DataObjects/          # DTOs, Value Objects
├── Enums/                # Status enumerations
├── Events/               # Domain events
├── Exceptions/           # Domain exceptions
├── Models/               # Eloquent models
├── Policies/             # Authorization policies
├── Projectors/           # Event projectors
├── Queries/              # Query objects (CQRS)
├── Repositories/         # Data access
├── Services/             # Business logic
└── Workflows/            # Saga workflows
    └── Activities/       # Workflow activities
```

### Creating a New Domain

1. **Define the bounded context** - What business capability does it provide?

2. **Design aggregates** - What are the consistency boundaries?

3. **Define events** - What state changes occur?

4. **Implement services** - Business logic goes here

5. **Create projectors** - Build read models from events

6. **Add workflows** - Multi-step processes with compensation

### Event Sourcing Patterns

```php
// Aggregate example
class AccountAggregate extends AggregateRoot
{
    private AccountState $state;

    public function credit(Money $amount, string $reference): self
    {
        // Validate business rules
        if ($amount->isNegative()) {
            throw new InvalidAmountException('Amount must be positive');
        }

        // Record the event (don't modify state directly)
        $this->recordThat(new AccountCredited(
            accountId: $this->uuid(),
            amount: $amount,
            reference: $reference,
            occurredAt: now(),
        ));

        return $this;
    }

    // Apply method updates internal state
    protected function applyAccountCredited(AccountCredited $event): void
    {
        $this->state = $this->state->withBalance(
            $this->state->balance->add($event->amount)
        );
    }
}
```

### Workflow (Saga) Patterns

```php
// Workflow with compensation
class TransferWorkflow extends Workflow
{
    public function execute(TransferRequest $request): Generator
    {
        // Step 1: Debit source account
        $debitResult = yield ActivityStub::make(
            DebitAccountActivity::class,
            $request->sourceAccountId,
            $request->amount,
        );

        try {
            // Step 2: Credit destination account
            yield ActivityStub::make(
                CreditAccountActivity::class,
                $request->destinationAccountId,
                $request->amount,
            );
        } catch (Throwable $e) {
            // Compensate: reverse the debit
            yield ActivityStub::make(
                CreditAccountActivity::class,
                $request->sourceAccountId,
                $request->amount,
            );
            throw $e;
        }
    }
}
```

---

## Documentation

### Code Documentation

- Add PHPDoc for public methods
- Explain complex business logic
- Document non-obvious decisions

```php
/**
 * Calculates the Net Asset Value (NAV) for a basket currency.
 *
 * The NAV is computed as the weighted sum of component values,
 * where weights represent the target allocation percentage.
 *
 * @param array<string, float> $composition Component weights (must sum to 1.0)
 * @param array<string, Money> $prices Current market prices
 * @return Money The calculated NAV in the base currency
 *
 * @throws InvalidCompositionException If weights don't sum to 1.0
 */
public function calculateNAV(array $composition, array $prices): Money
```

### API Documentation

Update OpenAPI specs when changing endpoints:

```bash
php artisan l5-swagger:generate
```

### When to Update Docs

- New features → Add user guide
- API changes → Update OpenAPI spec
- Architecture changes → Update architecture docs
- Breaking changes → Add migration guide

---

## Getting Help

### Resources

- [Documentation](docs/README.md) - Comprehensive guides
- [GitHub Discussions](https://github.com/FinAegis/core-banking-prototype-laravel/discussions) - Questions & ideas
- [GitHub Issues](https://github.com/FinAegis/core-banking-prototype-laravel/issues) - Bug reports

### Issue Templates

When reporting bugs, include:
- Steps to reproduce
- Expected vs actual behavior
- Environment details (PHP version, OS)
- Relevant logs or screenshots

### Feature Requests

For new features:
- Describe the use case
- Explain expected behavior
- Consider alternatives
- Note any breaking changes

---

## Recognition

Contributors are recognized in:
- Release notes
- Contributors list
- Commit history with Co-Authored-By

Thank you for contributing to FinAegis!
