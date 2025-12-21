# Changelog

All notable changes to the FinAegis Core Banking Platform will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Open source foundation documentation (CONTRIBUTING.md, SECURITY.md, CODE_OF_CONDUCT.md)
- Architecture Decision Records (ADRs) for key design decisions
- Architectural roadmap and implementation plan

### Changed
- Website content updated for open-source accuracy
- Investment components converted to demo-only mode

## [0.9.0] - 2024-12-18

### Added
- **Agent Protocol (AP2/A2A)** - Full implementation of Google's Agent Payments Protocol
  - Agent registration with DID support
  - Escrow service for secure transactions
  - Reputation and trust scoring system
  - A2A messaging infrastructure
  - MCP tools for AI agent integration
  - Protocol negotiation API
  - OAuth2-style agent scopes

### Changed
- AI Framework enhanced with Agent Protocol bridge service
- Multi-agent coordination capabilities

## [0.8.0] - 2024-12-01

### Added
- **Treasury Management Domain**
  - Portfolio management with event sourcing
  - Cash allocation and yield optimization
  - Investment strategy workflows
  - Treasury aggregates with full audit trail

- **Enhanced Compliance Domain**
  - Three-tier KYC verification (Basic, Enhanced, Full)
  - AML screening integration
  - Transaction monitoring with SAR/CTR generation
  - Biometric verification support

### Changed
- Improved event sourcing patterns across domains
- Enhanced saga compensation logic

## [0.7.0] - 2024-11-15

### Added
- **AI Framework**
  - Production-ready MCP server with 20+ banking tools
  - Event-sourced AI interactions
  - Tool execution with audit trail
  - Claude and OpenAI provider support

- **Distributed Tracing**
  - OpenTelemetry integration
  - Cross-domain trace correlation
  - Performance monitoring

### Fixed
- PHPStan level 5 compliance issues
- Test isolation for security tests

## [0.6.0] - 2024-11-01

### Added
- **Governance Domain**
  - Democratic voting system
  - Asset-weighted voting strategy
  - Proposal lifecycle management
  - GCU basket composition voting

- **Stablecoin Domain Enhancements**
  - Multi-collateral support
  - Health monitoring with margin calls
  - Liquidation workflows
  - Position management

## [0.5.0] - 2024-10-15

### Added
- **GCU (Global Currency Unit) Basket**
  - 6-currency basket implementation (USD, EUR, GBP, CHF, JPY, XAU)
  - NAV calculation service
  - Automatic rebalancing with governance
  - Performance tracking

- **Liquidity Pool Enhancements**
  - Spread management saga
  - Market maker workflow
  - Impermanent loss protection
  - AMM (Automated Market Maker) implementation

## [0.4.0] - 2024-10-01

### Added
- **Exchange Domain**
  - Order matching engine with saga pattern
  - Liquidity pool management
  - External exchange connectors (Binance, Kraken)
  - 6-tier fee system
  - 44 domain events

- **Lending Domain**
  - P2P lending platform
  - Credit scoring system
  - Loan lifecycle management
  - Risk assessment workflows

## [0.3.0] - 2024-09-15

### Added
- **Wallet Domain**
  - Multi-chain blockchain support (BTC, ETH, Polygon, BSC)
  - Transaction signing
  - Balance tracking
  - Withdrawal workflows with saga compensation

- **Demo Mode Architecture**
  - Service switching pattern
  - Mock implementations for all external services
  - Demo data seeding
  - Visual demo indicators

## [0.2.0] - 2024-09-01

### Added
- **Account/Banking Domain**
  - Event-sourced account management
  - Multi-asset balance tracking
  - SEPA/SWIFT transfer support
  - Multi-bank connector pattern (Paysera, Deutsche Bank, Santander)
  - Intelligent bank routing

- **CQRS Infrastructure**
  - Command Bus with middleware support
  - Query Bus with caching
  - Domain Event Bus bridging Laravel events

## [0.1.0] - 2024-08-15

### Added
- Initial project structure with Domain-Driven Design
- Event sourcing foundation using Spatie Event Sourcing
- Laravel 12 with PHP 8.4 support
- Filament 3.0 admin panel
- Pest PHP testing framework
- PHPStan level 5 static analysis
- CI/CD pipeline with GitHub Actions

---

## Version History Summary

| Version | Date | Highlights |
|---------|------|------------|
| 0.9.0 | 2024-12-18 | Agent Protocol (AP2/A2A) |
| 0.8.0 | 2024-12-01 | Treasury Management, Enhanced Compliance |
| 0.7.0 | 2024-11-15 | AI Framework, Distributed Tracing |
| 0.6.0 | 2024-11-01 | Governance, Stablecoin Enhancements |
| 0.5.0 | 2024-10-15 | GCU Basket, Liquidity Pools |
| 0.4.0 | 2024-10-01 | Exchange, Lending |
| 0.3.0 | 2024-09-15 | Wallet, Demo Mode |
| 0.2.0 | 2024-09-01 | Account/Banking, CQRS |
| 0.1.0 | 2024-08-15 | Initial Release |

## Upgrade Notes

### From 0.8.x to 0.9.x
- Run `php artisan migrate` for Agent Protocol tables
- Update `.env` with `AGENT_PROTOCOL_*` configuration
- Register AgentProtocolServiceProvider if not auto-discovered

### From 0.7.x to 0.8.x
- Run `php artisan migrate` for Treasury tables
- New compliance configuration in `config/compliance.php`

### From 0.6.x to 0.7.x
- Run `php artisan migrate` for AI Framework tables
- Configure AI providers in `config/ai.php`

[Unreleased]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.9.0...HEAD
[0.9.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.8.0...v0.9.0
[0.8.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/FinAegis/core-banking-prototype-laravel/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/FinAegis/core-banking-prototype-laravel/releases/tag/v0.1.0
