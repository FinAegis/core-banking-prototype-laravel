# Test Suite Fix TODO List

## Summary

The test suite has multiple issues that need to be addressed:
1. **Primary Issue**: All tests fail due to `TracksTestPerformance` trait calling `getName()` which doesn't exist in Pest
2. **PHPStan Issues**: 2,280 static analysis errors found
3. **Test Configuration**: Tests are configured correctly in GitHub Actions but need fixes to run

## Critical Issues (Fix First)

### 1. TracksTestPerformance Trait Compatibility
- **Issue**: The trait calls `$this->getName()` which is a PHPUnit method not available in Pest
- **Files Affected**: `tests/Traits/TracksTestPerformance.php`, `tests/TestCase.php`
- **Fix**: Update the trait to work with Pest or disable performance tracking temporarily
- **Priority**: CRITICAL - Blocking all tests

### 2. Missing Event Sourcing Classes
- **Issue**: Many event sourcing related classes are not found
- **Examples**:
  - `App\Domain\Account\Events\*` - Account events missing
  - `App\Domain\Card\Events\*` - Card events missing
  - `App\Domain\Transaction\Events\*` - Transaction events missing
  - `App\Domain\Wallet\Events\*` - Wallet events missing
- **Root Cause**: Recent refactoring may have moved/renamed these classes
- **Priority**: HIGH - Core functionality

## PHPStan Issues by Category

### 1. Event Sourcing & Domain Events (Most Critical)
- **Count**: ~500+ errors
- **Pattern**: Missing event classes that tests are trying to use
- **Examples**:
  ```
  Class App\Domain\Account\Events\AccountCredited not found
  Class App\Domain\Account\Events\AccountDebited not found
  Class App\Domain\Account\Events\AccountOpened not found
  Class App\Domain\Card\Events\CardIssued not found
  Class App\Domain\Transaction\Events\TransactionInitiated not found
  ```
- **Action**: Investigate if events were moved/renamed during refactoring

### 2. Aggregate & Repository Classes
- **Count**: ~200+ errors
- **Pattern**: Missing aggregates and repositories
- **Examples**:
  ```
  Class App\Domain\Account\Aggregates\AccountAggregate not found
  Class App\Domain\Card\Repositories\CardRepository not found
  Class App\Domain\Transaction\Aggregates\TransactionAggregate not found
  ```
- **Action**: Check if these follow new patterns after DDD refactoring

### 3. Type Mismatches & Method Calls
- **Count**: ~300+ errors
- **Common Issues**:
  - Undefined methods on mock objects
  - Type mismatches between expected and actual
  - Incorrect return types
- **Examples**:
  ```
  Call to an undefined method Mockery\ExpectationInterface|Mockery\HigherOrderMessage::withArgs()
  Method App\Models\Account::user() should return BelongsTo but returns BelongsTo<User, Account>
  ```

### 4. Feature Test Issues
- **Count**: ~100+ errors
- **Pattern**: Missing HTTP test assertions and route issues
- **Examples**:
  ```
  Call to an undefined method Illuminate\Testing\TestResponse::assertJsonApiError()
  Property Tests\Feature\BasketManagementTest::$basket does not exist
  ```

### 5. Security Test Issues
- **Count**: ~50+ errors
- **Pattern**: Authentication and authorization test failures
- **Issues**:
  - Missing security event classes
  - Incorrect assertions on authentication

### 6. Reflection API Issues
- **Count**: ~50 errors
- **Pattern**: PHP 8.x reflection API changes
- **Example**: `Call to an undefined method ReflectionType::getName()`
- **Fix**: Use `(string) $type` instead of `$type->getName()`

## Recommended Fix Order

### Phase 1: Unblock All Tests (Critical)
1. Fix `TracksTestPerformance` trait to work with Pest
2. Run tests again to get actual test failures

### Phase 2: Core Domain Issues (High Priority)
1. Investigate and fix missing event classes
2. Fix aggregate and repository class references
3. Update tests to match new domain structure

### Phase 3: Test-Specific Issues (Medium Priority)
1. Fix Mockery usage in unit tests
2. Update feature tests for new API structure
3. Fix security test assertions

### Phase 4: Static Analysis (Low Priority)
1. Fix PHPStan type hints
2. Update docblocks
3. Remove unnecessary type checks

## Quick Wins

1. **Disable TracksTestPerformance temporarily**:
   ```php
   // In TestCase.php, comment out:
   // use TracksTestPerformance;
   // $this->setUpPerformanceTracking();
   // $this->trackTestPerformance();
   ```

2. **Run specific test suites** to isolate issues:
   ```bash
   ./vendor/bin/pest --testsuite=Unit --filter=CommandsTest
   ```

3. **Check for moved files**:
   ```bash
   find app -name "*Event.php" -o -name "*Aggregate.php"
   ```

## Investigation Commands

```bash
# Find all event classes
find app -name "*Event.php" | sort

# Find all aggregate classes
find app -name "*Aggregate.php" | sort

# Check if events were moved to a different namespace
grep -r "class.*Event" app/

# Look for event sourcing configuration
cat config/event-sourcing.php
```

## Notes

- The project uses event sourcing, sagas, workflows, and DDD patterns
- Recent refactoring may have changed class locations/namespaces
- Tests were working before, so this is likely a structural change issue
- GitHub Actions configuration is correct; the issue is in the code