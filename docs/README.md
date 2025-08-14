# FinAegis Platform Documentation

Welcome to the **FinAegis Core Banking Platform** documentation - a comprehensive guide for our modern banking platform with event sourcing, DDD, and workflow orchestration.

📚 **[View Complete Documentation Index](INDEX.md)** - Quick access to all documentation organized by category

## 🏛️ Platform Overview

**FinAegis** is a production-ready banking platform featuring:

**🌟 Core Features:**
- **Global Currency Unit (GCU)** - Democratic digital currency with basket-based valuation
- **Multi-Currency Support** - USD, EUR, GBP, and custom tokens
- **Event Sourcing Architecture** - Complete audit trail and event-driven design
- **Workflow Orchestration** - Saga pattern with compensation support

**⚙️ Key Modules:**
- **Exchange Trading** - Order matching engine with liquidity pools
- **P2P Lending** - Automated loan workflows with credit scoring
- **Stablecoin Framework** - Collateralized token issuance and management
- **Treasury Management** - Multi-bank allocation and reconciliation

## 🚦 Platform Status

- **Current Version**: 2.0.0
- **Environment**: Demo Platform - Educational Use
- **Demo Mode**: ✅ Fully Implemented
- **Sandbox Mode**: ✅ Available for testing
- **Production**: 🚧 Requires third-party integrations

## 📚 Documentation Structure

### [01-VISION](01-VISION/)
Strategic and business documentation
- **[GCU_VISION.md](01-VISION/GCU_VISION.md)** - Global Currency Unit flagship product vision
- **[UNIFIED_PLATFORM_VISION.md](01-VISION/UNIFIED_PLATFORM_VISION.md)** - FinAegis unified platform architecture and sub-products
- **[FINANCIAL_INSTITUTION_REQUIREMENTS.md](01-VISION/FINANCIAL_INSTITUTION_REQUIREMENTS.md)** - Banking partner requirements for GCU participation
- **[ROADMAP.md](01-VISION/ROADMAP.md)** - Development phases and current status
- **[REGULATORY_STRATEGY.md](01-VISION/REGULATORY_STRATEGY.md)** - Compliance and regulatory approach

### [02-ARCHITECTURE](02-ARCHITECTURE/)
Technical architecture and design patterns
- **[ARCHITECTURE.md](02-ARCHITECTURE/ARCHITECTURE.md)** - System architecture overview
- **[MULTI_ASSET_ARCHITECTURE.md](02-ARCHITECTURE/MULTI_ASSET_ARCHITECTURE.md)** - Multi-currency implementation
- **[WORKFLOW_PATTERNS.md](02-ARCHITECTURE/WORKFLOW_PATTERNS.md)** - Saga pattern and workflows

### [03-FEATURES](03-FEATURES/)
Feature documentation and release information
- **[FEATURES.md](03-FEATURES/FEATURES.md)** - Complete feature reference
- **[RELEASE_NOTES.md](03-FEATURES/RELEASE_NOTES.md)** - Version history and changelog

### [04-API](04-API/)
API documentation and integration guides
- **[REST_API_REFERENCE.md](04-API/REST_API_REFERENCE.md)** - Complete REST API endpoints (v2.0)
- **[BIAN_API_DOCUMENTATION.md](04-API/BIAN_API_DOCUMENTATION.md)** - BIAN-compliant API
- **[API_VOTING_ENDPOINTS.md](04-API/API_VOTING_ENDPOINTS.md)** - Governance API
- **[WEBHOOK_INTEGRATION.md](04-API/WEBHOOK_INTEGRATION.md)** - Webhook system

### [09-DEVELOPER](09-DEVELOPER/)
Developer resources and tools
- **[API-INTEGRATION-GUIDE.md](09-DEVELOPER/API-INTEGRATION-GUIDE.md)** - API integration guide
- **[SDK-GUIDE.md](09-DEVELOPER/SDK-GUIDE.md)** - SDK documentation
- **[API-EXAMPLES.md](09-DEVELOPER/API-EXAMPLES.md)** - API usage examples
- **[finaegis-api-v2.postman_collection.json](09-DEVELOPER/finaegis-api-v2.postman_collection.json)** - Postman collection

### [10-OPERATIONS](10-OPERATIONS/)
Operations and performance guides
- **[PERFORMANCE-OPTIMIZATION.md](10-OPERATIONS/PERFORMANCE-OPTIMIZATION.md)** - Performance guide
- **[SECURITY-AUDIT-PREPARATION.md](10-OPERATIONS/SECURITY-AUDIT-PREPARATION.md)** - Security guide

### [05-TECHNICAL](05-TECHNICAL/)
Technical specifications and implementation details
- **[DATABASE_SCHEMA.md](05-TECHNICAL/DATABASE_SCHEMA.md)** - Database structure
- **[ADMIN_DASHBOARD.md](05-TECHNICAL/ADMIN_DASHBOARD.md)** - Filament admin panel
- **[BASKET_ASSETS_DESIGN.md](05-TECHNICAL/BASKET_ASSETS_DESIGN.md)** - Basket implementation
- **[CUSTODIAN_INTEGRATION.md](05-TECHNICAL/CUSTODIAN_INTEGRATION.md)** - Bank connectors

### [05-USER-GUIDES](05-USER-GUIDES/)
End-user documentation and guides
- **[README.md](05-USER-GUIDES/README.md)** - User guide index
- **[LIQUIDITY_POOLS_GUIDE.md](05-USER-GUIDES/LIQUIDITY_POOLS_GUIDE.md)** - Liquidity pool management
- **[P2P_LENDING_GUIDE.md](05-USER-GUIDES/P2P_LENDING_GUIDE.md)** - P2P lending platform guide
- **[STABLECOIN_GUIDE.md](05-USER-GUIDES/STABLECOIN_GUIDE.md)** - Stablecoin minting and management

