# FinAegis Documentation

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
>
> Some documents in this repository describe concepts, plans or demo features; none of them describe a licensed service.

This is the documentation for FinAegis, an open-source reference platform for core banking software. It covers the 61 domain modules, the APIs and how to run the platform. The current release is v7.18.1.

## Start here

| If you want to... | Start with... |
|-------------------|---------------|
| **Try the platform** | [Getting Started](05-USER-GUIDES/GETTING-STARTED.md) |
| **Set up development** | [Development Guide](06-DEVELOPMENT/DEVELOPMENT.md) |
| **Integrate through the API** | [REST API Reference](04-API/REST_API_REFERENCE.md) |
| **Understand the architecture** | [Architecture Overview](02-ARCHITECTURE/ARCHITECTURE.md) |
| **Build AI agents** | [AI Framework](13-AI-FRAMEWORK/README.md) |
| **Run a deployment** | [Deployment Guide](10-OPERATIONS/DEPLOYMENT_GUIDE.md) and the [runbooks](#runbooks) |
| **See what changed** | [Changelog](../CHANGELOG.md) and [Version Roadmap](VERSION_ROADMAP.md) |

## Documentation by topic

### Vision and strategy
- [GCU Vision](01-VISION/GCU_VISION.md) describes the GCU demo concept. GCU is a software demonstration that is not issued or offered.
- [Platform Vision](01-VISION/UNIFIED_PLATFORM_VISION.md) describes the overall platform.
- [Roadmap](01-VISION/ROADMAP.md) lists the development phases.

### Architecture
- [System Architecture](02-ARCHITECTURE/ARCHITECTURE.md) gives the technical overview.
- [Multi-Tenancy](V2.0.0_MULTI_TENANCY_ARCHITECTURE.md) explains team-based tenant isolation.
- [Multi-Asset Support](02-ARCHITECTURE/MULTI_ASSET_ARCHITECTURE.md) explains how several currencies and assets are handled.
- [Workflow Patterns](02-ARCHITECTURE/WORKFLOW_PATTERNS.md) explains sagas and orchestration.
- [Threat Model](THREAT_MODEL.md) lists the security threats and how they are handled.

### Features
- [Feature Overview](03-FEATURES/FEATURES.md) lists the features.
- [Exchange Trading](03-FEATURES/EXCHANGE.md) describes the demo trading engine.
- [Treasury Management](03-FEATURES/TREASURY-MANAGEMENT.md) describes cash-allocation modelling.
- [Liquidity Pools](03-FEATURES/LIQUIDITY-POOLS.md) describes pools and swaps in the demo trading engine.
- [Demo Mode](03-FEATURES/DEMO-MODE.md) describes the demo environment.

### API reference
- [REST API](04-API/REST_API_REFERENCE.md) documents the REST endpoints. A running deployment also serves the OpenAPI documentation at `/api/documentation`.
- The GraphQL API is served at `/graphql`. [`graphql/schema.graphql`](../graphql/schema.graphql) imports 43 domain schemas plus `directives` and `subscriptions`.
- [WebSocket Streaming](04-API/REST_API_REFERENCE.md#websocket-streaming) describes the real-time events.
- [BIAN API](04-API/BIAN_API_DOCUMENTATION.md) describes the endpoints modelled on the BIAN banking standard.
- [Webhook Integration](04-API/WEBHOOK_INTEGRATION.md) explains event notifications.
- [CQRS Implementation](04-API/CQRS_IMPLEMENTATION.md) explains how commands and queries are separated.

### Mobile app backend
- [Mobile API Compatibility](MOBILE_API_COMPATIBILITY.md) lists the endpoints the Zelta mobile app uses.
- [Mobile App Specification](MOBILE_APP_SPECIFICATION.md) describes the mobile app and its backend contract.
- [Cards, identity checks and ramp handover](BACKEND_HANDOVER_CARDS_KYC_RAMP.md) describes the card, identity check and fiat ramp endpoints for the mobile team.
- [FinCard Mobile Integration](FINCARD_MOBILE_INTEGRATION.md) describes the FinCard card endpoints.
- [RAILGUN Mobile Integration](RAILGUN_MOBILE_INTEGRATION.md) describes the support services for the on-device RAILGUN privacy engine.

### User guides
- [Getting Started](05-USER-GUIDES/GETTING-STARTED.md) covers the first steps.
- [Demo Guide](05-USER-GUIDES/DEMO-USER-GUIDE.md) walks through the demo environment.
- [Wallet Management](05-USER-GUIDES/WALLET_MANAGEMENT_GUIDE.md) explains how to manage wallets.
- [Exchange Trading](05-USER-GUIDES/EXCHANGE_ENGINE_GUIDE.md) explains the demo trading engine.
- [P2P Lending](05-USER-GUIDES/P2P_LENDING_GUIDE.md) was withdrawn. [REGULATORY-CLAIMS.md](REGULATORY-CLAIMS.md) explains why.
- [Stablecoins](05-USER-GUIDES/STABLECOIN_GUIDE.md) was withdrawn. [REGULATORY-CLAIMS.md](REGULATORY-CLAIMS.md) explains why.
- [GCU demo guide](05-USER-GUIDES/GCU-USER-GUIDE.md) explains the Global Currency Unit software demonstration. It is simulated and is not issued, offered or sold.

### Development
- [Development Setup](06-DEVELOPMENT/DEVELOPMENT.md) explains how to set up the environment.
- [Testing Guide](06-DEVELOPMENT/TESTING_GUIDE.md) explains the test patterns and tools.
- [Demo Environment](06-DEVELOPMENT/DEMO-ENVIRONMENT.md) explains how demo mode works for developers.

### Developer resources
- [API Integration Guide](09-DEVELOPER/API-INTEGRATION-GUIDE.md) explains integration patterns.
- [SDK Guide](09-DEVELOPER/SDK-GUIDE.md) explains the JavaScript, PHP and Python SDKs.
- [API Examples](09-DEVELOPER/API-EXAMPLES.md) has code examples.
- The [Postman Collection](../public/postman/Zelta-API.postman_collection.json) is generated from the OpenAPI specification. [09-DEVELOPER/README.md](09-DEVELOPER/README.md#testing) explains how to regenerate it.

### Operations
- [Deployment Guide](10-OPERATIONS/DEPLOYMENT_GUIDE.md) explains deployment and configuration.
- [Operational Runbook](10-OPERATIONS/OPERATIONAL_RUNBOOK.md) covers day-to-day operations.
- [Performance Optimization](10-OPERATIONS/PERFORMANCE-OPTIMIZATION.md) covers performance tuning.
- [Security Audit Preparation](10-OPERATIONS/SECURITY-AUDIT-PREPARATION.md) explains how to prepare for a security audit.

### Runbooks
These runbooks are for operators of the hosted Zelta deployment.
- [Bridge.xyz ramp](operations/bridge-ramp.md) covers the fiat ramp adapter, its webhooks and its operator commands.
- [HyperSwitch deposits](operations/hyperswitch-deposits.md) covers the experimental card deposit rail, which is off by default.
- [In-app subscriptions](operations/iap-subscriptions.md) covers Apple App Store and Google Play subscriptions.
- [MCP directory submissions](operations/mcp-directory-submissions.md) covers the public MCP server listings.
- [RAILGUN infrastructure](operations/railgun-infra.md) covers the services behind the on-device privacy engine.
- [Reviewer accounts](operations/reviewer-accounts.md) covers app-store review accounts.
- [Wallet sponsorship](operations/wallet-sponsorship.md) covers sponsored gas and Solana fees for wallet sends.

### Design patterns
- [Event Sourcing](12-PATTERNS/EVENT_SOURCING_BEST_PRACTICES.md) explains the event sourcing patterns.
- [Workflow Orchestration](12-PATTERNS/WORKFLOW_ORCHESTRATION.md) explains the workflow patterns.

### AI framework
- [Overview](13-AI-FRAMEWORK/00-Overview.md) introduces the AI framework.
- [MCP Integration](13-AI-FRAMEWORK/MCP_INTEGRATION.md) explains the Model Context Protocol integration.
- [MCP Tool Reference](13-AI-FRAMEWORK/05-MCP-Tool-Reference.md) lists the tools of the public MCP server. The catalog has 14 tools, and the production server exposes 10 of them.
- [Agent Creation](13-AI-FRAMEWORK/02-Agent-Creation.md) explains how to build AI agents.
- [Workflows](13-AI-FRAMEWORK/03-Workflows.md) explains AI workflow development.
- [API Reference](13-AI-FRAMEWORK/05-API-Reference.md) documents the AI API.

### Technical reference
- [Database Schema](14-TECHNICAL/DATABASE_SCHEMA.md) describes the data model.
- [Admin Dashboard](14-TECHNICAL/ADMIN_DASHBOARD.md) describes the Filament admin panel.
- [Architecture Decision Records](adr/README.md) explain the main design choices.

### Troubleshooting
- [Common Issues](08-TROUBLESHOOTING/TROUBLESHOOTING.md) helps with common problems.

## Platform status

FinAegis is an open-source reference platform. The current release is v7.18.1, from 24 November 2026.

### Recent releases
- **v7.18.1** fixed the card pilot after the card partner answered our questions. The identity check now gets the right SDK token and can start. Asset transfers, currency conversions and GCU demo trades now book the right amounts. The daily spending limit chosen when connecting an AI assistant is now saved. The admin Settings and Bank Allocations pages were repaired, and the Bank Operations and Audit Log pages, which could never work, were removed.
- **v7.18.0** added virtual cards through Lepo Finance for a closed pilot. Only users on the pilot list can open a card, and topping up a card from the wallet is built but stays switched off. The release also shows 3-D Secure codes in the app, keeps wallet sends with an unclear network result as submitted, and upgrades the code analyzers. It removed the liquidity add, remove and rebalance flow and the saving of bank accounts on the withdrawal page, because neither ever worked. It also removed the unused contact ticket service.
- **v7.17.1 and v7.17.0** are security releases. They fix the issues in advisories GHSA-rrfq-93h6-29wj and GHSA-385w-q6j4-5qw6.
- **v7.16.0** moved the RAILGUN privacy backend to a non-custodial design in which the device holds every key.

The [changelog](../CHANGELOG.md) has the full history.

### Earlier releases
- v7.x added ISO 20022, Open Banking, payment rails, the ledger and microfinance at the message layer, a Helm chart, Solana support, the public MCP server, non-custodial wallet sends, in-app subscriptions and the Bridge.xyz ramp adapter.
- v6.x added post-quantum cryptography, card-issuing integration adapters, the developer ecosystem, the plugin marketplace and the SDKs.
- v5.x added x402 micropayments, RAILGUN privacy, event streaming, rewards, a fiat ramp adapter and design system v2.
- v4.x added the GraphQL API, Event Store v2, the plugin marketplace and real-time subscriptions.
- v3.x added cross-chain and DeFi adapters and compliance readiness tooling. That tooling covers SOC 2 and PCI DSS evidence helpers and GDPR export and erasure. No certification is held.
- v2.x added multi-tenancy, hardware wallets, the mobile backend, the privacy layer, ERC-4337 and RegTech.
- v1.x laid the foundation with event sourcing, domain-driven design and the core banking domains.

*Named third-party providers: Integration adapters for third-party APIs; no partnership or endorsement implied.*

## Contributing to the docs

1. Put a new document in the numbered directory that fits its topic.
2. Write in plain language.
3. Include code examples where they help.
4. Update this index when you add a document.

---

Questions? [Open an issue](https://github.com/FinAegis/core-banking-prototype-laravel/issues) or start a [discussion](https://github.com/FinAegis/core-banking-prototype-laravel/discussions).
