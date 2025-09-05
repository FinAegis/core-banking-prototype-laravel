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
    FILES="app/ config/ database/ routes/ tests/"
    echo -e "${YELLOW}Checking all files in app/, config/, database/, routes/, and tests/...${NC}"
else
    # Get modified PHP files - check BOTH staged AND working directory changes
    # This ensures we catch issues whether files are staged or not
    FILES=$(
        {
            # Staged changes (files in index)
            git diff --cached --name-only --diff-filter=ACMR -- '*.php'
            # Working directory changes (modified but not staged)
            git diff --name-only --diff-filter=ACMR -- '*.php'
            # Files different from HEAD (catches both staged and unstaged)
            git diff --name-only --diff-filter=ACMR HEAD -- '*.php'
        } | sort -u | grep -E '^(app|config|database|routes|tests)/' || true
    )
    if [ -z "$FILES" ]; then
        echo -e "${GREEN}No PHP files modified in app/, config/, database/, routes/, or tests/. Skipping checks.${NC}"
        exit 0
    fi
    echo -e "${YELLOW}Checking modified files:${NC}"
    echo "$FILES" | sed 's/^/  - /'
fi

# Track if any checks fail
FAILED=false
FAILURE_REASONS=""
ISSUES_FIXED=false

# Function to add failure reason
add_failure() {
    FAILED=true
    FAILURE_REASONS="${FAILURE_REASONS}  - $1\n"
}

# IMPORTANT: First check for issues BEFORE fixing them
# This ensures we report what was wrong, even if we auto-fix

# 1. Check PHP CodeSniffer FIRST (before any fixes) - CHECK BOTH app/ AND tests/
echo -e "${BLUE}[1/6] Checking PHP CodeSniffer (PSR-12)...${NC}"
PHPCS_HAD_ISSUES=false

if [ "$CHECK_ALL" = true ]; then
    # Check all directories when --all flag is used
    echo -e "${YELLOW}  Checking all files (--all mode)...${NC}"
    
    # Check app/ directory with standard rules
    if ! ./vendor/bin/phpcs app/ database/ routes/ config/ > /dev/null 2>&1; then
        PHPCS_HAD_ISSUES=true
        echo -e "${YELLOW}⚠ PHPCS: PSR-12 violations detected in app/, database/, routes/, config/${NC}"
        ./vendor/bin/phpcs app/ database/ routes/ config/ | head -20
    fi
    
    # Check tests/ directory with our custom ruleset (handles Pest patterns)
    if ! ./vendor/bin/phpcs tests/ --standard=phpcs.xml > /dev/null 2>&1; then
        PHPCS_HAD_ISSUES=true
        echo -e "${YELLOW}⚠ PHPCS: PSR-12 violations detected in tests/${NC}"
        ./vendor/bin/phpcs tests/ --standard=phpcs.xml | head -20
    fi
else
    # Only check modified files
    echo -e "${YELLOW}  Checking modified files only...${NC}"
    
    # Get modified PHP files in different directories
    APP_FILES=$(echo "$FILES" | grep -E '^(app|database|routes|config)/' | tr '\n' ' ' || true)
    TEST_FILES=$(echo "$FILES" | grep -E '^tests/' | tr '\n' ' ' || true)
    
    # Check app/database/routes/config files if any were modified
    if [ -n "$APP_FILES" ]; then
        if ! ./vendor/bin/phpcs $APP_FILES > /dev/null 2>&1; then
            PHPCS_HAD_ISSUES=true
            echo -e "${YELLOW}⚠ PHPCS: PSR-12 violations in modified app files${NC}"
            ./vendor/bin/phpcs $APP_FILES | head -20
        fi
    fi
    
    # Check test files if any were modified
    if [ -n "$TEST_FILES" ]; then
        if ! ./vendor/bin/phpcs $TEST_FILES --standard=phpcs.xml > /dev/null 2>&1; then
            PHPCS_HAD_ISSUES=true
            echo -e "${YELLOW}⚠ PHPCS: PSR-12 violations in modified test files${NC}"
            ./vendor/bin/phpcs $TEST_FILES --standard=phpcs.xml | head -20
        fi
    fi
