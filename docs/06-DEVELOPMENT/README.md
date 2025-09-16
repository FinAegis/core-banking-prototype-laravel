# Development Documentation

This directory contains guides for developers working on the FinAegis platform.

## Contents

### Core Development Guides
- **[CLAUDE.md](CLAUDE.md)** - AI assistant guidance for Claude Code and other AI coding tools
- **[DEVELOPMENT.md](DEVELOPMENT.md)** - Development setup, conventions, and best practices
- **[DEMO.md](DEMO.md)** - Demo environment setup and sample data
- **[DEMO-DEPOSIT.md](DEMO-DEPOSIT.md)** - Demo deposit functionality implementation

### Testing Documentation
- **[BEHAT.md](BEHAT.md)** - Behat testing framework setup and usage
- **[PARALLEL-TESTING.md](PARALLEL-TESTING.md)** - Parallel test execution configuration
- **[TEST_SKIP_ANALYSIS.md](TEST_SKIP_ANALYSIS.md)** - Analysis of skipped tests and coverage gaps

### CGO Implementation Guides
- **[CGO_KYC_AML.md](CGO_KYC_AML.md)** - KYC/AML implementation for CGO investments
- **[CGO_INVESTMENT_AGREEMENTS.md](CGO_INVESTMENT_AGREEMENTS.md)** - Investment agreement generation
- **[CGO_PAYMENT_VERIFICATION.md](CGO_PAYMENT_VERIFICATION.md)** - Payment verification workflows

### Feature Documentation
- **[FRAUD-DETECTION.md](FRAUD-DETECTION.md)** - Fraud detection implementation
- **[REGULATORY-REPORTING.md](REGULATORY-REPORTING.md)** - Regulatory compliance reporting
- **[features/fund-flow-visualization.md](features/fund-flow-visualization.md)** - Fund flow visualization feature

## Purpose

These documents help developers:
- Set up their development environment
- Understand coding conventions and patterns
- Use AI coding assistants effectively
- Create demo environments
- Follow best practices for contributions
- Run tests and maintain code quality
- Implement complex features like KYC/AML and payment processing
- Write comprehensive tests including Behat scenarios

## Current Development Status (September 2024)

### Recently Implemented Features
- ✅ **CGO Complete Implementation**
  - KYC/AML verification with tiered levels
  - Payment integration (Stripe & Coinbase Commerce)
  - Investment agreement PDF generation
  - Event-sourced refund processing
  - Admin dashboard for investment management
  
- ✅ **Testing Infrastructure**
  - Parallel testing configuration
  - Behat testing framework setup
  - Test coverage at 88%
  
- ✅ **Compliance Features**
  - Fraud detection system
  - Regulatory reporting (CTR, SAR)
  - GDPR compliance tools

- ✅ **Visualization Tools**
  - Fund flow visualization with D3.js
  - Interactive account network graphs

### Development Commands Quick Reference
```bash
# Run tests in parallel
./vendor/bin/pest --parallel

# Run Behat tests
./vendor/bin/behat

# Generate API documentation
php artisan l5-swagger:generate

# Create admin user
php artisan make:filament-user

# Run specific test coverage
./vendor/bin/pest --coverage --min=50
```