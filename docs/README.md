# FinAegis Core Banking Platform Documentation

This directory contains comprehensive documentation for the FinAegis Core Banking Platform, organized into logical sections for easy navigation.

## Documentation Structure

### 📋 [01-VISION](./01-VISION/)
Strategic vision, roadmap, and regulatory strategy documents
- [GCU Vision](./01-VISION/GCU_VISION.md) - Global Currency Unit vision and strategy
- [Roadmap](./01-VISION/ROADMAP.md) - Development phases and milestones
- [Regulatory Strategy](./01-VISION/REGULATORY_STRATEGY.md) - Compliance framework

### 🏗️ [02-ARCHITECTURE](./02-ARCHITECTURE/)
System architecture, design patterns, and technical foundations
- [Architecture Overview](./02-ARCHITECTURE/ARCHITECTURE.md) - System design and patterns
- [Multi-Asset Architecture](./02-ARCHITECTURE/MULTI_ASSET_ARCHITECTURE.md) - Currency basket implementation
- [Workflow Patterns](./02-ARCHITECTURE/WORKFLOW_PATTERNS.md) - Saga pattern implementation

### ✨ [03-FEATURES](./03-FEATURES/)
Platform features, capabilities, and release notes
- [Feature Matrix](./03-FEATURES/FEATURES.md) - Comprehensive capability list
- [Release Notes](./03-FEATURES/RELEASE_NOTES.md) - Version history

### 🔌 [04-API](./04-API/)
REST API documentation, endpoints, and integration guides
- [REST API Reference](./04-API/REST_API_REFERENCE.md) - Complete API documentation
- [API Voting Endpoints](./04-API/API_VOTING_ENDPOINTS.md) - Governance API details
- [Webhook Integration](./04-API/WEBHOOK_INTEGRATION.md) - Event notifications

### ⚙️ [05-TECHNICAL](./05-TECHNICAL/)
Technical specifications, database schema, and component details
- [Admin Dashboard](./05-TECHNICAL/ADMIN_DASHBOARD.md) - Filament admin guide
- [Basket Assets Design](./05-TECHNICAL/BASKET_ASSETS_DESIGN.md) - Basket implementation
- [Custodian Integration](./05-TECHNICAL/CUSTODIAN_INTEGRATION.md) - Bank connectors
- [Database Schema](./05-TECHNICAL/DATABASE_SCHEMA.md) - Complete data model

### 💻 [06-DEVELOPMENT](./06-DEVELOPMENT/)
Development guides, AI assistant instructions, and coding standards
- [CLAUDE.md](./06-DEVELOPMENT/CLAUDE.md) - AI coding assistant guide
- [Development Guide](./06-DEVELOPMENT/DEVELOPMENT.md) - Setup and best practices
- [Demo Guide](./06-DEVELOPMENT/DEMO.md) - Demo environment setup

### 🚀 [07-IMPLEMENTATION](./07-IMPLEMENTATION/)
Phase-specific implementation documentation and guides
- [Phase 4.2 Enhanced Governance](./07-IMPLEMENTATION/PHASE_4.2_ENHANCED_GOVERNANCE.md)
- [Phase 5.2 Transaction Processing](./07-IMPLEMENTATION/PHASE_5.2_TRANSACTION_PROCESSING.md)

### 🛠️ [08-OPERATIONS](./08-OPERATIONS/)
Operational procedures, deployment, and monitoring (planned)

### 📦 [archive](./archive/)
Historical and deprecated documentation

## Quick Start Guide

### 1. Understanding the Platform
1. Read [GCU Vision](./01-VISION/GCU_VISION.md) for business context
2. Review [Architecture](./02-ARCHITECTURE/ARCHITECTURE.md) for technical design
3. Check [Features](./03-FEATURES/FEATURES.md) for capabilities

### 2. Setting Up Development
1. Follow [Main README](../README.md) for installation
2. Read [Development Guide](./06-DEVELOPMENT/DEVELOPMENT.md) for conventions
3. Use [CLAUDE.md](./06-DEVELOPMENT/CLAUDE.md) for AI-assisted development

### 3. Key Commands
```bash
# Testing
./vendor/bin/pest --parallel

# API Documentation
php artisan l5-swagger:generate

# Basket Management
php artisan baskets:rebalance
php artisan baskets:performance

# Voting Setup
php artisan voting:setup

# Cache Management
php artisan cache:warmup

# Custodian Sync
php artisan custodian:sync-balances
```

### 4. API Integration
- Base URL: `http://localhost:8000/api`
- Authentication: Laravel Sanctum
- Documentation: `/api/documentation`
- Full Reference: [REST API Reference](./04-API/REST_API_REFERENCE.md)

## Finding Information

- **Business Context**: Start with [01-VISION](./01-VISION/)
- **Technical Details**: Check [02-ARCHITECTURE](./02-ARCHITECTURE/) and [05-TECHNICAL](./05-TECHNICAL/)
- **API Integration**: See [04-API](./04-API/)
- **Development**: Refer to [06-DEVELOPMENT](./06-DEVELOPMENT/)
- **Specific Features**: Look in [07-IMPLEMENTATION](./07-IMPLEMENTATION/)

## External Links

- **GitHub**: [FinAegis/core-banking-prototype-laravel](https://github.com/FinAegis/core-banking-prototype-laravel)
- **Contributing**: [CONTRIBUTING.md](../CONTRIBUTING.md)
- **License**: [LICENSE](../LICENSE)

---

*For AI-assisted development, see [CLAUDE.md](./06-DEVELOPMENT/CLAUDE.md). For business context, start with [GCU Vision](./01-VISION/GCU_VISION.md).*