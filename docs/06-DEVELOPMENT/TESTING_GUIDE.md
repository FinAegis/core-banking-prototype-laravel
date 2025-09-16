# FinAegis Testing Guide

**Last Updated:** 2024-09-07  
**Version:** 1.0  
**Test Framework:** Pest PHP

## Overview

This guide provides comprehensive information about testing in the FinAegis platform, including test organization, patterns, and best practices.

## Table of Contents

1. [Test Organization](#test-organization)
2. [Running Tests](#running-tests)
3. [Test Coverage](#test-coverage)
4. [Testing Patterns](#testing-patterns)
5. [Domain Testing](#domain-testing)
6. [Feature Testing](#feature-testing)
7. [API Testing](#api-testing)
8. [Frontend Testing](#frontend-testing)
9. [Performance Testing](#performance-testing)
10. [CI/CD Integration](#cicd-integration)

## Test Organization

### Directory Structure

```
tests/
├── Unit/               # Unit tests for individual classes
├── Feature/            # Integration tests for features
├── Domain/             # Domain-specific tests
│   ├── Account/        # Account domain tests
│   ├── Asset/          # Asset management tests
│   ├── Exchange/       # Trading engine tests
│   ├── Lending/        # P2P lending tests
│   └── Stablecoin/     # Stablecoin tests
├── Browser/            # Browser automation tests
├── Performance/        # Performance benchmarks
└── TestCase.php        # Base test class
```

### Test Naming Conventions

- **Unit Tests**: `{ClassName}Test.php`
- **Feature Tests**: `{Feature}Test.php`
- **API Tests**: `{Endpoint}ApiTest.php`
- **Domain Tests**: `{Aggregate}AggregateTest.php`

## Running Tests

### Basic Commands

```bash
# Run all tests
./vendor/bin/pest

# Run with parallel execution (recommended)
./vendor/bin/pest --parallel

# Run specific test suite
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/Feature
./vendor/bin/pest tests/Domain

# Run specific test file
./vendor/bin/pest tests/Feature/Account/AccountCreationTest.php

# Run tests matching pattern
./vendor/bin/pest --filter="can create account"

# Run with coverage
./vendor/bin/pest --coverage --min=50

# Run with coverage HTML report
./vendor/bin/pest --coverage-html=coverage-report
```

### Parallel Testing

```bash
# Use all available cores
./vendor/bin/pest --parallel

# Specify number of processes
./vendor/bin/pest --parallel --processes=4

# Optimal for CI
./vendor/bin/pest --parallel --processes=8 --coverage --min=50
```

## Test Coverage

### Current Coverage Status

| Component | Coverage | Target |
|-----------|----------|---------|
| Core Banking | 95% | 90% |
| Multi-Asset | 90% | 85% |
| Exchange | 85% | 80% |
| P2P Lending | 75% | 85% |
| Stablecoins | 80% | 85% |
| Liquidity Pools | 70% | 85% |
| API Layer | 88% | 85% |
| **Overall** | **85%** | **85%** |

### Coverage Requirements

- **Minimum**: 50% for all new code
- **Target**: 85% for production features
- **Critical**: 95% for financial operations

### Generating Coverage Reports

```bash
# Terminal report
./vendor/bin/pest --coverage

# HTML report
./vendor/bin/pest --coverage-html=coverage-report

# Clover format (for CI)
./vendor/bin/pest --coverage-clover=coverage.xml

# With minimum threshold
./vendor/bin/pest --coverage --min=85
```

## Testing Patterns

### Pest PHP Syntax

```php
// Basic test
it('can create an account', function () {
    $account = Account::factory()->create();
    
    expect($account)->toBeInstanceOf(Account::class);
    expect($account->balance)->toBe(0);
});

// With dataset
it('validates email format', function (string $email, bool $valid) {
    $result = validateEmail($email);
    
    expect($result)->toBe($valid);
})->with([
    ['user@example.com', true],
    ['invalid-email', false],
    ['user@domain', false],
]);

// Using hooks
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

afterEach(function () {
    // Cleanup
});
```

### Factory Pattern

```php
// Account factory with states
$account = Account::factory()
    ->forUser($user)
    ->withBalance(10000)
    ->frozen()
    ->create();

// Multiple assets
$account = Account::factory()
    ->hasBalances([
        ['asset_code' => 'USD', 'balance' => 10000],
        ['asset_code' => 'EUR', 'balance' => 5000],
    ])
    ->create();
```

## Domain Testing

### Event Sourcing Tests

```php
it('records events when adding money', function () {
    $aggregate = TransactionAggregate::retrieve($uuid);
    
    $aggregate->addMoney(
        new Money(1000, new Currency('USD')),
        HashGenerator::generate()
    );
    
    $events = $aggregate->getRecordedEvents();
    
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(MoneyAdded::class);
    expect($events[0]->money->getAmount())->toBe('1000');
});
```

### Workflow Testing

```php
it('executes transfer workflow with compensation', function () {
    WorkflowStub::fake();
    
    $workflow = WorkflowStub::make(TransferWorkflow::class);
    $workflow->start($fromAccount, $toAccount, Money::USD(1000));
    
    // Assert activities dispatched
    WorkflowStub::assertDispatched(WithdrawActivity::class);
    WorkflowStub::assertDispatched(DepositActivity::class);
    
    // Simulate failure and compensation
    WorkflowStub::failActivity(DepositActivity::class);
    
    // Assert compensation executed
    WorkflowStub::assertDispatched(DepositActivity::class, function ($activity) {
        return $activity->isCompensation();
    });
});
```

### Projector Testing

```php
it('projects account balance correctly', function () {
    $projector = new AccountBalanceProjector();
    
    $event = new AssetBalanceAdded(
        accountUuid: $accountUuid,
        assetCode: 'USD',
        amount: BigDecimal::of('1000'),
        hash: HashGenerator::generate()
    );
    
    $projector->onAssetBalanceAdded($event);
    
    $balance = AccountBalance::where('account_uuid', $accountUuid)
        ->where('asset_code', 'USD')
        ->first();
    
    expect($balance->balance)->toBe('1000');
});
```

## Feature Testing

### API Authentication

```php
it('requires authentication for protected endpoints', function () {
    $response = $this->getJson('/api/accounts');
    
    $response->assertStatus(401);
});

it('allows authenticated access', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->getJson('/api/accounts');
    
    $response->assertStatus(200);
});
```

### Complex Scenarios

```php
it('handles complete lending lifecycle', function () {
    // Setup
    $borrower = User::factory()->create();
    $lender = User::factory()->create();
    
    // Create loan application
    $this->actingAs($borrower)
        ->postJson('/api/loans/apply', [
            'amount' => 10000,
            'term_months' => 12,
            'purpose' => 'business',
        ])
        ->assertStatus(201);
    
    // Lender funds loan
    $application = LoanApplication::latest()->first();
    
    $this->actingAs($lender)
        ->postJson("/api/loans/{$application->id}/fund", [
            'amount' => 10000,
        ])
        ->assertStatus(200);
    
    // Make payment
    $loan = Loan::where('application_id', $application->id)->first();
    
    $this->actingAs($borrower)
        ->postJson("/api/loans/{$loan->id}/repay", [
            'amount' => 877.84,
        ])
        ->assertStatus(200);
    
    // Verify state
    $loan->refresh();
    expect($loan->payments_made)->toBe(1);
    expect($loan->outstanding_balance)->toBeLessThan(10000);
});
```

## API Testing

### Request Validation

```php
it('validates required fields', function () {
    $response = $this->postJson('/api/accounts', []);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'currency']);
});
```

### Response Structure

```php
it('returns correct response structure', function () {
    $account = Account::factory()->create();
    
    $response = $this->actingAs($account->user)
        ->getJson("/api/accounts/{$account->uuid}");
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'uuid',
                'name',
                'status',
                'balances' => [
                    '*' => ['asset_code', 'balance', 'available']
                ],
                'created_at',
                'updated_at',
            ]
        ]);
});
```

### Error Handling

```php
it('handles insufficient funds gracefully', function () {
    $account = Account::factory()->withBalance(100)->create();
    
    $response = $this->actingAs($account->user)
        ->postJson("/api/accounts/{$account->uuid}/withdraw", [
            'amount' => 1000,
            'currency' => 'USD',
        ]);
    
    $response->assertStatus(422)
        ->assertJson([
            'error' => [
                'code' => 'INSUFFICIENT_FUNDS',
                'message' => 'Insufficient balance in account',
            ]
        ]);
});
```

## Frontend Testing

### Component Testing (Vue)

```javascript
import { mount } from '@vue/test-utils';
import AccountBalance from '@/Components/AccountBalance.vue';

test('displays formatted balance', () => {
    const wrapper = mount(AccountBalance, {
        props: {
            balance: 1234567,
            currency: 'USD'
        }
    });
    
    expect(wrapper.text()).toContain('$12,345.67');
});
```

### Integration Testing

```javascript
test('completes deposit flow', async () => {
    const wrapper = mount(DepositForm);
    
    await wrapper.find('input[name="amount"]').setValue('1000');
    await wrapper.find('select[name="currency"]').setValue('USD');
    await wrapper.find('form').trigger('submit');
    
    expect(wrapper.emitted('deposit')).toBeTruthy();
    expect(wrapper.emitted('deposit')[0]).toEqual([{
        amount: 1000,
        currency: 'USD'
    }]);
});
```

## Performance Testing

### Load Testing

```php
it('handles concurrent transfers efficiently', function () {
    $accounts = Account::factory()->count(100)->create();
    
    $start = microtime(true);
    
    // Simulate 100 concurrent transfers
    $promises = [];
    foreach ($accounts->chunk(2) as $chunk) {
        if ($chunk->count() === 2) {
            $promises[] = async(fn() => 
                $this->postJson('/api/transfers', [
                    'from' => $chunk[0]->uuid,
                    'to' => $chunk[1]->uuid,
                    'amount' => 100,
                    'currency' => 'USD',
                ])
            );
        }
    }
    
    await($promises);
    
    $duration = microtime(true) - $start;
    
    expect($duration)->toBeLessThan(5.0); // Should complete within 5 seconds
});
```

### Benchmark Tests

```php
it('benchmarks order matching performance', function () {
    $orderBook = OrderBook::factory()
        ->hasOrders(1000)
        ->create();
    
    $benchmark = benchmark(function () use ($orderBook) {
        app(OrderMatchingEngine::class)->match($orderBook);
    }, 10); // Run 10 iterations
    
    expect($benchmark->avg())->toBeLessThan(100); // Average < 100ms
    expect($benchmark->min())->toBeLessThan(80);   // Best case < 80ms
});
```

## CI/CD Integration

### GitHub Actions Configuration

```yaml
# .github/workflows/test.yml
name: Tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: self-hosted
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
      
      redis:
        image: redis:7
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo, pdo_mysql, redis
          coverage: xdebug
      
      - name: Install Dependencies
        run: |
          composer install --no-interaction
          npm ci
          npm run build
      
      - name: Run Tests
        run: |
          ./vendor/bin/pest --parallel --coverage --min=50
      
      - name: Upload Coverage
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml
```

### Pre-commit Hooks

```bash
#!/bin/sh
# .git/hooks/pre-commit

# Run tests for changed files
CHANGED_PHP=$(git diff --cached --name-only --diff-filter=ACM | grep ".php$")

if [ ! -z "$CHANGED_PHP" ]; then
    echo "Running tests for changed PHP files..."
    ./vendor/bin/pest --parallel --filter="$(echo $CHANGED_PHP | sed 's/.php//g' | sed 's/app/tests/g')"
    
    if [ $? -ne 0 ]; then
        echo "Tests failed! Please fix before committing."
        exit 1
    fi
fi
```

## Best Practices

### 1. Test Isolation

```php
// Good: Each test is independent
it('creates account with zero balance', function () {
    $account = Account::factory()->create();
    expect($account->balance)->toBe(0);
});

// Bad: Depends on previous test state
it('has balance from previous test', function () {
    $account = Account::first();
    expect($account->balance)->toBeGreaterThan(0);
});
```

### 2. Use Factories

```php
// Good: Use factories for test data
$user = User::factory()->create();
$account = Account::factory()->for($user)->create();

// Bad: Manual creation
$user = new User(['email' => 'test@example.com']);
$user->save();
```

### 3. Test Behavior, Not Implementation

```php
// Good: Test the outcome
it('transfers money between accounts', function () {
    $transfer = transferMoney($from, $to, 1000);
    
    expect($from->fresh()->balance)->toBe(0);
    expect($to->fresh()->balance)->toBe(1000);
});

// Bad: Test implementation details
it('calls withdraw then deposit', function () {
    // Don't test internal method calls
});
```

### 4. Meaningful Assertions

```php
// Good: Specific assertions
expect($response->json('data.status'))->toBe('completed');
expect($response->json('data.amount'))->toBe(1000);

// Bad: Generic assertions
expect($response->status())->toBe(200);
```

### 5. Test Edge Cases

```php
it('handles maximum values', function () {
    $maxAmount = PHP_INT_MAX;
    // Test behavior at limits
});

it('handles empty inputs gracefully', function () {
    // Test with null, empty strings, etc.
});

it('handles concurrent operations', function () {
    // Test race conditions
});
```

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   ```bash
   # Ensure test database exists
   php artisan migrate --env=testing
   ```

2. **Redis Connection Errors**
   ```bash
   # Start Redis for testing
   redis-server --port 6380
   ```

3. **Parallel Test Conflicts**
   ```php
   // Use unique identifiers
   $uuid = Str::uuid();
   ```

4. **Memory Issues**
   ```bash
   # Increase memory limit
   ./vendor/bin/pest --memory-limit=512M
   ```

## Resources

- [Pest PHP Documentation](https://pestphp.com)
- [Laravel Testing Docs](https://laravel.com/docs/testing)
- [PHPUnit Assertions](https://docs.phpunit.de/en/10.0/assertions.html)
- [Test Driven Development](https://tdd.finaegis.internal)

---

**Remember**: Good tests are the foundation of a reliable financial platform. Write tests first, code second!