# FinAegis Platform Documentation

## Quick Start

- **Vision**: [GCU Vision](../GCU_VISION.md) - Start here to understand the platform
- **Setup**: [Main README](../README.md) - Installation and development guide
- **API Docs**: `/api/documentation` when running locally

## Core Documentation

### Platform Architecture
- [Architecture Overview](02-ARCHITECTURE/ARCHITECTURE.md) - System design and patterns
- [Multi-Asset Architecture](02-ARCHITECTURE/MULTI_ASSET_ARCHITECTURE.md) - Currency basket implementation
- [Database Schema](04-TECHNICAL/DATABASE_SCHEMA.md) - Complete data model

### Key Features
- [Feature Matrix](03-FEATURES/FEATURES.md) - Comprehensive capability list
- [Admin Dashboard](04-TECHNICAL/ADMIN_DASHBOARD.md) - Management interface guide
- [Basket Assets](04-TECHNICAL/BASKET_ASSETS_DESIGN.md) - Currency basket implementation

### Integration Guides
- [Custodian Integration](04-TECHNICAL/CUSTODIAN_INTEGRATION.md) - Bank connector framework
- [Webhook Integration](04-TECHNICAL/WEBHOOK_INTEGRATION.md) - Event notifications
- [API Documentation](API_VOTING_ENDPOINTS.md) - Voting system endpoints

### Implementation Details
- [Enhanced Governance](PHASE_4.2_ENHANCED_GOVERNANCE.md) - User voting system
- [Regulatory Strategy](01-VISION/REGULATORY_STRATEGY.md) - Compliance framework

## Development Workflow

### 1. Understanding the Platform
1. Read [GCU Vision](../GCU_VISION.md) for business context
2. Review [Architecture](02-ARCHITECTURE/ARCHITECTURE.md) for technical design
3. Check [Features](03-FEATURES/FEATURES.md) for capabilities

### 2. Setting Up Development
1. Follow [Main README](../README.md) for installation
2. Run seeders to populate test data including GCU basket
3. Access admin panel at `/admin` (create user with `php artisan make:filament-user`)

### 3. Key Commands
```bash
# Testing
./vendor/bin/pest --parallel

# Basket Management
php artisan baskets:rebalance
php artisan baskets:performance

# Voting Setup
php artisan voting:setup

# Cache Management
php artisan cache:warmup
```

### 4. API Integration
- Base URL: `http://localhost:8000/api`
- Authentication: Laravel Sanctum
- Documentation: `/api/documentation`
- Key endpoints: accounts, assets, polls, custodians

## Quick Links

- **GitHub**: [FinAegis/core-banking-prototype-laravel](https://github.com/FinAegis/core-banking-prototype-laravel)
- **Roadmap**: [ROADMAP.md](../ROADMAP.md)
- **Contributing**: [CONTRIBUTING.md](../CONTRIBUTING.md)
- **License**: [LICENSE](../LICENSE)

---

*For detailed technical documentation, explore the subdirectories. For business context, see [GCU Vision](../GCU_VISION.md).*