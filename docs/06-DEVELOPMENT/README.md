# Development Documentation

This directory holds guides for developers who work on FinAegis. FinAegis is an open-source reference platform, and Zelta is the hosted non-custodial wallet built on it. The current version is v7.18.1.

## Contents

### Core Development Guides
- **[CLAUDE.md](CLAUDE.md)** holds instructions for Claude Code and other AI coding tools.
- **[DEVELOPMENT.md](DEVELOPMENT.md)** covers setup, architecture, conventions and good practices.
- **[DOMAIN_MANAGEMENT.md](DOMAIN_MANAGEMENT.md)** explains the modular domain system and the `domain:*` and `module:*` commands.
- **[INFRASTRUCTURE.md](INFRASTRUCTURE.md)** describes the command bus, query bus and domain event bus.
- **[EVENT-SOURCING-BEST-PRACTICES.md](EVENT-SOURCING-BEST-PRACTICES.md)** collects event sourcing patterns.
- **[PERFORMANCE-OPTIMIZATION.md](PERFORMANCE-OPTIMIZATION.md)** describes ways to keep the platform fast.
- **[EMAIL-SETUP.md](EMAIL-SETUP.md)** explains how to configure email sending.
- **[QUALITY/code-quality-workflow.md](QUALITY/code-quality-workflow.md)** lists the quality checks to run before you push.

### Demo Environment
- **[DEMO.md](DEMO.md)** explains how to set up the demo and its sample data.
- **[DEMO-ENVIRONMENT.md](DEMO-ENVIRONMENT.md)** describes the demo services and how `APP_ENV=demo` switches them on.
- **[DEMO-DEPOSIT.md](DEMO-DEPOSIT.md)** documents the `demo:deposit` command.
- **[DEMO-MODE-REFACTORING.md](DEMO-MODE-REFACTORING.md)** records how demo mode was moved to `APP_ENV=demo`.

### Testing Documentation
- **[TESTING_GUIDE.md](TESTING_GUIDE.md)** explains how tests are organised and run.
- **[TESTING-STRATEGY.md](TESTING-STRATEGY.md)** describes the testing approach and standards.
- **[PARALLEL-TESTING.md](PARALLEL-TESTING.md)** explains parallel test runs and isolation.
- **[BEHAT.md](BEHAT.md)** explains the Behat setup and its suites.

### CGO Demo Module Guides
The CGO module is a demo and not an offer of securities. It is disabled on the production site.
- **[CGO_KYC_AML.md](CGO_KYC_AML.md)** describes the tiered KYC and AML checks for CGO investments.
- **[CGO_INVESTMENT_AGREEMENTS.md](CGO_INVESTMENT_AGREEMENTS.md)** describes how sample agreements and certificates are generated.
- **[CGO_PAYMENT_VERIFICATION.md](CGO_PAYMENT_VERIFICATION.md)** describes payment verification.

### Feature Documentation
- **[FRAUD-DETECTION.md](FRAUD-DETECTION.md)** describes fraud detection.
- **[REGULATORY-REPORTING.md](REGULATORY-REPORTING.md)** describes the regulatory report drafts.
- **[features/fund-flow-visualization.md](features/fund-flow-visualization.md)** describes the fund flow page.

### Planning
- **[MOBILE_APP_PLANNING.md](MOBILE_APP_PLANNING.md)** is the historical mobile app plan from January 2026.

## Purpose

These documents help developers set up their environment and understand the conventions and patterns. They explain how to work with AI coding assistants, run the demo, run the tests and keep the code quality high. They also describe complex features such as KYC and AML checks and payment processing.

## Development Commands Quick Reference
```bash
# Run tests in parallel
./vendor/bin/pest --parallel --stop-on-failure

# Run Behat tests
./bin/behat

# Code quality
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
./vendor/bin/phpcs
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G

# Generate API documentation (commit storage/api-docs/api-docs.json; CI checks it)
APP_URL=http://localhost php artisan l5-swagger:generate

# Create admin user
php artisan user:create --admin

# Populate demo data (demo users, password demo123)
php artisan demo:populate

# Run specific test coverage (local only; CI does not collect coverage)
./vendor/bin/pest --coverage --min=50
```
