#!/bin/bash
#
# Pre-Commit Quality Check Script
# Runs all code quality tools in the correct order
#
# Usage: ./bin/pre-commit-check.sh [--fix] [--all]
#        --fix: Auto-fix issues where possible
#        --all: Check all files (default: only modified files)

set -e

# Get project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

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

# 1. PHP CS Fixer - Fix style issues first if auto-fix enabled
echo -e "${YELLOW}[1/4] Running PHP CS Fixer...${NC}"
if [ "$AUTO_FIX" = true ]; then
    # For multiple files, we need to add --config parameter
    if [ "$CHECK_ALL" = true ]; then
        ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php app/ || true
        ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php tests/ || true
    else
        echo "$FILES" | xargs ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php || true
    fi
    echo -e "${GREEN}✓ PHP CS Fixer: Fixed style issues${NC}"
else
    if [ "$CHECK_ALL" = true ]; then
        FIX_OUTPUT=$(./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php app/ tests/ --dry-run --diff 2>&1 || true)
    else
        FIX_OUTPUT=$(echo "$FILES" | xargs ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --dry-run --diff 2>&1 || true)
    fi
    
    if echo "$FIX_OUTPUT" | grep -q "Found 0 of"; then
        echo -e "${GREEN}✓ PHP CS Fixer: No issues found${NC}"
    else
        echo -e "${RED}✗ PHP CS Fixer: Issues found (run with --fix to auto-fix)${NC}"
        echo "$FIX_OUTPUT"
        FAILED=true
    fi
fi
echo ""

# 2. PHP CodeSniffer - Check PSR-12 compliance
echo -e "${YELLOW}[2/4] Running PHP CodeSniffer (PSR-12)...${NC}"

# Use project's phpcs.xml config if it exists
PHPCS_STANDARD="PSR12"
if [ -f "${PROJECT_ROOT}/phpcs.xml" ]; then
    PHPCS_STANDARD="phpcs.xml"
fi

if [ "$AUTO_FIX" = true ]; then
    # Try to auto-fix with PHPCBF
    if [ "$CHECK_ALL" = true ]; then
        ./vendor/bin/phpcbf --standard="$PHPCS_STANDARD" app/ tests/ 2>/dev/null || true
    else
        echo "$FILES" | xargs ./vendor/bin/phpcbf --standard="$PHPCS_STANDARD" 2>/dev/null || true
    fi
fi

# Run PHPCS
if [ "$CHECK_ALL" = true ]; then
    PHPCS_OUTPUT=$(./vendor/bin/phpcs --standard="$PHPCS_STANDARD" --report=summary app/ tests/ 2>&1 || true)
else
    PHPCS_OUTPUT=$(echo "$FILES" | xargs ./vendor/bin/phpcs --standard="$PHPCS_STANDARD" --report=summary 2>&1 || true)
fi

if echo "$PHPCS_OUTPUT" | grep -q "0 ERRORS AND 0 WARNINGS"; then
    echo -e "${GREEN}✓ PHPCS: PSR-12 compliant${NC}"
else
    echo -e "${RED}✗ PHPCS: PSR-12 violations found${NC}"
    echo "$PHPCS_OUTPUT"
    
    # Check if only warnings (not errors)
    if [ "$CHECK_ALL" = true ]; then
        ERROR_CHECK=$(./vendor/bin/phpcs --standard="$PHPCS_STANDARD" -n app/ tests/ 2>&1 || true)
    else
        ERROR_CHECK=$(echo "$FILES" | xargs ./vendor/bin/phpcs --standard="$PHPCS_STANDARD" -n 2>&1 || true)
    fi
    
    if [ -z "$ERROR_CHECK" ] || echo "$ERROR_CHECK" | grep -q "0 ERRORS"; then
        echo -e "${YELLOW}  Note: Only warnings found, not blocking commit${NC}"
    else
        FAILED=true
    fi
    
    if [ "$AUTO_FIX" = false ]; then
        echo -e "${YELLOW}  Tip: Run with --fix to auto-fix some issues${NC}"
    fi
fi
echo ""

# 3. PHPStan - Static analysis (on modified files only unless --all)
echo -e "${YELLOW}[3/4] Running PHPStan (Level 5)...${NC}"

