# Vision Documentation

This directory contains documentation about the project's vision, strategic direction, and roadmap.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory
> compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.

## Contents

### Core Vision Documents
- **[UNIFIED_PLATFORM_VISION.md](UNIFIED_PLATFORM_VISION.md)** describes how the FinAegis modules share one platform.
- **[GCU_VISION.md](GCU_VISION.md)** describes the GCU demo concept.

### Planning
- **[ROADMAP.md](ROADMAP.md)** summarises the release history and the architecture patterns.
- The strategic plan for 2025 is kept in the archive as [STRATEGIC_PLAN_2025.md](../archive/STRATEGIC_PLAN_2025.md).
- The full version history is in [VERSION_ROADMAP.md](../VERSION_ROADMAP.md) and [CHANGELOG.md](../../CHANGELOG.md).

### Withdrawn
The following documents have been withdrawn pending regulatory review:
- [SUB_PRODUCTS_OVERVIEW.md](SUB_PRODUCTS_OVERVIEW.md) (withdrawn — see [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md))
- [REGULATORY_STRATEGY.md](REGULATORY_STRATEGY.md) (withdrawn — see [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md))
- [FINANCIAL_INSTITUTION_REQUIREMENTS.md](FINANCIAL_INSTITUTION_REQUIREMENTS.md) (withdrawn — see [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md))
- [WEBSITE_CONTENT_STRATEGY.md](WEBSITE_CONTENT_STRATEGY.md) (withdrawn — see [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md))

## Purpose

These documents explain the long-term goals of FinAegis. They show how the GCU demo, a basket-unit reference implementation, is built. They also show how the modules fit together and how the platform developed over time.

## Current Status (v7.18.1, November 2026)

FinAegis is an open-source reference platform, not a production bank. Zelta is the hosted non-custodial wallet product built on it.

- The latest release is v7.18.1 (2026-11-24). It fixes the card pilot's identity check and makes asset transfers and currency conversions book the right amounts.
- Version 7.18.0 (2026-11-23) added virtual cards through Lepo Finance for a closed pilot. Production offers cards only to the users listed for the pilot.
- The platform has 61 domain folders in `app/Domain/`.
- The standards modules work at the message layer and in demo mode. They cover ISO 20022, ISO 8583, Open Banking, payment rails, Interledger, the ledger and microfinance. They are disabled on the production site.
- The demo trading engine, which includes the exchange and liquidity pools, is off in production through `TRADING_ENABLED=false`.
- Compliance modules are readiness tooling. No certification or licence is held.

See [CHANGELOG.md](../../CHANGELOG.md) for the details of each release.