### [06-DEVELOPMENT](06-DEVELOPMENT/)
Development guides and tools
- **[DEVELOPMENT.md](06-DEVELOPMENT/DEVELOPMENT.md)** - Developer setup guide
- **[CLAUDE.md](06-DEVELOPMENT/CLAUDE.md)** - AI assistant development guide (v8.0)
- **[TESTING_GUIDE.md](06-DEVELOPMENT/TESTING_GUIDE.md)** - Comprehensive testing guide
- **[BEHAT.md](06-DEVELOPMENT/BEHAT.md)** - BDD testing guide
- **[DEMO.md](06-DEVELOPMENT/DEMO.md)** - Demo environment setup
- **[DEMO_MODE_IMPLEMENTATION_SUMMARY.md](../DEMO_MODE_IMPLEMENTATION_SUMMARY.md)** - Demo mode implementation details

### [11-USER-GUIDES](11-USER-GUIDES/)
End-user documentation
- **[GETTING-STARTED.md](11-USER-GUIDES/GETTING-STARTED.md)** - User onboarding guide
- **[GCU-USER-GUIDE.md](11-USER-GUIDES/GCU-USER-GUIDE.md)** - GCU platform guide

### [07-IMPLEMENTATION](07-IMPLEMENTATION/)
Implementation details and phase documentation
- **[API_IMPLEMENTATION.md](07-IMPLEMENTATION/API_IMPLEMENTATION.md)** - API implementation notes
- **[IMPLEMENTATION_SUMMARY.md](07-IMPLEMENTATION/IMPLEMENTATION_SUMMARY.md)** - Implementation summary
- **[PHASE_4.2_ENHANCED_GOVERNANCE.md](07-IMPLEMENTATION/PHASE_4.2_ENHANCED_GOVERNANCE.md)** - Governance implementation
- **[PHASE_5.2_TRANSACTION_PROCESSING.md](07-IMPLEMENTATION/PHASE_5.2_TRANSACTION_PROCESSING.md)** - Transaction processing
- **[LITAS_INTEGRATION_ANALYSIS.md](07-IMPLEMENTATION/LITAS_INTEGRATION_ANALYSIS.md)** - Sub-product integration analysis (archived)

### [08-OPERATIONS](08-OPERATIONS/)
Operational procedures
- Deployment guides (coming soon)
- Monitoring setup (coming soon)
- Backup procedures (coming soon)

### Production & Deployment
- **[PRODUCTION_READINESS_REPORT.md](PRODUCTION_READINESS_REPORT.md)** - Current production status
- **[PRODUCTION_READINESS_CHECKLIST.md](PRODUCTION_READINESS_CHECKLIST.md)** - Launch requirements checklist
- **[DEMO_ENVIRONMENT_PLAN.md](DEMO_ENVIRONMENT_PLAN.md)** - Demo implementation strategy

### [TROUBLESHOOTING.md](TROUBLESHOOTING.md)
Common issues and solutions guide

### [archive](archive/)
Historical and deprecated documentation

## 🚀 Quick Start

### For Developers
1. Start with [DEVELOPMENT.md](06-DEVELOPMENT/DEVELOPMENT.md) for setup
2. Review [ARCHITECTURE.md](02-ARCHITECTURE/ARCHITECTURE.md) for system design
3. Check [API-INTEGRATION-GUIDE.md](09-DEVELOPER/API-INTEGRATION-GUIDE.md) for API usage
4. Read [TESTING_GUIDE.md](06-DEVELOPMENT/TESTING_GUIDE.md) for test patterns

### For Users
1. Begin with [User Guide Index](05-USER-GUIDES/README.md)
2. Learn about [Liquidity Pools](05-USER-GUIDES/LIQUIDITY_POOLS_GUIDE.md)
3. Explore [P2P Lending](05-USER-GUIDES/P2P_LENDING_GUIDE.md)
4. Understand [Stablecoins](05-USER-GUIDES/STABLECOIN_GUIDE.md)
5. Review platform vision in [UNIFIED_PLATFORM_VISION.md](01-VISION/UNIFIED_PLATFORM_VISION.md)

### For AI Assistants
1. Use [CLAUDE.md](06-DEVELOPMENT/CLAUDE.md) for development guidance (v8.0)
2. Reference [FEATURES.md](03-FEATURES/FEATURES.md) for all Phase 8 capabilities
3. Check [TESTING_GUIDE.md](06-DEVELOPMENT/TESTING_GUIDE.md) for test requirements

### For Banking Partners
1. Review [FINANCIAL_INSTITUTION_REQUIREMENTS.md](01-VISION/FINANCIAL_INSTITUTION_REQUIREMENTS.md)
2. This is a demonstration platform for educational purposes

## 📋 Documentation Status

- ✅ **Current**: As of Version 8.0 (August 2025)
- 📝 **Last Updated**: July 29, 2025
- 🎯 **Coverage**: All features documented including liquidity pools, P2P lending, stablecoins
- 🔄 **Recent Updates**: Version 8.0 release notes added, outdated January 2025 references updated

## 🤝 Contributing

When adding new documentation:
1. Place files in the appropriate numbered directory
2. Update this index with new entries
3. Keep consistent formatting and style
4. Update RELEASE_NOTES.md for significant changes