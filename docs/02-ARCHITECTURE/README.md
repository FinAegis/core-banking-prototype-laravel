# Architecture Documentation

This directory contains technical architecture documentation for the FinAegis platform.

## Contents

- **[ARCHITECTURE.md](ARCHITECTURE.md)** - Core system architecture, including domain-driven design, event sourcing, and CQRS patterns
- **[MULTI_ASSET_ARCHITECTURE.md](MULTI_ASSET_ARCHITECTURE.md)** - Multi-asset support architecture and implementation details
- **[CRYPTO_EXCHANGE_ARCHITECTURE.md](CRYPTO_EXCHANGE_ARCHITECTURE.md)** - Cryptocurrency exchange integration architecture
- **[WORKFLOW_PATTERNS.md](WORKFLOW_PATTERNS.md)** - Workflow orchestration patterns using the Saga pattern

## Purpose

These documents provide technical guidance on:
- System architecture and design patterns
- Domain boundaries and responsibilities
- Event sourcing implementation
- CQRS (Command Query Responsibility Segregation) patterns
- Multi-asset support design
- Workflow orchestration and compensation
- Integration patterns and best practices
- Cryptocurrency exchange integration
- Payment processing architecture

## Current Architecture Status (September 2024)

### Implemented Architecture Components
- ✅ **Domain-Driven Design**: Complete with 8 bounded contexts
- ✅ **Event Sourcing**: Full implementation with Spatie Laravel Event Sourcing
- ✅ **CQRS Pattern**: Separate read/write models across all domains
- ✅ **Saga Pattern**: Compensation workflows for distributed transactions
- ✅ **Multi-Asset Support**: Complete architecture for fiat and crypto assets
- ✅ **Event Store**: Custom repositories per domain (e.g., CgoEventRepository)
- ✅ **API-First Architecture**: RESTful APIs for all operations

### Recent Architectural Additions
- **CGO Domain**: Continuous Growth Offering with event sourcing
- **Payment Integration**: Stripe and Coinbase Commerce services
- **Refund Processing**: Event-sourced refund workflows
- **Circuit Breaker Pattern**: For external service resilience
- **Webhook System**: For real-time event notifications