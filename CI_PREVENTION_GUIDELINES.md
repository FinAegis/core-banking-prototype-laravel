# CI Prevention Guidelines

## Security Enhancement Best Practices

### 1. User Enumeration Prevention
When implementing security fixes that prevent user enumeration:
- **Always return the same response** regardless of whether a user/email exists
- **Update all related tests** to expect the new behavior
- **Document the security change** in comments for clarity

### 2. Rate Limiting Implementation
When adding rate limiting to endpoints:
- **Clear rate limits in tests** before testing: `RateLimiter::clear($key)`
- **Test rate limiting separately** from functionality tests
- **Use different IP keys** in tests to avoid interference

### 3. API Scope Enforcement
When implementing API scope checks:
- **Use `tokenCan()` for API tokens** checking abilities
- **Use `hasRole()` for user roles** checking permissions
- **Document scope requirements** in controller comments
- **Test both positive and negative cases** for each scope

## Testing Best Practices

### 1. Before Pushing Changes
Always run the following checks locally:
```bash
# Run comprehensive pre-commit check
./bin/pre-commit-check.sh --fix

# Or run individual checks:
./vendor/bin/php-cs-fixer fix
./vendor/bin/phpcbf --standard=PSR12 app/
./vendor/bin/pest --parallel
XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G
```

### 2. Common Test Issues and Solutions

#### Issue: Tests fail due to rate limiting
**Solution**: Clear rate limits before tests
```php
RateLimiter::clear('key-name:' . request()->ip());
```

#### Issue: User enumeration tests expect old behavior
**Solution**: Update tests to expect generic security messages
```php
// Old (reveals if email exists):
$response->assertStatus(422)
    ->assertJsonValidationErrors(['email']);

// New (prevents enumeration):
$response->assertStatus(200)
    ->assertJson(['message' => 'Generic success message']);
```

#### Issue: Scope/permission tests fail
**Solution**: Use correct method for checking
```php
// For API tokens with abilities:
$user->tokenCan('admin')

// For user roles:
$user->hasRole('admin')
```

### 3. CI-Specific Considerations

#### Test Environment Differences
- CI runs in isolated containers with fresh databases
- No persistent state between test runs
- Different rate limiting keys may be used
- Always mock external services in tests

#### Common CI Failures and Fixes
1. **Timeout issues**: Use shorter timeouts in tests
2. **Database conflicts**: Use RefreshDatabase trait
3. **External API calls**: Mock all external services
4. **File permissions**: Don't assume write permissions

## Security Test Checklist

Before implementing security features:
- [ ] Implement the security fix
- [ ] Update all affected tests
- [ ] Add new tests for security scenarios
- [ ] Document security behavior in code
- [ ] Run full test suite locally
- [ ] Check for rate limiting interference
- [ ] Verify no user enumeration vulnerabilities
- [ ] Ensure proper scope/permission checks

## Commit Message Guidelines

For security fixes:
```
fix: [Security Issue] Brief description

- Detailed explanation of the security fix
- Impact on existing functionality
- Test updates required
- Breaking changes (if any)

Security: [CVE/Issue reference if applicable]
```

## CI Pipeline Monitoring

### Quick Commands
```bash
# Check PR CI status
gh pr view [PR-NUMBER] --json statusCheckRollup

# View failed jobs
gh run list --workflow="CI Pipeline" --limit 5

# Get logs from failed job
gh run view [RUN-ID] --log-failed
```

### What to Do When CI Fails
1. Check the specific failing job in GitHub Actions
2. Look for the exact error message
3. Reproduce locally if possible
4. Fix and test locally before pushing
5. Consider if it's environment-specific

## Prevention Summary

1. **Always test security changes thoroughly** - both positive and negative cases
2. **Update tests when changing behavior** - especially for security enhancements
3. **Clear rate limits in tests** - prevent test interference
4. **Use proper authentication methods** - tokenCan() vs hasRole()
5. **Run pre-commit checks** - catch issues before CI
6. **Document security decisions** - help future developers understand
7. **Mock external services** - ensure tests are deterministic
