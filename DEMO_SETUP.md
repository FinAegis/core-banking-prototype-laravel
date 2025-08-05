# FinAegis Demo Environment Setup Guide

This guide explains how to set up and run the FinAegis core banking platform in demo mode.

## Overview

Demo mode allows you to showcase the platform's capabilities without requiring real payment processor integrations, bank APIs, or blockchain connections. All external API calls are bypassed and simulated with instant responses.

## Quick Start

1. **Copy the demo environment file:**
   ```bash
   cp .env.demo .env
   ```

2. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

3. **Set up the demo database:**
   ```bash
   mysql -e "CREATE DATABASE finaegis_demo"
   php artisan migrate:fresh --seed
   ```

4. **Start the demo server:**
   ```bash
   php artisan serve
   npm run dev
   ```

5. **Create a demo admin user:**
   ```bash
   php artisan make:filament-user --name="Demo Admin" --email="admin@demo.com"
   ```

## Demo Mode Features

### 1. Instant Payment Processing
- **Card Deposits**: Simulated Stripe payments complete instantly
- **Bank Deposits**: Mock bank transfers complete immediately
- **Withdrawals**: Instant processing without actual bank integration
- **OpenBanking**: Simulated OAuth flow with instant authorization

### 2. Mock External Services
- **Banks**: DemoBankConnector simulates bank operations
- **Blockchain**: Fake transactions without actual blockchain interaction
- **Exchange Rates**: Fixed rates for consistent demos
- **KYC/AML**: Auto-approved verification

### 3. Demo Data Generation
- **Default Amounts**: $100 for deposits, configurable via `DEMO_DEFAULT_DEPOSIT_AMOUNT`
- **Test Accounts**: Auto-created with $1,000 starting balance
- **Transaction History**: Pre-populated with sample data

## Configuration Options

### Environment Variables

Key demo settings in `.env`:

```env
# Enable demo mode
DEMO_MODE=true

# Feature toggles
DEMO_INSTANT_DEPOSITS=true      # Skip payment processing delays
DEMO_SKIP_KYC=true              # Auto-approve KYC verification
DEMO_MOCK_BANKS=true            # Use mock bank connectors
DEMO_FAKE_BLOCKCHAIN=true       # Simulate blockchain transactions
DEMO_FIXED_EXCHANGE_RATES=true  # Use fixed exchange rates

# Demo indicators
DEMO_SHOW_BANNER=true           # Show demo mode banner
DEMO_WATERMARK=true             # Add watermark to pages

# Demo data
DEMO_DEFAULT_DEPOSIT_AMOUNT=10000  # $100.00
DEMO_DEFAULT_CURRENCY=USD
DEMO_SUCCESS_RATE=100              # 100% success rate
```

### Configuration File

Advanced settings in `config/demo.php`:

```php
'demo_data' => [
    'exchange_rates' => [
        'EUR/USD' => 1.10,
        'GBP/USD' => 1.27,
        // Add more rates as needed
    ],
],

'security' => [
    'rate_limiting' => [
        'deposits_per_hour' => 10,
        'withdrawals_per_hour' => 5,
    ],
],
```

## Demo Workflows

### 1. Card Deposit Flow
```
User clicks "Deposit" → Enters amount → Clicks "Pay with Card"
→ Demo payment intent created → Instant success → Balance updated
```

### 2. Bank Deposit Flow
```
User selects "Bank Transfer" → Chooses demo bank → Enters amount
→ Instant transfer simulation → Balance updated immediately
```

### 3. Withdrawal Flow
```
User requests withdrawal → Enters bank details → Confirms
→ Instant processing → Demo transaction created
```

### 4. Crypto Operations
```
User selects crypto → Gets demo address → "Sends" crypto
→ Fake blockchain confirmation → Balance credited
```

## Testing Different Scenarios

### Success Scenarios
By default, all operations succeed. The system simulates:
- Successful card payments
- Completed bank transfers
- Approved KYC verification
- Confirmed blockchain transactions

