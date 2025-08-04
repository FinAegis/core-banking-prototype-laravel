# Essential Commands for FinAegis Development

## Testing Commands
```bash
# Run all tests in parallel (RECOMMENDED)
./vendor/bin/pest --parallel

# Run specific test file
./vendor/bin/pest tests/Feature/Http/Controllers/Api/FraudDetectionControllerTest.php

# Run with coverage report (minimum 50% required)
./vendor/bin/pest --parallel --coverage --min=50

# Run tests for CI environment
./vendor/bin/pest --configuration=phpunit.ci.xml --parallel --coverage --min=50

# Run specific test suites
./vendor/bin/pest tests/Domain/         # Domain layer tests
./vendor/bin/pest tests/Feature/        # Feature tests
```

## Code Quality Commands
```bash
# Run PHPStan analysis (Level 5)
TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G

# Run PHPStan on specific files
TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse app/Http/Controllers/Api/FraudDetectionController.php --level=5 --no-progress

# Run PHP-CS-Fixer to check code style
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix code style issues automatically
./vendor/bin/php-cs-fixer fix

# Fix specific file
./vendor/bin/php-cs-fixer fix app/Http/Controllers/Api/SomeController.php
```

## Development Server
```bash
# Start Laravel development server
php artisan serve

# Start Vite dev server for frontend assets
npm run dev

# Build production assets
npm run build
```

## Database Commands
```bash
# Run migrations
php artisan migrate

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Run specific seeder
php artisan db:seed --class=GCUBasketSeeder
```

## Queue Management
```bash
# Start queue workers
php artisan queue:work --queue=events,ledger,transactions,transfers,webhooks

# Monitor queues with Horizon
php artisan horizon

# Clear failed jobs
php artisan queue:clear
```

## Admin & API
```bash
# Create admin user for Filament dashboard
php artisan make:filament-user

# Generate/update API documentation
php artisan l5-swagger:generate
# Access at: http://localhost:8000/api/documentation
```

## Cache Management
```bash
# Clear all caches
php artisan cache:clear

# Warm up cache for all accounts
php artisan cache:warmup

# Clear Laravel config cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Git Workflow
```bash
# Check git status
git status

# Run tests before committing
./vendor/bin/pest --parallel

# Check code quality before PR
TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Commit with conventional commit message
git commit -m "fix: Add missing fraud detection routes for tests"
```

## Installation Commands
```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

## System Utilities (Linux)
```bash
# Find files
find . -name "*.php" -type f

# Search in files (use ripgrep)
rg "search-term" --type php

# List files
ls -la

# Check disk usage
df -h

# Monitor processes
htop
```