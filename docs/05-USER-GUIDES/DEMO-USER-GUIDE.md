# Demo Environment User Guide

## Welcome to the FinAegis Demo

This guide describes the FinAegis demo environment at [finaegis.org](https://finaegis.org). The demo runs the open-source FinAegis platform with `APP_ENV=demo`. No real money moves in it.

> All balances, tokens and transactions in the demo are simulated demo balances with no real value.
> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory
> compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

The production Zelta site is a different deployment. Zelta is the non-custodial wallet product built on FinAegis, and it turns the demo modules described here off.

## Quick Start

### 1. Open the Demo

Visit [finaegis.org](https://finaegis.org). You can also run the demo on your own machine with the steps in [Getting Started](GETTING-STARTED.md).

### 2. Demo Accounts

`php artisan demo:populate` creates five demo users. Each one has the password `demo123` and simulated starting balances.

| Persona | Email | Simulated balances |
|---------|-------|--------------------|
| Regular user | `demo.user@gcu.global` | $1,000 and 950 GCU |
| High-inflation country user | `demo.argentina@gcu.global` | $500 and 450 GCU |
| Digital nomad | `demo.nomad@gcu.global` | $2,000, €1,500 and 1,800 GCU |
| Business user | `demo.business@gcu.global` | $10,000, €8,000, £5,000 and 9,500 GCU |
| Investor persona | `demo.investor@gcu.global` | $50,000, 48,500 GCU and 1 oz of gold |

None of the demo accounts has the admin role. On a local copy you can create an admin user with `php artisan user:create --admin`.

### 3. How to Tell You Are in the Demo

When `APP_ENV=demo`, a "Sandbox" label sits in the bottom-left corner of every page. Click it to read that the site uses test data and processes no real funds. The deposit pages also show a yellow "Demo Mode Active" notice.

## Features Overview

### Banking Operations

#### Accounts

Each demo user starts with one account that holds several assets.

1. Open `/accounts` to see your accounts and their balances.
2. Click **Manage** on an account to open the wallet page with its balances and recent transactions.

The web pages offer a **Create Account** button only to a user who has no account yet. To open more accounts, use the API with `POST /api/accounts`.

#### Deposits, Transfers and Withdrawals

The wallet pages under `/wallet` handle deposits, transfers, conversions and withdrawals. Deposits, conversions and withdrawals need `LEDGER_DIRECT_FUNDING_ENABLED=true`. That flag is on in the demo and off in production.

- **Deposit** at `/wallet/deposit` links to a card deposit and a bank transfer. Separate pages exist for Open Banking, crypto, manual and Paysera deposits under `/wallet/deposit/...`. In the demo, card deposits go through a simulated payment gateway that always succeeds, and no card number is checked.
- **Transfer** at `/wallet/transfer` moves money to another account.
- **Convert** at `/wallet/convert` turns one currency into another at a simulated rate.
- **Withdraw** at `/wallet/withdraw` offers a bank transfer or an Open Banking withdrawal. Since v7.18.0, the bank transfer page no longer saves bank accounts, so you type the bank details for each withdrawal. The bank payout step is a stub, so a withdrawal only lowers the demo ledger balance.

### Trading Platform

#### Exchange

The demo order book at `/exchange` needs `TRADING_ENABLED=true`. That flag is on in the demo and off in production.

1. Open **Exchange** in the top navigation.
2. Choose a trading pair.
3. Place a market order or a limit order.

The exchange supports market orders and limit orders only. Prices come from the demo order book and from simulated rates. See the [Exchange Engine guide](EXCHANGE_ENGINE_GUIDE.md) for details.

#### Liquidity Pools

The liquidity pages at `/liquidity` list the demo pools and show a pool's details. They also need `TRADING_ENABLED=true`. Adding and removing liquidity was removed in v7.18.0, so these pages are read-only.

### Stablecoin Operations

The stablecoin operations pages at `/wallet/stablecoin-operations` are for staff only. They need one of the roles `admin`, `super_admin`, `bank_admin` or `stablecoin_operator`, so the demo users cannot open them. Minting and burning also need `LEDGER_DIRECT_FUNDING_ENABLED=true`. Any stablecoin in the demo is a simulated demo token that is not issued.

### Lending

The lending pages at `/lending` show the loan application flow from the borrower's side. They need the Lending module, which is on in the demo and disabled in production.

1. Open **Lending** and choose **Apply**.
2. Enter an amount between 100 and 1,000,000 and a term between 1 and 360 months.
3. Submit the application.

In the demo, the application gets a simulated credit score and an automatic decision. No real loan is made, and there is no page for lenders.

### Governance (GCU Voting)

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.

#### Taking Part in a Vote

1. Open **Governance** in the top navigation. It leads to `/gcu/voting`.
2. Click a proposal to read its description and the proposed basket weights.
3. Vote for, against or abstain while the proposal is open.
4. The page shows the running totals. You can change your vote until voting closes.

**Demo voting weight:**
- Your voting weight equals your simulated demo GCU balance. GCU has no monetary value.
- You need a demo GCU balance above zero to vote.
- Only administrators can create new proposals.

See the [GCU voting guide](GCU_VOTING_GUIDE.md) for the API and the monthly schedule.

## Advanced Features

### API Access

The API reference is at `/api/documentation`. Use a bearer token from the login endpoint.

```bash
# Get a token for a demo account
curl -X POST https://finaegis.org/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "demo.user@gcu.global", "password": "demo123"}'

# List your accounts
curl https://finaegis.org/api/accounts \
  -H "Authorization: Bearer {access_token}"

# Transfer between accounts
curl -X POST https://finaegis.org/api/transfers \
  -H "Authorization: Bearer {access_token}" \
  -H "Content-Type: application/json" \
  -d '{"from_account_uuid": "...", "to_account_uuid": "...", "amount": 100, "asset_code": "USD"}'
```

Check the exact request fields for each endpoint in `/api/documentation`.

### Webhook Testing

Webhooks are platform settings, so only administrators can register them through the API at `/api/v2/webhooks`. The demo users cannot. Anyone can call `GET /api/v2/webhooks/events` to list the event types. They cover accounts, transactions, transfers, baskets, polls, votes and exchange rates. Examples are `account.created`, `transaction.completed`, `transfer.failed` and `vote.cast`. See the [Webhook Integration guide](../04-API/WEBHOOK_INTEGRATION.md) for the payload and the signature check.

## Demo Limitations

### What You Cannot Do

- You cannot move real money.
- You cannot withdraw to a real bank account.
- You cannot trade on a real exchange.
- You cannot see production data.

### Data Cleanup

In the demo environment, `php artisan demo:cleanup` runs every day at 03:00. It deletes demo-tagged transactions and events older than `DEMO_CLEANUP_RETENTION_DAYS`, which defaults to one day. It also deletes old users with an `@demo.finaegis.com` email and their accounts. The five seeded `@gcu.global` demo users stay.

### Rate Limits

The API applies per-user limits to money-moving requests. For example, a user can make 10 deposits, 5 withdrawals, 15 transfers and 20 conversions per hour. Read requests have their own per-minute limits.

## Tips

- The interface is in English only.
- The pages work on phone-sized screens.
- On a local copy, `php artisan demo:populate --fresh` resets the database to the demo data.

## Troubleshooting

### Common Issues

#### You cannot log in
- Check that the email and password match exactly.
- On a local copy, check that you ran `php artisan demo:populate`.

#### A page returns "not found"
- The exchange and liquidity pages need `TRADING_ENABLED=true`.
- The deposit, convert and withdraw actions need `LEDGER_DIRECT_FUNDING_ENABLED=true`.
- The lending and voting pages need their modules to be enabled.

#### A page returns "forbidden" or sends you back
- The stablecoin operations pages need a staff role.
- `/admin` sends users without the admin role back to `/dashboard`.

## Support

- [GitHub Issues](https://github.com/FinAegis/core-banking-prototype-laravel/issues)

## Next Steps

- [API Reference](../04-API/REST_API_REFERENCE.md)
- [Demo mode technical notes](../03-FEATURES/DEMO-MODE.md)
- [Getting Started](GETTING-STARTED.md)

---

**Note**: This is a demonstration environment. No real financial transactions occur. All data is simulated and old demo transactions are cleared daily.
