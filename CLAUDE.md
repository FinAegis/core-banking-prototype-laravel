# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Important: Documentation Structure

**CRITICAL**: The comprehensive documentation has been reorganized into a structured format in the `docs/` directory. Please review ALL documentation sections to understand the full context:

- **[docs/01-VISION/](docs/01-VISION/)** - Strategic vision, GCU implementation, and roadmap
- **[docs/02-ARCHITECTURE/](docs/02-ARCHITECTURE/)** - System architecture, patterns, and design decisions
- **[docs/03-FEATURES/](docs/03-FEATURES/)** - Feature list and release notes
- **[docs/04-API/](docs/04-API/)** - Complete REST API reference and integration guides
- **[docs/05-TECHNICAL/](docs/05-TECHNICAL/)** - Technical specifications and implementation details
- **[docs/06-DEVELOPMENT/CLAUDE.md](docs/06-DEVELOPMENT/CLAUDE.md)** - Detailed development patterns and examples
- **[docs/07-IMPLEMENTATION/](docs/07-IMPLEMENTATION/)** - Phase-specific implementation guides
- **[docs/README.md](docs/README.md)** - Documentation index and navigation guide

**NOTE**: The full development guide with code examples and patterns is in [docs/06-DEVELOPMENT/CLAUDE.md](docs/06-DEVELOPMENT/CLAUDE.md).

## Recent Updates (Phase 5.2 - Error Handling)

The following resilience patterns have been implemented for bank connector failures:

### Circuit Breaker Service
- Located at: `app/Domain/Custodian/Services/CircuitBreakerService.php`
- Prevents cascading failures by stopping requests to failing services
- Configurable thresholds and timeouts

### Retry Service
- Located at: `app/Domain/Custodian/Services/RetryService.php`
- Implements exponential backoff with jitter
- Configurable retry attempts and delays

### Fallback Service
- Located at: `app/Domain/Custodian/Services/FallbackService.php`
- Provides graceful degradation when services are unavailable
- Supports cached data and alternative routing

### Health Monitoring
- Located at: `app/Domain/Custodian/Services/CustodianHealthMonitor.php`
- Real-time health tracking for all custodian services
- Automatic circuit breaker integration

## Quick Reference

### Running Tests
```bash
./vendor/bin/pest --parallel
```

### Key Locations
- Domain code: `app/Domain/`
- API controllers: `app/Http/Controllers/Api/`
- Models: `app/Models/`
- Tests: `tests/`
- Config: `config/`

### Current Architecture
- Event Sourcing with spatie/laravel-event-sourcing
- CQRS pattern implementation
- Saga pattern workflows with waterhole/waterline
- Multi-asset support with exchange rates
- Basket assets for currency composition
- Real bank integration with resilience patterns

## AI-Friendly Development

**FinAegis welcomes contributions from AI coding assistants!** This project is designed to be highly compatible with AI agents including Claude Code, GitHub Copilot, Cursor, and other AI coding tools. The domain-driven design, comprehensive documentation, and well-structured patterns make it easy for AI agents to understand and contribute meaningfully to the codebase.

### Contribution Requirements for AI-Generated Code
All contributions (human or AI-generated) must include:
- **Full test coverage**: Every new feature, workflow, or significant change must have comprehensive tests
- **Complete documentation**: Update relevant documentation files and add inline documentation for complex logic
- **Code quality**: Follow existing patterns and maintain the established architecture principles
- **Always update or create new tests and update documentation whenever you're doing something**

---

**For the complete development guide, see [docs/06-DEVELOPMENT/CLAUDE.md](docs/06-DEVELOPMENT/CLAUDE.md)**