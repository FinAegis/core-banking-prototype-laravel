# Implementation Documentation

This directory holds implementation notes from the early development phases in 2024. They are historical records. They explain how parts of the platform were first built and which technical decisions were made.

For the current state of the platform, read the [CHANGELOG](../../CHANGELOG.md) and the [version roadmap](../VERSION_ROADMAP.md). The current version is v7.18.1.

## Contents

- **[IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)** summarises the first API layer and the test fixes that came with it.
- **[API_IMPLEMENTATION.md](API_IMPLEMENTATION.md)** describes the first account, transaction, transfer and balance API controllers.
- **[PHASE_4.2_ENHANCED_GOVERNANCE.md](PHASE_4.2_ENHANCED_GOVERNANCE.md)** describes the governance and voting work for the GCU demo.
- **[PHASE_5.2_TRANSACTION_PROCESSING.md](PHASE_5.2_TRANSACTION_PROCESSING.md)** describes multi-custodian transfer routing and settlement.
- **[PHASE_8_ADVANCED_TRADING.md](PHASE_8_ADVANCED_TRADING.md)** describes the exchange, stablecoin, blockchain wallet and lending work.

The September 2024 implementation status report is kept in the archive as [IMPLEMENTATION_STATUS_2025.md](../archive/IMPLEMENTATION_STATUS_2025.md), despite its file name.

## Status recorded in September 2024

The phases below were recorded as complete in September 2024. Much has changed since then. FinAegis is now an open-source reference platform, and Zelta is the hosted non-custodial wallet built on it. Many of the modules named here are disabled on the production site.

- Phases 1 to 3 built the multi-asset foundation, exchange rates and platform integration.
- Phase 4 extended the GCU demo with user bank selection, enhanced governance and a compliance framework.
- Phase 5 added bank connector adapters, multi-bank transfers, settlement logic, monitoring and operations. The adapters are integration adapters for third-party bank APIs, for example Paysera, Deutsche Bank and Santander (integration adapters for third-party APIs; no partnership or endorsement implied).
- Phase 6 built the GCU demo, which is a simulated reference implementation. It added the user interface, the public API, webhook integration and documentation.
- Phase 7 added the simulated GCU demo basket voting, security features such as two-factor authentication and OAuth2, trading operations, compliance monitoring and the CGO demo module. The CGO demo module is not an offer of securities.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.
