# FinAegis Documentation

Welcome to the FinAegis documentation. This guide will help you understand, use, and contribute to the platform.

## Start Here

| If you want to... | Start with... |
|-------------------|---------------|
| **Try the platform** | [Getting Started](05-USER-GUIDES/GETTING-STARTED.md) |
| **Set up development** | [Development Guide](06-DEVELOPMENT/DEVELOPMENT.md) |
| **Integrate via API** | [REST API Reference](04-API/REST_API_REFERENCE.md) |
| **Understand the architecture** | [Architecture Overview](02-ARCHITECTURE/ARCHITECTURE.md) |
| **Build AI agents** | [AI Framework](13-AI-FRAMEWORK/README.md) |

## Documentation by Topic

### Vision & Strategy
- [GCU Vision](01-VISION/GCU_VISION.md) - Global Currency Unit concept
- [Platform Vision](01-VISION/UNIFIED_PLATFORM_VISION.md) - Overall platform architecture
- [Roadmap](01-VISION/ROADMAP.md) - Development phases

### Architecture
- [System Architecture](02-ARCHITECTURE/ARCHITECTURE.md) - Technical overview
- [Multi-Tenancy v2.0](V2.0.0_MULTI_TENANCY_ARCHITECTURE.md) - Team-based tenant isolation
- [Multi-Asset Support](02-ARCHITECTURE/MULTI_ASSET_ARCHITECTURE.md) - Multi-currency implementation
- [Workflow Patterns](02-ARCHITECTURE/WORKFLOW_PATTERNS.md) - Saga and orchestration patterns

### Features
- [Feature Overview](03-FEATURES/FEATURES.md) - Complete feature list
- [Exchange Trading](03-FEATURES/EXCHANGE.md) - Trading engine
- [Treasury Management](03-FEATURES/TREASURY-MANAGEMENT.md) - Bank allocation
- [Liquidity Pools](03-FEATURES/LIQUIDITY-POOLS.md) - AMM and pools
- [Demo Mode](03-FEATURES/DEMO-MODE.md) - Demo environment features

