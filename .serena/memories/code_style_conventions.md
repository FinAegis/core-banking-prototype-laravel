# Code Style and Conventions for FinAegis

## PHP Code Style
- **Standard**: PSR-12 with Laravel conventions
- **PHP Version**: 8.4+ with typed properties and return types
- **Namespace Structure**: 
  - `App\Domain\{Context}\` for domain logic
  - `App\Http\Controllers\Api\` for API controllers
  - `App\Models\` for Eloquent models
  - `App\Services\` for application services

## Naming Conventions
- **Classes**: PascalCase (e.g., `FraudDetectionController`)
- **Methods**: camelCase (e.g., `getAlertDetails`)
- **Properties**: camelCase with type declarations
- **Constants**: UPPER_SNAKE_CASE
- **Database Tables**: snake_case plural (e.g., `fraud_cases`)
- **Database Columns**: snake_case (e.g., `created_at`)

## Type Declarations
```php
// Always use strict types
<?php
declare(strict_types=1);

// Property types required
protected User $user;
protected string $status;
protected ?Carbon $completedAt = null;

// Return types required
public function process(): JsonResponse
public function calculate(float $amount): BigDecimal
```

## Testing Conventions
- **Test Framework**: Pest PHP
- **Test Files**: Suffix with `Test.php`
- **Test Methods**: Use `#[Test]` attribute or `test_` prefix
- **Mocking**: Use Mockery with proper type hints
```php
/** @var ServiceClass&MockInterface */
protected $mockService;
```
- **Assertions**: Use Pest expectations when possible
- **Coverage**: Minimum 50% for new code

## Documentation
- **PHPDoc**: Required for complex methods
- **Inline Comments**: Only when necessary for clarity
- **README Files**: Update when adding major features
- **API Documentation**: OpenAPI annotations for endpoints

## Domain-Driven Design Patterns
```php
// Value Objects
final class ExchangeRate {
    public function __construct(
        public readonly string $fromCurrency,
        public readonly string $toCurrency,
        public readonly BigDecimal $rate
    ) {}
}

// Aggregates
class LedgerAggregate extends AggregateRoot {
    // Event sourcing methods
}

// Services
class FraudDetectionService {
    public function __construct(
        private readonly Repository $repository
    ) {}
}
```

## Laravel Specific Conventions
- **Controllers**: Single responsibility, resource-based when possible
- **Requests**: Form request validation for all user input
- **Resources**: API resources for response transformation
- **Middleware**: Custom middleware in `App\Http\Middleware`
- **Migrations**: Descriptive names with timestamp prefix
- **Seeders**: Idempotent and environment-aware

## Import Organization
```php
// Order of imports
use App\Domain\...; // Domain layer
use App\Http\...;   // HTTP layer
use App\Models\...;  // Models
use App\Services\...; // Services
use Illuminate\...; // Laravel framework
use Laravel\...;    // Laravel packages
use Mockery\...;    // Testing
use PHPUnit\...;    // Testing
```

## Git Commit Conventions
- **Format**: Conventional commits
- **Types**: feat, fix, docs, style, refactor, test, chore
- **Example**: `fix: Add missing fraud detection routes for tests`
- **AI Attribution**: Include when using AI assistance:
```
🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

## PHP-CS-Fixer Rules
- Configuration in `.php-cs-fixer.php`
- Key rules:
  - PSR-12 base
  - Array syntax: short (`[]` not `array()`)
  - Binary operators: aligned
  - Ordered imports
  - Single blank line before return
  - No unused imports
  - Trailing comma in multiline arrays

## PHPStan Configuration
- **Level**: 5
- **Paths**: app/, tests/
- **Baseline**: phpstan-baseline.neon for legacy issues
- **Mockery Support**: Use PHPDoc annotations for mock types