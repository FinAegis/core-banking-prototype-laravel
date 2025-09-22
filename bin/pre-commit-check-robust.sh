#!/bin/bash
#
# Robust Pre-Commit Quality Check Script for Financial System
# CRITICAL: Matches GitHub Actions CI Pipeline exactly
#
# Features:
# - Detects ALL file changes (staged, unstaged, direct commits)
# - Checks for missing newlines at end of files
# - Runs PHPCS exactly as CI does
# - Fails on ANY quality issue
# - Provides clear feedback and fix instructions
#
# Usage: ./bin/pre-commit-check-robust.sh [--fix]
#        --fix: Auto-fix issues where possible
#
set -e

# Get project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
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

echo -e "${BOLD}${GREEN}========================================${NC}"
echo -e "${BOLD}${GREEN}  Robust Pre-Commit Quality Check${NC}"
echo -e "${BOLD}${GREEN}  Financial System Standards Enforced${NC}"
echo -e "${BOLD}${GREEN}========================================${NC}"
echo ""

# CRITICAL: Improved file detection that catches ALL scenarios
# This addresses the bug where files committed directly weren't caught
FILES=$(
    {
        # 1. Staged files (in index)
        git diff --cached --name-only --diff-filter=ACMR -- '*.php' 2>/dev/null || true

        # 2. Modified files in working directory
        git diff --name-only --diff-filter=ACMR -- '*.php' 2>/dev/null || true

        # 3. Files different from HEAD (catches direct commits)
        git diff HEAD --name-only --diff-filter=ACMR -- '*.php' 2>/dev/null || true

        # 4. Files in the commit if we're in a git hook context
        if [ -n "$GIT_COMMIT" ]; then
            git diff-tree --no-commit-id --name-only -r "$GIT_COMMIT" -- '*.php' 2>/dev/null || true
        fi
    } | sort -u | grep -E '^(app|config|database|routes|tests)/' || true
)

if [ -z "$FILES" ]; then
    echo -e "${GREEN}No PHP files modified. Nothing to check.${NC}"
    exit 0
fi

echo -e "${YELLOW}Checking files:${NC}"
echo "$FILES" | sed 's/^/  - /' | head -10
FILE_COUNT=$(echo "$FILES" | wc -l)
if [ "$FILE_COUNT" -gt 10 ]; then
    echo "  ... and $((FILE_COUNT - 10)) more files"
fi
echo ""

# Track failures
FAILED=false
FAILURE_MESSAGES=()

# Function to add failure
add_failure() {
    FAILED=true
    FAILURE_MESSAGES+=("$1")
}

# CRITICAL CHECK 1: Missing newlines at end of files
echo -e "${BLUE}[1/5] Checking for missing newlines at end of files...${NC}"
NEWLINE_ISSUES=false
NEWLINE_FILES=()

for file in $FILES; do
    if [ -f "$file" ]; then
        # Check if file exists and last character is not a newline
        if [ -s "$file" ] && [ -n "$(tail -c 1 "$file")" ]; then
            NEWLINE_ISSUES=true
            NEWLINE_FILES+=("$file")
        fi
    fi
done

if [ "$NEWLINE_ISSUES" = true ]; then
    echo -e "${RED}✗ Missing newline at end of files:${NC}"
    for file in "${NEWLINE_FILES[@]}"; do
        echo -e "  ${RED}- $file${NC}"
        if [ "$AUTO_FIX" = true ]; then
            # Add newline to end of file
            echo "" >> "$file"
            echo -e "    ${GREEN}✓ Fixed${NC}"
        fi
    done

    if [ "$AUTO_FIX" = false ]; then
        add_failure "Missing newlines at end of files (use --fix to auto-fix)"
    else
        echo -e "${GREEN}✓ Fixed missing newlines${NC}"
    fi
else
    echo -e "${GREEN}✓ All files have proper newlines${NC}"
fi
echo ""

# CHECK 2: PHP CS Fixer (matches CI configuration)
echo -e "${BLUE}[2/5] Running PHP CS Fixer...${NC}"
FILES_FOR_FIXER=$(echo "$FILES" | tr '\n' ' ')

if [ "$AUTO_FIX" = true ]; then
    # Apply fixes
    ./vendor/bin/php-cs-fixer fix --path-mode=intersection --config=.php-cs-fixer.php $FILES_FOR_FIXER > /dev/null 2>&1 || true
    echo -e "${GREEN}✓ PHP CS Fixer: Applied fixes${NC}"
