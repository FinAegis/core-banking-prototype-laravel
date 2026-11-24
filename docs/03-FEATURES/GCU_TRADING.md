# GCU Trading (Simulated Demo)

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.
>
> See [REGULATORY-CLAIMS.md](../REGULATORY-CLAIMS.md).

## Overview

The GCU trading module is a demonstration of converting demo fiat balances into GCU demo units and back.
It shows quotes, a fee and limits tied to identity-check (Know Your Customer, KYC) levels as reference logic.
No real funds are used.

The module belongs to the Basket domain.
Its API and web routes use the `module.enabled:Basket` middleware, and production deployments list `Basket` in `MODULES_DISABLED`.
It therefore only runs on the demo site or a local install.

## Features

### Simulated buy

A buy converts a demo balance in EUR, USD, GBP or CHF into GCU demo units.
The minimum amount is 100 units of the chosen currency.
The quote shows a 1% fee on the amount.

### Simulated sell

A sell converts GCU demo units back into EUR, USD, GBP or CHF.
The minimum amount is 10 GCU.
The quote shows a 1% fee on the proceeds.

### Trading limits (reference logic)

The limits endpoint returns illustrative limits for each KYC level.
At v7.18.1 the controller always reports KYC level 2 and reports zero used amounts.
The buy and sell endpoints do not check these limits.

| KYC Level | Status | Daily Buy | Daily Sell | Monthly Buy | Monthly Sell |
|-----------|--------|-----------|------------|-------------|--------------|
| 0 | Unverified | €0 | €0 | €0 | €0 |
| 1 | Basic | €1,000 | €1,000 | €10,000 | €10,000 |
| 2 | Verified | €10,000 | €10,000 | €100,000 | €100,000 |
| 3 | Enhanced | €50,000 | €50,000 | €500,000 | €500,000 |
| 4 | Corporate | €1,000,000 | €1,000,000 | €10,000,000 | €10,000,000 |

### Quotes

A quote is valid for 5 minutes.
Its rate comes from the latest stored GCU basket value in `basket_values`.
It lists the fee and the exchange rate before anything is converted.
When no basket value is stored, the buy, sell and quote endpoints answer with HTTP 503.

### What the conversion books

Since v7.18.1 a buy or sell books the amount the person typed, converted exactly into the asset's minor units (#1247).
The conversion uses the stored exchange rate between the two assets in `exchange_rates` and does not take a fee.
The quote and the buy or sell response use the GCU basket value and the 1% fee instead.
The amounts in the response can therefore differ from what the conversion books.
Aligning the two needs a product decision.

## User Interface

The web page is `/gcu/trading`. It is served by `App\Http\Controllers\GcuTradingController` with the Blade view `resources/views/gcu/trading.blade.php`.
The page is meant to show the illustrative GCU value, the person's simulated GCU and USD balances, a price history, recent demo trades and forms for a simulated buy and sell.
At v7.18.1 the page cannot load.
Its controller reads prices through a `BasketPrice` model that does not exist, and its forms point at a route named `api.exchange` that is not registered.
Use the API endpoints below instead.

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

### Backend

The API controller is `App\Http\Controllers\Api\V2\GCUTradingController`.
It handles buy, sell, quote and trading limits.
A buy or sell starts `App\Domain\Wallet\Workflows\WalletConvertWorkflow` to move the demo balances.
`ExchangeRateService` and `AccountService` support the conversion.

Every endpoint needs a Sanctum token.
The controller checks that the account belongs to the caller and is not frozen.
The buy and sell routes also use the `transaction.rate_limit:convert` middleware.

### Frontend

The `/gcu/trading` page is a Blade view.
A Vue component, `resources/js/Pages/GCU/Trading.vue`, also exists in the repository, but no route renders it.

## Trading Flow (simulated)

### Buy flow

1. The person enters an amount in a fiat currency and asks for a quote.
2. The person reviews the quote and the fee, then confirms.
3. The controller checks the account owner, the frozen flag and the demo balance.
4. The conversion workflow updates the demo balances.
5. The response returns the new simulated GCU balance.

### Sell flow

1. The person enters an amount in GCU and asks for a quote.
2. The person reviews the quote and the fee, then confirms.
3. The controller checks the account owner, the frozen flag and the GCU demo balance.
4. The conversion workflow updates the demo balances.
5. The response returns the new simulated GCU balance.

## Error Handling

### Common errors

The endpoints reject an amount below the minimum, an amount that is not a valid number or an unsupported currency with HTTP 422.
They reject an account that belongs to someone else with HTTP 403.
They reject a frozen account or an insufficient demo balance with HTTP 422.
They answer with HTTP 503 when no GCU basket value is stored.
A failure inside the conversion answers with HTTP 500 and the error "Transaction Failed".

## Testing

`tests/Feature/Api/V2/GcuTradingUnitsTest.php` checks that buy and sell pass the right minor-unit amounts to the conversion.
`tests/Feature/Basket/GcuBasketValueLookupTest.php` covers the GCU basket value lookup.
`tests/Feature/ProductionSwitchesTest.php` checks that the routes are switched off when the Basket module is disabled.
