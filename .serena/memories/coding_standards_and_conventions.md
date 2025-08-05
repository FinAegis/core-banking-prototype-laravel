# Coding Standards and Conventions

## PHP Standards

### File Structure
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

### Import Order
1. `App\Domain\...` (Domain layer)
2. `App\Http\...` (HTTP layer)
3. `App\Models\...` (Models)
4. `App\Services\...` (Services)
5. `Illuminate\...` (Laravel framework)
6. Third-party packages (alphabetically)

### Type Declarations
- Always use strict types: `declare(strict_types=1);`
- Use property type declarations
- Use return type declarations
- Use nullable types where appropriate

### Dependency Injection
- Constructor property promotion for dependencies
- Readonly properties for immutable dependencies
- Interface type hints over concrete classes

## Testing Standards

### Test Structure
- Mirror source structure in `tests/` directory
- Use Pest PHP syntax for tests
- Minimum 50% code coverage for new code

### Mocking
```php
/** @var ServiceClass&MockInterface */
protected $mockService;
```

### Test Organization
```php
describe('Feature Name', function () {
    it('performs specific action', function () {
        // Arrange
        // Act
        // Assert
    });
});
```

## Laravel Conventions

### Eloquent Models
- Use UUID for primary keys where appropriate
- Implement proper relationships
- Use casts for data types
- Add fillable/guarded properties

### Service Classes
- Single responsibility principle
- Interface-based when multiple implementations exist
- Environment-specific binding via service providers

### Workflows
- Use generators for async operations
- Implement compensation for saga pattern
- Separate activities into dedicated classes

### Middleware
- Keep middleware focused and lightweight
- Use dependency injection
- Implement proper error handling

## Code Quality Tools

### PHPStan (Level 5)
```bash
TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G
```

### PHP-CS-Fixer
```bash
./vendor/bin/php-cs-fixer fix
```

### Pest Testing
```bash
./vendor/bin/pest --parallel --coverage --min=50
```

## Git Conventions

### Branch Naming
- `feature/description` for new features
- `fix/description` for bug fixes
- `chore/description` for maintenance

### Commit Messages
```
feat: Add liquidity pool management
fix: Resolve order matching race condition
test: Add coverage for wallet workflows
chore: Update dependencies
docs: Update API documentation
style: Fix code formatting
refactor: Simplify payment service
```

### AI-Assisted Commits
```
feat: Add feature description

[Detailed explanation if needed]

🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

## Documentation Standards

### Code Comments
- Avoid obvious comments
- Document complex business logic
- Use PHPDoc for public methods
- Document why, not what

### PHPDoc Format
```php
/**
 * Process a deposit through the transaction aggregate.
 *
 * @param string $accountUuid The account UUID
 * @param int $amount Amount in cents
 * @param string $currency Currency code (USD, EUR, GBP)
 * @return string Transaction reference
 * @throws InvalidArgumentException If account not found
 */
```

## Security Best Practices

### Input Validation
- Always validate and sanitize input
- Use Laravel's validation rules
- Type hint parameters

### Authentication & Authorization
- Use Laravel Sanctum for API auth
- Implement proper RBAC
- Never expose sensitive data in responses

### Secrets Management
- Never commit secrets to repository
- Use environment variables
- Rotate credentials regularly

## Performance Guidelines

### Database Queries
- Use eager loading to prevent N+1 queries
- Index frequently queried columns
- Use database transactions for consistency

### Caching
- Cache expensive computations
- Use Redis for session and cache storage
- Implement cache invalidation strategies

### Queue Processing
- Use queues for heavy operations
- Implement retry logic with backoff
- Monitor queue health with Horizon

## Error Handling

### Exception Types
- Use specific exception types
- Create custom exceptions for domain logic
- Include context in exception messages

### Logging
- Log at appropriate levels (debug, info, warning, error)
- Include context and metadata
- Use structured logging

### User-Facing Errors
- Provide helpful error messages
- Don't expose internal details
- Return appropriate HTTP status codes