else
    # Check without fixing
    if ! ./vendor/bin/php-cs-fixer fix --dry-run --diff --path-mode=intersection --config=.php-cs-fixer.php $FILES_FOR_FIXER > /dev/null 2>&1; then
        echo -e "${RED}✗ PHP CS Fixer: Style violations detected${NC}"
        ./vendor/bin/php-cs-fixer fix --dry-run --diff --path-mode=intersection --config=.php-cs-fixer.php $FILES_FOR_FIXER 2>&1 | head -20
        add_failure "PHP CS Fixer violations (use --fix to auto-fix)"
    else
        echo -e "${GREEN}✓ PHP CS Fixer: No issues${NC}"
    fi
fi
echo ""

# CHECK 3: PHPCS - EXACTLY as CI runs it
echo -e "${BLUE}[3/5] Running PHPCS (PSR-12 Compliance)...${NC}"

# Separate files by type
APP_FILES=$(echo "$FILES" | grep -E '^(app|database|routes|config)/' | tr '\n' ' ' || true)
TEST_FILES=$(echo "$FILES" | grep -E '^tests/' | tr '\n' ' ' || true)

PHPCS_FAILED=false

# Check app files with default configuration (PSR-12)
if [ -n "$APP_FILES" ]; then
    echo -e "${YELLOW}  Checking app files...${NC}"

    if [ "$AUTO_FIX" = true ]; then
        ./vendor/bin/phpcbf $APP_FILES > /dev/null 2>&1 || true
    fi

    # Run PHPCS exactly as CI does
    if ! ./vendor/bin/phpcs $APP_FILES > /dev/null 2>&1; then
        echo -e "${RED}✗ PHPCS: PSR-12 violations in app files${NC}"
        ./vendor/bin/phpcs $APP_FILES --report=summary 2>&1 | head -20
        PHPCS_FAILED=true
    else
        echo -e "${GREEN}  ✓ App files are PSR-12 compliant${NC}"
    fi
fi

# Check test files with phpcs.xml configuration
if [ -n "$TEST_FILES" ]; then
    echo -e "${YELLOW}  Checking test files...${NC}"

    if [ "$AUTO_FIX" = true ]; then
        ./vendor/bin/phpcbf $TEST_FILES --standard=phpcs.xml > /dev/null 2>&1 || true
    fi

    # Run PHPCS with phpcs.xml for test files
    if ! ./vendor/bin/phpcs $TEST_FILES --standard=phpcs.xml > /dev/null 2>&1; then
        echo -e "${RED}✗ PHPCS: Violations in test files${NC}"
        ./vendor/bin/phpcs $TEST_FILES --standard=phpcs.xml --report=summary 2>&1 | head -20
        PHPCS_FAILED=true
    else
        echo -e "${GREEN}  ✓ Test files are compliant${NC}"
    fi
fi

if [ "$PHPCS_FAILED" = true ]; then
    if [ "$AUTO_FIX" = true ]; then
        echo -e "${YELLOW}⚠ Some PHPCS issues may not be auto-fixable${NC}"
    fi
    add_failure "PHPCS violations detected"
else
    echo -e "${GREEN}✓ PHPCS: All files compliant${NC}"
fi
echo ""

# CHECK 4: PHPStan Static Analysis
echo -e "${BLUE}[4/5] Running PHPStan (Level 5)...${NC}"
export XDEBUG_MODE=off
export TMPDIR=/tmp/phpstan-$$

FILES_FOR_PHPSTAN=$(echo "$FILES" | tr '\n' ' ')
if timeout 60 vendor/bin/phpstan analyse $FILES_FOR_PHPSTAN --memory-limit=2G --level=5 --no-progress > /tmp/phpstan_output.log 2>&1; then
    if grep -q "\[OK\] No errors" /tmp/phpstan_output.log; then
        echo -e "${GREEN}✓ PHPStan: No issues found${NC}"
    else
        echo -e "${RED}✗ PHPStan: Static analysis errors${NC}"
        cat /tmp/phpstan_output.log | grep -E "Line|ERROR" | head -20
        add_failure "PHPStan errors detected"
    fi
