# GCU Trading (Simulated Demo)

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.
>
> See [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md).

## Overview

The GCU demo trading module simulates conversions between demo fiat balances and GCU demo units, with quotes, fees and KYC-tiered limits as reference logic. No real funds are used.

## Features

### 1. Simulated Buy (demo)
- **Supported Currencies**: EUR, USD, GBP, CHF
- **Minimum Purchase**: 100 units of selected currency
- **Trading Fee**: 1% of transaction amount
- **Real-time Quotes**: Live exchange rates with 5-minute validity
- **Execution**: Immediate update of demo ledger balances

### 2. Simulated Sell (demo)
- **Supported Currencies**: EUR, USD, GBP, CHF
- **Minimum Sale**: 10 GCU
- **Trading Fee**: 1% of transaction amount
- **Real-time Quotes**: Live exchange rates with 5-minute validity
- **Settlement**: Immediate update of demo fiat balance

### 3. Trading Limits (reference logic)
Illustrative demo limits are based on KYC verification level:

| KYC Level | Status | Daily Buy | Daily Sell | Monthly Buy | Monthly Sell |
|-----------|--------|-----------|------------|-------------|--------------|
| 0 | Unverified | €0 | €0 | €0 | €0 |
| 1 | Basic | €1,000 | €1,000 | €10,000 | €10,000 |
| 2 | Verified | €10,000 | €10,000 | €100,000 | €100,000 |
| 3 | Enhanced | €50,000 | €50,000 | €500,000 | €500,000 |
| 4 | Corporate | €1,000,000 | €1,000,000 | €10,000,000 | €10,000,000 |

### 4. Quote System
- **Quote Validity**: 5 minutes
- **Dynamic Pricing**: Based on current GCU basket value
- **Fee Transparency**: Clear breakdown of fees before execution
- **Exchange Rate Display**: Shows exact conversion rates

## User Interface

### Trading Page (`/gcu/trading`)
The trading interface provides:

1. **GCU Summary Card**
   - Illustrative GCU demo value in USD
   - 24-hour change of the illustrative value
   - User's simulated GCU demo balance

2. **Simulated Buy Panel**
   - Amount input (fiat currency)
   - Currency selector
   - Real-time quote display
   - Fee breakdown
   - Buy button with validation

3. **Simulated Sell Panel**
   - Amount input (GCU)
   - Currency selector
   - Real-time quote display
   - Fee breakdown
   - Sell button with validation

4. **Trading Limits Dashboard**
   - Daily limits with progress bars
   - Monthly limits with progress bars
   - KYC level display
   - Link to increase limits

## API Endpoints

### 1. Simulated Buy (demo ledger)
```
POST /api/v2/gcu/buy
```

**Request Body:**
```json
{
    "amount": 1000.00,
    "currency": "EUR",
    "account_uuid": "optional-account-uuid"
}
```

**Response:**
```json
{
    "data": {
        "transaction_id": "550e8400-e29b-41d4-a716-446655440000",
        "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
        "spent_amount": 1000.00,
        "spent_currency": "EUR",
        "received_amount": 912.45,
        "received_currency": "GCU",
        "exchange_rate": 0.91245,
        "fee_amount": 10.00,
        "fee_currency": "EUR",
        "new_gcu_balance": 1912.45,
        "timestamp": "2024-09-02T15:30:00Z"
    },
    "message": "Successfully purchased 912.45 GCU"
}
```

### 2. Simulated Sell (demo ledger)
```
POST /api/v2/gcu/sell
```

**Request Body:**
```json
{
    "amount": 100.00,
    "currency": "EUR",
    "account_uuid": "optional-account-uuid"
}
```

**Response:**
```json
{
    "data": {
        "transaction_id": "660e8400-e29b-41d4-a716-446655440001",
        "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
        "sold_amount": 100.00,
        "sold_currency": "GCU",
        "received_amount": 109.00,
        "received_currency": "EUR",
        "exchange_rate": 1.0956,
        "fee_amount": 1.10,
        "fee_currency": "EUR",
        "new_gcu_balance": 812.45,
        "timestamp": "2024-09-02T15:35:00Z"
    },
    "message": "Successfully sold 100.00 GCU"
}
```