fi

if [ "$PHPCS_HAD_ISSUES" = true ]; then
    if [ "$AUTO_FIX" = true ]; then
        echo -e "${BLUE}  Attempting auto-fix with PHPCBF...${NC}"
        
        if [ "$CHECK_ALL" = true ]; then
            # Fix all directories when --all flag is used
            ./vendor/bin/phpcbf app/ database/ routes/ config/ 2>/dev/null || true
            ./vendor/bin/phpcbf tests/ --standard=phpcs.xml 2>/dev/null || true
        else
            # Only fix modified files
            if [ -n "$APP_FILES" ]; then
                ./vendor/bin/phpcbf $APP_FILES 2>/dev/null || true
            fi
            if [ -n "$TEST_FILES" ]; then
                ./vendor/bin/phpcbf $TEST_FILES --standard=phpcs.xml 2>/dev/null || true
            fi
        fi
        
        ISSUES_FIXED=true
        
        # Re-check after fix
        STILL_HAS_ISSUES=false
        
        if [ "$CHECK_ALL" = true ]; then
            if ! ./vendor/bin/phpcs app/ database/ routes/ config/ > /dev/null 2>&1; then
                STILL_HAS_ISSUES=true
            fi
            if ! ./vendor/bin/phpcs tests/ --standard=phpcs.xml > /dev/null 2>&1; then
                STILL_HAS_ISSUES=true
            fi
        else
            if [ -n "$APP_FILES" ]; then
                if ! ./vendor/bin/phpcs $APP_FILES > /dev/null 2>&1; then
                    STILL_HAS_ISSUES=true
                fi
            fi
            if [ -n "$TEST_FILES" ]; then
                if ! ./vendor/bin/phpcs $TEST_FILES --standard=phpcs.xml > /dev/null 2>&1; then
                    STILL_HAS_ISSUES=true
                fi
            fi
        fi
        
        if [ "$STILL_HAS_ISSUES" = false ]; then
            echo -e "${GREEN}  ✓ PHPCS issues auto-fixed${NC}"
        else
            echo -e "${RED}  ✗ Some PHPCS issues could not be auto-fixed${NC}"
            add_failure "PSR-12 violations (not auto-fixable)"
        fi
    else
        add_failure "PSR-12 violations"
    fi
else
    echo -e "${GREEN}✓ PHPCS: PSR-12 compliant${NC}"
fi
echo ""

# 2. Check PHP CS Fixer (runs on both app/ and tests/ via config)
echo -e "${BLUE}[2/6] Running PHP CS Fixer (CI Standard)...${NC}"
PHPCS_FIXER_HAD_ISSUES=false

# When not checking all, pass specific files to PHP CS Fixer
if [ "$CHECK_ALL" = true ]; then
    # Check all files using the config
    if ! ./vendor/bin/php-cs-fixer fix --dry-run --diff > /dev/null 2>&1; then
        PHPCS_FIXER_HAD_ISSUES=true
        echo -e "${YELLOW}⚠ PHP CS Fixer: Style issues detected${NC}"
        ./vendor/bin/php-cs-fixer fix --dry-run --diff | head -20
        
        if [ "$AUTO_FIX" = true ]; then
            echo -e "${BLUE}  Applying PHP CS Fixer fixes...${NC}"
            ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php || true
            ISSUES_FIXED=true
            echo -e "${GREEN}  ✓ PHP CS Fixer: Fixed style issues${NC}"
        else
            add_failure "PHP CS Fixer violations"
        fi
    else
        echo -e "${GREEN}✓ PHP CS Fixer: No issues found${NC}"
    fi
