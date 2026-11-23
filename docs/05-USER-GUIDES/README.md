# User Guides

These guides explain how to use the FinAegis web application. FinAegis is an open-source reference platform, and most of what these guides describe runs in the demo environment at [finaegis.org](https://finaegis.org) or on your own local copy.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory
> compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
> Balances shown in the demo environment are simulated demo balances with no real value.

The production Zelta site does not show these features. Zelta is the non-custodial wallet product built on FinAegis, and it turns the demo modules off. The exchange and liquidity pages need `TRADING_ENABLED=true`. The web wallet's deposit, withdraw and convert actions need `LEDGER_DIRECT_FUNDING_ENABLED=true`. Both are off in production and on in the demo environment.

## Getting Started

If you are new, start with these two guides.

- **[Getting Started](GETTING-STARTED.md)** explains how to log in to the demo or run it on your own machine.
- **[Demo Mode Guide](DEMO-USER-GUIDE.md)** describes what the demo environment contains and how it behaves.

## Core Features

### Wallets and Trading

| Guide | Description |
|-------|-------------|
| [Wallet Management](WALLET_MANAGEMENT_GUIDE.md) | Wallets, deposits and withdrawals |
| [Exchange Engine](EXCHANGE_ENGINE_GUIDE.md) | The demo order book, market and limit orders, and the exchange API |

### Demos

| Guide | Description |
|-------|-------------|
| [GCU demo](GCU-USER-GUIDE.md) | Walkthrough of the GCU software demonstration (simulated; not issued or offered) |
| [GCU demo voting](GCU_VOTING_GUIDE.md) | Simulated basket votes |

### Withdrawn

These four guides were withdrawn during the review recorded in [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md). Their content could not be fixed with small edits. Each file now holds only a short notice, so there is nothing in them to follow.

- `LIQUIDITY_POOLS_GUIDE.md`
- `P2P_LENDING_GUIDE.md`
- `STABLECOIN_GUIDE.md`
- `CGO-USER-GUIDE.md`

## Suggested Order

If you want to try the demo as a regular user, read Getting Started first. Then read Wallet Management, the Exchange Engine guide and the GCU demo guide.

If you only want to try the order book, the Exchange Engine guide is enough.

## Need Help?

- The [Troubleshooting Guide](../08-TROUBLESHOOTING/) covers common problems.
- The [API Documentation](../04-API/) describes the REST and GraphQL interfaces.
- The [Developer Resources](../09-DEVELOPER/) explain how to build on FinAegis.

---

*Last updated: November 2026 (v7.18.1)*