### API Reference
- [REST API v2.1](04-API/REST_API_REFERENCE.md) - Complete API documentation
- [Hardware Wallet API](04-API/REST_API_REFERENCE.md#hardware-wallet) - Ledger/Trezor integration
- [WebSocket Streaming](04-API/REST_API_REFERENCE.md#websocket) - Real-time events
- [BIAN API](04-API/BIAN_API_DOCUMENTATION.md) - Banking industry standard
- [Webhook Integration](04-API/WEBHOOK_INTEGRATION.md) - Event notifications
- [CQRS Implementation](04-API/CQRS_IMPLEMENTATION.md) - Command/Query separation
- [GraphQL API](../graphql-playground) - GraphQL API (33 domains)
- [Event Streaming](../config/event-streaming.php) - Event Streaming configuration

### User Guides
- [Getting Started](05-USER-GUIDES/GETTING-STARTED.md) - First steps
- [Demo Guide](05-USER-GUIDES/DEMO-USER-GUIDE.md) - Demo environment walkthrough
- [Wallet Management](05-USER-GUIDES/WALLET_MANAGEMENT_GUIDE.md) - Managing wallets
- [Exchange Trading](05-USER-GUIDES/EXCHANGE_ENGINE_GUIDE.md) - Trading guide
- [P2P Lending](05-USER-GUIDES/P2P_LENDING_GUIDE.md) - Lending platform
- [Stablecoins](05-USER-GUIDES/STABLECOIN_GUIDE.md) - Token management
- [Liquidity Pools](05-USER-GUIDES/LIQUIDITY_POOLS_GUIDE.md) - AMM guide
- [GCU Guide](05-USER-GUIDES/GCU-USER-GUIDE.md) - Global Currency Unit
- [CGO Investment](05-USER-GUIDES/CGO-USER-GUIDE.md) - Investment platform

### Development
- [Development Setup](06-DEVELOPMENT/DEVELOPMENT.md) - Environment setup
- [Testing Guide](06-DEVELOPMENT/TESTING_GUIDE.md) - Test patterns and tools
- [Demo Environment](06-DEVELOPMENT/DEMO-ENVIRONMENT.md) - Demo mode development

### Technical Reference
- [Database Schema](14-TECHNICAL/DATABASE_SCHEMA.md) - Data model
- [Admin Dashboard](14-TECHNICAL/ADMIN_DASHBOARD.md) - Filament admin
- [CGO Documentation](14-TECHNICAL/CGO_DOCUMENTATION.md) - Investment platform

### Developer Resources
- [API Integration Guide](09-DEVELOPER/API-INTEGRATION-GUIDE.md) - Integration patterns
- [SDK Guide](09-DEVELOPER/SDK-GUIDE.md) - SDK usage
- [API Examples](09-DEVELOPER/API-EXAMPLES.md) - Code examples
- [Postman Collection](09-DEVELOPER/finaegis-api-v2.postman_collection.json) - API testing

### Operations
- [Performance Optimization](10-OPERATIONS/PERFORMANCE-OPTIMIZATION.md) - Performance tuning
- [Security Audit Prep](10-OPERATIONS/SECURITY-AUDIT-PREPARATION.md) - Security guidelines

### Design Patterns
- [Event Sourcing](12-PATTERNS/EVENT_SOURCING_BEST_PRACTICES.md) - Event sourcing patterns
- [Workflow Orchestration](12-PATTERNS/WORKFLOW_ORCHESTRATION.md) - Workflow patterns

### AI Framework
- [Overview](13-AI-FRAMEWORK/00-Overview.md) - AI framework introduction
- [MCP Integration](13-AI-FRAMEWORK/01-MCP-Integration.md) - Model Context Protocol
- [Agent Creation](13-AI-FRAMEWORK/02-Agent-Creation.md) - Building AI agents
- [Workflows](13-AI-FRAMEWORK/03-Workflows.md) - AI workflow development
- [Event Sourcing](13-AI-FRAMEWORK/04-Event-Sourcing.md) - AI event patterns
- [API Reference](13-AI-FRAMEWORK/05-API-Reference.md) - AI API documentation
- [Agent Protocol Phases](13-AI-FRAMEWORK/) - Implementation phases

### Troubleshooting
- [Common Issues](08-TROUBLESHOOTING/TROUBLESHOOTING.md) - Problem resolution

### CGO (Continuous Growth Offering)
- [Analysis Report](16-CGO/CGO_ANALYSIS_REPORT.md) - CGO analysis
- [Implementation Plan](16-CGO/CGO_IMPLEMENTATION_PLAN.md) - Development plan
- [Refund Processing](16-CGO/CGO_REFUND_PROCESSING.md) - Refund handling

## Platform Status

- **Version**: 5.1.0 (Mobile API Completeness)
- **Status**: Demonstration Prototype
- **Last Updated**: February 16, 2026

### Current Release Features (v5.1.0)
- **v5.1.0**: 21 mobile API endpoints, GraphQL 33-domain full coverage, blockchain address models, CI hardening
- **Event Streaming**: Redis Streams publisher/consumer for real-time event distribution
- **Live Dashboard**: 5 metrics endpoints for real-time platform monitoring
- **Multi-Channel Notification System**: Email, push, in-app, webhook, SMS notification channels
- **API Gateway Middleware**: Request ID tracing, timing headers for observability
- **GraphQL Schema Expansion**: 33 domain schemas (up from 14 in v4.3.0)

### Previous Releases
- v4.3.0: Developer Experience (GraphQL Fraud/Banking/Mobile/TrustCert, CLI commands, security hardening)
- v4.2.0: Real-time Platform (GraphQL subscriptions, plugin hook system, webhook/audit plugins)
- v4.1.0: GraphQL Expansion (6 new domains, event replay filtering, projector health monitoring)
- v4.0.0: Architecture Evolution (Event Store v2, GraphQL API foundation, Plugin Marketplace)
- v3.5.0: Compliance Certification (SOC 2, PCI DSS, multi-region, GDPR enhanced)
- v3.4.0: API Maturity & DX (API versioning, rate limiting, SDK generation, OpenAPI)
- v3.3.0: Event Store Optimization & Observability
- v3.2.0: Production Readiness & Plugin Architecture
- v3.1.0: Consolidation & Documentation
- v3.0.0: Cross-Chain & DeFi
- v2.8.0-v2.9.0: AI, RegTech, BaaS, ML Fraud Detection
- v2.6.0: Privacy Layer & ERC-4337 (Merkle Trees, Smart Accounts, Gas Station, UserOp Signing)
- v2.5.0: Mobile App Launch (Expo/React Native, separate repository)
- v2.4.0: Privacy & Identity (Key Management, ZK-KYC, Commerce, TrustCert)
- v2.3.0: AI-Powered Banking, RegTech Automation, Embedded Finance (BaaS)
- v2.2.0: Mobile Backend (Device Management, Biometrics, Push Notifications)
- v2.1.0: Hardware Wallets, Multi-Sig, WebSocket Streaming, Kubernetes
- v2.0.0: Multi-Tenancy with Team-Based Isolation

This is an educational prototype demonstrating modern banking architecture patterns. It's not production-ready.

## Contributing to Docs

1. Place documentation in the appropriate numbered directory
2. Use clear, concise language
3. Include code examples where helpful
4. Update this index when adding new docs

---

Questions? [Open an issue](https://github.com/finaegis/core-banking-prototype-laravel/issues) or start a [discussion](https://github.com/finaegis/core-banking-prototype-laravel/discussions).
