# FinAegis Troubleshooting Guide

**Last Updated:** 2026-11-24  
**Platform version:** v7.18.1

This guide helps you fix common problems when you develop or run FinAegis. The stack is PHP 8.4, Laravel 12, MySQL 8 or MariaDB, and Redis.

## Table of Contents

1. [Installation Issues](#installation-issues)
2. [Database Problems](#database-problems)
3. [Redis & Caching](#redis--caching)
4. [Testing Failures](#testing-failures)
5. [API Errors](#api-errors)
6. [Frontend Issues](#frontend-issues)
7. [Performance Problems](#performance-problems)
8. [Event Sourcing Issues](#event-sourcing-issues)
9. [Deployment Problems](#deployment-problems)

## Installation Issues

### Composer Install Fails

If Composer cannot install the dependencies, you see this error:
```bash
Your requirements could not be resolved to an installable set of packages
```

Try these steps:
```bash
# Clear composer cache
composer clear-cache

# Update composer
composer self-update

# Install with verbose output
composer install -vvv
```

The project requires PHP 8.4. If the error is a PHP version mismatch, install PHP 8.4 rather than skipping the platform checks with `--ignore-platform-reqs`.

### NPM Install Issues

If the Node modules fail to install, clear the cache and reinstall from the lock file:
```bash
# Clear npm cache
npm cache clean --force

# Delete existing modules
rm -rf node_modules

# Reinstall exactly what package-lock.json lists
npm ci
```

Keep `package-lock.json`. The deploy runs `npm ci`, which needs it.

### Permission Errors

If Laravel cannot write to `storage/logs`, fix the permissions:
```bash
# Fix permissions
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache

# Or for development
sudo chown -R $USER:www-data storage bootstrap/cache
```

## Database Problems

### Migration Failures

If migrations fail on foreign key constraints in a local database, rebuild it. This deletes all data:
```bash
# Drop all tables and migrate again
php artisan migrate:fresh --seed

# Or manually in MySQL
SET FOREIGN_KEY_CHECKS=0;
-- Run migrations
SET FOREIGN_KEY_CHECKS=1;
```

### Connection Refused

If you see `SQLSTATE[HY000] [2002] Connection refused`, the database server is not reachable. Check that it runs and that `.env` points to it:
```bash
# Check MySQL is running
sudo systemctl status mysql

# Start if needed
sudo systemctl start mysql

# Check credentials in .env
DB_HOST=127.0.0.1
DB_PORT=3306
```

### Event Store Issues

If the event store table is missing, run the migrations. The `stored_events` and `snapshots` tables and the domain-specific event tables come from the project's own migrations in `database/migrations/`:
```bash
php artisan migrate

# On a multi-database tenancy setup, also migrate the tenant databases
php artisan tenants:migrate
```

## Redis & Caching

### Redis Connection Failed

If the connection to Redis fails, check that Redis runs and that `.env` points to it:
```bash
# Check Redis is running
redis-cli ping

# Start Redis
sudo systemctl start redis

# Check config in .env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Cache Not Updating

If your changes do not show up after a cache clear, clear every cache and restart the workers:
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Flush Redis entirely (local only; this deletes all Redis data)
redis-cli FLUSHALL

# Restart queue workers
php artisan queue:restart
```

## Testing Failures

### Tests Timeout

If tests hang or run very long, find the slow ones first. Many tests need Redis, so check that Redis runs.
```bash
# Show the slowest tests
./vendor/bin/pest --profile

# Stop at the first failure
./vendor/bin/pest --parallel --stop-on-failure
```

### Database Not Found

Most test configurations (`phpunit.xml`, `phpunit.ci.xml` and others) use SQLite in memory and need no database server. `phpunit.unit.xml` and the multi-connection tests in `tests/MultiConnection/` use a real MySQL database named `testing`. If that database does not exist, create it:
```bash
# Create test database
mysql -u root -p -e "CREATE DATABASE testing;"

# Run migrations for test
php artisan migrate --env=testing
```

### Parallel Test Conflicts

If tests fail in parallel but pass on their own, they usually share state. Give each test unique data, and use the array cache instead of a shared Redis cache:
```php
// Use unique identifiers
beforeEach(function () {
    $this->testId = Str::uuid();
    config(['cache.default' => 'array']);
});
```

To check a single test without parallel workers, run it with a filter:
```bash
./vendor/bin/pest --filter="requires isolation"
```

See [PARALLEL-TESTING.md](../06-DEVELOPMENT/PARALLEL-TESTING.md) for more.

## API Errors

### 401 Unauthorized

If the API returns 401 even though you send a token, check the header and the token:
```php
// Check token format
Authorization: Bearer YOUR_TOKEN_HERE

// Verify token is valid
$user = auth()->user(); // Should return user

// Regenerate token
$token = $user->createToken('api')->plainTextToken;
```

### 419 CSRF Token Mismatch

If web POST requests fail with 419, the request has no valid CSRF token. Include it in the request:
```javascript
// Include CSRF token in requests
axios.defaults.headers.common['X-CSRF-TOKEN'] = 
    document.querySelector('meta[name="csrf-token"]').content;
```

API routes under `/api` do not use the web session, so they need a bearer token rather than a CSRF token. The project's exclusions for web routes such as webhooks are in `app/Http/Middleware/VerifyCsrfToken.php`.

### 429 Too Many Requests

If you get 429, a rate limit was hit. The API does not use one global throttle. It uses the project's own middleware (`api.rate_limit` and `transaction.rate_limit`), configured in `config/rate_limiting.php`:
```env
# Turn rate limiting off locally
RATE_LIMITING_ENABLED=false

# Or raise a single limit, for example for auth endpoints
RATE_LIMIT_AUTH=10
```

Some features have their own named limiters, for example `sms-send` and `mcp.aggregate`. Look for `RateLimiter::for(` in `app/Providers/` to find them.

## Frontend Issues

### Vite Build Errors

If `npm run build` fails, clear the Vite cache and build again. The build output names the file with the error.
```bash
# Clear Vite cache
rm -rf node_modules/.vite

# Rebuild
npm run build
```

### Scripts Not Loading

The web front end uses Blade views with Livewire, and Vite builds the scripts and styles. Vue is not installed, so the `.vue` files in `resources/js/components/` and `resources/js/Pages/` are not built. If a script does not load, check that its file is listed in the `input` array in `vite.config.js` and that the page includes it with `@vite`:
```php
// In the Blade layout
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

### Hot Reload Not Working

If changes do not appear in the browser, restart Vite and check the HMR settings:
```bash
# Restart Vite
npm run dev

# Check Vite config
// vite.config.js
server: {
    hmr: {
        host: 'localhost',
    },
},
```

## Performance Problems

### Slow Queries

If pages load slowly, look at the queries:
```php
// Enable query logging
DB::enableQueryLog();
// ... run code ...
dd(DB::getQueryLog());

// Add indexes
Schema::table('transactions', function (Blueprint $table) {
    $table->index(['account_uuid', 'created_at']);
});

// Use eager loading
$accounts = Account::with('balances', 'user')->get();
```

### Memory Exhausted

If PHP reports "Allowed memory size exhausted", raise the limit or process the data in chunks:
```php
// Increase memory limit
ini_set('memory_limit', '512M');

// Or use chunking
Account::chunk(1000, function ($accounts) {
    // Process accounts
});

// Or use lazy loading
Account::lazy()->each(function ($account) {
    // Process one at a time
});
```

### Queue Processing Slow

If jobs take too long, check that a worker serves every queue. Horizon manages the workers in production:
```bash
# Start Horizon
php artisan horizon

# Check Horizon and its supervisors
php artisan horizon:status
php artisan horizon:supervisors

# Warn when a queue grows too large
php artisan queue:monitor redis:default --max=100
```

## Event Sourcing Issues

### Events Not Projecting

If projections do not update, replay the projector. Projectors and reactors in `app/Domain/*/Projectors` and `app/Domain/*/Reactors` are registered automatically through `auto_discover_projectors_and_reactors` in `config/event-sourcing.php`.
```bash
# List the registered projectors and reactors
php artisan event-sourcing:list

# Replay projector
php artisan event-sourcing:replay "App\Domain\Account\Projectors\AccountProjector"
```

Make sure the queue workers are running, because many projectors are queued.

### Snapshot Issues

If aggregates load slowly, create snapshots:
```bash
# Create snapshots
php artisan snapshot:create

# Configure snapshot frequency
// In aggregate
public function getSnapshotVersion(): int
{
    return 100; // Snapshot every 100 events
}
```

### Event Version Conflicts

If an event class is not found after a change, check the event class map. `config/event-sourcing.php` sets `enforce_event_class_map` to true, so every stored event needs an alias in `event_class_map`:
```php
// config/event-sourcing.php
'event_class_map' => [
    'account_created' => App\Domain\Account\Events\AccountCreated::class,
    // keep the old alias when you rename or move an event class
],
```

## Deployment Problems

### Storage Not Writable

If Laravel reports "The stream or file could not be opened", fix the storage permissions:
```bash
# Production permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Create required directories
php artisan storage:link
```

### Environment Issues

If `.env` changes are not picked up in production, the configuration is cached. Rebuild the caches. Then run `php artisan ops:verify-env` to check the production settings before a deploy.
```bash
# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear if issues
php artisan config:clear
```

### SSL/HTTPS Issues

If the browser shows mixed content warnings, force HTTPS and set the URL:
```php
// Force HTTPS in AppServiceProvider
if (app()->environment('production')) {
    URL::forceScheme('https');
}

// In .env
APP_URL=https://your-domain.com
```

## Quick Fixes

### Reset Everything

```bash
# Reset a local database and all caches (deletes all data)
php artisan migrate:fresh --seed
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan queue:restart
npm run build
```

### Debug Mode

```bash
# Enable debug mode
APP_DEBUG=true
APP_ENV=local

# Check logs
tail -f storage/logs/laravel.log

# Use tinker for debugging
php artisan tinker
>>> User::first()
>>> app(AccountService::class)->create(...)
```

### Common Commands

```bash
# Check application health
php artisan about

# List all routes
php artisan route:list

# List all commands
php artisan list

# Clear compiled classes
php artisan clear-compiled

# Optimize for production
php artisan optimize
```

## Getting Help

1. Read the log in `storage/logs/laravel.log`.
2. Set `APP_DEBUG=true` in a local environment to see full errors.
3. Search the GitHub issues at github.com/FinAegis/core-banking-prototype-laravel/issues.
4. Read the rest of the documentation in the `docs/` directory.

Most problems have simple causes. Check the logs, clear the caches and check the configuration first.