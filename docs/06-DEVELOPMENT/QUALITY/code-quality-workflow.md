# Code Quality Workflow - Preventing CI Failures

## Problem Summary
We encountered a CI failure on PR #236 where PHPCS caught a trailing whitespace issue that wasn't detected locally. This happened because we were only running PHP CS Fixer and PHPStan, but not PHPCS (PHP_CodeSniffer).

## Root Cause Analysis

### Why We Missed This
1. **Different Tools, Different Rules**:
   - **PHP CS Fixer**: Configured with PSR-12 rules, focuses on fixing style issues
   - **PHPCS (PHP_CodeSniffer)**: Validates PSR-12 compliance more strictly
   - **PHPStan**: Static analysis tool - doesn't check code style at all

2. **The Gap**:
   - Our local workflow only ran PHP CS Fixer and PHPStan
   - CI pipeline additionally runs PHPCS which caught the whitespace issue
   - Some issues (like trailing whitespace) can be missed by PHP CS Fixer

## Solution: Comprehensive Pre-Commit Checks

### New Pre-Commit Script
Location: `bin/pre-commit-check.sh`

Features:
- Checks only modified files (fast)
- Runs all quality tools in correct order
- Auto-fix capability with `--fix` flag
- Full codebase check with `--all` flag

### Correct Tool Execution Order

```bash
# 1. PHP CS Fixer - Fixes most style issues
./vendor/bin/php-cs-fixer fix

# 2. PHPCS/PHPCBF - Catches PSR-12 issues CS Fixer might miss
./vendor/bin/phpcbf --standard=PSR12 app/

# 3. PHPStan - Static analysis
XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ vendor/bin/phpstan analyse --memory-limit=2G

# 4. Tests - Verify functionality
./vendor/bin/pest --parallel
```

### Usage

#### Quick Check (Recommended)
```bash
# Check modified files only
./bin/pre-commit-check.sh

# Auto-fix issues where possible
./bin/pre-commit-check.sh --fix

# Check entire codebase
./bin/pre-commit-check.sh --all
```

#### Manual Tools (if needed)
```bash
# Fix specific file with PHPCS
./vendor/bin/phpcbf --standard=PSR12 path/to/file.php

# Check specific file with PHPCS
./vendor/bin/phpcs --standard=PSR12 path/to/file.php

# Fix with PHP CS Fixer
./vendor/bin/php-cs-fixer fix path/to/file.php
```

## Git Pre-Commit Hook

A git hook has been installed at `.git/hooks/pre-commit` that automatically runs quality checks before each commit.

To bypass (only use when necessary):
```bash
git commit --no-verify
```

## CI Pipeline Requirements

The CI pipeline (GitHub Actions) checks:
1. PHP CS Fixer compliance
2. PHPCS PSR-12 standard
3. PHPStan Level 5 analysis
4. Test suite passes
5. Security scanning

## Best Practices

1. **Always run pre-commit check**: `./bin/pre-commit-check.sh --fix`
2. **Don't skip PHPCS**: It catches issues PHP CS Fixer might miss
3. **Fix in order**: PHP CS Fixer → PHPCS → PHPStan → Tests
4. **Use auto-fix**: Both tools support auto-fixing (`--fix` flag)
5. **Check CI logs**: If CI fails, check which specific tool failed

## Common Issues and Solutions

### Trailing Whitespace
- **Tool**: PHPCS
- **Fix**: `./vendor/bin/phpcbf --standard=PSR12 file.php`

### Line Length Warnings
- **Tool**: PHPCS
- **Note**: Warnings don't block commits, only errors do
- **Fix**: Manual refactoring to shorten lines

### Import Order
- **Tool**: PHP CS Fixer
- **Fix**: `./vendor/bin/php-cs-fixer fix file.php`

### Type Hints
- **Tool**: PHPStan
- **Fix**: Add proper type declarations and PHPDoc blocks

## Summary

The key lesson is that **PHP CS Fixer and PHPCS serve different purposes**:
- PHP CS Fixer: Focuses on fixing code style
- PHPCS: Validates strict PSR-12 compliance
- Both are needed for complete code quality assurance

Always run the pre-commit check script before pushing to avoid CI failures.
