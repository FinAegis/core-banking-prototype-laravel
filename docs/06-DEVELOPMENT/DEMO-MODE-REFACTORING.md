# Demo Mode Configuration Refactoring Plan

## Current Issue

The application currently has two overlapping ways to determine if it's in demo mode:
1. `APP_ENV=demo` - Laravel's environment variable
2. `DEMO_MODE=true` + `config('demo.mode')` - Custom demo flag

This creates confusion and redundancy.

## Recommended Approach

### Option 1: Use Laravel Environment (RECOMMENDED)

**Advantages:**
- Uses Laravel's built-in environment system
- Single source of truth
- Consistent with Laravel best practices
- Simpler configuration

**Implementation:**
```php
// Replace all instances of:
if (config('demo.mode')) {
    // demo logic
}

// With:
if (app()->environment('demo')) {
    // demo logic
}
```

**Environment Configuration:**
```env
# .env.demo
APP_ENV=demo

# .env.production
APP_ENV=production

# .env.local
APP_ENV=local
```

### Option 2: Keep Separate Demo Flag

**Advantages:**
- Can enable demo features in any environment
- More granular control
- Can have staging with demo features

**Use Cases:**
- Running demo features in staging environment
- Testing demo mode locally without changing environment
- Gradual rollout of demo features

**Implementation:**
```php
// Keep current approach but document clearly:
if (config('demo.mode')) {
    // This checks DEMO_MODE env variable
    // Independent of APP_ENV
}
```

## Migration Path

### Phase 1: Audit Current Usage
```bash
# Find all demo mode checks
grep -r "config('demo.mode')" app/
grep -r 'config("demo.mode")' app/
grep -r "DEMO_MODE" .env*
grep -r "app()->environment('demo')" app/
```

### Phase 2: Standardize Approach

If choosing Option 1 (recommended):

1. Update bootstrap/app.php:
```php
// Change from:
if (env('DEMO_MODE', false)) {
    $middleware->appendToGroup('web', \App\Http\Middleware\DemoMode::class);
}

// To:
if (app()->environment('demo')) {
    $middleware->appendToGroup('web', \App\Http\Middleware\DemoMode::class);
}
```

2. Update DemoServiceProvider:
```php
// Change from:
if (config('demo.mode')) {
    $this->app->bind(PaymentServiceInterface::class, DemoPaymentService::class);
}

// To:
if ($this->app->environment('demo')) {
    $this->app->bind(PaymentServiceInterface::class, DemoPaymentService::class);
}
```

3. Update all service checks similarly

### Phase 3: Configuration Cleanup

1. Deprecate `DEMO_MODE` environment variable
2. Update .env.demo file to only use `APP_ENV=demo`
3. Consider keeping demo.php config for feature flags:

```php
// config/demo.php
return [
    // Only feature flags, not the main mode flag
    'features' => [
        'instant_deposits' => env('DEMO_INSTANT_DEPOSITS', true),
        'skip_kyc' => env('DEMO_SKIP_KYC', true),
        // ... other feature flags
    ],
    
    // Remove 'mode' => env('DEMO_MODE', false),
];
```

## Benefits of Refactoring

1. **Clarity**: Single way to check demo mode
2. **Consistency**: Aligns with Laravel conventions
3. **Simplicity**: Less configuration to manage
4. **Maintainability**: Easier for new developers to understand

## Implementation Checklist

- [ ] Decision on approach (Option 1 or 2)
- [ ] Update bootstrap/app.php
- [ ] Update DemoServiceProvider.php
- [ ] Update all demo service implementations
- [ ] Update middleware checks
- [ ] Update console commands
- [ ] Update configuration files
- [ ] Update .env.demo file
- [ ] Update documentation
- [ ] Test all demo features
- [ ] Update deployment scripts

## Files to Update

Primary files that need updating:
1. `/bootstrap/app.php` - Line 87
2. `/app/Providers/DemoServiceProvider.php`
3. `/app/Http/Middleware/DemoMode.php`
4. `/config/demo.php`
5. All Demo*Service.php files
6. `.env.demo`
7. Documentation files

## Testing Plan

1. Verify demo services activate correctly with `APP_ENV=demo`
2. Ensure production environment doesn't load demo services
3. Test all demo features work as expected
4. Verify demo banner appears when appropriate
5. Check API responses include demo indicators

## Timeline

- **Phase 1**: Immediate - Document current state
- **Phase 2**: Next sprint - Implement refactoring
- **Phase 3**: Following sprint - Clean up and test

## Notes

- This change is backward compatible if we temporarily support both methods
- Consider adding a deprecation notice for `DEMO_MODE` variable
- Update all documentation to reflect the new approach