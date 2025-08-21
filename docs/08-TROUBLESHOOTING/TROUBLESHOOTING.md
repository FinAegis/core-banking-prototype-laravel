# FinAegis Troubleshooting Guide

**Last Updated:** 2025-07-07  
**Version:** 1.0

This guide helps resolve common issues encountered when developing or running the FinAegis platform.

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

**Problem**: Dependencies won't install
```bash
Your requirements could not be resolved to an installable set of packages
```

**Solution**:
```bash
# Clear composer cache
composer clear-cache

# Update composer
composer self-update

# Install with verbose output
composer install -vvv

# If PHP version mismatch
composer install --ignore-platform-reqs
```

### NPM Install Issues

**Problem**: Node modules fail to install

**Solution**:
```bash
# Clear npm cache
npm cache clean --force

# Delete existing modules
rm -rf node_modules package-lock.json

# Reinstall
npm install

# Or use yarn
yarn install
```

### Permission Errors

**Problem**: Laravel can't write to storage/logs

**Solution**:
```bash
# Fix permissions
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache

# Or for development
sudo chown -R $USER:www-data storage bootstrap/cache
```

## Database Problems

### Migration Failures

**Problem**: Migrations fail with foreign key constraints

**Solution**:
```bash
# Disable foreign key checks
php artisan migrate:fresh --seed

# Or manually in MySQL
SET FOREIGN_KEY_CHECKS=0;
-- Run migrations
SET FOREIGN_KEY_CHECKS=1;
```

### Connection Refused

**Problem**: SQLSTATE[HY000] [2002] Connection refused

**Solution**:
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

**Problem**: Event store table not found

**Solution**:
```bash
# Run event sourcing migrations
php artisan event-sourcing:migrate

# Or create manually
php artisan migrate --path=vendor/spatie/laravel-event-sourcing/database/migrations
```

## Redis & Caching

### Redis Connection Failed

**Problem**: Connection to Redis failed

**Solution**:
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

**Problem**: Changes not reflected despite cache clear

**Solution**:
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Flush Redis entirely
redis-cli FLUSHALL

# Restart queue workers
php artisan queue:restart
```

## Testing Failures

### Tests Timeout

**Problem**: Tests hang or timeout

**Solution**:
```php
// Increase timeout in specific tests
it('handles large dataset', function () {
    // test code
})->timeout(30); // 30 seconds

// Or globally in phpunit.xml
<phpunit executionTimeLimit="300">
```

### Database Not Found

**Problem**: Database 'testing' doesn't exist

**Solution**:
```bash
# Create test database
mysql -u root -p -e "CREATE DATABASE testing;"

# Run migrations for test
php artisan migrate --env=testing
```

### Parallel Test Conflicts

**Problem**: Tests fail in parallel but pass individually

**Solution**:
```php
// Use unique identifiers
beforeEach(function () {
    $this->testId = Str::uuid();
});

// Or disable parallel for specific tests
it('requires isolation', function () {
    // test code
})->sequential();
```

## API Errors

### 401 Unauthorized

**Problem**: API returns 401 even with token

**Solution**:
```php
// Check token format
Authorization: Bearer YOUR_TOKEN_HERE

// Verify token is valid
$user = auth()->user(); // Should return user

// Regenerate token
$token = $user->createToken('api')->plainTextToken;
```

### 419 CSRF Token Mismatch

**Problem**: POST requests fail with 419

**Solution**:
```javascript
// Include CSRF token in requests
axios.defaults.headers.common['X-CSRF-TOKEN'] = 
    document.querySelector('meta[name="csrf-token"]').content;

// Or exclude API routes from CSRF
// In VerifyCsrfToken.php
protected $except = [
    'api/*'
];
```

### 429 Too Many Requests

**Problem**: Rate limiting triggered

**Solution**:
```php
// Increase rate limit in RouteServiceProvider
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
});

// Or disable for testing
if (app()->environment('testing')) {
    RateLimiter::for('api', fn() => Limit::none());
}
```

## Frontend Issues

### Vite Build Errors

**Problem**: npm run build fails

**Solution**:
```bash
# Clear Vite cache
rm -rf node_modules/.vite

# Rebuild
npm run build

# Check for syntax errors
npm run lint
```

### Components Not Found

**Problem**: Vue components not loading

**Solution**:
```javascript
// Register globally in app.js
import AccountBalance from './Components/AccountBalance.vue';
app.component('AccountBalance', AccountBalance);

// Or check import path
import AccountBalance from '@/Components/AccountBalance.vue';
```

### Hot Reload Not Working

**Problem**: Changes not reflected in browser

**Solution**:
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

**Problem**: Pages loading slowly

**Solution**:
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

**Problem**: Allowed memory size exhausted

**Solution**:
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

**Problem**: Jobs taking too long

**Solution**:
```bash
# Increase workers
php artisan queue:work --queue=high,default,low --tries=3

# Use Horizon for better management
php artisan horizon

# Monitor queue size
php artisan queue:size
```

## Event Sourcing Issues

### Events Not Projecting

**Problem**: Projections not updating

**Solution**:
```bash
# Replay projector
php artisan event-sourcing:replay AccountProjector

# Check projector is registered
// config/event-sourcing.php
'projectors' => [
    AccountProjector::class,
],

# Run manually
app(AccountProjector::class)->onMoneyAdded($event);
```

### Snapshot Issues

**Problem**: Aggregates loading slowly

**Solution**:
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

**Problem**: Event class not found after changes

**Solution**:
```php
// Use event aliases
// config/event-sourcing.php
'event_aliases' => [
    'money_added_v1' => MoneyAddedV1::class,
    'money_added_v2' => MoneyAddedV2::class,
],
```

## Deployment Problems

### Storage Not Writable

**Problem**: The stream or file could not be opened

**Solution**:
```bash
# Production permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Create required directories
php artisan storage:link
```

### Environment Issues

**Problem**: .env not loading in production

**Solution**:
```bash
# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear if issues
php artisan config:clear
```

### SSL/HTTPS Issues

**Problem**: Mixed content warnings

**Solution**:
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
# Nuclear option - reset all
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
>>> app(AccountService::class)->createAccount(...)
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

1. **Check Logs**: `storage/logs/laravel.log`
2. **Enable Debug**: Set `APP_DEBUG=true`
3. **Search Issues**: github.com/FinAegis/core-banking-prototype-laravel/issues
4. **Community**: discord.gg/finaegis
5. **Documentation**: docs.finaegis.com

---

**Remember**: Most issues have simple solutions. Check logs, clear caches, and verify configurations first!