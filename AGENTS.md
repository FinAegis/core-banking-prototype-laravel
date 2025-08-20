# AGENTS.md

Welcome AI coding agents! This file provides essential information to help you work effectively with the FinAegis Core Banking Platform.

## Project Overview

FinAegis is a comprehensive core banking platform prototype built with Laravel 12, demonstrating modern banking architecture with event sourcing, domain-driven design (DDD), and advanced financial features including the Global Currency Unit (GCU) concept.

**Live Demo**: https://finaegis.org

## Quick Start

```bash
# Clone and setup
git clone https://github.com/FinAegis/core-banking-prototype-laravel.git
cd core-banking-prototype-laravel
cp .env.example .env
composer install
npm install && npm run build

# Database setup
php artisan migrate:fresh --seed
php artisan db:seed --class=GCUBasketSeeder

# Development server
php artisan serve
npm run dev
```

## Dev Environment Tips

### Essential Commands

```bash
# Run tests (ALWAYS run before committing)
./vendor/bin/pest --parallel

# Code quality checks (MANDATORY before commits)
XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/php-cs-fixer fix

# Quick validation (one-liner - recommended)
./vendor/bin/pest --parallel && XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G && ./vendor/bin/php-cs-fixer fix
```

### API Documentation
```bash
php artisan l5-swagger:generate
# Access at: http://localhost:8000/api/documentation
```

## Architecture Guidelines

### Domain-Driven Design Structure
- **Domains**: All business logic in `app/Domain/` with separate bounded contexts
- **Event Sourcing**: All state changes through events using Spatie Event Sourcing
- **CQRS Pattern**: Separate Command and Query buses with handlers
- **Sagas**: Multi-step workflows with compensation support

### Key Domains
- `Account/` - Account management and balances
- `Exchange/` - Trading engine and order management
- `Stablecoin/` - Token lifecycle and reserve management
- `Lending/` - P2P lending platform
- `Treasury/` - Cash and risk management
- `AI/` - AI agent framework with MCP integration

## Code Style Guidelines

### PHP Standards
- PHP 8.4+ with strict types
- Laravel 12 conventions
- PSR-12 coding standard
- PHPStan Level 5 (minimum)

### Import Order
1. `App\Domain\...`
2. `App\Http\...`
3. `App\Models\...`
4. `App\Services\...`
5. `Illuminate\...`
6. Third-party packages

### Testing Requirements
- Minimum 50% code coverage for new code
- Test location mirrors source structure in `tests/`
- Use Pest PHP for all tests
- Mock types with PHPDoc annotations

## Testing Instructions

### Test Suites
```bash
# All tests
./vendor/bin/pest --parallel

# Specific domain
./vendor/bin/pest tests/Domain/Exchange/ --parallel

# With coverage
./vendor/bin/pest --parallel --coverage --min=50

# CI environment
./vendor/bin/pest --configuration=phpunit.ci.xml --parallel
```

### Before Committing
1. Run all tests: `./vendor/bin/pest --parallel`
2. Check code quality: `TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G`
3. Fix code style: `./vendor/bin/php-cs-fixer fix`
4. Update API docs if needed: `php artisan l5-swagger:generate`

## Security Considerations

### Critical Security Rules
- **NEVER** commit secrets or API keys
- **NEVER** log sensitive data (passwords, tokens, PII)
- **ALWAYS** validate and sanitize input
- **ALWAYS** use parameterized queries
- **ALWAYS** hash passwords with bcrypt
- **ALWAYS** validate webhook signatures

### Demo Mode
- Use demo services for external integrations
- Test card: 4242424242424242 always succeeds
- Demo data is isolated with scopes

## PR Instructions

### Commit Messages
Use conventional commits:
```
feat: Add liquidity pool management
fix: Resolve order matching race condition
test: Add coverage for wallet workflows
docs: Update API documentation
```

When using AI assistance, include:
```
🤖 Generated with [Claude Code](https://claude.ai/code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

### Pull Request Process
1. Create feature branch: `git checkout -b feature/your-feature`
2. Make changes following all guidelines
3. Run full validation suite
4. Create PR with comprehensive description
5. Ensure GitHub Actions pass
6. Request review

### PR Template
```markdown
## Summary
Brief description of changes

## Key Changes
- List of major changes
- Implementation details

## Test Coverage
- Tests added/modified
- Coverage percentage

## Checklist
- [ ] Tests pass
- [ ] PHPStan passes
- [ ] Code style fixed
- [ ] Documentation updated
```

## Event Sourcing Patterns

### Creating Events
```php
class OrderPlaced extends ShouldBeStored
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $accountId,
        // ... other properties
    ) {}
}
```

### Using Aggregates
```php
Order::retrieve($orderId)
    ->placeOrder($data)
    ->persist();
```

### Implementing Sagas
```php
class OrderRoutingSaga extends Reactor
{
    public function onOrderPlaced(OrderPlaced $event): void
    {
        // Handle multi-step workflow
    }
}
```

## AI Framework Integration

The platform includes a complete AI Agent Framework:
- MCP (Model Context Protocol) server implementation
- Multi-LLM support (OpenAI, Claude)
- Event-sourced AI interactions
- Banking-specific tools and workflows

See `docs/13-AI-FRAMEWORK/` for complete documentation.

## Common Gotchas

1. **Database Transactions**: Event sourcing handles transactions automatically
2. **Async Operations**: Use Laravel Workflow for multi-step processes
3. **Demo Mode**: Check `APP_ENV_MODE=demo` for demo-specific behavior
4. **Queue Workers**: Required for event processing and workflows
5. **Cache**: Remember to clear cache after config changes

## Useful Resources

- **Documentation**: `docs/README.md` - Complete documentation index
- **API Reference**: `docs/04-API/REST_API_REFERENCE.md`
- **Architecture**: `docs/02-ARCHITECTURE/ARCHITECTURE.md`
- **Demo Guide**: `docs/11-USER-GUIDES/DEMO-USER-GUIDE.md`
- **AI Framework**: `docs/13-AI-FRAMEWORK/00-Overview.md`

## Contact & Support

- **GitHub Issues**: Report bugs and request features
- **Demo Site**: https://finaegis.org
- **Documentation**: Comprehensive guides in `/docs`

---

Remember: This is a demonstration prototype showcasing modern banking architecture. Always follow best practices and security guidelines when working with financial systems.