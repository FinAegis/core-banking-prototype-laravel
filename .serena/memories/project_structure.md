# FinAegis Project Structure

## Root Directory Structure
```
/home/yozaz/www/finaegis/core-banking-prototype-laravel/
├── app/                    # Application code
│   ├── Domain/            # Domain layer (DDD)
│   ├── Http/              # HTTP layer (controllers, middleware)
│   ├── Models/            # Eloquent models
│   ├── Services/          # Application services
│   ├── Filament/          # Admin panel resources
│   ├── Console/           # Artisan commands
│   ├── Jobs/              # Queue jobs
│   ├── Providers/         # Service providers
│   └── Workflows/         # Workflow definitions
├── config/                # Configuration files
├── database/              # Migrations, factories, seeders
├── docs/                  # Documentation
│   ├── 01-VISION/        # Project vision and roadmap
│   ├── 02-ARCHITECTURE/  # Architecture documentation
│   ├── 03-DOMAIN/        # Domain documentation
│   ├── 04-API/           # API documentation
│   ├── 05-DATABASE/      # Database documentation
│   └── 06-DEVELOPMENT/   # Development guides
├── features/              # Behat features
├── public/                # Public assets
├── resources/             # Views, raw assets
├── routes/                # Route definitions
│   ├── api.php           # API routes
│   ├── web.php           # Web routes
│   └── api/              # API route modules
├── storage/               # Storage (logs, cache)
├── tests/                 # Test files
│   ├── Unit/             # Unit tests
│   ├── Feature/          # Feature tests
│   ├── Domain/           # Domain tests
│   └── Security/         # Security tests
└── vendor/                # Composer dependencies
```

## Domain Layer Structure (DDD)
```
app/Domain/
├── Account/               # Account management
│   ├── Aggregates/       # Event sourcing aggregates
│   ├── Events/           # Domain events
│   ├── Models/           # Domain models
│   ├── Projections/      # Event projections
│   ├── Repositories/     # Repository interfaces
│   ├── Services/         # Domain services
│   └── ValueObjects/     # Value objects
├── Exchange/              # Currency exchange
├── Fraud/                 # Fraud detection
├── Compliance/            # Compliance and KYC
├── Custodian/            # Bank custodian services
├── Stablecoin/           # Stablecoin operations
├── Voting/               # Democratic voting
└── Wallet/               # Wallet management
```

## Important Files
- `.env` - Environment configuration (gitignored)
- `.env.example` - Example environment file
- `.env.testing` - Testing environment
- `composer.json` - PHP dependencies
- `package.json` - Node.js dependencies
- `phpunit.xml` - Test configuration
- `phpstan.neon` - Static analysis config
- `.php-cs-fixer.php` - Code style config
- `CLAUDE.md` - AI assistant guidance
- `TODO.md` - Local task tracking (gitignored)

## API Structure
```
routes/api/
├── fraud.php              # Fraud detection endpoints
├── exchange.php           # Exchange rate endpoints
├── compliance.php         # KYC/AML endpoints
├── stablecoin.php        # Stablecoin operations
└── voting.php            # Voting system endpoints
```

## Test Organization
```
tests/
├── Feature/
│   ├── Http/Controllers/Api/  # API controller tests
│   ├── Domain/                 # Domain feature tests
│   ├── Models/                 # Model tests
│   └── Workflows/              # Workflow tests
├── Unit/
│   ├── Domain/                 # Domain unit tests
│   ├── Services/               # Service tests
│   └── ValueObjects/           # Value object tests
└── Security/
    ├── Authentication/         # Auth tests
    └── Penetration/           # Security tests
```

## Configuration Files
- Laravel configs in `config/`
- Domain-specific configs in `config/domain/`
- API versioning in `config/api.php`
- Queue configuration in `config/queue.php`
- Cache configuration in `config/cache.php`

## Key Entry Points
- `artisan` - Laravel CLI
- `public/index.php` - Web entry point
- Admin panel: `/admin`
- API documentation: `/api/documentation`
- API endpoints: `/api/v2/*`