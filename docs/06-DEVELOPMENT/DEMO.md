# GCU Platform Demo Environment

This guide explains how to set up and use the demo environment for the FinAegis platform with GCU implementation.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

## Quick Start

```bash
# Set up fresh demo environment
php artisan demo:populate --fresh --with-admin

# Or populate demo data into existing database
php artisan demo:populate
```

## Demo Users

The demo environment includes 5 user personas representing different use cases:

### 1. High-Inflation Country User (Argentina)
- **Email**: demo.argentina@gcu.global
- **Password**: demo123
- **Scenario**: Demo persona with simulated multi-currency balances
- **Holdings**: $500 USD, 450 GCU
- **Banks**: 40% Bank A, 30% Bank B, 30% Bank C

### 2. Digital Nomad
- **Email**: demo.nomad@gcu.global
- **Password**: demo123
- **Scenario**: International freelancer needing multi-currency support
- **Holdings**: $2,000 USD, €1,500 EUR, 1,800 GCU
- **Banks**: 50% Bank D, 30% Bank A, 20% Bank E

### 3. Business User
- **Email**: demo.business@gcu.global
- **Password**: demo123
- **Scenario**: Tech company with international operations
- **Holdings**: $10,000 USD, €8,000 EUR, £5,000 GBP, 9,500 GCU
- **Banks**: 60% Bank B, 40% Bank C

### 4. Investor
- **Email**: demo.investor@gcu.global
- **Password**: demo123
- **Scenario**: High net worth individual diversifying holdings
- **Holdings**: $50,000 USD, 48,500 GCU, 1oz Gold
- **Banks**: 35% Bank C, 35% Bank B, 30% Bank A

### 5. Regular User
- **Email**: demo.user@gcu.global
- **Password**: demo123
- **Scenario**: Standard user with simple needs
- **Holdings**: $1,000 USD, 950 GCU
- **Banks**: 100% Bank A

## Admin Access

- **URL**: /admin
- **Email**: admin@gcu.global
- **Password**: admin123

## Demo Features

### 1. GCU Basket
- Pre-configured with USD (35%), EUR (25%), GBP (20%), CHF (10%), JPY (5%), Gold (5%)
- Monthly rebalancing scheduled
- Performance tracking enabled

### 2. Voting System
- Active poll for current month's basket composition
- Demo votes from argentina, investor, and business users
- Completed poll from last month showing results
- Draft poll for next month

### 3. Multi-Bank Distribution (simulated)
- Each demo user has different bank allocation preferences
- Showcases the multi-bank distribution feature
- Demonstrates privacy (each bank only sees their portion)

### 4. Transaction History
- Sample transfers between demo accounts
- Shows multi-currency capabilities
- Demonstrates low fees (0.01%)

## API Testing

### Authentication
```bash
# Get auth token
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "demo.user@gcu.global", "password": "demo123"}'
```

### Key Endpoints
- `GET /api/accounts/{uuid}/balances` - Multi-asset balances
- `GET /api/voting/polls` - Active voting polls
- `POST /api/voting/polls/{uuid}/vote` - Submit vote
- `GET /api/assets` - List all assets including GCU

## Testing Scenarios

### 1. Multi-currency view (Argentina persona)
- Login as demo.argentina@gcu.global
- View the simulated GCU demo balance
- Check the simulated bank-allocation settings

### 2. International Payments (Nomad User)
- Login as demo.nomad@gcu.global
- View multi-currency balances
- Test currency conversion with low fees

### 3. Business Operations (Business User)
- Login as demo.business@gcu.global
- Check large multi-currency holdings
- View simulated bank-allocation settings

### 4. Basket vote (simulated)
- Login as any demo user
- View current voting poll
- Submit a simulated vote for the demo basket composition
- Check simulated voting power based on demo GCU balances

## Resetting Demo Data

```bash
# Complete fresh start
php artisan migrate:fresh --seed
php artisan demo:populate --with-admin

# Just refresh demo users
php artisan demo:populate --fresh
```

## Local Development

The demo environment is designed to work with the standard local development setup:

```bash
# Start local server
php artisan serve

# Run queue workers (for async operations)
php artisan queue:work

# Access points
- Application: http://localhost:8000
- Admin Panel: http://localhost:8000/admin
- API Docs: http://localhost:8000/api/documentation
```

## Production Demo

For production demo deployment:

1. Set environment to `production`
2. Configure real domain (e.g., demo.gcu.global)
3. Set up SSL certificates
4. Configure email services for notifications
5. Set up monitoring and logging

## Support

For demo-related issues:
- Check logs in `storage/logs/`
- Verify queue workers are running
- Ensure Redis is available for caching
- Check database migrations are up to date