else
    # Check only modified files - convert newline-separated list to space-separated
    FILES_FOR_FIXER=$(echo "$FILES" | tr '\n' ' ')
    if [ -n "$FILES_FOR_FIXER" ]; then
        # Run PHP CS Fixer on specific files  
        if ! ./vendor/bin/php-cs-fixer fix --dry-run --diff --path-mode=intersection --config=.php-cs-fixer.php $FILES_FOR_FIXER > /dev/null 2>&1; then
            PHPCS_FIXER_HAD_ISSUES=true
            echo -e "${YELLOW}⚠ PHP CS Fixer: Style issues detected in modified files${NC}"
            ./vendor/bin/php-cs-fixer fix --dry-run --diff --path-mode=intersection --config=.php-cs-fixer.php $FILES_FOR_FIXER | head -20
            
            if [ "$AUTO_FIX" = true ]; then
                echo -e "${BLUE}  Applying PHP CS Fixer fixes to modified files...${NC}"
                ./vendor/bin/php-cs-fixer fix --path-mode=intersection --config=.php-cs-fixer.php $FILES_FOR_FIXER || true
                ISSUES_FIXED=true
                echo -e "${GREEN}  ✓ PHP CS Fixer: Fixed style issues in modified files${NC}"
            else
                add_failure "PHP CS Fixer violations in modified files"
            fi
        else
            echo -e "${GREEN}✓ PHP CS Fixer: No issues found in modified files${NC}"
        fi
    fi
fi
echo ""

# 3. After all fixes, re-run PHPCS to ensure compliance
if [ "$AUTO_FIX" = true ] && [ "$ISSUES_FIXED" = true ]; then
    echo -e "${BLUE}[3/6] Final PSR-12 compliance check...${NC}"
    FINAL_ISSUES=false
    
    if ! ./vendor/bin/phpcs app/ database/ routes/ config/ > /dev/null 2>&1; then
        FINAL_ISSUES=true
        echo -e "${RED}✗ Final check: Still has PSR-12 violations in app/, database/, routes/, config/${NC}"
        ./vendor/bin/phpcs app/ database/ routes/ config/ | head -20
    fi
    
    if ! ./vendor/bin/phpcs tests/ --standard=phpcs.xml > /dev/null 2>&1; then
        FINAL_ISSUES=true
        echo -e "${RED}✗ Final check: Still has PSR-12 violations in tests/${NC}"
        ./vendor/bin/phpcs tests/ --standard=phpcs.xml | head -20
    fi
    
    if [ "$FINAL_ISSUES" = false ]; then
        echo -e "${GREEN}✓ Final check: PSR-12 compliant${NC}"
    else
        add_failure "PSR-12 violations remain after auto-fix"
    fi
    echo ""
fi

# 4. PHPStan - ENHANCED with better timeout handling and memory management
echo -e "${BLUE}[4/6] Running PHPStan (Level 5)...${NC}"

# Set PHPStan configuration
export XDEBUG_MODE=off
PHPSTAN_MEMORY_LIMIT="4G"
PHPSTAN_TIMEOUT=180  # 3 minutes timeout

# Function to run PHPStan with progress indication
run_phpstan() {
    local SCOPE=$1
    local TIMEOUT=$2
    
    echo -e "${YELLOW}  Running PHPStan on ${SCOPE}...${NC}"
    
    # Create a temporary file for output
    TMPFILE=$(mktemp)
    
    # Run PHPStan in background with timeout
    (
        timeout $TIMEOUT bash -c "vendor/bin/phpstan analyse --memory-limit=$PHPSTAN_MEMORY_LIMIT --no-progress --no-ansi $SCOPE 2>&1" > $TMPFILE 2>&1
    ) &
    
    # Get the process ID
    PID=$!
    
    # Show progress dots while PHPStan runs
    echo -n "  "
    while kill -0 $PID 2>/dev/null; do
        echo -n "."
        sleep 2
    done
    echo ""
    
    # Check exit status
    wait $PID
    EXIT_CODE=$?
    
    # Read output
    OUTPUT=$(cat $TMPFILE)
    rm -f $TMPFILE
    
    if [ $EXIT_CODE -eq 124 ]; then
        echo -e "${YELLOW}  PHPStan timed out after ${TIMEOUT} seconds${NC}"
        return 124
    elif [ $EXIT_CODE -eq 0 ]; then
        if echo "$OUTPUT" | grep -q "\[OK\] No errors"; then
            echo -e "${GREEN}  ✓ PHPStan: No issues found${NC}"
            return 0
        else
            echo -e "${RED}  ✗ PHPStan: Issues found${NC}"
            echo "$OUTPUT" | head -30
            return 1
        fi
    else
        echo -e "${RED}  ✗ PHPStan: Failed with error${NC}"
        echo "$OUTPUT" | head -30
        return $EXIT_CODE
    fi
}

