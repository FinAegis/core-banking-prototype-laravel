# Parallel Testing Guide

## Overview

This guide documents the parallel testing setup and best practices for the FinAegis platform. Parallel testing significantly improves test execution speed by running tests concurrently across multiple processes.

## Configuration

### Local Development (phpunit.xml)
```xml
<env name="PEST_PARALLEL" value="true"/>
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

### CI Environment (phpunit.ci.xml)
```xml
<env name="PEST_PARALLEL" value="true"/>
<env name="DB_CONNECTION" value="mysql"/>
<env name="REDIS_HOST" value="127.0.0.1"/>
<env name="REDIS_PORT" value="6379"/>
```

## Running Tests in Parallel

### Basic Commands
```bash
# Run all tests in parallel
./vendor/bin/pest --parallel

# Run with specific number of processes
./vendor/bin/pest --parallel --processes=4

# Run with coverage
./vendor/bin/pest --parallel --coverage --min=50

# Run specific test suite
./vendor/bin/pest --testsuite=Unit --parallel
```

### CI/CD Commands
```bash
# Unit tests with MySQL
./vendor/bin/pest --testsuite=Unit --configuration=phpunit.ci.xml --parallel --coverage --min=70

# Feature tests
./vendor/bin/pest --testsuite=Feature --configuration=phpunit.ci.xml --parallel --coverage --min=65

# Integration tests
./vendor/bin/pest --testsuite=Integration --configuration=phpunit.ci.xml --parallel --coverage --min=55
```

## Test Isolation

### Database Isolation
- **Local**: Uses SQLite in-memory database (`:memory:`)
- **CI**: Uses MySQL with database transactions
- **RefreshDatabase**: Automatically handles cleanup between tests

### Cache and Redis Isolation
The `TestCase` base class automatically sets up isolation:
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

### 4. Proper Test Categorization
- **Unit Tests**: No database, fast, isolated
- **Feature Tests**: Database access, API testing
- **Integration Tests**: Full stack, external services

### 5. Handle Temporal Workflows
```php
// Use WorkflowStub::fake() for testing
WorkflowStub::fake();

// Assert workflow was started
WorkflowStub::assertStarted(MyWorkflow::class);
```

## Common Issues and Solutions

### Issue 1: Database Lock Errors
**Problem**: Multiple processes trying to access the same database
**Solution**: Use database transactions or separate test databases

### Issue 2: Redis Key Conflicts
**Problem**: Tests interfering with each other's cache
**Solution**: Automatic prefixing handled by TestCase

### Issue 3: File System Conflicts
**Problem**: Tests writing to the same files
**Solution**: Use unique file names with process token
```php
$filename = 'test_' . ParallelTesting::token() . '_file.txt';
```

### Issue 4: Port Conflicts
**Problem**: Tests trying to bind to the same ports
**Solution**: Use dynamic port allocation
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

1. **Group Related Tests**: Tests in the same file run in the same process
2. **Minimize Database Operations**: Use factories efficiently
3. **Avoid Sleep/Wait**: Use proper assertions instead
4. **Clean Up Resources**: Always clean up in tearDown()

## Monitoring Test Performance

```bash
# Generate timing report
./vendor/bin/pest --parallel --profile

# Identify slow tests
./vendor/bin/pest --parallel --slow=1000

# Run only fast tests
./vendor/bin/pest --parallel --stop-on-slow
```

## Debugging Parallel Tests

When tests fail in parallel but pass in serial:

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

The GitHub Actions workflow is configured for parallel testing:

```yaml
- name: Run Unit Tests
  run: |
    ./vendor/bin/pest --testsuite=Unit \
      --configuration=phpunit.ci.xml \
      --parallel \
      --coverage \
      --min=70
```

## Conclusion

Parallel testing significantly improves test execution speed. By following these guidelines and best practices, you can ensure your tests run reliably in both local and CI environments.