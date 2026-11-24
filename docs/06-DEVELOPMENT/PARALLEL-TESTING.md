# Parallel Testing Guide

## Overview

This guide describes how FinAegis runs tests in parallel and how to keep parallel tests reliable. Pest runs tests in several processes at once through Paratest. This makes the suite much faster.

## Configuration

### Local Development (phpunit.xml)
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="PEST_PARALLEL" value="false"/>
```

`phpunit.xml` uses SQLite in memory. You turn on parallel runs with the `--parallel` flag.

### CI Configuration (phpunit.ci.xml)
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="PEST_PARALLEL" value="true"/>
<env name="REDIS_HOST" value="127.0.0.1"/>
```

CI runs most suites through `bin/pest-batch` and `bin/pest-rest` with `phpunit.ci-optimized.xml`. Unit tests use SQLite in memory with two parallel processes. Feature tests use MySQL and run without parallel processes, because they share database state. The multi-connection tests in `tests/MultiConnection/` run against a real MySQL database.

## Running Tests in Parallel

### Basic Commands
```bash
# Run all tests in parallel
./vendor/bin/pest --parallel

# Run with specific number of processes
./vendor/bin/pest --parallel --processes=4

# Stop at the first failure
./vendor/bin/pest --parallel --stop-on-failure

# Run specific test suite
./vendor/bin/pest --testsuite=Unit --parallel
```

`--parallel` accepts only one test directory at a time. To run a subset, use `--filter` or `--testsuite` instead of several paths.

### CI Commands
```bash
# Run one or more directories in memory-efficient batches
PEST_PARALLEL=true PARALLEL_PROCS=2 ./bin/pest-batch tests/Unit/Domain

# Run every subdirectory of tests/Unit that no earlier batch claimed
./bin/pest-rest tests/Unit tests/Unit/Domain tests/Unit/Http tests/Unit/Services

# Multi-connection tests against real MySQL
./vendor/bin/pest --testsuite=MultiConnection
```

`bin/pest-batch` reads `MEMORY_LIMIT`, `CONFIG_FILE` (default `phpunit.ci-optimized.xml`), `PEST_PARALLEL`, `PARALLEL_PROCS` and `PEST_STOP_ON_FAILURE`. `bin/check-test-coverage` fails CI when a top-level directory under `tests/` is not claimed by any job. CI does not collect code coverage.

## Test Isolation

### Database Isolation
- Local runs and unit tests use SQLite in memory (`:memory:`). Each process has its own database.
- Feature tests in CI use MySQL without parallel processes.
- `RefreshDatabase` resets the database between tests.

### Cache and Redis Isolation
The `TestCase` base class sets up the isolation automatically:
```php
protected function setUpParallelTesting(): void
{
    $token = ParallelTesting::token();
    
    if ($token) {
        config([
            'database.redis.options.prefix' => 'test_' . $token . ':',
            'cache.prefix' => 'test_' . $token,
            'horizon.prefix' => 'test_' . $token . '_horizon:',
        ]);
    }
}
```

Many tests need Redis. If parallel tests still interfere through the cache, set `config(['cache.default' => 'array'])` in the test.

### Event Sourcing Isolation
Event sourcing storage is also isolated per test process:
```php
config([
    'event-sourcing.storage_prefix' => 'test_' . $token,
]);
```

## Best Practices

### 1. Avoid Shared State
```php
// Bad - Uses global state
Cache::put('key', 'value');
$value = Cache::get('key');

// Good - Use dependency injection
public function __construct(private CacheManager $cache) {}
$this->cache->put('key', 'value');
```

For cache counters, use `Cache::add($key, 0, $ttl)` followed by `Cache::increment()`. Never read the value and then write it back.

### 2. Use Database Transactions
```php
// Tests automatically use transactions with RefreshDatabase trait
use RefreshDatabase;

// For manual control
DB::beginTransaction();
// ... test code ...
DB::rollback();
```

### 3. Mock External Services
```php
// Mock external API calls
Http::fake([
    'api.example.com/*' => Http::response(['data' => 'test'], 200),
]);

// Mock queue jobs
Queue::fake();

// Mock events
Event::fake();
```

### 4. Test Categories
- Unit tests do not touch the database. They are fast and isolated.
- Feature tests use the database and test the API.
- Integration tests run the full stack and may call fake external services.

### 5. Handle Workflows
The project uses the `durable-workflow/workflow` package for sagas and workflows.
```php
// Use WorkflowStub::fake() for testing
WorkflowStub::fake();

// Assert workflow was started
WorkflowStub::assertStarted(MyWorkflow::class);
```

## Common Issues and Solutions

### Database Lock Errors
Several processes may try to use the same database. Use transactions or separate test databases.

### Redis Key Conflicts
Tests may overwrite each other's cache entries. `TestCase` prefixes the keys per process. For tests that still conflict, switch them to the array cache.

### File System Conflicts
Tests may write to the same files. Put the process token in the file name:
```php
$filename = 'test_' . ParallelTesting::token() . '_file.txt';
```

### Port Conflicts
Tests may try to bind to the same port. Derive the port from the process token:
```php
$port = 8000 + (int) ParallelTesting::token();
```

## Migration Guide

### Converting Unit Tests to Integration Tests

1. **Move the file**:
   ```bash
   mv tests/Unit/MyTest.php tests/Feature/MyIntegrationTest.php
   ```

2. **Update namespace**:
   ```php
   namespace Tests\Feature;
   ```

3. **Remove mocking of database models**:
   ```php
   // Before
   $account = Mockery::mock(Account::class);
   
   // After
   $account = Account::factory()->create();
   ```

4. **Use real workflows**:
   ```php
   // Before
   $workflowStub = Mockery::mock(WorkflowStub::class);
   
   // After
   WorkflowStub::fake();
   ```

## Performance Tips

1. Group related tests. Tests in the same file run in the same process.
2. Keep database work small and use factories efficiently.
3. Do not sleep or wait in tests. Use assertions instead.
4. Clean up resources in `tearDown()`.

## Monitoring Test Performance

```bash
# Show the ten slowest tests
./vendor/bin/pest --parallel --profile
```

## Debugging Parallel Tests

When tests fail in parallel but pass on their own, try these steps:

1. **Run specific test in isolation**:
   ```bash
   ./vendor/bin/pest tests/Feature/MyTest.php
   ```

2. **Check for race conditions**:
   ```bash
   ./vendor/bin/pest --parallel --processes=1
   ```

3. **Enable verbose output**:
   ```bash
   ./vendor/bin/pest --parallel -vvv
   ```

4. **Check process isolation**:
   ```php
   dump('Process Token: ' . ParallelTesting::token());
   ```

## CI/CD Integration

The Test Suite workflow (`.github/workflows/03-test-suite.yml`) runs the unit tests like this:

```yaml
- name: Run Unit Tests
  env:
    DB_CONNECTION: sqlite
    DB_DATABASE: ":memory:"
    PEST_PARALLEL: true
    PARALLEL_PROCS: 2
  run: |
    ./bin/pest-batch tests/Unit/Domain || exit 1
```

## Conclusion

Parallel testing makes the suite much faster. Keep tests free of shared state so that they pass the same way locally and in CI.