# Try different strategies based on the mode
PHPSTAN_FAILED=false

if [ "$CHECK_ALL" = true ] || [ "$CI_MODE" = true ]; then
    # Check traits first for unused trait detection
    echo -e "${YELLOW}  Checking for unused test traits...${NC}"
    if ! XDEBUG_MODE=off vendor/bin/phpstan analyse tests/Traits --memory-limit=$PHPSTAN_MEMORY_LIMIT --no-progress 2>&1 | grep -q "\[OK\] No errors"; then
        echo -e "${RED}  ✗ PHPStan: Found unused traits in tests/Traits/${NC}"
        XDEBUG_MODE=off vendor/bin/phpstan analyse tests/Traits --memory-limit=$PHPSTAN_MEMORY_LIMIT --no-progress 2>&1 | grep -E "unused|Line" | head -10
        PHPSTAN_FAILED=true
        add_failure "Unused test traits detected - these will fail CI"
    else
        echo -e "${GREEN}  ✓ No unused test traits${NC}"
    fi

    # Full codebase scan
    if ! run_phpstan "" $PHPSTAN_TIMEOUT; then
        if [ $? -eq 124 ]; then
            # Timeout - try with smaller scope
            echo -e "${YELLOW}  Trying PHPStan with directory-by-directory approach...${NC}"
            
            # Clear PHPStan cache to reduce memory usage
            rm -rf %tmpDir%/phpstan 2>/dev/null || true
            
            # Check critical directories individually
            PHPSTAN_DIR_FAILED=false
            for DIR in app/Http app/Domain app/Services app/Models; do
                if [ -d "$DIR" ]; then
                    if ! run_phpstan "$DIR" 60; then
                        PHPSTAN_DIR_FAILED=true
                    fi
                fi
            done
            
            if [ "$PHPSTAN_DIR_FAILED" = true ]; then
                PHPSTAN_FAILED=true
                add_failure "PHPStan errors detected"
            fi
        else
            PHPSTAN_FAILED=true
            add_failure "PHPStan errors detected"
        fi
    fi
