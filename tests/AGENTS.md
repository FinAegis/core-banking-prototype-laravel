# AGENTS.md - Testing

This directory contains all tests following Pest PHP conventions.

## Test Structure

- `Unit/` - Unit tests for isolated components
- `Feature/` - Integration tests for complete features
- `Domain/` - Domain-specific tests
- `Api/` - API endpoint tests

## Testing Guidelines

### Running Tests
```bash
# All tests
./vendor/bin/pest --parallel

# Specific file
./vendor/bin/pest tests/Feature/AccountTest.php

# With coverage
./vendor/bin/pest --coverage --min=50
```

### Writing Tests
```php
test('can create account', function () {
    // Arrange
    $user = User::factory()->create();
    
    // Act
    $response = $this->actingAs($user)
        ->post('/api/accounts', [...]);
    
    // Assert
    expect($response->status())->toBe(201);
});
```

### Event Testing
```php
Event::fake();

// Perform action

Event::assertDispatched(OrderPlaced::class, function ($event) {
    return $event->amount === 100;
});
```

### Database Testing
- Use `RefreshDatabase` trait
- Create factories for all models
- Use database transactions for isolation

## Common Issues
- **Parallel conflicts**: Use unique identifiers
- **Event faking**: Remember to fake before actions
- **Mocking**: Use PHPDoc for type hints
- **Cleanup**: Close Mockery in tearDown