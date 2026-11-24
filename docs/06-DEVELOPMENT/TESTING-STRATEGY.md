# Testing Strategy

## Overview

This document describes how FinAegis is tested. It covers the testing approach, the standards and the good practices that keep the platform reliable, secure and fast. The repository has about 1,300 `*Test.php` files. They run with Pest 3.8 on PHPUnit 11.

## Testing Philosophy

### Core Principles

1. Write the test before the implementation. This is Test-Driven Development (TDD).
2. Describe business behaviour in the tests. Behat covers this as Behavior-Driven Development (BDD).
3. Tests run on every pull request and on every push to `main` and `develop`.
4. Every new feature and bug fix comes with tests. CI does not measure coverage, so reviewers check this.
5. Tests run in parallel where they can, so that feedback is fast.

## Testing Pyramid

```
         /\
        /E2E\        <- End-to-end tests (5%)
       /------\
      /Feature \     <- Feature/Integration tests (20%)
     /----------\
    /   Unit     \   <- Unit tests (75%)
   /______________\
```

### Unit Tests (75%)

Unit tests check single components in isolation. They live in `tests/Unit/`.

**Examples**:
```php
// tests/Unit/Models/AccountTest.php
test('account can calculate balance', function () {
    $account = Account::factory()->create();
    $account->addBalance('USD', 10000);
    
    expect($account->getBalance('USD'))->toBe(10000);
});
```

They cover models and value objects, services and utilities, aggregates and events, and helpers and formatters.

### Feature Tests (20%)

Feature tests check whole features and their integrations. They live in `tests/Feature/`. Tests that need two real MySQL sessions live in `tests/MultiConnection/`.

**Examples**:
```php
// tests/Feature/Api/TransferControllerTest.php
test('can transfer between accounts', function () {
    $from = Account::factory()->withBalance(10000)->create();
    $to = Account::factory()->create();
    
    $response = $this->postJson('/api/transfers', [
        'from_account' => $from->uuid,
        'to_account' => $to->uuid,
        'amount' => 50.00,
        'asset_code' => 'USD'
    ]);
    
    $response->assertStatus(201);
    expect($from->fresh()->getBalance('USD'))->toBe(5000);
    expect($to->fresh()->getBalance('USD'))->toBe(5000);
});
```

They cover API endpoints, workflows and sagas, event sourcing flows, admin panel operations and authentication flows.

### End-to-End Tests (5%)

End-to-end tests check complete user journeys. The project has Behat features in `features/` and Laravel Dusk browser tests in `tests/Browser/`. Dusk tests are not part of the CI run. Examples of journeys:
- user registration and identity checks
- a full payment cycle from deposit to transfer to withdrawal
- a trading workflow from placing an order to matching and settlement

## Testing Tools & Framework

### Primary Framework: Pest PHP

```bash
# Run all tests
./vendor/bin/pest

# Run with coverage (needs Xdebug or PCOV locally)
./vendor/bin/pest --coverage

# Run in parallel (faster)
./vendor/bin/pest --parallel

# Run specific suite
./vendor/bin/pest tests/Feature/
```

### Testing Libraries

- Pest PHP is the testing framework.
- PHPUnit is the underlying test runner.
- Mockery is the mocking framework.
- Faker generates test data.
- Laravel's testing helpers test HTTP requests.
- Livewire testing helpers test the Filament admin components.
- Behat runs the BDD features. See [BEHAT.md](BEHAT.md).

## Domain-Specific Testing

### Event Sourcing Tests

```php
test('aggregate records events correctly', function () {
    $aggregate = LedgerAggregate::retrieve($uuid);
    $aggregate->createAccount($userId, 'USD');
    
    $events = $aggregate->getRecordedEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(AccountCreated::class);
});
```

### Workflow Testing

```php
test('transfer workflow handles compensation', function () {
    WorkflowStub::fake();
    
    $workflow = WorkflowStub::make(TransferWorkflow::class);
    $workflow->shouldFail();
    
    expect(fn() => $workflow->start($from, $to, $amount))
        ->toThrow(TransferFailedException::class);
    
    WorkflowStub::assertCompensated();
});
```

### Multi-Asset Testing

```php
test('handles cross-currency transfers', function () {
    $account = Account::factory()->create();
    $account->addBalance('USD', 10000);
    $account->addBalance('EUR', 5000);
    
    // Test currency conversion
    $service = app(ExchangeService::class);
    $converted = $service->convert(100, 'USD', 'EUR');
    
    expect($converted)->toBeGreaterThan(80)
                      ->toBeLessThan(120);
});
```

## Test Data Management

### Factories

Most models have factories:

```php
// Create test account with balance
$account = Account::factory()
    ->withBalance(10000)
    ->forUser($user)
    ->create();

// Create test transaction
$transaction = Transaction::factory()
    ->deposit()
    ->forAccount($account)
    ->create();
```

### Seeders for Testing

```php
// Database seeding for test scenarios
$this->seed(TestDataSeeder::class);
$this->seed(DemoDataSeeder::class);
```

### Test Database

```env
# .env.testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

## Continuous Integration

### GitHub Actions Workflow

The Test Suite workflow (`.github/workflows/03-test-suite.yml`) runs the tests in batches with `bin/pest-batch` and `bin/pest-rest`:

```yaml
# .github/workflows/03-test-suite.yml
- name: Run Unit Tests
  env:
    PEST_PARALLEL: true
    PARALLEL_PROCS: 2
  run: |
    ./bin/pest-batch tests/Unit/Domain || exit 1
