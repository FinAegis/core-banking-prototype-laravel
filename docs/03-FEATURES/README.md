# Features Documentation

This directory describes the features of the FinAegis platform at v7.18.1.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
>
> Named third-party providers: Integration adapters for third-party APIs; no partnership or endorsement implied.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

## Contents

### Overview
- **[FEATURES.md](FEATURES.md)** lists the platform features and shows where each one runs.
- **[RELEASE_NOTES.md](RELEASE_NOTES.md)** is the early release history. Current release notes are in [CHANGELOG.md](../../CHANGELOG.md).
- **[DEMO-MODE.md](DEMO-MODE.md)** explains demo mode and the demo services.

### Trading (demo)
The exchange, the liquidity pools and the exchange connectors run only when `TRADING_ENABLED` is on. GCU trading depends on the Basket module instead, which is disabled on the production site.

- **[EXCHANGE.md](EXCHANGE.md)** describes the order book exchange engine.
- **[LIQUIDITY-POOLS.md](LIQUIDITY-POOLS.md)** describes the liquidity pools.
- **[EXTERNAL-EXCHANGE-CONNECTORS.md](EXTERNAL-EXCHANGE-CONNECTORS.md)** describes the Binance and Kraken connector adapters.
- **[GCU_TRADING.md](GCU_TRADING.md)** describes the GCU demo trading module (simulated; GCU is not issued or offered).

### Payments and Wallets
- **[PAYMENT-ABSTRACTION.md](PAYMENT-ABSTRACTION.md)** describes the payment service abstraction.
- **[OPENBANKING_WITHDRAWAL.md](OPENBANKING_WITHDRAWAL.md)** describes the bank and Open Banking withdrawal pages.
- **[TRANSACTION_STATUS_TRACKING.md](TRANSACTION_STATUS_TRACKING.md)** describes transaction status tracking.
- **[VISA-CLI.md](VISA-CLI.md)** describes the card-payment CLI adapter (`visa-cli`).

### Business and Agents
- **[BUSINESS_TEAM_MANAGEMENT.md](BUSINESS_TEAM_MANAGEMENT.md)** describes team management for business accounts.
- **[TREASURY-MANAGEMENT.md](TREASURY-MANAGEMENT.md)** describes the treasury module.
- **[VIRTUALS-AGENT-INTEGRATION.md](VIRTUALS-AGENT-INTEGRATION.md)** describes the Virtuals agent adapter.

## Current Status (v7.18.1)

FinAegis is an open-source reference platform, not a production bank. Zelta is the hosted non-custodial wallet product built on it.

The latest release is v7.18.1 (2026-11-24). It fixes the card pilot and several ledger operations that booked wrong amounts:
- The identity check in the virtual card pilot can now start. It gets its SumSub SDK token from Lepo Finance.
- Asset transfers, wallet currency conversions and GCU demo buy and sell now book the right amounts.
- The daily spending limit that a person sets when connecting an AI assistant is now saved.
- The admin Settings and Bank Allocations pages open again. The admin Bank Operations and Audit Log pages never worked and are removed.

The release before it, v7.18.0 (2026-11-23), added these features:
- Virtual cards through Lepo Finance for a closed pilot. Only listed pilot users can get a card.
- Card top-ups from the wallet ("Add to card"). They stay off through `CARD_TOPUPS_ENABLED=false`.
- 3-D Secure codes for online card payments. A code is kept encrypted for at most ten minutes and is never stored in the database or the logs.
- Safer wallet send outcomes. A send that the network may have accepted stays submitted, and a second concurrent submit returns `409 SUBMIT_IN_PROGRESS`.
- The last sign-in time in `users.last_login_at`.

v7.18.0 also removed three things:
- The exchange's add, remove and rebalance liquidity flow, which never worked.
- Saved bank accounts on the bank withdrawal page, which never worked.
- The unused contact ticket service.

Many modules run only in demo mode. The production site disables the banking standards modules (ISO 20022, ISO 8583, Open Banking, payment rails, Interledger, Ledger and Microfinance) and the Banking, Basket, Cgo, CrossChain, DeFi, FinancialInstitution, Governance, Lending, Stablecoin and Treasury modules. It also turns off the exchange through `TRADING_ENABLED=false`. See [FEATURES.md](FEATURES.md#where-each-feature-runs) for the full table.

## Purpose

These documents explain which features exist and how they work. They also state the limits of each feature, so that developers know what runs in production and what is a demo.