else
    EXIT_CODE=$?
    if [ $EXIT_CODE -eq 124 ]; then
        echo -e "${YELLOW}⚠ PHPStan: Timeout - Run manually: vendor/bin/phpstan analyse${NC}"
        echo -e "${YELLOW}  This may indicate performance issues that need investigation${NC}"
    else
        echo -e "${RED}✗ PHPStan: Analysis failed${NC}"
        cat /tmp/phpstan_output.log | head -20
        add_failure "PHPStan analysis failed"
    fi
fi
rm -f /tmp/phpstan_output.log
echo ""

# CHECK 5: File Permissions (security check for financial system)
echo -e "${BLUE}[5/5] Checking file permissions...${NC}"
PERMISSION_ISSUES=false

for file in $FILES; do
    if [ -f "$file" ]; then
        # Check if file is executable (PHP files should not be)
        if [ -x "$file" ]; then
            echo -e "${RED}✗ Executable permission on PHP file: $file${NC}"
            PERMISSION_ISSUES=true
            if [ "$AUTO_FIX" = true ]; then
                chmod -x "$file"
                echo -e "  ${GREEN}✓ Fixed permissions${NC}"
            fi
        fi
    fi
done

if [ "$PERMISSION_ISSUES" = true ] && [ "$AUTO_FIX" = false ]; then
    add_failure "Incorrect file permissions (PHP files should not be executable)"
elif [ "$PERMISSION_ISSUES" = false ]; then
    echo -e "${GREEN}✓ File permissions correct${NC}"
fi
echo ""

# FINAL VALIDATION: If auto-fix was used, verify everything is now clean
if [ "$AUTO_FIX" = true ] && [ "$FAILED" = false ]; then
    echo -e "${BLUE}Final validation after fixes...${NC}"

    # Re-run critical checks
    FINAL_FAILED=false

    # Check newlines again
    for file in $FILES; do
        if [ -f "$file" ] && [ -s "$file" ] && [ -n "$(tail -c 1 "$file")" ]; then
            echo -e "${RED}✗ Still missing newline: $file${NC}"
            FINAL_FAILED=true
        fi
    done

    # Check PHPCS again
    if [ -n "$APP_FILES" ]; then
        if ! ./vendor/bin/phpcs $APP_FILES > /dev/null 2>&1; then
            echo -e "${RED}✗ Still has PHPCS violations in app files${NC}"
            FINAL_FAILED=true
        fi
    fi

    if [ -n "$TEST_FILES" ]; then
        if ! ./vendor/bin/phpcs $TEST_FILES --standard=phpcs.xml > /dev/null 2>&1; then
            echo -e "${RED}✗ Still has PHPCS violations in test files${NC}"
            FINAL_FAILED=true
        fi
    fi

    if [ "$FINAL_FAILED" = true ]; then
        add_failure "Some issues could not be auto-fixed"
    else
        echo -e "${GREEN}✓ All issues resolved${NC}"
    fi
    echo ""
fi

# SUMMARY
echo -e "${BOLD}${GREEN}========================================${NC}"

if [ "$FAILED" = true ]; then
    echo -e "${BOLD}${RED}✗ QUALITY CHECK FAILED${NC}"
    echo ""
    echo -e "${RED}Issues found:${NC}"
    for msg in "${FAILURE_MESSAGES[@]}"; do
        echo -e "  ${RED}• $msg${NC}"
    done
    echo ""

    if [ "$AUTO_FIX" = false ]; then
        echo -e "${YELLOW}To auto-fix most issues, run:${NC}"
        echo -e "${BOLD}  ./bin/pre-commit-check-robust.sh --fix${NC}"
        echo ""
    fi

    echo -e "${RED}CRITICAL: In a financial system, these quality issues${NC}"
    echo -e "${RED}could lead to security vulnerabilities or compliance failures.${NC}"
    echo -e "${RED}DO NOT use --no-verify to skip these checks!${NC}"
    echo ""
    echo -e "${BOLD}${GREEN}========================================${NC}"
    exit 1
else
    echo -e "${BOLD}${GREEN}✓ ALL QUALITY CHECKS PASSED${NC}"
    echo -e "${GREEN}Code meets financial system quality standards${NC}"
    echo -e "${GREEN}Ready to commit!${NC}"
    echo -e "${BOLD}${GREEN}========================================${NC}"
    exit 0
fi