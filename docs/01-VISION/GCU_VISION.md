# Global Currency Unit (GCU) - Concept

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.
>
> See [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md).

## What is GCU?

The GCU demo is a reference implementation of a basket-referenced unit built with FinAegis. It shows three ideas.

- **Basket reference.** The demo calculates an illustrative value from a weighted basket of currencies and gold.
- **Simulated governance.** Demo accounts cast simulated basket "votes".
- **Simulated custodian allocation.** The demo shows how balances could be split across custodians. No funds are held at any bank.

The `Basket` and `Governance` modules that run the demo are disabled on the production site.

## How It Would Work

### Custodian allocation (illustrative)

A demo user chooses how demo balances are split, for example like this:
- 40% Custodian A
- 30% Custodian B
- 30% Custodian C

### Simulated governance

Demo basket polls are weighted by simulated demo GCU balances. Votes have no monetary effect.

### Basket composition (demo)

`GCUBasketSeeder` seeds the demo basket with these weights.

| Asset | Weight |
|-------|--------|
| USD | 40% |
| EUR | 30% |
| GBP | 15% |
| CHF | 10% |
| JPY | 3% |
| Gold (XAU) | 2% |

## Technical Implementation

The demo uses these parts of the FinAegis platform:

- **Event sourcing** keeps an audit trail of every operation.
- **The multi-asset ledger** holds the basket and its components.
- **The governance module** runs the polls and the voting.
- **The custodian abstraction** defines how bank connectors would plug in.

## Demo Features

### What you can try

| Feature | Description |
|---------|-------------|
| Basket management | View the basket composition. |
| Bank allocation | See how a multi-bank split would look. |
| Voting | Take part in demo governance polls. |
| Simulated conversion | Convert demo balances between GCU and other demo assets. |
| Balance tracking | See how the illustrative GCU value follows the basket. |

### What is simulated

- Bank connections use mock implementations.
- Basket rebalancing is simulated.
- No real funds move.

## Getting Started

### Try the demo

```bash
# Clone and set up
git clone https://github.com/finaegis/core-banking-prototype-laravel.git
cd core-banking-prototype-laravel
composer install && npm install
cp .env.demo .env
php artisan key:generate
php artisan migrate
php artisan demo:populate
npm run build

# Start the server
php artisan serve
```

`php artisan migrate --seed` does not create the demo users. `php artisan demo:populate` runs the seeders and creates the demo accounts.

Visit `http://localhost:8000` and log in with `demo.investor@gcu.global` and the password `demo123`.

### Explore the code

These folders hold the GCU components:
- `app/Domain/Basket/` holds the basket assets and their valuation.
- `app/Domain/Governance/` holds the voting system.
- `app/Domain/Asset/` holds multi-asset support.
- `app/Domain/Custodian/` holds the bank integration abstraction.

## Resources

- [Architecture Overview](../02-ARCHITECTURE/ARCHITECTURE.md)
- [API Reference](../04-API/REST_API_REFERENCE.md)
- [User Guide](../05-USER-GUIDES/GCU-USER-GUIDE.md)
- [Voting Guide](../05-USER-GUIDES/GCU_VOTING_GUIDE.md)

---

*GCU is a software demonstration built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value.*
