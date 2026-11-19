# Global Currency Unit (GCU) - Concept

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.
>
> See [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md).

## What is GCU?

The GCU demo is a reference implementation of a basket-referenced unit built with FinAegis. In this model:

- **Basket-Referenced**: An illustrative value is calculated from a weighted basket of currencies and gold
- **Simulated Governance**: Demo accounts cast simulated basket "votes"
- **Multi-Custodian Allocation (simulated)**: Illustrates how balances could be allocated across custodians; no funds are held at any bank

## How It Would Work

### In the Demo

**Custodian Allocation (illustrative)**
Choose how demo balances are allocated:
- 40% Custodian A
- 30% Custodian B
- 30% Custodian C

**Simulated Governance**
Demo basket polls are weighted by simulated demo GCU balances. Votes have no monetary effect.

### Current Basket Composition (Demo)

| Asset | Weight | Purpose |
|-------|--------|---------|
| USD | 35% | Global trade currency |
| EUR | 30% | European stability |
| GBP | 20% | Financial markets access |
| CHF | 10% | Safe haven |
| JPY | 3% | Asian market exposure |
| Gold (XAU) | 2% | Inflation hedge |

## Technical Implementation

Built on the FinAegis platform:

- **Event Sourcing**: Complete audit trail of all operations
- **Multi-Asset Ledger**: Native support for currency baskets
- **Democratic Governance**: Built-in voting and poll management
- **Custodian Abstraction**: Ready for multi-bank integration

## Demo Features

### What You Can Try

| Feature | Description |
|---------|-------------|
| Basket Management | View and understand basket composition |
| Bank Allocation | See how multi-bank distribution works |
| Voting System | Participate in demo governance polls |
| Simulated Conversion | Convert demo balances between GCU and other demo assets |
| Balance Tracking | See how the illustrative GCU value tracks the basket |

### What's Simulated

- Bank connections (mock implementations)
- Real-time basket rebalancing
- Actual fund movements

## Getting Started

### Try the Demo

```bash
# Clone and setup
git clone https://github.com/finaegis/core-banking-prototype-laravel.git
cd core-banking-prototype-laravel
composer install && npm install
cp .env.demo .env
php artisan key:generate
php artisan migrate --seed
npm run build

# Start the server
php artisan serve
```

Visit `http://localhost:8000` and log in with `demo.investor@gcu.global` / `demo123`

### Explore the Code

Key GCU components:
- `app/Domain/Governance/` - Voting system
- `app/Domain/Asset/` - Multi-asset support
- `app/Domain/Custodian/` - Bank integration abstraction

## Resources

- [Architecture Overview](../02-ARCHITECTURE/ARCHITECTURE.md)
- [API Reference](../04-API/REST_API_REFERENCE.md)
- [User Guide](../05-USER-GUIDES/GCU-USER-GUIDE.md)
- [Voting Guide](../05-USER-GUIDES/GCU_VOTING_GUIDE.md)

---

*GCU is a software demonstration built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value.*