# Check for unused traits and trivial assertions using bleeding edge rules
PHPSTAN_CONFIG="phpstan.neon"

# Create a temporary PHPStan config with bleeding edge rules if checking tests
if [ "$CHECK_ALL" = true ] || echo "$FILES" | grep -q "^tests/"; then
    # Create temporary config with bleeding edge rules for better test coverage
    cat > /tmp/phpstan-ci.neon << 'EOCONFIG'
includes:
    - phpstan.neon

parameters:
    # Additional rules for CI to catch more issues
    reportUnmatchedIgnoredErrors: true
    checkTooWideReturnTypesInProtectedAndPublicMethods: true
    checkUninitializedProperties: true
    checkMissingCallableSignature: true
    
rules:
    # Detect trivial conditions and assertions
    - PHPStan\Rules\Comparison\BooleanAndConstantConditionRule
    - PHPStan\Rules\Comparison\BooleanOrConstantConditionRule
    
    # Ensure traits are used
    - PHPStan\Rules\Classes\UnusedConstructorParametersRule
EOCONFIG
    PHPSTAN_CONFIG="/tmp/phpstan-ci.neon"
fi

if [ "$CHECK_ALL" = true ]; then
    PHPSTAN_OUTPUT=$(XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ ./vendor/bin/phpstan analyse --configuration=$PHPSTAN_CONFIG --memory-limit=2G --no-progress 2>&1 || true)
else
    # PHPStan with specific files
    PHPSTAN_OUTPUT=$(echo "$FILES" | XDEBUG_MODE=off TMPDIR=/tmp/phpstan-$$ xargs ./vendor/bin/phpstan analyse --configuration=$PHPSTAN_CONFIG --memory-limit=2G --no-progress 2>&1 || true)
fi

# Clean up temporary config
[ -f "/tmp/phpstan-ci.neon" ] && rm -f /tmp/phpstan-ci.neon

if echo "$PHPSTAN_OUTPUT" | grep -q "\[OK\] No errors"; then
    echo -e "${GREEN}✓ PHPStan: No issues found${NC}"
else
    # Check for specific issues that CI catches
    if echo "$PHPSTAN_OUTPUT" | grep -q "assertTrue() with true will always evaluate to true"; then
        echo -e "${RED}✗ PHPStan: Trivial assertion detected (assertTrue(true))${NC}"
        FAILED=true
    fi
    if echo "$PHPSTAN_OUTPUT" | grep -q "Trait.*is not used"; then
        echo -e "${RED}✗ PHPStan: Unused trait detected${NC}"
        FAILED=true
    fi
    if ! [ "$FAILED" = true ]; then
        echo -e "${RED}✗ PHPStan: Issues found${NC}"
        FAILED=true
    fi
    echo "$PHPSTAN_OUTPUT"
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
    # Check if any test files were modified
    TEST_FILES=$(echo "$FILES" | grep -E '^tests/.*Test\.php$' || true)
    if [ -n "$TEST_FILES" ]; then
        echo -e "${YELLOW}  Running tests for modified test files...${NC}"
        # Extract unique directories from test files
        TEST_DIRS=$(echo "$TEST_FILES" | xargs -n1 dirname | sort -u | head -1)
        if [ -n "$TEST_DIRS" ]; then
            # Run tests for the first directory (Pest with --parallel only accepts single path)
            if ./vendor/bin/pest "$TEST_DIRS" --parallel --compact > /dev/null 2>&1; then
                echo -e "${GREEN}✓ Tests: Modified tests passed${NC}"
            else
                echo -e "${RED}✗ Tests: Some modified tests failed${NC}"
                ./vendor/bin/pest "$TEST_DIRS" --parallel
                FAILED=true
            fi
        fi
    else
        echo -e "${YELLOW}  No test files modified, skipping test run${NC}"
    fi
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
    echo -e "${YELLOW}Note: CI runs PHPStan on both app/ and tests/ directories.${NC}"
    echo -e "${YELLOW}      Ensure your changes pass all quality checks.${NC}"
    exit 1
else
    echo -e "${GREEN}✓ All pre-commit checks PASSED${NC}"
    echo -e "${GREEN}Ready to commit!${NC}"
fi
echo -e "${GREEN}========================================${NC}"
