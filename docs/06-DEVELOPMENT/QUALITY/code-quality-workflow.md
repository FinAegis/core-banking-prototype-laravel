# Code Quality Workflow

This guide explains which quality checks to run before you push. Running them locally prevents CI failures.

## Background

A CI run on PR #236 failed because PHP_CodeSniffer (PHPCS) found trailing whitespace. The problem was not caught locally. At the time, the local workflow ran only PHP CS Fixer and PHPStan, while CI also ran PHPCS.

The three tools check different things:
- PHP CS Fixer fixes code style. The project rules live in `.php-cs-fixer.php`.
- PHPCS checks PSR-12 compliance more strictly. The rules live in `phpcs.xml`. CI runs PHPCS v4 from `vendor/bin` (`squizlabs/php_codesniffer` 4.0.4 in `composer.lock`), so run the same binary locally.
- PHPStan is a static analysis tool. It does not check code style. The project runs it at Level 8 with the baselines listed in `phpstan.neon`.

Some issues, such as trailing whitespace, can slip past PHP CS Fixer. That is why all three are needed.

## Pre-commit script

The script `bin/pre-commit-check.sh` runs all quality tools in the right order. By default it checks only modified files, so it is fast.

```bash
# Check modified files only
./bin/pre-commit-check.sh

# Auto-fix issues where possible
./bin/pre-commit-check.sh --fix

# Check the entire codebase
./bin/pre-commit-check.sh --all

# Run in CI mode with stricter checks
./bin/pre-commit-check.sh --ci
```

## Running the tools by hand

Run the tools in this order:

```bash
# 1. PHP CS Fixer fixes most style issues
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php

# 2. PHPCS/PHPCBF catches PSR-12 issues that CS Fixer misses
./vendor/bin/phpcbf --standard=PSR12 app/
./vendor/bin/phpcs

# 3. PHPStan runs static analysis
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G

# 4. Tests verify behaviour
./vendor/bin/pest --parallel --stop-on-failure
```

For a single file:

```bash
# Fix specific file with PHPCS
./vendor/bin/phpcbf --standard=PSR12 path/to/file.php

# Check specific file with PHPCS
./vendor/bin/phpcs --standard=PSR12 path/to/file.php

# Fix with PHP CS Fixer
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php path/to/file.php
```

## Git pre-commit hook

Git hooks are not stored in the repository. You can point a local `.git/hooks/pre-commit` hook at `bin/pre-commit-check.sh` so the checks run before each commit.

To bypass the hook when you really need to:
```bash
git commit --no-verify
```

## What CI checks

The Code Quality workflow (`.github/workflows/01-code-quality.yml`) runs these steps:
1. `bin/check-test-coverage`, which fails when a top-level directory under `tests/` is not claimed by any CI job.
2. `vendor/bin/phpcs`.
3. `vendor/bin/phpstan analyse --memory-limit=2G` at Level 8.
4. `vendor/bin/php-cs-fixer fix --dry-run --diff`.
5. An OpenAPI drift check on `storage/api-docs/api-docs.json`.

The test suite and the security scans run in their own workflows.

## Good habits

1. Run `./bin/pre-commit-check.sh --fix` before every push.
2. Do not skip PHPCS, because it catches issues that PHP CS Fixer misses.
3. Fix in this order: PHP CS Fixer, then PHPCS, then PHPStan, then tests.
4. Use the auto-fixers. Both style tools can fix most issues themselves.
5. When CI fails, check the log to see which tool failed.

## Common issues

### Trailing whitespace
PHPCS reports it. Fix it with `./vendor/bin/phpcbf --standard=PSR12 file.php`.

### Line length
`phpcs.xml` excludes the line length rule, so PHPCS does not report long lines. Shorten them by hand when it makes the code easier to read.

### Import order
PHP CS Fixer reports it. Fix it with `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php file.php`.

### Type hints
PHPStan reports them. Add proper type declarations and PHPDoc blocks.

## Summary

PHP CS Fixer and PHPCS serve different purposes. PHP CS Fixer fixes code style. PHPCS checks strict PSR-12 compliance. You need both for complete coverage, and PHPStan on top for type safety. Run the pre-commit script before you push to avoid CI failures.
