# FinAegis Documentation

Welcome to the FinAegis documentation. This guide covers the platform's 49 domain modules, APIs, and operational patterns.

## Start Here

| If you want to... | Start with... |
|-------------------|---------------|
| **Try the platform** | [Getting Started](05-USER-GUIDES/GETTING-STARTED.md) |
| **Set up development** | [Development Guide](06-DEVELOPMENT/DEVELOPMENT.md) |
| **Integrate via API** | [REST API Reference](04-API/REST_API_REFERENCE.md) |
| **Understand the architecture** | [Architecture Overview](02-ARCHITECTURE/ARCHITECTURE.md) |
| **Build AI agents** | [AI Framework](13-AI-FRAMEWORK/README.md) |
| **See the roadmap** | [Version Roadmap](VERSION_ROADMAP.md) |

## Documentation by Topic

### Vision & Strategy
- [GCU Vision](01-VISION/GCU_VISION.md) - Global Currency Unit concept
- [Platform Vision](01-VISION/UNIFIED_PLATFORM_VISION.md) - Overall platform architecture
- [Roadmap](01-VISION/ROADMAP.md) - Development phases

### Architecture
- [System Architecture](02-ARCHITECTURE/ARCHITECTURE.md) - Technical overview
- [Multi-Tenancy](V2.0.0_MULTI_TENANCY_ARCHITECTURE.md) - Team-based tenant isolation
- [Multi-Asset Support](02-ARCHITECTURE/MULTI_ASSET_ARCHITECTURE.md) - Multi-currency implementation
- [Workflow Patterns](02-ARCHITECTURE/WORKFLOW_PATTERNS.md) - Saga and orchestration patterns

### Features
- [Feature Overview](03-FEATURES/FEATURES.md) - Complete feature list
- [Exchange Trading](03-FEATURES/EXCHANGE.md) - Trading engine
- [Treasury Management](03-FEATURES/TREASURY-MANAGEMENT.md) - Bank allocation
- [Liquidity Pools](03-FEATURES/LIQUIDITY-POOLS.md) - AMM and pools
- [Demo Mode](03-FEATURES/DEMO-MODE.md) - Demo environment features

### API Reference
- [REST API](04-API/REST_API_REFERENCE.md) - Complete API documentation (1,250+ routes)
- [GraphQL API](04-API/REST_API_REFERENCE.md#graphql) - 36 domain schemas
- [WebSocket Streaming](04-API/REST_API_REFERENCE.md#websocket) - Real-time events
- [BIAN API](04-API/BIAN_API_DOCUMENTATION.md) - Banking industry standard
- [Webhook Integration](04-API/WEBHOOK_INTEGRATION.md) - Event notifications
- [CQRS Implementation](04-API/CQRS_IMPLEMENTATION.md) - Command/Query separation

### User Guides
- [Getting Started](05-USER-GUIDES/GETTING-STARTED.md) - First steps
- [Demo Guide](05-USER-GUIDES/DEMO-USER-GUIDE.md) - Demo environment walkthrough
- [Wallet Management](05-USER-GUIDES/WALLET_MANAGEMENT_GUIDE.md) - Managing wallets
- [Exchange Trading](05-USER-GUIDES/EXCHANGE_ENGINE_GUIDE.md) - Trading guide
- [P2P Lending](05-USER-GUIDES/P2P_LENDING_GUIDE.md) - Lending platform
- [Stablecoins](05-USER-GUIDES/STABLECOIN_GUIDE.md) - Token management
- [GCU Guide](05-USER-GUIDES/GCU-USER-GUIDE.md) - Global Currency Unit

### Development
- [Development Setup](06-DEVELOPMENT/DEVELOPMENT.md) - Environment setup
- [Testing Guide](06-DEVELOPMENT/TESTING_GUIDE.md) - Test patterns and tools
- [Demo Environment](06-DEVELOPMENT/DEMO-ENVIRONMENT.md) - Demo mode development

### Developer Resources
- [API Integration Guide](09-DEVELOPER/API-INTEGRATION-GUIDE.md) - Integration patterns
- [SDK Guide](09-DEVELOPER/SDK-GUIDE.md) - SDK usage (JS, PHP, Python)
- [API Examples](09-DEVELOPER/API-EXAMPLES.md) - Code examples
- [Postman Collection](09-DEVELOPER/finaegis-api-v2.postman_collection.json) - API testing

### Operations
- [Deployment Guide](10-OPERATIONS/DEPLOYMENT_GUIDE.md) - Deployment and configuration
- [Operational Runbook](10-OPERATIONS/OPERATIONAL_RUNBOOK.md) - Day-to-day operations
- [Performance Optimization](10-OPERATIONS/PERFORMANCE-OPTIMIZATION.md) - Performance tuning
- [Security Audit Prep](10-OPERATIONS/SECURITY-AUDIT-PREPARATION.md) - Security guidelines

### Design Patterns
- [Event Sourcing](12-PATTERNS/EVENT_SOURCING_BEST_PRACTICES.md) - Event sourcing patterns
- [Workflow Orchestration](12-PATTERNS/WORKFLOW_ORCHESTRATION.md) - Workflow patterns

### AI Framework
- [Overview](13-AI-FRAMEWORK/00-Overview.md) - AI framework introduction
- [MCP Integration](13-AI-FRAMEWORK/01-MCP-Integration.md) - Model Context Protocol (17 tools)
- [Agent Creation](13-AI-FRAMEWORK/02-Agent-Creation.md) - Building AI agents
- [Workflows](13-AI-FRAMEWORK/03-Workflows.md) - AI workflow development
- [API Reference](13-AI-FRAMEWORK/05-API-Reference.md) - AI API documentation

### Technical Reference
- [Database Schema](14-TECHNICAL/DATABASE_SCHEMA.md) - Data model
- [Admin Dashboard](14-TECHNICAL/ADMIN_DASHBOARD.md) - Filament admin

### Troubleshooting
- [Common Issues](08-TROUBLESHOOTING/TROUBLESHOOTING.md) - Problem resolution

## Platform Status

- **Version**: 6.1.1
- **Status**: Production-Grade Platform
- **Last Updated**: March 20, 2026

### Current Release (v6.1.x)
- **v6.1.1**: Card transaction sync webhooks, card management REST + GraphQL API, bank transfer state machine
- **v6.1.0**: Post-quantum cryptography (ML-KEM, ML-DSA), Rain card issuing, Open Banking PSD2, tenant provisioning, event streaming DLQ, cross-chain/DeFi production adapters, ZK on-chain verifier
- **v6.0.0**: Developer ecosystem — plugin marketplace, developer portal, 3 official SDKs

### Previous Releases
- v5.x: x402 micropayments, RAILGUN privacy, event streaming, rewards, fiat ramp, design system v2
- v4.x: GraphQL API (36 domains), Event Store v2, Plugin Marketplace, real-time subscriptions
- v3.x: Cross-chain & DeFi, compliance certification (SOC 2, PCI DSS, GDPR), production readiness
- v2.x: Multi-tenancy, hardware wallets, mobile backend, privacy layer, ERC-4337, RegTech
- v1.x: Foundation — event sourcing, DDD, core banking domains

## Contributing to Docs

1. Place documentation in the appropriate numbered directory
2. Use clear, concise language
3. Include code examples where helpful
4. Update this index when adding new docs

---

Questions? [Open an issue](https://github.com/FinAegis/core-banking-prototype-laravel/issues) or start a [discussion](https://github.com/FinAegis/core-banking-prototype-laravel/discussions).
