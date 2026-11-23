# Architecture Documentation

This directory holds the technical architecture documents for the FinAegis platform. FinAegis is an open-source reference platform, not a production bank. Zelta is the hosted non-custodial wallet product built on it.

## Contents

- [ARCHITECTURE.md](ARCHITECTURE.md) describes the core architecture. It covers domain-driven design, event sourcing, the command and query buses, sagas, caching and security.
- [DOMAIN_DEPENDENCIES.md](DOMAIN_DEPENDENCIES.md) maps how the main domains depend on each other.
- [WORKFLOW_PATTERNS.md](WORKFLOW_PATTERNS.md) explains the saga and workflow patterns and how to write new workflows.
- [MULTI_ASSET_ARCHITECTURE.md](MULTI_ASSET_ARCHITECTURE.md) is the 2024 design for multi-asset support. It is a historical document.
- [CRYPTO_EXCHANGE_ARCHITECTURE.md](CRYPTO_EXCHANGE_ARCHITECTURE.md) is the 2024 design for the exchange engine. It is a historical document.

## Architecture at v7.18.1

- The code is split into 61 domain folders under `app/Domain/`. Each folder is a bounded context in the sense of domain-driven design.
- Changes to accounts and other aggregates are stored as events with Spatie Laravel Event Sourcing (v7.15). Several domains keep their own event tables.
- Multi-step operations run as sagas with Laravel Workflow. A failed step triggers compensations for the steps that already ran.
- A command bus, a query bus and a domain event bus live in `app/Infrastructure/`. No command or query handler is registered yet, so controllers call services and workflows directly.
- The REST API sits next to a GraphQL API built with Lighthouse PHP. `graphql/schema.graphql` imports 43 domain schemas plus the shared directives and subscriptions.
- Team-based multi-tenancy uses stancl/tenancy. Models with the `UsesTenantConnection` trait run on the tenant connection.
- Events are streamed through Redis Streams with a dead-letter queue and backpressure handling.
- The stack is PHP 8.4, Laravel 12, MySQL 8 and Redis, with PHPStan at level 8.

## What runs in production

The production Zelta configuration disables the demo modules through `MODULES_DISABLED`. These are Banking, Basket, Cgo, CrossChain, DeFi, FinancialInstitution, Governance, ISO20022, ISO8583, Interledger, Ledger, Lending, Microfinance, OpenBanking, PaymentRails, Stablecoin and Treasury. It also turns off the demo trading engine with `TRADING_ENABLED=false`.

The standards modules (ISO 20022, ISO 8583, Open Banking, payment rails such as SEPA and the US rails, Interledger, Ledger and Microfinance) work at the message layer and in demo mode. They have no live bank or scheme connection.

The domains that run Zelta include Wallet (non-custodial sends on Polygon, Base, Arbitrum and Solana), Relayer (gas sponsorship), Ramp (bank transfers through Bridge), Subscription (App Store and Google Play purchases), CardIssuance (virtual cards through Lepo Finance in a closed pilot), MCP and the x402 payment protocol. The Privacy domain serves RAILGUN private transfers, and its move to a design where the device holds all keys is still in progress. The MPP payment protocol is built but off by default (`MPP_ENABLED=false`).

## Changes in v7.18.0 and v7.18.1

- Virtual cards through Lepo Finance were added for a closed pilot. Card top-ups from the wallet are built but stay off while `CARD_TOPUPS_ENABLED=false`.
- Wallet sends with an unclear outcome now stay submitted until the chain settles them.
- The exchange liquidity flow for adding, removing and rebalancing liquidity was removed, together with its workflows, activities, routes, `PoolRebalancingService` and the `RebalancePoolsCommand`. Pool lists, swaps, positions, rewards and impermanent loss protection remain.
- `ContactTicketService` and the saved bank accounts on the bank withdrawal page were removed.
- In v7.18.1, asset transfers, wallet currency conversions and GCU buy and sell book the right amounts. Before that they stopped on a missing method.
- In v7.18.1, the card identity check gets its SumSub SDK token from Lepo, and the daily spending limit chosen on the MCP consent screen is saved.
- In v7.18.1, the admin Bank Operations and Audit Log pages were removed because they could never work. The Settings and Bank Allocations pages were fixed.

## Related documents

- [Architecture decision records](../adr/README.md)
- [REST API reference](../04-API/REST_API_REFERENCE.md)
- [CQRS implementation](../04-API/CQRS_IMPLEMENTATION.md)
- [Event sourcing best practices](../12-PATTERNS/EVENT_SOURCING_BEST_PRACTICES.md)
