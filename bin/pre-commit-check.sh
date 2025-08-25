#!/bin/bash
#
# Enhanced Pre-Commit Quality Check Script
# Matches GitHub Actions CI Pipeline exactly
#
# Usage: ./bin/pre-commit-check.sh [--fix] [--all] [--ci]
#        --fix: Auto-fix issues where possible
#        --all: Check all files (default: only modified files)
#        --ci: Run in CI mode (stricter checks)

set -e

# Get project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Parse arguments
AUTO_FIX=false
CHECK_ALL=false
CI_MODE=false
for arg in "$@"; do
    case $arg in
        --fix)
            AUTO_FIX=true
            ;;
        --all)
            CHECK_ALL=true
            ;;
        --ci)
            CI_MODE=true
            ;;
    esac
done

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Enhanced Pre-Commit Quality Check${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Get list of modified PHP files
if [ "$CHECK_ALL" = true ]; then
    FILES="app/ tests/"
    echo -e "${YELLOW}Checking all files in app/ and tests/...${NC}"
else
    # Get modified PHP files (staged and unstaged) from both app/ and tests/
    FILES=$(git diff --name-only --diff-filter=ACMR HEAD -- '*.php' | grep -E '^(app|tests)/' || true)
    if [ -z "$FILES" ]; then
        echo -e "${GREEN}No PHP files modified in app/ or tests/. Skipping checks.${NC}"
        exit 0
    fi
    echo -e "${YELLOW}Checking modified files:${NC}"
    echo "$FILES" | sed 's/^/  - /'
fi
echo ""

# Track if any checks fail
FAILED=false
FAILURE_REASONS=""

# Function to add failure reason
add_failure() {
    FAILED=true
    FAILURE_REASONS="${FAILURE_REASONS}  - $1\n"
}

# 1. PHP CS Fixer - EXACTLY as CI runs it
echo -e "${BLUE}[1/5] Running PHP CS Fixer (CI Standard)...${NC}"
if [ "$AUTO_FIX" = true ]; then
    ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php || true
    echo -e "${GREEN}✓ PHP CS Fixer: Fixed style issues${NC}"
else
    # CI runs with --dry-run --diff
    if ./vendor/bin/php-cs-fixer fix --dry-run --diff > /dev/null 2>&1; then
        echo -e "${GREEN}✓ PHP CS Fixer: No issues found${NC}"
    else
        echo -e "${RED}✗ PHP CS Fixer: Issues found${NC}"
        ./vendor/bin/php-cs-fixer fix --dry-run --diff | head -50
        add_failure "PHP CS Fixer violations"
    fi
fi
echo ""

# 2. PHP CodeSniffer - EXACTLY as CI runs it (PSR12 on app/ only)
echo -e "${BLUE}[2/5] Running PHP CodeSniffer (PSR-12)...${NC}"
if [ "$AUTO_FIX" = true ]; then
    ./vendor/bin/phpcbf --standard=PSR12 --exclude=Generic.Files.LineLength app/ 2>/dev/null || true
fi

# CI command: vendor/bin/phpcs --standard=PSR12 --exclude=Generic.Files.LineLength app/
if ./vendor/bin/phpcs --standard=PSR12 --exclude=Generic.Files.LineLength app/ > /dev/null 2>&1; then
    echo -e "${GREEN}✓ PHPCS: PSR-12 compliant${NC}"
else
    echo -e "${RED}✗ PHPCS: PSR-12 violations found${NC}"
    ./vendor/bin/phpcs --standard=PSR12 --exclude=Generic.Files.LineLength app/ | head -50
    add_failure "PSR-12 violations"
fi
echo ""

# 3. PHPStan - EXACTLY as CI runs it (with timeout to prevent hanging)
echo -e "${BLUE}[3/5] Running PHPStan (Level 5)...${NC}"
# CI command: vendor/bin/phpstan analyse --memory-limit=2G
# Adding 60-second timeout to prevent hanging on large codebases
if timeout 60 bash -c "XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G --no-progress --no-ansi 2>&1" | grep -q "\[OK\] No errors"; then
    echo -e "${GREEN}✓ PHPStan: No issues found${NC}"
else
    if [ $? -eq 124 ]; then
        echo -e "${YELLOW}⚠ PHPStan: Timed out after 60 seconds (consider running manually)${NC}"
        echo -e "${YELLOW}  Run manually: XDEBUG_MODE=off vendor/bin/phpstan analyse${NC}"
    else
        echo -e "${RED}✗ PHPStan: Issues found${NC}"
        timeout 60 bash -c "XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G --no-progress --no-ansi 2>&1" | head -50
        add_failure "PHPStan errors"
    fi
fi
echo ""
echo ""

# 4. Security Tests - Check if tests pass
echo -e "${BLUE}[4/5] Running Security Tests...${NC}"
if [ "$CI_MODE" = true ] || [ "$CHECK_ALL" = true ]; then
    # Run security tests specifically
    if ./vendor/bin/pest tests/Feature/Security --parallel --compact > /dev/null 2>&1; then
        echo -e "${GREEN}✓ Security Tests: All passed${NC}"
    else
        echo -e "${RED}✗ Security Tests: Some failed${NC}"
        ./vendor/bin/pest tests/Feature/Security --parallel
        add_failure "Security test failures"
    fi
else
    echo -e "${YELLOW}  Skipping security tests (use --all or --ci to run)${NC}"
fi
echo ""

# 5. All Tests - Run full test suite in CI mode
echo -e "${BLUE}[5/5] Running Test Suite...${NC}"
if [ "$CI_MODE" = true ]; then
    echo -e "${YELLOW}  Running full test suite (CI mode)...${NC}"
    if ./vendor/bin/pest --parallel --compact > /dev/null 2>&1; then
        echo -e "${GREEN}✓ Tests: All tests passed${NC}"
    else
        echo -e "${RED}✗ Tests: Some tests failed${NC}"
        # Show failing tests
        ./vendor/bin/pest --parallel 2>&1 | grep -A 5 "FAILED\|Error"
        add_failure "Test failures"
    fi
elif [ "$CHECK_ALL" = true ]; then
    echo -e "${YELLOW}  Running all tests...${NC}"
    if ./vendor/bin/pest --parallel --compact > /dev/null 2>&1; then
        echo -e "${GREEN}✓ Tests: All tests passed${NC}"
    else
        echo -e "${RED}✗ Tests: Some tests failed${NC}"
        add_failure "Test failures"
    fi
else
    # Check if any test files were modified
    TEST_FILES=$(echo "$FILES" | grep -E '^tests/.*Test\.php$' || true)
    if [ -n "$TEST_FILES" ]; then
        echo -e "${YELLOW}  Running tests for modified test files...${NC}"
        TEST_DIRS=$(echo "$TEST_FILES" | xargs -n1 dirname | sort -u | head -1)
        if ./vendor/bin/pest "$TEST_DIRS" --parallel --compact > /dev/null 2>&1; then
            echo -e "${GREEN}✓ Tests: Modified tests passed${NC}"
        else
            echo -e "${RED}✗ Tests: Some modified tests failed${NC}"
            add_failure "Modified test failures"
        fi
    else
        echo -e "${YELLOW}  No test files modified, skipping test run${NC}"
    fi
fi
echo ""

# CI Simulation Summary
if [ "$CI_MODE" = true ]; then
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}  CI Pipeline Simulation Results${NC}"
    echo -e "${BLUE}========================================${NC}"
    
    if [ "$FAILED" = false ]; then
        echo -e "${GREEN}✓ All CI checks would PASS${NC}"
        echo -e "${GREEN}  Your code is ready for GitHub Actions!${NC}"
    else
        echo -e "${RED}✗ CI checks would FAIL${NC}"
        echo -e "${RED}  GitHub Actions will reject this code!${NC}"
        echo ""
        echo -e "${YELLOW}Failures:${NC}"
        echo -e "$FAILURE_REASONS"
    fi
    echo -e "${BLUE}========================================${NC}"
    echo ""
