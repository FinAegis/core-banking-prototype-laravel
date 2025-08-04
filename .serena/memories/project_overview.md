# FinAegis Core Banking Prototype

## Project Purpose
FinAegis is a comprehensive core banking platform prototype demonstrating modern banking architecture with event sourcing, domain-driven design, and modern banking patterns. It showcases technical architecture for innovative financial products including the Global Currency Unit (GCU) concept - a democratic digital currency backed by real banks.

## Tech Stack
- **Backend**: PHP 8.4+, Laravel 12
- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Cache/Queue**: Redis 6.0+
- **Frontend**: Livewire, Tailwind CSS, Alpine.js
- **Testing**: Pest PHP (parallel testing support)
- **API Documentation**: OpenAPI/Swagger (L5-Swagger)
- **Admin Panel**: Filament 3.0
- **Queue Management**: Laravel Horizon
- **Workflow Engine**: Laravel Workflow with Waterline
- **Event Sourcing**: Spatie Event Sourcing
- **Node.js**: 18+ (for asset compilation with Vite)

## Architecture Patterns
- Domain-Driven Design (DDD)
- Event Sourcing
- CQRS (Command Query Responsibility Segregation)
- Saga Pattern for workflows
- Repository Pattern
- Service Layer Pattern
- Value Objects
- Aggregates

## Key Features
- Multi-asset banking operations (fiat, crypto, commodities)
- Global Currency Unit (GCU) implementation
- Event-driven architecture with audit trails
- Comprehensive workflow orchestration
- Democratic voting system
- Multi-currency exchange system
- Compliance and regulatory features
- API v2.0 with REST endpoints

## Development Environment
- Linux-based development
- GitHub Actions CI/CD
- Self-hosted runners for improved performance
- Comprehensive test coverage requirements (minimum 50%)