### 3. Get Quote
```
GET /api/v2/gcu/quote?operation=buy&amount=1000&currency=EUR
```

**Response:**
```json
{
    "data": {
        "operation": "buy",
        "input_amount": 1000.00,
        "input_currency": "EUR",
        "output_amount": 912.45,
        "output_currency": "GCU",
        "exchange_rate": 0.91245,
        "fee_amount": 10.00,
        "fee_currency": "EUR",
        "fee_percentage": 1.0,
        "quote_valid_until": "2024-09-02T15:35:00Z",
        "minimum_amount": 100.00,
        "maximum_amount": 1000000.00
    }
}
```

### 4. Get Trading Limits
```
GET /api/v2/gcu/trading-limits
```

**Response:**
```json
{
    "data": {
        "daily_buy_limit": 10000.00,
        "daily_sell_limit": 10000.00,
        "daily_buy_used": 2500.00,
        "daily_sell_used": 0.00,
        "monthly_buy_limit": 100000.00,
        "monthly_sell_limit": 100000.00,
        "monthly_buy_used": 15000.00,
        "monthly_sell_used": 5000.00,
        "minimum_buy_amount": 100.00,
        "minimum_sell_amount": 10.00,
        "kyc_level": 2,
        "limits_currency": "EUR"
    }
}
```

## Technical Implementation

### Backend Architecture

1. **Controller**: `App\Http\Controllers\Api\V2\GCUTradingController`
   - Handles buy/sell operations
   - Provides quotes and limits
   - Validates transactions

2. **Services Used**:
   - `ExchangeRateService`: Currency conversion
   - `AccountService`: Balance management
   - `AssetTransferWorkflow`: Transaction execution

3. **Security Features**:
   - Authentication required (Sanctum)
   - Account ownership verification
   - Frozen account checks
   - Transaction rate limiting

### Frontend Implementation

1. **Vue Component**: `resources/js/Pages/GCU/Trading.vue`
   - Real-time quote updates
   - Form validation
   - Error handling
   - Loading states

2. **Features**:
   - Debounced quote requests
   - Input validation
   - Progress indicators
   - Success/error notifications

## Trading Flow (simulated)

### Buy Flow
1. User enters amount in fiat currency
2. System fetches real-time quote
3. User reviews quote and fees
4. User confirms purchase
5. System validates:
   - Sufficient fiat balance
   - Trading limits not exceeded
   - Account not frozen
6. Transaction executed via workflow
7. Balances updated
8. Confirmation displayed

### Sell Flow
1. User enters amount in GCU
2. System fetches real-time quote
3. User reviews quote and fees
4. User confirms sale
5. System validates:
   - Sufficient GCU balance
   - Trading limits not exceeded
   - Account not frozen
6. Transaction executed via workflow
7. Balances updated
8. Confirmation displayed

## Error Handling

### Common Errors
1. **Insufficient Balance**: User lacks required funds
2. **Account Frozen**: Trading disabled on frozen accounts
3. **Below Minimum**: Amount below minimum threshold
4. **Above Limits**: Exceeds daily/monthly limits
5. **Invalid Currency**: Unsupported currency selected
6. **GCU Value Unavailable**: Cannot determine current value

### Error Responses
All errors return appropriate HTTP status codes:
- `400`: Bad Request (invalid parameters)
- `403`: Forbidden (unauthorized access)
- `422`: Unprocessable Entity (validation errors)
- `500`: Internal Server Error (system errors)
- `503`: Service Unavailable (external service down)

## Testing

### Unit Tests
Located in `tests/Feature/Api/V2/GCUTradingTest.php`:
- Quote generation
- Buy operations
- Sell operations
- Trading limits
- Error scenarios
- Authentication

### Browser Tests
Located in `tests/Browser/GCUTradingTest.php`:
- UI interaction
- Form validation
- Quote updates
- Transaction flow
- Error display
