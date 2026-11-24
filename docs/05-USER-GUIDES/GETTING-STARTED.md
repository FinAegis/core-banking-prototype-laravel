# Getting Started with FinAegis

This guide walks you through the FinAegis demo environment. In the demo, every balance and transaction is simulated, so you can try things without any risk.

The production Zelta site does not show these pages. Zelta is the non-custodial wallet product built on FinAegis, and it turns the demo modules off.

## Quick Start

### Option 1: Use the Hosted Demo

Visit [finaegis.org](https://finaegis.org) and log in with one of the demo accounts. Every demo account uses the password `demo123`.

| Account | Email | Password |
|---------|-------|----------|
| Regular user | `demo.user@gcu.global` | `demo123` |
| Business user | `demo.business@gcu.global` | `demo123` |
| Investor persona | `demo.investor@gcu.global` | `demo123` |
| High-inflation country user | `demo.argentina@gcu.global` | `demo123` |
| Digital nomad | `demo.nomad@gcu.global` | `demo123` |

### Option 2: Run Locally

```bash
git clone https://github.com/finaegis/core-banking-prototype-laravel.git
cd core-banking-prototype-laravel
composer install
cp .env.demo .env
php artisan key:generate
php artisan migrate
php artisan demo:populate
npm install && npm run build
php artisan serve
```

`php artisan migrate --seed` creates the assets, roles and settings, but it does not create the demo users. `php artisan demo:populate` runs the standard seeders and then `DemoDataSeeder`, which creates the five demo accounts above with their balances and voting polls. Add `--fresh` to wipe the database first. Add `--with-admin` to also create `admin@gcu.global` with the password `admin123`. That user does not get the admin role automatically, so run `php artisan user:promote admin@gcu.global` before you open `/admin`.

Then visit `http://localhost:8000` and log in with one of the demo accounts.

## Exploring the Dashboard

After you log in, the dashboard shows your total balance, a breakdown by asset, your recent transactions and shortcuts to common actions.

### Navigation

| Section | What you can do |
|---------|-----------------|
| **Wallet** | See your balances and open the deposit, withdraw, transfer and convert pages |
| **Banking** | Open the transaction history, status tracking, the fund flow view, the simulated bank allocation and the blockchain wallet |
| **Exchange** | Use the demo order book (needs `TRADING_ENABLED=true`) |
| **Governance** | Take part in the simulated GCU basket votes |

## Try These Features

### 1. Multiple Assets

The asset seeder creates the fiat currencies USD, EUR, GBP, CHF and JPY. It also creates the cryptocurrencies BTC and ETH, gold (XAU), and the GCU demo unit. GCU is simulated and has no monetary value.

Each demo user starts with one account that holds several of these assets. The **Accounts** page at `/accounts` lists your accounts and their balances. The web pages offer a **Create Account** button only to a user who has no account yet. To open more accounts, use the API with `POST /api/accounts`.

### 2. Currency Conversion

The convert page at `/wallet/convert` turns one demo balance into another.

1. Open **Wallet** and choose **Convert**.
2. Select the source and target currencies.
3. Enter the amount.
4. Review the rate and confirm.

Exchange rates in the demo are simulated. The convert page needs `LEDGER_DIRECT_FUNDING_ENABLED=true`, which is on in the demo and off in production.

### 3. Transfers

The transfer page at `/wallet/transfer` moves demo money between accounts.

1. Open **Wallet** and choose **Transfer**.
2. Select the source account and enter the recipient's account UUID.
3. Enter the amount and confirm.

### 4. Global Currency Unit (GCU)

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.

The GCU demo is a reference implementation of a basket-referenced unit built with FinAegis. Its balances, conversions and votes are simulated.

The demo basket holds six components, which are USD, EUR, GBP, CHF, JPY and gold. The public `/gcu` page shows the example weights from `config/platform.php`, which are USD 35%, EUR 30%, GBP 20%, CHF 10%, JPY 3% and gold 2%. The basket that `GCUBasketSeeder` creates starts at USD 40%, EUR 30%, GBP 15%, CHF 10%, JPY 3% and gold 2%.

The bank allocation page at `/wallet/bank-allocation` shows how balances could be spread across custodians. The allocation is simulated, and no money is held at any bank. The voting pages show how basket polls could work. See the [GCU demo guide](GCU-USER-GUIDE.md) and the [GCU voting guide](GCU_VOTING_GUIDE.md).

## Admin Dashboard

The admin panel at `/admin` gives a full view of the platform. Only users with the admin role can open it. Other users are sent back to `/dashboard`.

To get an admin user, run one of these commands.

```bash
php artisan user:create --admin          # create a new admin user
php artisan user:promote you@example.com # give an existing user the admin role
```

## API Access

The interactive API reference is at `/api/documentation`. The GraphQL endpoint is at `/graphql`.

**Authentication:**
```bash
# Get a token
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "demo.user@gcu.global", "password": "demo123"}'

# Use the access_token from the response
curl http://localhost:8000/api/accounts \
  -H "Authorization: Bearer {your-token}"
```

## What Works in Demo Mode

| Feature | Status | Notes |
|---------|--------|-------|
| Account management | Works | Create and view accounts; the API can also freeze and unfreeze them |
| Transfers | Works | Simulated, no real money |
| Currency conversion | Works | Simulated rates |
| GCU basket | Works | Simulated (demo only; not issued) |
| Voting system | Works | Demo votes |
| Admin dashboard | Works | Needs the admin role |
| API endpoints | Works | See `/api/documentation` |
| Event sourcing | Works | Every balance change is stored as an event |

## What Is Simulated

- Bank connections use mock connectors. No real bank is contacted.
- Blockchain transactions in the demo get simulated confirmations.
- Identity checks are approved automatically in the demo (`DEMO_SKIP_KYC`).
- Card deposits go through a simulated payment gateway, and no real money moves.

## Next Steps

- Developers can read the [Development Guide](../06-DEVELOPMENT/DEVELOPMENT.md).
- API users can read the [API Reference](../04-API/REST_API_REFERENCE.md).
- The [Architecture Overview](../02-ARCHITECTURE/ARCHITECTURE.md) explains how the platform is built.

## Troubleshooting

**You cannot log in.**
- Check that the email and password match exactly. They are case-sensitive.
- Check that you ran `php artisan demo:populate`. `migrate --seed` alone does not create the demo users.
- Check that the server is running.

**A page or feature is missing.**
- Check that `.env` has `APP_ENV=demo`.
- The exchange and liquidity pages need `TRADING_ENABLED=true`.
- The deposit, withdraw and convert actions need `LEDGER_DIRECT_FUNDING_ENABLED=true`.

**The API returns errors.**
- Check that you send a valid bearer token.
- Check the endpoint paths in `/api/documentation`.

---

Questions? [Open an issue](https://github.com/finaegis/core-banking-prototype-laravel/issues) on GitHub.