```

Other jobs and workflows run the multi-connection tests, the security tests, the Behat features and the slow tests. See [PARALLEL-TESTING.md](PARALLEL-TESTING.md) for details.

### Coverage

CI does not collect code coverage, so it does not block a merge on a coverage number. Instead, `bin/check-test-coverage` fails CI when a top-level test directory is not claimed by any CI job. Aim for full coverage of money-moving and security-critical paths.

### Performance Benchmarks

```bash
# Run performance tests
./vendor/bin/pest tests/Performance/

# Benchmarks should complete within:
# - Unit tests: <100ms
# - Feature tests: <500ms
# - E2E tests: <2000ms
```

## Testing Standards

### Test Naming Conventions

```php
// Use descriptive test names
test('user can deposit money into account')
test('transfer fails when insufficient balance')
test('exchange rate updates trigger recalculation')

// Group related tests
describe('Account Operations', function () {
    test('can create account')
    test('can deposit funds')
    test('can withdraw funds')
});
```

### Assertion Best Practices

```php
// Prefer specific assertions
expect($account->balance)->toBe(10000);  // Good
expect($account->balance > 0)->toBeTrue(); // Less specific

// Use custom expectations
expect($response)->toBeSuccessful()
                 ->toHaveStatus(201)
                 ->toContainJson(['status' => 'success']);
```

### Test Isolation

```php
// Each test should be independent
beforeEach(function () {
    $this->account = Account::factory()->create();
});

// Clean up after tests
afterEach(function () {
    Cache::flush();
    Queue::clear();
});
```

## Mock Services

### Demo Mode Testing

`DemoServiceProvider` binds the demo services when `APP_ENV` is `demo`. Tests run with `APP_ENV=testing`, so a test binds the demo service itself:

```php
test('demo services return mock data', function () {
    // DemoServiceProvider binds DemoPaymentService when APP_ENV=demo
    $this->app->bind(PaymentServiceInterface::class, DemoPaymentService::class);
    
    $service = app(PaymentServiceInterface::class);
    expect($service)->toBeInstanceOf(DemoPaymentService::class);
    
    $account = Account::factory()->create();
    $intentId = $service->processStripeDeposit([
        'account_uuid' => $account->uuid,
        'amount'       => 10000,
        'currency'     => 'usd',
        'reference'    => 'test-deposit',
    ]);
    expect($intentId)->toStartWith('demo_pi_');
});
```

### External API Mocking

```php
Http::fake([
    'api.stripe.com/*' => Http::response(['id' => 'pi_test'], 200),
    'api.binance.com/*' => Http::response(['price' => '50000'], 200),
]);
```

## Test Categories

### Security Tests

```php
// tests/Security/
test('prevents SQL injection')
test('validates input sanitization')
test('enforces authentication')
test('implements rate limiting')
```

### Performance Tests

```php
// tests/Performance/
test('handles 1000 concurrent transfers')
test('processes batch operations efficiently')
test('maintains sub-second response times')
```

### Compliance Tests

```php
// tests/Feature/Compliance/ and tests/Unit/Domain/Compliance/
test('generates CTR reports correctly')
test('implements KYC verification')
test('enforces transaction limits')
```

## Testing Commands

### Running Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific file
./vendor/bin/pest tests/Feature/AccountTest.php

# Run with filter
./vendor/bin/pest --filter="transfer"

# Run in parallel
./vendor/bin/pest --parallel --processes=8

# Generate coverage report
./vendor/bin/pest --coverage-html coverage/
```

### Debugging Tests

```bash
# Stop on first failure
./vendor/bin/pest --bail

# Show detailed output
./vendor/bin/pest -v

# Debug specific test
./vendor/bin/pest --filter="test name" --debug
```

## Test Maintenance

### Regular Tasks

1. Every week, review and update failing tests.
2. Every month, look at the coverage reports.
3. Every quarter, review the performance benchmarks.
4. Every year, review this test strategy.

### Test Refactoring

```php
// Extract common setup
trait CreatesTestAccounts
{
    protected function createAccountWithBalance(int $balance): Account
    {
        return Account::factory()
            ->withBalance($balance)
            ->create();
    }
}
```

## Future Enhancements

### Planned Improvements

1. Run the Laravel Dusk browser tests in `tests/Browser/` in CI.
2. Validate API responses against the OpenAPI schema. CI already checks that `storage/api-docs/api-docs.json` matches the annotations.
3. Extend the k6 load tests in `tests/k6/`, which the Performance workflow runs.
4. Add mutation testing. Pest supports it with `--mutate`.
5. Add visual regression tests, for example with Percy or BackstopJS.

### Testing Metrics

Track and improve:
- Test execution time
- Coverage percentage
- Test stability (flaky test detection)
- Bug escape rate
- Test maintenance cost

## Best Practices Summary

1. Write the tests first.
2. Keep tests fast and run them in parallel.
3. Test behaviour and outcomes, not the implementation.
4. Keep test data in factories and seeders.
5. Mock external dependencies so that tests are reliable.
6. Make sure every new directory under `tests/` is run by CI.
7. Review and refactor tests regularly.
8. Give tests clear names that describe the scenario.

Pass `['read', 'write', 'delete']` abilities to `Sanctum::actingAs()` in API tests, because the scope middleware maps GET to `read`, POST, PUT and PATCH to `write`, and DELETE to `delete`.

## Resources

- [Pest PHP Documentation](https://pestphp.com)
- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [Event Sourcing Test Patterns](https://www.eventstore.com/blog/testing-event-sourced-applications)
- The `durable-workflow/workflow` package documentation covers workflow testing with `WorkflowStub::fake()`.