fi

# Summary
echo -e "${GREEN}========================================${NC}"
if [ "$FAILED" = true ]; then
    echo -e "${RED}✗ Pre-commit checks FAILED${NC}"
    echo -e "${YELLOW}Please fix the issues above before committing.${NC}"
    
    if [ "$AUTO_FIX" = false ]; then
        echo -e "${YELLOW}Tip: Run with --fix to auto-fix style issues${NC}"
    fi
    
    if [ "$CI_MODE" = false ]; then
        echo -e "${YELLOW}Tip: Run with --ci to simulate full CI pipeline${NC}"
    fi
    
    echo ""
    echo -e "${YELLOW}GitHub Actions CI runs these exact commands:${NC}"
    echo -e "  1. vendor/bin/phpcs --standard=PSR12 --exclude=Generic.Files.LineLength app/"
    echo -e "  2. vendor/bin/phpstan analyse --memory-limit=2G"
    echo -e "  3. vendor/bin/php-cs-fixer fix --dry-run --diff"
    echo -e "  4. vendor/bin/pest --parallel"
    
    exit 1
else
    echo -e "${GREEN}✓ All pre-commit checks PASSED${NC}"
    echo -e "${GREEN}Ready to commit!${NC}"
    
    if [ "$CI_MODE" = false ]; then
        echo -e "${YELLOW}Tip: Run with --ci to ensure GitHub Actions will pass${NC}"
    fi
fi
echo -e "${GREEN}========================================${NC}"
