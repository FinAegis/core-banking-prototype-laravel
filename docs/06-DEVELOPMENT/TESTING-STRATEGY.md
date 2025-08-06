# Testing Strategy

## Overview

The FinAegis platform follows a comprehensive testing strategy to ensure reliability, security, and performance across all components. This document outlines our testing approach, standards, and best practices.

## Testing Philosophy

### Core Principles

1. **Test-Driven Development (TDD)**: Write tests before implementation
2. **Behavior-Driven Development (BDD)**: Focus on business behavior
3. **Continuous Testing**: Tests run on every commit and PR
4. **Coverage Requirements**: Minimum 50% coverage for all new code
5. **Fast Feedback**: Parallel execution for rapid feedback

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

**Purpose**: Test individual components in isolation

**Location**: `tests/Unit/`

**Examples**:
```php
// tests/Unit/Models/AccountTest.php
test('account can calculate balance', function () {
    $account = Account::factory()->create();
    $account->addBalance('USD', 10000);
    
    expect($account->getBalance('USD'))->toBe(10000);
});
```

**Coverage Areas**:
- Models and value objects
- Services and utilities
- Aggregates and events
- Helpers and formatters

### Feature Tests (20%)

**Purpose**: Test feature workflows and integrations

**Location**: `tests/Feature/`

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

**Coverage Areas**:
- API endpoints
- Workflows and sagas
- Event sourcing flows
- Admin panel operations
- Authentication flows

### End-to-End Tests (5%)

**Purpose**: Test complete user journeys

**Location**: `tests/E2E/` (future)

**Examples**:
- Complete user registration and KYC
- Full payment cycle (deposit → transfer → withdrawal)
- Trading workflow (place order → match → settle)

## Testing Tools & Framework

### Primary Framework: Pest PHP

```bash
# Run all tests
./vendor/bin/pest

# Run with coverage
./vendor/bin/pest --coverage --min=50

# Run in parallel (faster)
./vendor/bin/pest --parallel

# Run specific suite
./vendor/bin/pest tests/Feature/
```

### Testing Libraries

- **Pest PHP**: Modern testing framework
- **PHPUnit**: Underlying test runner
- **Mockery**: Mocking framework
- **Faker**: Test data generation
- **Laravel Testing**: HTTP testing utilities
- **Livewire Testing**: Filament component testing

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

All models have corresponding factories:

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

```yaml
# .github/workflows/test.yml
- name: Run tests
  run: |
    ./vendor/bin/pest --parallel \
      --coverage \
      --min=50 \
      --configuration=phpunit.ci.xml
```

### Coverage Requirements

- **Minimum**: 50% for all new code
- **Target**: 80% for critical paths
- **Enforcement**: CI blocks merge if coverage drops

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

```php
test('demo services return mock data', function () {
    Config::set('demo.mode', true);
    
    $service = app(PaymentServiceInterface::class);
    expect($service)->toBeInstanceOf(DemoPaymentService::class);
    
    $result = $service->createPaymentIntent(10000);
    expect($result['id'])->toStartWith('demo_pi_');
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
// tests/Compliance/
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

1. **Weekly**: Review and update failing tests
2. **Monthly**: Analyze coverage reports
3. **Quarterly**: Performance benchmark review
4. **Yearly**: Test strategy evaluation

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

1. **Browser Testing**: Laravel Dusk for E2E tests
2. **API Documentation Tests**: OpenAPI schema validation
3. **Load Testing**: K6 or JMeter integration
4. **Mutation Testing**: Infection PHP integration
5. **Visual Regression**: Percy or BackstopJS

### Testing Metrics

Track and improve:
- Test execution time
- Coverage percentage
- Test stability (flaky test detection)
- Bug escape rate
- Test maintenance cost

## Best Practices Summary

1. **Write tests first** - TDD approach
2. **Keep tests fast** - Use parallel execution
3. **Test behavior, not implementation** - Focus on outcomes
4. **Maintain test data** - Use factories and seeders
5. **Mock external dependencies** - Ensure reliability
6. **Monitor coverage** - Maintain minimum standards
7. **Review test quality** - Regular refactoring
8. **Document test scenarios** - Clear test names

## Resources

- [Pest PHP Documentation](https://pestphp.com)
- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [Event Sourcing Test Patterns](https://www.eventstore.com/blog/testing-event-sourced-applications)
- [Workflow Testing Guide](https://temporal.io/docs/php/testing)