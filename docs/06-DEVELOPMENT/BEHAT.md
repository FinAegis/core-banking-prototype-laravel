# Behat BDD Testing Guide

This guide explains how to use Behat for Behavior-Driven Development (BDD) testing in FinAegis.

## Installation

Behat is already installed as a development dependency. These packages come with it:
- `behat/behat` is the core BDD framework.
- `behat/mink` is the web acceptance testing framework.
- `behat/mink-browserkit-driver` is the Symfony BrowserKit driver for Mink.
- `dmore/behat-chrome-extension` drives a Chrome or Chromium browser.

`./bin/behat` boots Laravel and then calls `vendor/bin/behat` with your arguments. Feature files live in `features/`.

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

The `behat.yml` configuration defines three suites:
- `acceptance` runs the feature files in `features/` with `FeatureContext` and `LaravelFeatureContext`. It skips scenarios tagged `@web`, `@wip` or `@api`.
- `api` runs the feature files in `features/api/` with `ApiContext`.
- `web` runs the scenarios tagged `@web` with `WebContext` and `SubproductContext`.

Tags decide what runs. A feature tagged `@wip` is a specification that is not implemented yet. To enable it, remove the `@wip` tag. The `ci` profile also skips `@skip`.

Some `@wip` feature files describe features that are not built or were removed. For example, `features/liquidity-pools.feature` still describes adding, removing and rebalancing liquidity, which was removed in v7.18.0.

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

The contexts live in `features/bootstrap/`.

1. `FeatureContext` is the main context with Laravel integration. It covers database setup, assets, accounts and baskets.
2. `LaravelFeatureContext` holds Laravel-specific steps for authentication, accounts and transactions.
3. `ApiContext` tests the REST API. It sends HTTP requests and checks responses and JSON.
4. `WebContext` and `SubproductContext` test web pages, including the promo and sub-product pages.

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

1. Keep each scenario focused on one behaviour.
2. Give scenarios names that say clearly what is tested.
3. Describe business behaviour, not technical implementation.
4. Write generic steps that you can reuse.
5. Use Gherkin tables for several similar operations.
6. Use tags such as `@api`, `@web` and `@wip` to organise and filter tests.

## Integration with CI/CD

The `.github/workflows/behat-tests.yml` workflow runs Behat in CI. It starts a Laravel server and runs `./bin/behat-ci`. Without arguments, that script runs the `acceptance` suite with the `ci` profile, without interaction, and stops on the first failure. That workflow runs on pull requests that change the Behat files. The Behat job in `.github/workflows/03-test-suite.yml` runs the same suite with `vendor/bin/behat --profile=ci --suite=acceptance` on every pipeline run.

```yaml
- name: Run Behat Tests
  env:
    BEHAT_BASE_URL: http://127.0.0.1:8000
    QUEUE_CONNECTION: sync
  run: |
    ./bin/behat-ci
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

Behat uses the testing environment by default. Configure test settings in these files:
- `.env.testing` holds the environment variables for testing.
- `phpunit.xml` holds the database and other test settings.
- `behat.yml` holds the Behat suites, profiles and the Mink base URL (`BEHAT_BASE_URL`).
