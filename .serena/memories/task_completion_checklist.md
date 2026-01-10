# Task Completion Checklist for FinAegis

## Before Marking Any Task Complete

### 1. Run Tests
```bash
# Always run tests first
./vendor/bin/pest --parallel

# If you modified specific areas, run their tests
./vendor/bin/pest tests/Feature/Http/Controllers/Api/  # For API changes
./vendor/bin/pest tests/Domain/                        # For domain logic changes
```

### 2. Check Code Quality
```bash
# Run PHPStan analysis (Level 8 as of v1.1.0)
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G

# Check for PHPStan errors in your modified files specifically
TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse [your-modified-files] --level=8
```

### 3. Fix Code Style
```bash
# Check for style issues
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix any style issues automatically
./vendor/bin/php-cs-fixer fix

# Fix specific files you modified
./vendor/bin/php-cs-fixer fix [your-modified-files]
```

### 4. Update Documentation
- Update relevant documentation in `docs/` if needed
- Update API documentation if endpoints changed:
  ```bash
  php artisan l5-swagger:generate
  ```
- Update inline PHPDoc comments for complex logic

### 5. Verify Coverage (for new features)
```bash
# Run with coverage to ensure minimum 50%
./vendor/bin/pest --parallel --coverage --min=50
```

### 6. Before Committing
1. Review all changes: `git diff`
2. Stage only relevant files: `git add [files]`
3. Write clear commit message using conventional commits
4. Include AI attribution if applicable

### 7. Before Creating/Updating PR
```bash
# Final quality check
TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/php-cs-fixer fix --dry-run --diff
./vendor/bin/pest --parallel

# Push to branch
git push origin [branch-name]
```

### 8. CI/CD Checks
After pushing, monitor GitHub Actions for:
- Test workflow success
- Security scan pass
- PHPStan analysis pass
- Coverage requirements met

## Quick Validation Commands
```bash
# One-liner to check everything before commit
./vendor/bin/pest --parallel && TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G && ./vendor/bin/php-cs-fixer fix --dry-run --diff
```

## Common Issues and Fixes

### PHPStan Mock Type Errors
Use PHPDoc annotations:
```php
/** @var ServiceClass&MockInterface */
protected $mockService;
```

### Import Order Issues
PHP-CS-Fixer will automatically fix import order

### Test Failures
- Check for missing routes
- Verify mock expectations
- Ensure database migrations are run
- Check for missing seeders

## Important Notes
- NEVER skip tests before marking complete
- ALWAYS run phpcs (php-cs-fixer) before creating/updating PR
- Update tests when modifying existing features
- Create tests for all new features
- Maintain minimum 50% code coverage