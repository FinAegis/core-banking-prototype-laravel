# Getting Started with FinAegis

This guide walks you through the FinAegis demo environment. In the sandbox, all transactions are simulated — perfect for exploring without risk.

## Quick Start

### Option 1: Use the Live Demo

Visit [finaegis.org](https://finaegis.org) and log in with a demo account:

| Account | Email | Password |
|---------|-------|----------|
| Regular User | `demo.user@gcu.global` | `demo123` |
| Business | `demo.business@gcu.global` | `demo123` |
| Investor | `demo.investor@gcu.global` | `demo123` |

### Option 2: Run Locally

```bash
git clone https://github.com/finaegis/core-banking-prototype-laravel.git
cd core-banking-prototype-laravel
composer install
cp .env.demo .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Then visit `http://localhost:8000` and use the demo credentials above.

## Exploring the Dashboard

Once logged in, you'll see:

- **Total Balance** - Combined value across all currencies
- **Asset Breakdown** - Visual representation of holdings
- **Recent Transactions** - Latest activity
- **Quick Actions** - Common operations

### Navigation

| Section | What You Can Do |
|---------|-----------------|
| **Accounts** | View multi-currency accounts |
| **Transfers** | Send money between accounts |
| **Exchange** | Convert between currencies |
| **GCU Wallet** | Explore Global Currency Unit features |

## Try These Features

### 1. Multi-Currency Support

FinAegis supports multiple asset types:

**Fiat**: USD, EUR, GBP, CHF, JPY
**Crypto**: BTC, ETH
**Commodities**: XAU (Gold), XAG (Silver)
**Special**: GCU (Global Currency Unit)

Try adding a new currency under **Accounts → Add Currency**.

### 2. Currency Exchange

1. Go to **Exchange**
2. Select source and target currencies
3. Enter amount
4. Review the rate and confirm

Note: Exchange rates in demo mode are simulated but realistic.

### 3. Transfers

**Internal Transfer** (between your accounts):
1. **Transfers → Internal Transfer**
2. Select source and destination
3. Enter amount and confirm

**External Transfer** (to other users):
1. **Transfers → Send Money**
2. Enter recipient account UUID
3. Complete the transfer

### 4. Global Currency Unit (GCU)

GCU is the flagship concept - a basket currency backed by multiple assets:

- **Current Composition**: USD 35%, EUR 30%, GBP 20%, CHF 10%, JPY 3%, Gold 2%
- **Bank Allocation**: Explore how funds could be distributed across banks
- **Voting**: See how democratic governance could work

Navigate to **GCU Wallet** to explore these features.

## Admin Dashboard

For a complete view of the platform:

1. Visit `/admin`
2. Create an admin user: `php artisan make:filament-user`
3. Explore account management, transactions, and analytics

## API Access

Test the API at `/api/documentation` (OpenAPI/Swagger interface).

**Authentication:**
```bash
# Get a token
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "demo.user@gcu.global", "password": "demo123"}'

# Use the token
curl http://localhost:8000/api/accounts \
  -H "Authorization: Bearer {your-token}"
```

## What Works in Demo Mode

| Feature | Status | Notes |
|---------|--------|-------|
| Account management | Works | Full CRUD operations |
| Transfers | Works | Instant (simulated) |
| Currency exchange | Works | Simulated rates |
| GCU basket | Works | Full functionality |
| Voting system | Works | Demo votes |
| Admin dashboard | Works | Full access |
| API endpoints | Works | All documented endpoints |
| Event sourcing | Works | Complete audit trails |

## What's Simulated

- **Bank connections** - Mock implementations
- **Blockchain transactions** - Simulated confirmations
- **KYC verification** - Auto-approved in demo
- **Payment processing** - Instant (no real money)

## Next Steps

- **Developers**: See [Development Guide](../06-DEVELOPMENT/DEVELOPMENT.md)
- **API Integration**: See [API Reference](../04-API/REST_API_REFERENCE.md)
- **Architecture**: See [Architecture Overview](../02-ARCHITECTURE/ARCHITECTURE.md)

## Troubleshooting

**Can't log in?**
- Check credentials match exactly (case-sensitive)
- Try clearing browser cache
- Verify the server is running

**Missing features?**
- Run `php artisan migrate --seed` to ensure demo data exists
- Check `.env` has `APP_ENV=demo`

**API errors?**
- Ensure you have a valid token
- Check `/api/documentation` for correct endpoints

---

Questions? [Open an issue](https://github.com/finaegis/core-banking-prototype-laravel/issues) on GitHub.
