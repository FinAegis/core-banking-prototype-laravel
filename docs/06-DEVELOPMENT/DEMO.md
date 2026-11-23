# GCU Platform Demo Environment

This guide explains how to set up and use the FinAegis demo environment with the GCU reference implementation.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

## Quick Start

```bash
# Set up fresh demo environment
php artisan demo:populate --fresh --with-admin

# Or populate demo data into existing database
php artisan demo:populate
```

`php artisan migrate --seed` does not create the demo users. `demo:populate` runs the standard seeders and then `DemoDataSeeder`, `SmsDemoSeeder` and `MobileRewardsDemoSeeder`. The `--fresh` option wipes the database first and asks for confirmation.

## Demo Users

The demo environment includes 5 user personas. Each one represents a different use case. All balances are simulated.

### 1. High-Inflation Country User (Argentina)
- **Email**: demo.argentina@gcu.global
- **Password**: demo123
- **Scenario**: Demo persona with simulated multi-currency balances
- **Holdings**: $500 USD, 450 GCU
- **Banks**: 40% Bank A, 30% Bank B, 30% Bank C (simulated allocation)

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

`--with-admin` creates this user with `make:filament-user`, which does not assign the `admin` role. The admin panel accepts only users with that role and sends everyone else to `/dashboard`. Run `php artisan user:promote admin@gcu.global` before you open `/admin`.

## Demo Features

### 1. GCU Basket
`GCUBasketSeeder` creates the demo basket with USD (40%), EUR (30%), GBP (15%), CHF (10%), JPY (3%) and Gold (XAU, 2%). The scheduler runs `baskets:rebalance` on the 1st of each month and recalculates basket values every hour. Both jobs run only when the Basket module is enabled.

### 2. Voting System
The seeder creates three simulated polls on the demo basket composition. There is an active poll for the current month with demo votes from the Argentina, investor and business users. There is an executed poll for last month and a poll for next month. The scheduler runs `voting:setup` on the 20th of each month when the Governance module is enabled.

### 3. Multi-Bank Distribution (simulated)
Each demo user has different simulated bank allocation preferences. This shows how the multi-bank distribution settings work in the demo. No real bank accounts are involved.

### 4. Transaction History
The demo seeder does not create transaction history. It writes the starting balances directly. Use the API or `php artisan demo:deposit` to create transactions.

## API Testing

### Authentication
```bash
# Get auth token
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "demo.user@gcu.global", "password": "demo123"}'
```

### Key Endpoints
- `GET /api/accounts/{uuid}/balances` returns multi-asset balances.
- `GET /api/voting/polls` lists the voting polls.
- `POST /api/voting/polls/{uuid}/vote` submits a simulated vote.
- `GET /api/v1/assets` lists all assets, including GCU.

## Testing Scenarios

### 1. Multi-currency view (Argentina persona)
Log in as demo.argentina@gcu.global. View the simulated GCU demo balance and the simulated bank allocation settings.

### 2. International Payments (Nomad User)
Log in as demo.nomad@gcu.global. View the multi-currency balances and try a currency conversion.

### 3. Business Operations (Business User)
Log in as demo.business@gcu.global. Check the larger multi-currency holdings and the simulated bank allocation settings.

### 4. Basket vote (simulated)
Log in as any demo user and open the current voting poll. Submit a simulated vote for the demo basket composition. The voting power shown is simulated and based on the demo GCU balances.

## Resetting Demo Data

```bash
# Complete fresh start
php artisan migrate:fresh --seed
php artisan demo:populate --with-admin

# Wipe the database and repopulate the demo data
php artisan demo:populate --fresh
```

## Local Development

The demo environment works with the standard local development setup.

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

A hosted demo runs with `APP_ENV=demo`, not `production`. See `.env.demo` for the full set of demo settings. To deploy one:

1. Set `APP_ENV=demo` and keep `MODULES_DISABLED` empty so that the demo domains load.
2. Configure the domain, for example demo.gcu.global.
3. Set up SSL certificates.
4. Configure an email service for notifications.
5. Set up monitoring and logging.

## Support

If the demo does not work, try these checks:
- Read the logs in `storage/logs/`.
- Make sure the queue workers are running.
- Make sure Redis is available for caching.
- Make sure the database migrations are up to date.
