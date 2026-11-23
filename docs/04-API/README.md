# API Documentation

This directory holds the REST API documentation for the FinAegis platform. It describes the API as of v7.18.1.

## Contents

- [REST_API_REFERENCE.md](REST_API_REFERENCE.md) lists the main endpoint groups, shows how to call them and says which of them are switched on in production.
- [API_VOTING_ENDPOINTS.md](API_VOTING_ENDPOINTS.md) describes the voting and poll endpoints of the GCU demo.
- [WEBHOOK_INTEGRATION.md](WEBHOOK_INTEGRATION.md) explains how to set up and receive outgoing webhooks.
- [BIAN_API_DOCUMENTATION.md](BIAN_API_DOCUMENTATION.md) describes the endpoints modelled on the BIAN service domains.
- [CQRS_IMPLEMENTATION.md](CQRS_IMPLEMENTATION.md) explains the command and query buses behind the API.
- [SERVICES_REFERENCE.md](SERVICES_REFERENCE.md) describes the service layer that the controllers call.
- [OPENAPI_COVERAGE_100_PERCENT.md](OPENAPI_COVERAGE_100_PERCENT.md) is a historical coverage assessment from v3.4.0.

## Where to find the full list

The route list is the source of truth for which endpoints exist. Run `php artisan route:list` to see it. The OpenAPI document gives the request and response schemas. It is generated from the PHP attributes on the controllers and served at `/api/documentation`, with the raw file at `/docs/api-docs.json`. GraphQL is served at `/graphql`.

## What runs in production

FinAegis is an open-source reference platform, and Zelta is the hosted non-custodial wallet built on it. The production Zelta configuration switches off the demo modules through `MODULES_DISABLED`. It also sets `TRADING_ENABLED=false` and `LEDGER_DIRECT_FUNDING_ENABLED=false`. The voting, GCU, basket, CGO, stablecoin, lending, banking demo and trading endpoints are therefore not available on the production site. The standards modules (ISO 20022, ISO 8583, Open Banking, payment rails, Interledger, Ledger and Microfinance) work at the message layer and in demo mode, and they are disabled in production too. [REST_API_REFERENCE.md](REST_API_REFERENCE.md#which-endpoints-are-switched-on) shows each gate.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

## Main endpoint groups

### Zelta wallet (production)
- The wallet endpoints under `/api/v1/wallet/...` return balances and activity and run the prepare and submit flow for non-custodial sends.
- The bank ramp endpoints under `/api/v1/ramp/...` and `/api/v1/user/bridge-...` run bank transfers in through Bridge.
- The subscription endpoints under `/api/v1/subscription/...` verify App Store and Google Play purchases.
- The card endpoints under `/api/v1/cards/...` serve virtual cards through Lepo Finance. Cards are in a closed pilot, and only the users on the pilot list are offered a card. Card top-ups stay off while `CARD_TOPUPS_ENABLED=false`.
- The privacy endpoints under `/api/v1/privacy/...` serve RAILGUN privacy. The feature is moving from a custodial design to one in which the device holds all keys, and the backend now registers the device's public address and serves the engine configuration.
- Sign-in from the app uses `POST /api/v1/auth/privy-login`.

Named third-party providers: Integration adapters for third-party APIs; no partnership or endorsement implied.

### Core reference API
- Accounts, balances, transactions and transfers live under `/api/accounts`, `/api/transfers` and `/api/v2/...`.
- Authentication, two-factor authentication, passkeys and social login live under `/api/auth/...`.
- Exchange rates are public under `/api/exchange-rates`.
- Hardware wallets (Ledger and Trezor) live under `/api/hardware-wallet/...`, and multi-signature wallets under `/api/multi-sig/...`.
- Compliance readiness tooling lives under `/api/compliance/...`. It helps an operator build Know Your Customer (KYC) and data protection processes. No certification is held.

### Payment protocols
- Machine payments use x402 and the Machine Payments Protocol (MPP). Their discovery documents are at `/api/.well-known/x402-configuration` and `/api/.well-known/mpp-configuration`.
- Agent payment mandates (AP2) are described at `/api/.well-known/ap2-configuration`.
- The MCP server for AI agents runs at `https://mcp.zelta.app/mcp`. Its catalog in `config/mcp.php` has 14 tools, and the production server exposes 10 of them.

### Demo modules (off in production)
- The GCU demo endpoints are under `/api/v2/gcu/...`, with simulated conversions and simulated basket votes.
- The voting endpoints are under `/api/voting/...`, `/api/polls/...` and `/api/votes/...`.
- The trading demo has the order book under `/api/exchange/...`, the external exchange reads under `/api/external-exchange/...` and the liquidity pools under `/api/liquidity/...`. The add, remove and rebalance liquidity flow was removed in v7.18.0.
- The CGO demo runs as web pages under `/cgo` and has no REST API.

## WebSocket channels

The platform streams events on private channels such as `tenant.{tenantId}`, `tenant.{tenantId}.accounts`, `tenant.{tenantId}.transactions` and `tenant.{tenantId}.exchange`. `routes/channels.php` lists every channel.