### Failure Scenarios
To test error handling, you can:
1. Set `DEMO_SUCCESS_RATE=50` for 50% failure rate
2. Use specific test card numbers (if implementing Stripe test cards)
3. Trigger validation errors with invalid data

### Multi-Currency Testing
Demo mode includes fixed exchange rates:
- EUR/USD: 1.10
- GBP/USD: 1.27
- USD/EUR: 0.91
- USD/GBP: 0.79

## Sandbox Mode

For more realistic testing with actual APIs (but fake money):

```env
DEMO_SANDBOX_ENABLED=true
STRIPE_TEST_MODE=true
STRIPE_KEY=pk_test_YOUR_TEST_KEY
STRIPE_SECRET=sk_test_YOUR_TEST_SECRET
```

This uses:
- Stripe test mode with test cards
- Bank sandbox APIs
- Blockchain testnets

## Demo Commands

### Create Demo Data
```bash
# Create demo user with balance
php artisan demo:create-user user@example.com --balance=5000

# Generate demo transactions
php artisan demo:generate-transactions user@example.com --count=10

# Regular demo deposit (processes through queue)
php artisan demo:deposit user@example.com 100 --asset=USD

# Instant demo deposit (bypasses queue, demo mode only)
php artisan demo:deposit user@example.com 100 --asset=USD --instant

# Deposit with custom description
php artisan demo:deposit user@example.com 100 --asset=EUR --description="Test deposit" --instant
```

### Clean Demo Data
```bash
# Remove demo data older than 1 day (default)
php artisan demo:cleanup

# Remove demo data older than 7 days
php artisan demo:cleanup --days=7

# Preview what would be deleted (dry run)
php artisan demo:cleanup --dry-run

# Reset demo environment
php artisan demo:reset
```

## Security Considerations

Demo mode includes several security features:

1. **Database Isolation**: Uses separate `finaegis_demo` database
2. **API Blocking**: External APIs are disabled
3. **Clear Indicators**: Visual banners show demo mode
4. **Rate Limiting**: Prevents abuse of demo environment
5. **Data Cleanup**: Automatic removal of old demo data

## Troubleshooting

### Common Issues

1. **"No users found for demo deposit"**
   - Create a user first: `php artisan demo:create-user`

2. **Payment processing errors**
   - Ensure `DEMO_MODE=true` in `.env`
   - Check `storage/logs/laravel.log` for details

3. **Exchange rates not working**
   - Verify `DEMO_FIXED_EXCHANGE_RATES=true`
   - Check `config/demo.php` for rate configuration

### Debug Mode

Enable detailed logging:
```env
LOG_LEVEL=debug
DEMO_DEBUG=true
```

## Production Transition

To move from demo to production:

1. **Update Environment:**
   ```env
   APP_ENV=production
   DEMO_MODE=false
   ```

2. **Configure Real Services:**
   - Add real Stripe API keys
   - Configure bank API credentials
   - Set up blockchain node access

3. **Security Checklist:**
   - [ ] Remove demo users
   - [ ] Disable demo endpoints
   - [ ] Configure production database
   - [ ] Set up monitoring
   - [ ] Enable rate limiting
   - [ ] Configure backup systems

## API Testing

Test demo endpoints with curl:

```bash
# Create payment intent
curl -X POST http://localhost:8000/api/v1/deposits/intent \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d "amount=10000&currency=USD"

# Process deposit (demo mode)
curl -X POST http://localhost:8000/api/v1/deposits/process \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d "payment_intent_id=demo_pi_123"
```

## Support

For issues or questions:
1. Check `storage/logs/laravel.log`
2. Review this documentation
3. Contact the development team

## Next Steps

1. **Explore Admin Panel**: Log in at `/admin` with demo credentials
2. **Test User Flows**: Try deposits, withdrawals, and transfers
3. **Review Code**: Examine demo service implementations
4. **Customize**: Modify `config/demo.php` for your needs