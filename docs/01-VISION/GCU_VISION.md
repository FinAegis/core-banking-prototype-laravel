# Global Currency Unit (GCU) - Concept

## What is GCU?

The Global Currency Unit is a conceptual demonstration of how a democratic digital currency could work. In this model:

- **Basket-Backed**: Value comes from a weighted basket of currencies and gold
- **Democratic Governance**: Users vote on basket composition
- **Multi-Bank Distribution**: Funds spread across banks in different countries
- **Deposit Insurance**: Each bank deposit protected by local insurance schemes

## How It Would Work

### For Users

**Bank Allocation**
Choose how your funds are distributed:
- 40% Paysera (Lithuania)
- 30% Deutsche Bank (Germany)
- 30% Santander (Spain)

**Democratic Control**
Monthly votes determine the basket composition. Your voting power equals your GCU holdings.

**Deposit Insurance**
Each bank portion is protected separately:
- EU banks: Up to €100,000 per bank
- US banks: Up to $250,000 per bank

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
| Currency Exchange | Convert between GCU and other assets |
| Balance Tracking | See how GCU value tracks the basket |

### What's Simulated

- Bank connections (mock implementations)
- Deposit insurance verification
- Real-time basket rebalancing
- Actual fund movements

## Use Cases

### High-Inflation Protection

For users in countries with unstable currencies, GCU demonstrates how:
- Savings could be protected through diversification
- Access remains instant and global
- No black market needed
- Existing bank relationships maintained

### Business Treasury

For companies with multi-currency exposure:
- Reduced FX risk through basket stability
- Simplified treasury operations
- Automated rebalancing

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

*GCU is a conceptual demonstration. This platform shows how such a system could be built using modern banking architecture patterns.*
