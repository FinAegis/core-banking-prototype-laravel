# Behat BDD Testing Guide

This guide explains how to use Behat for Behavior-Driven Development (BDD) testing in the FinAegis Core Banking Platform.

## Installation

Behat and its dependencies are already installed. The following packages are included:
- `behat/behat`: Core BDD framework
- `behat/mink`: Web acceptance testing framework
- `behat/mink-browserkit-driver`: Symfony BrowserKit driver for Mink
- `dmore/behat-chrome-extension`: Chrome/Chromium browser automation

## Running Tests

### Basic Usage

```bash
# Run all features
./bin/behat

# Run specific feature
./bin/behat features/account_management.feature

# Run with pretty output
./bin/behat --format=pretty

# Run specific scenarios by name
./bin/behat --name="Creating a new account"

# Run scenarios with specific tags
./bin/behat --tags=@api

# Show available step definitions
./bin/behat -dl
```

### Test Suites

The `behat.yml` configuration defines the following suites:
- **acceptance**: General acceptance tests with Laravel integration
- **api**: API-specific tests with REST client integration
- **basket**: Basket asset management features

## Writing Features

### Feature File Structure

```gherkin
Feature: Feature Name
  In order to achieve some goal
  As a type of user
  I need to be able to perform some action

  Background:
    Given common setup steps

  Scenario: Scenario Name
    Given some initial context
    When some action is performed
    Then some outcome should be observed
```

### Available Contexts

1. **FeatureContext**: Main context with Laravel integration
   - Database operations
   - Asset and account management
   - Basket operations

2. **LaravelFeatureContext**: Laravel-specific steps
   - Authentication
   - Account operations
   - Transaction handling

3. **ApiContext**: REST API testing
   - HTTP requests
   - Response assertions
   - JSON validation

## Example Features

### Account Management

```gherkin
Feature: Account Management
  Scenario: Creating a new account
    Given I am logged in as "john@example.com"
    When I send a POST request to "/api/accounts"
    Then the response status code should be 201
    And the response should have a "uuid" field
```

### Money Transfers

```gherkin
Feature: Money Transfers
  Scenario: Successful transfer between accounts
    Given I have an account with balance 1000.00 USD
    When I transfer 250.00 USD to account "recipient-uuid"
    Then the transfer should be successful
    And my account balance should be 750.00 USD
```

### Basket Management

```gherkin
Feature: Basket Asset Management
  Scenario: Creating a fixed basket
    When I create a basket "STABLE" with the following components:
      | asset | weight |
      | USD   | 40     |
      | EUR   | 30     |
      | GBP   | 20     |
      | CHF   | 10     |
    Then the basket should be created successfully
```

## Custom Step Definitions

To add custom step definitions, edit the appropriate context file in `features/bootstrap/`:

```php
/**
 * @Given I have a premium account
 */
public function iHaveAPremiumAccount()
{
    $this->currentUser->update(['account_type' => 'premium']);
}
```

## Best Practices

1. **Keep scenarios focused**: Each scenario should test one specific behavior
2. **Use descriptive names**: Scenario names should clearly describe what is being tested
3. **Avoid implementation details**: Focus on business behavior, not technical implementation
4. **Reuse step definitions**: Create generic, reusable steps when possible
5. **Use data tables**: For multiple similar operations, use Gherkin tables
6. **Tag scenarios**: Use tags (@api, @basket, @slow) to organize and filter tests

## Integration with CI/CD

Add Behat tests to your GitHub Actions workflow:

```yaml
- name: Run Behat Tests
  run: |
    ./bin/behat --format=progress --no-colors
```

## Debugging

```bash
# Show step snippets for undefined steps
./bin/behat --append-snippets

# Verbose output
./bin/behat -vvv

# Stop on first failure
./bin/behat --stop-on-failure
```

## Environment Configuration

Behat uses the testing environment by default. Configure test-specific settings in:
- `.env.testing`: Environment variables for testing
- `phpunit.xml`: Database and other test configurations
- `behat.yml`: Behat-specific configuration