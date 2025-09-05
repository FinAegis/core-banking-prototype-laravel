# Test Traits

This directory contains reusable test traits that can be used across test files to reduce duplication and improve test maintenance.

## Available Traits

### InteractsWithFilament
Used for testing Filament admin panel functionality. Provides authentication and panel setup helpers.

**Usage:**
```php
use Tests\Traits\InteractsWithFilament;

class AdminPanelTest extends TestCase
{
    use InteractsWithFilament;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFilamentWithAuth();
    }
}
```

**Used in:** `tests/Feature/Filament/**` (via Pest.php configuration)

## Important Notes

⚠️ **PHPStan Validation**: All traits in this directory are checked by PHPStan for usage. Unused traits will cause CI failures.

⚠️ **Pre-commit Hook**: The pre-commit hook (`./bin/pre-commit-check.sh`) automatically checks for unused traits when:
- Running with `--all` or `--ci` flags
- Any file in the `tests/` directory is modified

## Adding New Traits

When adding a new trait:
1. Create the trait file in this directory
2. Immediately use it in at least one test file or configure it in `tests/Pest.php`
3. Document its purpose and usage in this README
4. Run `./bin/pre-commit-check.sh --all` to verify it passes PHPStan checks

## Removing Traits

Before removing a trait:
1. Check for all usages: `grep -r "TraitName" tests/`
2. Remove or update all references
3. Delete the trait file
4. Run `./bin/pre-commit-check.sh --all` to verify no issues