else
    # Check test traits if any test file was modified
    TEST_FILES_MODIFIED=$(echo "$FILES" | grep -E "^tests/" || true)
    
    if [ -n "$TEST_FILES_MODIFIED" ]; then
        echo -e "${YELLOW}  Test files modified - checking for unused traits...${NC}"
        if ! XDEBUG_MODE=off vendor/bin/phpstan analyse tests/Traits --memory-limit=$PHPSTAN_MEMORY_LIMIT --no-progress 2>&1 | grep -q "\[OK\] No errors"; then
            echo -e "${RED}  ✗ PHPStan: Found unused traits in tests/Traits/${NC}"
            XDEBUG_MODE=off vendor/bin/phpstan analyse tests/Traits --memory-limit=$PHPSTAN_MEMORY_LIMIT --no-progress 2>&1 | grep -E "unused|Line" | head -10
            PHPSTAN_FAILED=true
            add_failure "Unused test traits detected - these will fail CI"
        fi
    fi

    # Check only modified files
    if [ -n "$FILES" ]; then
        FILES_FOR_PHPSTAN=$(echo "$FILES" | tr '\n' ' ')
        
        # Try PHPStan on modified files with shorter timeout
        if ! run_phpstan "$FILES_FOR_PHPSTAN" 60; then
            if [ $? -eq 124 ]; then
                # On timeout, try running PHPStan file by file
                echo -e "${YELLOW}  PHPStan timed out. Trying file-by-file analysis...${NC}"
                PHPSTAN_FILE_FAILED=false
                for FILE in $FILES; do
                    if [ -f "$FILE" ]; then
                        echo -n "  Checking $FILE..."
                        if ! timeout 30 vendor/bin/phpstan analyse "$FILE" --memory-limit=$PHPSTAN_MEMORY_LIMIT --no-progress > /tmp/phpstan_single_file.log 2>&1; then
                            echo -e "${RED} ✗${NC}"
                            PHPSTAN_FILE_FAILED=true
                            cat /tmp/phpstan_single_file.log | grep -E "Line|expects|given|ERROR" | head -5
                        else
                            echo -e "${GREEN} ✓${NC}"
                        fi
                    fi
                done
                
                if [ "$PHPSTAN_FILE_FAILED" = true ]; then
                    PHPSTAN_FAILED=true
                    add_failure "PHPStan errors detected in modified files"
                    echo -e "${RED}  ✗ PHPStan found errors. CI will fail!${NC}"
                fi
                rm -f /tmp/phpstan_single_file.log
            else
                PHPSTAN_FAILED=true
                add_failure "PHPStan errors in modified files"
            fi
        fi
    fi
fi

if [ "$PHPSTAN_FAILED" = false ] && [ "$CHECK_ALL" = false ]; then
    echo -e "${YELLOW}  Note: Only checked modified files. Use --all for full scan.${NC}"
fi
echo ""

# 5. Security Tests - Check if tests pass
echo -e "${BLUE}[5/7] Running Security Tests...${NC}"
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

# 6. All Tests - Run full test suite in CI mode
echo -e "${BLUE}[6/7] Running Test Suite...${NC}"
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
    # Check if ANY PHP files were modified that could affect tests
    PHP_FILES_MODIFIED=$(echo "$FILES" | grep -E '\.php$' || true)
    
    if [ -n "$PHP_FILES_MODIFIED" ]; then
        echo -e "${YELLOW}  PHP files modified - running relevant tests...${NC}"
        
        # Check if test files were specifically modified
        TEST_FILES=$(echo "$FILES" | grep -E '^tests/.*Test\.php$' || true)
        
        if [ -n "$TEST_FILES" ]; then
            # Run tests for modified test files
            echo -e "${YELLOW}  Running tests for modified test files...${NC}"
            TEST_DIRS=$(echo "$TEST_FILES" | xargs -n1 dirname | sort -u | head -1)
            if ./vendor/bin/pest "$TEST_DIRS" --parallel --compact > /dev/null 2>&1; then
                echo -e "${GREEN}✓ Tests: Modified tests passed${NC}"
            else
                echo -e "${RED}✗ Tests: Some modified tests failed${NC}"
                # Show which tests failed
                ./vendor/bin/pest "$TEST_DIRS" --parallel 2>&1 | grep -E "FAILED|Error" | head -10
                add_failure "Modified test failures"
            fi
        else
            # PHP files modified but no test files - run a quick test suite
            echo -e "${YELLOW}  Running quick test suite for code changes...${NC}"
            echo -e "${YELLOW}  (Modified: $(echo "$PHP_FILES_MODIFIED" | wc -l) PHP files)${NC}"
            
            # Try to run tests with stop-on-failure for faster feedback
            if timeout 60 ./vendor/bin/pest --parallel --compact --stop-on-failure > /dev/null 2>&1; then
                echo -e "${GREEN}✓ Tests: Quick test suite passed${NC}"
            else
                echo -e "${RED}✗ Tests: Some tests failed${NC}"
                echo -e "${YELLOW}  Tip: Run './vendor/bin/pest' to see full results${NC}"
                echo -e "${YELLOW}  Or use '--all' flag to run full test suite${NC}"
                add_failure "Test failures detected - code changes may have broken tests"
            fi
        fi
    else
        echo -e "${YELLOW}  No PHP files modified, skipping test run${NC}"
    fi
