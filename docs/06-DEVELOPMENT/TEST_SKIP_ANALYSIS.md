# Test Skip Analysis Report

## Overview

This document provides a comprehensive analysis of all skipped tests in the FinAegis codebase, categorizing them by root cause and providing recommendations for resolution.

## Summary Statistics

- **Total Skipped Test Categories**: 7
- **Total Skipped Tests**: ~50+
- **Root Causes**: 5 distinct issues
- **Action Required**: 4 categories need fixes

## Categories of Skipped Tests

### 1. Filament Panel Configuration (7 test files)

**Status**: 🔧 Configuration needed

**Affected Tests**:
- `Tests\Feature\Filament\AccountExportTest`
- `Tests\Feature\Filament\AccountResourceTest`
- `Tests\Feature\Filament\ChartWidgetsTest`
- `Tests\Feature\Filament\PollResourceTest`
- `Tests\Feature\Filament\UserResourceTest`
- `Tests\Feature\Filament\VoteResourceTest`
- `Tests\Feature\Filament\WebhookResourceTest`

**Root Cause**: 
- Filament panel is not initialized during test execution
- `getCurrentPanel()` returns null because the `SetUpPanel` middleware doesn't run in tests
- Tests defensively check for panel availability and skip if not configured

**Solution**:
```php
// Add to test setup
$panel = Filament::getPanel('admin');
Filament::setCurrentPanel($panel);
Filament::bootCurrentPanel();
```

### 2. Event Sourcing Missing (2 test files)

**Status**: 🚧 Implementation needed

**Affected Tests**:
- `Tests\Feature\Stablecoin\StablecoinOperationsApiTest`
- `Tests\Console\Commands\CreateSnapshotTest`

**Root Cause**:
- Stablecoin operations use `WalletService` → workflows → aggregates but event sourcing isn't fully integrated
- Snapshot tests need actual events in `stored_events` table with sufficient count
- Missing test data setup for event-sourced aggregates

**Solution**:
- Implement proper event sourcing for stablecoin domain
- Create test helpers to generate events in `stored_events`
- Ensure workflows can persist events during tests

### 3. Parallel Testing Incompatibility (2 test files)

**Status**: ⚠️ Isolation needed

**Affected Tests**:
- `Tests\Domain\Account\Projectors\TurnoverProjectorTest`
- `Tests\Domain\Account\Workflows\CreateAccountWorkflowTest`

**Root Cause**:
- **TurnoverProjectorTest**: Race condition on unique constraint `['account_uuid', 'date']`
- **CreateAccountWorkflowTest**: Global state in `WorkflowStub::fake()` not safe for parallel execution
- Insufficient isolation between parallel test processes

**Solution**:
- Add `@group no-parallel` annotation
- Improve test isolation for event projectors
- Use unique identifiers per test run

### 4. Missing Feature Implementation (1 test file)

**Status**: 🚫 Feature not implemented

**Affected Tests**:
- `Tests\Feature\Basket\BasketAccountServiceTest` (11 tests)

**Root Cause**:
- Basket decompose/compose functionality hasn't been implemented
- Requires event sourcing refactoring for basket operations

**Solution**:
- Implement basket decomposition/composition features
- Add event sourcing support for basket operations

### 5. CI Stability Skips (1 test file)

**Status**: ✅ Intentionally skipped

**Affected Tests**:
- `Tests\Feature\Api\BIAN\CurrentAccountControllerTest` (3 tests)

**Root Cause**:
- Parallel testing race conditions in CI environment
- Route loading issues when running in parallel

**Solution**:
- Keep skipped for CI stability
- Consider running in separate non-parallel test suite

### 6. Wrong Test Approach (1 test file)

**Status**: ⚠️ Test design issue

**Affected Tests**:
- `Tests\Domain\Payment\Workflows\TransferActivityTest` (5 tests)

**Root Cause**:
- Tests try to test workflow activities in isolation
- `ActivityStub` requires full workflow runtime context
- Unit test approach incompatible with workflow testing

**Solution**:
- Rewrite as integration tests with full workflow runtime
- Or mock at a different level that doesn't require workflow context

### 7. Feature Flag Conditional (1 test file)

**Status**: ✅ Working as designed

**Affected Tests**:
- `Tests\Feature\RegistrationTest` (1 test conditionally)

**Root Cause**:
- Test is skipped when registration is enabled (correct behavior)
- Uses feature flag to determine if test should run

**Solution**:
- No action needed - working as designed

## Recommended Priorities

### High Priority (Blocking test coverage)
1. **Configure Filament for testing** - Quick fix, enables 7 test files
2. **Fix parallel testing issues** - Add proper isolation or disable parallel execution
3. **Complete basket feature implementation** - Significant feature gap

### Medium Priority (Feature completeness)
1. **Implement event sourcing for stablecoin** - Architectural improvement
2. **Fix workflow activity tests** - Improve test design

### Low Priority (Already handled)
1. CI stability skips - Keep as is
2. Feature flag conditional skips - Working correctly

## Implementation Plan

### Phase 1: Quick Wins (1-2 days)
- Configure Filament panel for testing
- Add `@group no-parallel` annotations
- Fix AssetController symbol issue (already done)

### Phase 2: Architecture (3-5 days)
- Implement event sourcing for stablecoin operations
- Create test helpers for event generation
- Improve parallel test isolation

### Phase 3: Feature Completion (1-2 weeks)
- Implement basket decompose/compose functionality
- Refactor workflow activity tests

## Conclusion

Most skipped tests fall into fixable categories. The primary issues are:
1. Missing test configuration (Filament)
2. Incomplete implementations (basket, stablecoin event sourcing)
3. Test isolation issues (parallel testing)

With focused effort, test coverage can be significantly improved by addressing these systematic issues.