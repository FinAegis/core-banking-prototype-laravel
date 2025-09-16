# Technical Documentation

This directory contains detailed technical documentation for system components.

## Contents

- **[ADMIN_DASHBOARD.md](ADMIN_DASHBOARD.md)** - Filament admin dashboard features and customization
- **[BASKET_ASSETS_DESIGN.md](BASKET_ASSETS_DESIGN.md)** - Basket asset implementation and design patterns
- **[CGO_DOCUMENTATION.md](CGO_DOCUMENTATION.md)** - Continuous Growth Offering technical implementation
- **[CUSTODIAN_INTEGRATION.md](CUSTODIAN_INTEGRATION.md)** - Bank custodian integration guide and patterns
- **[DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)** - Database schema documentation and relationships

## Purpose

These documents provide technical details on:
- Component implementation patterns
- Database design and relationships
- Integration patterns with external systems
- Admin interface customization
- Technical specifications for complex features
- Performance optimization strategies

## Current Technical Status (September 2024)

### Recently Implemented Technical Components
- ✅ **CGO Event Sourcing**: Custom event repository and aggregates
- ✅ **Payment Integration**: Stripe and Coinbase Commerce services
- ✅ **KYC/AML Service**: Tiered verification system
- ✅ **PDF Generation**: Investment agreements and certificates
- ✅ **Refund Processing**: Event-sourced refund workflows
- ✅ **Circuit Breaker Pattern**: External service resilience
- ✅ **Webhook Verification**: Secure webhook handling

### Core Technical Components
- ✅ **Event Sourcing**: Full implementation with Spatie package
- ✅ **CQRS Pattern**: Separated read/write models
- ✅ **Domain-Driven Design**: 8 bounded contexts
- ✅ **Saga Pattern**: Distributed transaction management
- ✅ **Multi-Asset Architecture**: Flexible asset management
- ✅ **Caching Layer**: Redis-based performance optimization
- ✅ **Queue System**: Asynchronous job processing