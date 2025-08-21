#!/bin/bash
#
# Pre-Commit Quality Check Script
# Runs all code quality tools in the correct order
#
# Usage: ./bin/pre-commit-check.sh [--fix] [--all]
#        --fix: Auto-fix issues where possible
#        --all: Check all files (default: only modified files)

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Parse arguments
AUTO_FIX=false
CHECK_ALL=false
for arg in "$@"; do
    case $arg in
        --fix)
            AUTO_FIX=true
            ;;
        --all)
            CHECK_ALL=true
            ;;
    esac
done

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Pre-Commit Quality Check${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Get list of modified PHP files
if [ "$CHECK_ALL" = true ]; then
    FILES="app/"
    echo -e "${YELLOW}Checking all files...${NC}"
else
    # Get modified PHP files (staged and unstaged)
    FILES=$(git diff --name-only --diff-filter=ACMR HEAD -- '*.php' | grep -E '^app/' || true)
    if [ -z "$FILES" ]; then
        echo -e "${GREEN}No PHP files modified in app/. Skipping checks.${NC}"
        exit 0
    fi
    echo -e "${YELLOW}Checking modified files:${NC}"
    echo "$FILES" | sed 's/^/  - /'
fi
echo ""

# Track if any checks fail
FAILED=false

# 1. PHP CS Fixer - Fix style issues first if auto-fix enabled
echo -e "${YELLOW}[1/4] Running PHP CS Fixer...${NC}"
if [ "$AUTO_FIX" = true ]; then
    echo "$FILES" | xargs ./vendor/bin/php-cs-fixer fix || true
    echo -e "${GREEN}✓ PHP CS Fixer: Fixed style issues${NC}"
else
    if echo "$FILES" | xargs ./vendor/bin/php-cs-fixer fix --dry-run --diff > /dev/null 2>&1; then
        echo -e "${GREEN}✓ PHP CS Fixer: No issues found${NC}"
    else
        echo -e "${RED}✗ PHP CS Fixer: Issues found (run with --fix to auto-fix)${NC}"
        echo "$FILES" | xargs ./vendor/bin/php-cs-fixer fix --dry-run --diff
        FAILED=true
    fi
fi
echo ""

# 2. PHP CodeSniffer - Check PSR-12 compliance
echo -e "${YELLOW}[2/4] Running PHP CodeSniffer (PSR-12)...${NC}"
if [ "$AUTO_FIX" = true ]; then
    # Try to auto-fix with PHPCBF
    echo "$FILES" | xargs ./vendor/bin/phpcbf --standard=PSR12 2>/dev/null || true
fi

# Run PHPCS
if echo "$FILES" | xargs ./vendor/bin/phpcs --standard=PSR12 --report=summary > /dev/null 2>&1; then
    echo -e "${GREEN}✓ PHPCS: PSR-12 compliant${NC}"
else
    echo -e "${RED}✗ PHPCS: PSR-12 violations found${NC}"
    echo "$FILES" | xargs ./vendor/bin/phpcs --standard=PSR12 --report=summary
    if [ "$AUTO_FIX" = false ]; then
        echo -e "${YELLOW}  Tip: Run with --fix to auto-fix some issues${NC}"
    fi
    # Don't fail on warnings, only errors
    if echo "$FILES" | xargs ./vendor/bin/phpcs --standard=PSR12 -n > /dev/null 2>&1; then
        echo -e "${YELLOW}  Note: Only warnings found, not blocking commit${NC}"
    else
        FAILED=true
    fi
fi
echo ""

# 3. PHPStan - Static analysis (on modified files only unless --all)
echo -e "${YELLOW}[3/4] Running PHPStan...${NC}"
if echo "$FILES" | xargs XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress 2>/dev/null; then
    echo -e "${GREEN}✓ PHPStan: No issues found${NC}"
else
    echo -e "${RED}✗ PHPStan: Issues found${NC}"
    echo "$FILES" | xargs XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ ./vendor/bin/phpstan analyse --memory-limit=2G
    FAILED=true
fi
echo ""

# 4. Tests - Run tests (quick mode for pre-commit)
echo -e "${YELLOW}[4/4] Running Tests (quick mode)...${NC}"
if [ "$CHECK_ALL" = true ]; then
    # Run all tests
    if ./vendor/bin/pest --parallel --compact > /dev/null 2>&1; then
        echo -e "${GREEN}✓ Tests: All tests passed${NC}"
    else
        echo -e "${RED}✗ Tests: Some tests failed${NC}"
        ./vendor/bin/pest --parallel
        FAILED=true
    fi
else
    echo -e "${YELLOW}  Skipping tests for quick check (use --all to run full suite)${NC}"
fi
echo ""

# Summary
echo -e "${GREEN}========================================${NC}"
if [ "$FAILED" = true ]; then
    echo -e "${RED}✗ Pre-commit checks FAILED${NC}"
    echo -e "${YELLOW}Please fix the issues above before committing.${NC}"
    if [ "$AUTO_FIX" = false ]; then
        echo -e "${YELLOW}Tip: Run './bin/pre-commit-check.sh --fix' to auto-fix some issues${NC}"
    fi
    exit 1
else
    echo -e "${GREEN}✓ All pre-commit checks PASSED${NC}"
    echo -e "${GREEN}Ready to commit!${NC}"
fi
echo -e "${GREEN}========================================${NC}"