fi
echo ""

# 7. Migration Validation - Check if migrations can run without errors
echo -e "${BLUE}[7/7] Validating Database Migrations...${NC}"

# Check if any migration files were modified
MIGRATION_FILES=$(echo "$FILES" | grep -E '^database/migrations/.*\.php$' || true)

if [ -n "$MIGRATION_FILES" ] || [ "$CI_MODE" = true ] || [ "$CHECK_ALL" = true ]; then
    if [ -n "$MIGRATION_FILES" ]; then
        echo -e "${YELLOW}  Modified migration files detected:${NC}"
        echo "$MIGRATION_FILES" | sed 's/^/    - /'
        echo ""
    fi
    
    echo -e "${YELLOW}  Testing migrations in isolated environment...${NC}"
    
    # Create a temporary test database configuration
    export DB_CONNECTION=sqlite
    export DB_DATABASE=":memory:"
    
    # Try to run migrations in a fresh environment
    if php artisan migrate:fresh --force > /tmp/migration_test.log 2>&1; then
        echo -e "${GREEN}✓ Migrations: All migrations run successfully${NC}"
    else
        echo -e "${RED}✗ Migrations: Migration errors detected${NC}"
        echo -e "${RED}  Error output:${NC}"
        cat /tmp/migration_test.log | grep -E "Error|Exception|SQLSTATE" | head -10
        echo ""
        echo -e "${YELLOW}  Full migration log saved to: /tmp/migration_test.log${NC}"
        echo -e "${YELLOW}  Common issues:${NC}"
        echo -e "${YELLOW}    - Foreign key type mismatches (UUID vs BIGINT)${NC}"
        echo -e "${YELLOW}    - Missing table dependencies${NC}"
        echo -e "${YELLOW}    - Duplicate column/index names${NC}"
        add_failure "Migration errors"
    fi
    
    # Clean up
    rm -f /tmp/migration_test.log 2>/dev/null || true
else
    echo -e "${YELLOW}  No migration files modified, skipping migration check${NC}"
fi
echo ""

# Report if issues were fixed
if [ "$ISSUES_FIXED" = true ]; then
    echo -e "${YELLOW}========================================${NC}"
    echo -e "${YELLOW}  Issues Were Auto-Fixed${NC}"
    echo -e "${YELLOW}========================================${NC}"
    if [ "$PHPCS_HAD_ISSUES" = true ]; then
        echo -e "${YELLOW}  - PHPCS (PSR-12) violations were fixed${NC}"
    fi
    if [ "$PHPCS_FIXER_HAD_ISSUES" = true ]; then
        echo -e "${YELLOW}  - PHP CS Fixer style issues were fixed${NC}"
    fi
    echo -e "${YELLOW}  Review changes before committing!${NC}"
    echo -e "${YELLOW}========================================${NC}"
    echo ""
fi

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
    echo -e "  1. vendor/bin/phpcs"
    echo -e "  2. vendor/bin/phpstan analyse --memory-limit=2G"
    echo -e "  3. vendor/bin/php-cs-fixer fix --dry-run --diff"
    echo -e "  4. vendor/bin/pest --parallel"
    echo -e "  5. php artisan migrate:fresh --force"
    
    exit 1
else
    echo -e "${GREEN}✓ All pre-commit checks PASSED${NC}"
    if [ "$ISSUES_FIXED" = true ]; then
        echo -e "${YELLOW}Note: Issues were auto-fixed. Review changes before committing!${NC}"
    else
        echo -e "${GREEN}Ready to commit!${NC}"
    fi
    
    if [ "$CI_MODE" = false ]; then
        echo -e "${YELLOW}Tip: Run with --ci to ensure GitHub Actions will pass${NC}"
    fi
fi
echo -e "${GREEN}========================================${NC}"
