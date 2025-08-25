# FinAegis Platform Architecture and Development Guide

## Project Overview
FinAegis is an AI-friendly, open-source core banking platform demonstrating modern financial technology patterns. Built with Laravel 12, PHP 8.4+, using Domain-Driven Design, Event Sourcing, and Saga patterns.

## Key Architectural Patterns

### Domain Structure
- **Location**: `app/Domain/[DomainName]/`
- **Pattern**: Each domain follows DDD with:
  - Aggregates/ (extend AggregateRoot for event sourcing)
  - Events/ (domain events)
  - Workflows/ (Laravel Workflow)
  - Activities/ (workflow steps)
  - Services/ (business logic)
  - Projectors/ (read models)
  - Repositories/ (data access)
  - ValueObjects/ (immutable data)

### Event Sourcing Pattern
- Each domain has its own event table: `[domain]_events`
- Aggregates record events using `recordThat()`
- Projectors build read models from events
- Snapshots for performance optimization

### Workflow/Saga Pattern
- Use Laravel Workflow for multi-step processes
- Sagas for distributed transactions with compensation
- Activities for individual workflow steps
- Always implement rollback/compensation

### Infrastructure Layer
- **CQRS**: CommandBus and QueryBus in `app/Infrastructure/CQRS/`
- **Events**: DomainEventBus bridges with Laravel events
- **AI**: LLM providers, vector DB, conversation store

## Domain Maturity Levels

### Fully Implemented (Reference Domains)
- **Account**: Best example of complete domain with event sourcing
- **Exchange**: Complex domain with order matching, liquidity pools, external integration
- **AI**: Modern implementation with MCP server and workflows
- **Lending**: Good example of loan lifecycle management
- **Stablecoin**: Collateral management, oracle integration, liquidation

### Partially Implemented
- **Compliance**: KYC/AML done, missing real-time monitoring
- **Wallet**: Multi-blockchain support, missing hardware wallet integration
- **Treasury**: Basic structure, missing liquidity forecasting
- **Governance**: Voting system, missing delegation features

### Skeletal (Need Implementation)
- **User**: Only has UserRoles, needs full user profile system
- **Performance**: Only optimization service, needs monitoring system
- **Product**: Only SubProductService, needs product catalog
- **Activity**: Basic model only, needs activity tracking

## Critical Security Issues to Address
1. **Token expiration not enforced** - needs middleware implementation
2. **User enumeration in password reset** - must return generic messages
3. **Session limit too high (10)** - reduce to 5 concurrent sessions
4. **Missing rate limiting** on authentication endpoints

## Testing Requirements
- Minimum 50% coverage for all features
- Use Pest PHP for testing: `./vendor/bin/pest --parallel`
- PHPStan Level 5 must pass
- Use `./bin/pre-commit-check.sh --fix` before ALL commits

## Demo Mode Implementation
- Platform runs in demo mode at finaegis.org
- All external services mocked in `Demo*Service` classes
- Enable with `APP_ENV_MODE=demo`
- Always implement both production and demo services

## Development Workflow

### Essential Commands
```bash
# Quality checks (MUST run before commits)
./bin/pre-commit-check.sh --fix

# Individual checks if needed
./vendor/bin/php-cs-fixer fix
./vendor/bin/phpcs --standard=PSR12 app/
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/pest --parallel

# Development servers
php artisan serve
npm run dev
```

### Creating New Features
1. Start with domain aggregate and events
2. Create projector for read models
3. Implement service layer with interface
4. Create demo service implementation
5. Add workflow if multi-step process
6. Write tests (minimum 50% coverage)
7. Update API documentation if needed

### File Creation Guidelines
1. NEVER create documentation unless explicitly requested
2. Always prefer editing existing files
3. Follow existing patterns in the domain
4. Every new feature needs tests
5. Run quality checks before marking complete

## API Development Patterns
- REST endpoints in `routes/api.php`
- Controllers in `app/Http/Controllers/Api/`
- Use Laravel Resources for responses
- Apply proper middleware and scopes
- Document with OpenAPI annotations
- Generate docs: `php artisan l5-swagger:generate`

## Configuration Files
- `.env.demo` - Demo environment settings
- `phpstan.neon` - Static analysis config (Level 5)
- `.php-cs-fixer.php` - Code style rules
- `phpunit.xml` - Test configuration
- `phpunit.ci.xml` - CI test configuration

## AI Agent Assignments
- **Laravel Backend**: Use `@laravel-backend-expert` for controllers, services, middleware
- **Data Layer**: Use `@laravel-eloquent-expert` for models, migrations, event stores
- **API Design**: Use `@api-architect` for REST API design and OpenAPI specs
- **Code Review**: Use `@code-reviewer` before merging to main
- **Performance**: Use `@performance-optimizer` for query optimization
- **Complex Features**: Use `@tech-lead-orchestrator` for multi-domain coordination

## Priority Development Tasks
1. **Critical**: Fix security vulnerabilities (token expiration, user enumeration, session limits)
2. **High**: Complete User domain (profiles, preferences, activity tracking)
3. **High**: Complete Performance domain (monitoring, analytics)
4. **Medium**: Enhance Treasury (liquidity forecasting, portfolio management)
5. **Medium**: Complete Compliance (real-time monitoring, EDD workflows)
6. **Normal**: Add monitoring infrastructure (ELK stack, Grafana)

## Remember
- This is an educational/demo platform showcasing best practices
- Focus on demonstrating patterns correctly
- Maintain AI-friendliness with clear, well-documented code
- Event sourcing for all important state changes
- Always implement both production and demo services
- Security fixes take priority over new features