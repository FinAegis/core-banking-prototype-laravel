# Technical Documentation

This folder holds detailed technical documentation for individual parts of the system.

## Contents

- **[ADMIN_DASHBOARD.md](ADMIN_DASHBOARD.md)** describes the Filament admin panel and how to extend it.
- **[BASKET_ASSETS_DESIGN.md](BASKET_ASSETS_DESIGN.md)** explains the design of basket assets in the Basket demo module.
- **[CGO_DOCUMENTATION.md](CGO_DOCUMENTATION.md)** explains the Continuous Growth Offering (CGO) demo module.
- **[CUSTODIAN_INTEGRATION.md](CUSTODIAN_INTEGRATION.md)** shows how bank custodian connectors are built and configured.
- **[DATABASE_SCHEMA.md](DATABASE_SCHEMA.md)** documents the core database tables and their relationships.
- **[WEBHOOK_SECURITY.md](WEBHOOK_SECURITY.md)** explains how incoming webhooks are verified.

## Purpose

These documents explain how components are built and how the database is designed. They also cover integrations with external systems and the admin interface.

## Current Technical Status (v7.18.1)

FinAegis is an open-source reference platform. Zelta is the hosted non-custodial wallet built on it. The current release is v7.18.1, dated 2026-11-24.

### Core Technical Components

- **Domains.** The code is split into 61 domain folders under `app/Domain/`, each a bounded context in the Domain-Driven Design sense.
- **Event sourcing.** Financial state changes are stored as events with `spatie/laravel-event-sourcing` 7.15. Many domains have their own event table.
- **CQRS.** The platform has separate command and query buses. The interfaces are in `app/Domain/Shared/CQRS/` and the implementations in `app/Infrastructure/CQRS/`. No handler is registered yet, so controllers still call services and workflows directly.
- **Sagas and workflows.** Multi-step processes run as durable workflows with compensation steps, using `durable-workflow/workflow`.
- **GraphQL.** Lighthouse 6.67 serves 43 domain schemas at `/graphql`.
- **Multi-tenancy.** Teams are isolated with `stancl/tenancy` 3.10.
- **Caching and queues.** Redis backs the cache, the queues and the event streams.
- **Stack.** The platform runs on PHP 8.4, Laravel 12.73, Filament 3.3 and MySQL 8, with PHPStan at level 8.

### Demo Modules

The CGO and Basket modules are demo modules. The standards modules (ISO 20022, ISO 8583, Open Banking, payment rails, Interledger, Ledger and Microfinance) work at the message layer and in demo mode. The production site switches all of them off through `MODULES_DISABLED`. The CGO module includes Stripe and Coinbase Commerce adapters, tiered identity checks, PDF agreements and event-sourced refunds. Named providers are integration adapters for third-party APIs; no partnership or endorsement implied.

### Recent Changes

Version 7.18.0 added virtual cards through Lepo Finance for a closed pilot. Production offers them only to the users listed for the pilot. It also added card top-ups from the wallet, which stay off through `CARD_TOPUPS_ENABLED=false`. Version 7.18.0 removed the exchange liquidity add, remove and rebalance flow, the unused contact ticket service and saved bank accounts on the bank withdrawal page.

Version 7.18.1 makes asset transfers, wallet currency conversions and GCU buy and sell book the right amounts. It saves the daily spending limit chosen on the MCP consent screen and gets the card identity check's SumSub SDK token from Lepo. It also removes the admin Bank Operations and Audit Log pages, which could never work. See `CHANGELOG.md` for the full list.
