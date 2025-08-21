# Code Quality Workflow - FinAegis Platform

## IMPORTANT: Correct Tool Order for Quality Checks

Always run quality tools in this specific order to catch all issues:

### 1. PHP CS Fixer (First)
```bash
./vendor/bin/php-cs-fixer fix
```
- Fixes most code style issues automatically
- Uses .php-cs-fixer.php configuration
- Run this FIRST as it fixes issues other tools will complain about

### 2. PHPCS/PHPCBF (Second)
```bash
# Check for issues
./vendor/bin/phpcs app/ tests/

# Auto-fix issues
./vendor/bin/phpcbf app/ tests/
```
- Catches PSR-12 compliance issues that PHP CS Fixer might miss
- Uses phpcs.xml configuration (already configured for the project)
- Allows snake_case test methods (configured in phpcs.xml)
- Line length limit: 150 chars (soft), 200 chars (hard)

### 3. PHPStan (Third)
```bash
XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G
```
- Static analysis for type safety and logic errors
- Level 5 configured in phpstan.neon
- Run AFTER style fixes to avoid false positives

### 4. Tests (Last)
```bash
./vendor/bin/pest --parallel
```
- Verify functionality after all fixes
- Run in parallel for speed

## Quick Commands

### Before Every Commit (Recommended)
```bash
# Use the pre-commit script (checks only modified files)
./bin/pre-commit-check.sh

# Or with auto-fix
./bin/pre-commit-check.sh --fix
```

### Full Validation (Before PR)
```bash
# Run all checks on entire codebase
./bin/pre-commit-check.sh --all --fix
```

### One-Liner for CI Parity
```bash
# This matches what CI runs
./vendor/bin/php-cs-fixer fix && ./vendor/bin/phpcbf app/ tests/ && ./vendor/bin/phpcs app/ tests/ && XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G && ./vendor/bin/pest --parallel
```

## Why This Order Matters

1. **PHP CS Fixer first**: Fixes most issues, reducing noise for other tools
2. **PHPCS second**: Catches remaining PSR-12 issues, especially whitespace
3. **PHPStan third**: Analyzes clean, properly formatted code
4. **Tests last**: Ensures all changes didn't break functionality

## Common Issues and Solutions

### Issue: CI fails but local passes
**Cause**: Not running PHPCS locally
**Solution**: Always run `./bin/pre-commit-check.sh` before committing

### Issue: Line length warnings
**Note**: Lines over 150 chars get warnings (can be ignored for data arrays)
**Hard limit**: 200 chars will fail

### Issue: Test method naming errors
**Solution**: Already configured - phpcs.xml excludes test files from CamelCase rule

### Issue: PHPStan memory errors
**Solution**: Always use `TMPDIR=/tmp/phpstan-$$ ` prefix and `--memory-limit=2G`

## Git Hook Installed
A pre-commit hook is installed at `.git/hooks/pre-commit` that automatically runs checks on modified files. To bypass: `git commit --no-verify`

## Files Created
- `bin/pre-commit-check.sh` - Main quality check script
- `.git/hooks/pre-commit` - Git hook that runs automatically
- `docs/code-quality-workflow.md` - Detailed documentation

## Key Learning
Always run PHPCS in addition to PHP CS Fixer - they catch different issues!