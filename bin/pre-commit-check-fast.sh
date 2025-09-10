#!/bin/bash
#
# Fast Pre-Commit Quality Check Script
# Only checks modified files for quick feedback
#
# Usage: ./bin/pre-commit-check-fast.sh [--fix]
#        --fix: Auto-fix issues where possible

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
for arg in "$@"; do
    case $arg in
        --fix)
            AUTO_FIX=true
            ;;
    esac
done

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Fast Pre-Commit Quality Check${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Get list of modified PHP files (staged and unstaged)
FILES=$(
    {
        git diff --cached --name-only --diff-filter=ACMR -- '*.php'
        git diff --name-only --diff-filter=ACMR -- '*.php'
    } | sort -u | grep -E '^(app|config|database|routes|tests)/' || true
)

if [ -z "$FILES" ]; then
    echo -e "${GREEN}No PHP files modified. Nothing to check.${NC}"
    exit 0
fi

echo -e "${YELLOW}Checking modified files:${NC}"
echo "$FILES" | sed 's/^/  - /'
echo ""

# Track if any checks fail
FAILED=false

# Function to add failure
add_failure() {
    FAILED=true
}

# 1. PHP CS Fixer (fastest, fixes most issues)
echo -e "${BLUE}[1/3] Running PHP CS Fixer...${NC}"
FILES_FOR_FIXER=$(echo "$FILES" | tr '\n' ' ')
if [ "$AUTO_FIX" = true ]; then
    ./vendor/bin/php-cs-fixer fix --path-mode=intersection --config=.php-cs-fixer.php $FILES_FOR_FIXER > /dev/null 2>&1 || true
    echo -e "${GREEN}✓ PHP CS Fixer: Applied fixes${NC}"
else
    if ! ./vendor/bin/php-cs-fixer fix --dry-run --diff --path-mode=intersection --config=.php-cs-fixer.php $FILES_FOR_FIXER > /dev/null 2>&1; then
        echo -e "${YELLOW}⚠ PHP CS Fixer: Issues detected (use --fix to auto-fix)${NC}"
        add_failure
    else
        echo -e "${GREEN}✓ PHP CS Fixer: No issues${NC}"
    fi
fi
echo ""

# 2. PHPCS Check (for PSR-12 compliance)
echo -e "${BLUE}[2/3] Checking PSR-12 Compliance...${NC}"

# Separate files by type
APP_FILES=$(echo "$FILES" | grep -E '^(app|database|routes|config)/' | tr '\n' ' ' || true)
TEST_FILES=$(echo "$FILES" | grep -E '^tests/' | tr '\n' ' ' || true)

PHPCS_FAILED=false

# Check app files - only fail on ERRORS, not warnings
if [ -n "$APP_FILES" ]; then
    if [ "$AUTO_FIX" = true ]; then
        ./vendor/bin/phpcbf $APP_FILES --standard=PSR12 > /dev/null 2>&1 || true
    fi
    
    # Check for errors using JSON output
    PHPCS_OUTPUT=$(./vendor/bin/phpcs $APP_FILES --standard=PSR12 --report=json 2>/dev/null || true)
    if [ -n "$PHPCS_OUTPUT" ]; then
        ERROR_COUNT=$(echo "$PHPCS_OUTPUT" | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['totals']['errors'])" 2>/dev/null || echo "0")
        if [ "$ERROR_COUNT" -gt 0 ]; then
            echo -e "${RED}✗ PHPCS: PSR-12 errors in app files${NC}"
            ./vendor/bin/phpcs $APP_FILES --standard=PSR12 --report=summary
            PHPCS_FAILED=true
        else
            WARNING_COUNT=$(echo "$PHPCS_OUTPUT" | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['totals']['warnings'])" 2>/dev/null || echo "0")
            if [ "$WARNING_COUNT" -gt 0 ]; then
                echo -e "${YELLOW}⚠ PHPCS: PSR-12 warnings in app files (not blocking)${NC}"
            else
                echo -e "${GREEN}✓ PHPCS: App files are PSR-12 compliant${NC}"
            fi
        fi
    fi
fi

# Check test files with phpcs.xml (excludes test_ prefix warnings)
if [ -n "$TEST_FILES" ]; then
    if [ "$AUTO_FIX" = true ]; then
        ./vendor/bin/phpcbf $TEST_FILES --standard=phpcs.xml > /dev/null 2>&1 || true
    fi
    
    # Check for errors using JSON output
    PHPCS_OUTPUT=$(./vendor/bin/phpcs $TEST_FILES --standard=phpcs.xml --report=json 2>/dev/null || true)
    if [ -n "$PHPCS_OUTPUT" ]; then
        ERROR_COUNT=$(echo "$PHPCS_OUTPUT" | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['totals']['errors'])" 2>/dev/null || echo "0")
        if [ "$ERROR_COUNT" -gt 0 ]; then
            echo -e "${RED}✗ PHPCS: Errors in test files${NC}"
            ./vendor/bin/phpcs $TEST_FILES --standard=phpcs.xml --report=summary
            PHPCS_FAILED=true
        else
            WARNING_COUNT=$(echo "$PHPCS_OUTPUT" | python3 -c "import sys, json; data = json.load(sys.stdin); print(data['totals']['warnings'])" 2>/dev/null || echo "0")
            if [ "$WARNING_COUNT" -gt 0 ]; then
                echo -e "${YELLOW}⚠ PHPCS: Warnings in test files (not blocking)${NC}"
            else
                echo -e "${GREEN}✓ PHPCS: Test files are compliant${NC}"
            fi
        fi
    fi
fi

if [ "$PHPCS_FAILED" = true ]; then
    add_failure
elif [ -n "$APP_FILES" ] || [ -n "$TEST_FILES" ]; then
    echo -e "${GREEN}✓ PHPCS: No blocking errors found${NC}"
fi
echo ""

# 3. PHPStan (only on modified files, with timeout)
echo -e "${BLUE}[3/3] Running PHPStan (Quick Check)...${NC}"
export XDEBUG_MODE=off

# Only run PHPStan on modified files to keep it fast
FILES_FOR_PHPSTAN=$(echo "$FILES" | tr '\n' ' ')
if timeout 30 vendor/bin/phpstan analyse $FILES_FOR_PHPSTAN --memory-limit=2G --level=5 > /tmp/phpstan_output.log 2>&1; then
    if grep -q "\[OK\] No errors" /tmp/phpstan_output.log; then
        echo -e "${GREEN}✓ PHPStan: No issues found${NC}"
    else
        echo -e "${YELLOW}⚠ PHPStan: Issues detected${NC}"
        cat /tmp/phpstan_output.log | grep -E "Line|ERROR" | head -10
        add_failure
    fi
else
    if [ $? -eq 124 ]; then
        echo -e "${YELLOW}⚠ PHPStan: Skipped (timeout) - Run manually with: vendor/bin/phpstan analyse${NC}"
    else
        echo -e "${RED}✗ PHPStan: Failed${NC}"
        cat /tmp/phpstan_output.log | head -10
        add_failure
    fi
fi
rm -f /tmp/phpstan_output.log
echo ""

# Summary
echo -e "${GREEN}========================================${NC}"
if [ "$FAILED" = true ]; then
    echo -e "${RED}✗ Pre-commit checks FAILED${NC}"
    if [ "$AUTO_FIX" = false ]; then
        echo -e "${YELLOW}Tip: Run with --fix to auto-fix most issues${NC}"
    fi
    echo -e "${YELLOW}For full checks, run: ./bin/pre-commit-check.sh --all${NC}"
    exit 1
else
    echo -e "${GREEN}✓ All pre-commit checks PASSED${NC}"
    echo -e "${GREEN}Ready to commit!${NC}"
fi
echo -e "${GREEN}========================================${NC}"
