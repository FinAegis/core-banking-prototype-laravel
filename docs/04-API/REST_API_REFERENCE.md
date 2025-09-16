# FinAegis REST API Reference

**Version:** 2.0  
**Last Updated:** 2024-09-14  
**Status:** Demo Platform - All Phase 8 Features Implemented

This document consolidates all REST API endpoints for the FinAegis Core Banking Platform, including the complete Phase 8 unified platform features.

## Table of Contents
- [Authentication](#authentication)
- [Account Management](#account-management)
- [Asset Management](#asset-management)
- [Transaction Management](#transaction-management)
- [Transfer Operations](#transfer-operations)
- [Exchange Rates](#exchange-rates)
- [Governance & Voting](#governance--voting)
- [GCU Trading](#gcu-trading)
- [CGO Investment Platform](#cgo-investment-platform)
- [Custodian Integration](#custodian-integration)
- [Webhooks](#webhooks)
- [Bank Allocation](#bank-allocation)
- [Batch Processing](#batch-processing)
- [Transaction Reversal](#transaction-reversal)
- [Regulatory Reporting](#regulatory-reporting)
- [Daily Reconciliation](#daily-reconciliation)
- [Bank Alerting](#bank-alerting)
- [Basket Performance](#basket-performance)
- [Stablecoin Operations](#stablecoin-operations)
- [User Voting](#user-voting)
- [KYC Management](#kyc-management)
- [GDPR Compliance](#gdpr-compliance)
- [Exchange & Trading](#exchange--trading)
- [Liquidity Pools](#liquidity-pools)
- [P2P Lending](#p2p-lending)
- [Blockchain Wallets](#blockchain-wallets)
- [External Exchange Integration](#external-exchange-integration)

## Authentication

All API endpoints require authentication using Laravel Sanctum tokens.

### Login
```http
POST /api/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}
```

### Logout
```http
POST /api/logout
Authorization: Bearer {token}
```

## Account Management

### List Accounts
```http
GET /api/accounts
Authorization: Bearer {token}
```

### Get Account Details
```http
GET /api/accounts/{uuid}
Authorization: Bearer {token}
```

### Create Account
```http
POST /api/accounts
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Savings Account",
  "currency": "USD",
  "type": "savings"
}
```

### Get Account Balances (Multi-Asset)
```http
GET /api/accounts/{uuid}/balances
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
    "balances": {
      "USD": 150000,
      "EUR": 50000,
      "BTC": 100000000,
      "GCU": 25000
    },
    "total_value_usd": 250000
  }
}
```

### Get Transaction History
```http
GET /api/accounts/{uuid}/transactions?page=1&per_page=20
Authorization: Bearer {token}
```

## Asset Management

### List Assets
```http
GET /api/assets
Authorization: Bearer {token}
```

### Get Asset Details
```http
GET /api/assets/{code}
Authorization: Bearer {token}
```

### Create Asset (Admin)
```http
POST /api/assets
Authorization: Bearer {token}
Content-Type: application/json

{
  "code": "JPY",
  "name": "Japanese Yen",
  "type": "fiat",
  "precision": 0,
  "is_active": true
}
```

### Get Asset Statistics
```http
GET /api/assets/{code}/statistics
Authorization: Bearer {token}
```

## Transaction Management

### Create Deposit
```http
POST /api/accounts/{uuid}/deposit
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 10000,
  "asset_code": "USD",
  "reference": "DEP-123456"
}
```

### Create Withdrawal
```http
POST /api/accounts/{uuid}/withdraw
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 5000,
  "asset_code": "USD",
  "reference": "WTH-123456"
}
```

### Get Transaction Status
```http
GET /api/transactions/{id}
Authorization: Bearer {token}
```

## Transfer Operations

### Create Transfer
```http
POST /api/transfers
Authorization: Bearer {token}
Content-Type: application/json

{
  "from_account_uuid": "123e4567-e89b-12d3-a456-426614174000",
  "to_account_uuid": "987fcdeb-51a2-43d1-b890-123456789012",
  "amount": 10000,
  "asset_code": "USD",
  "reference": "TRF-123456",
  "description": "Payment for services"
}
```

### Create Cross-Asset Transfer
```http
POST /api/transfers/cross-asset
Authorization: Bearer {token}
Content-Type: application/json

{
  "from_account_uuid": "123e4567-e89b-12d3-a456-426614174000",
  "to_account_uuid": "987fcdeb-51a2-43d1-b890-123456789012",
  "from_asset_code": "USD",
  "to_asset_code": "EUR",
  "amount": 10000,
  "reference": "XTF-123456"
}
```

### Get Transfer Status
```http
GET /api/transfers/{id}
Authorization: Bearer {token}
```

## Exchange Rates

### Get Current Rate
```http
GET /api/exchange-rates/{from}/{to}
Authorization: Bearer {token}
```

### Get Rate History
```http
GET /api/exchange-rates/{from}/{to}/history?days=30
Authorization: Bearer {token}
```

### Update Exchange Rate (Admin)
```http
POST /api/exchange-rates
Authorization: Bearer {token}
Content-Type: application/json

{
  "from_asset": "USD",
  "to_asset": "EUR",
  "rate": 0.85,
  "provider": "ECB"
}
```

## Governance & Voting

### List Polls
```http
GET /api/voting/polls?status=active
Authorization: Bearer {token}
```

### Get Poll Details
```http
GET /api/voting/polls/{uuid}
Authorization: Bearer {token}
```

### Submit Vote
```http
POST /api/voting/polls/{uuid}/vote
Authorization: Bearer {token}
Content-Type: application/json

{
  "allocations": {
    "USD": 35,
    "EUR": 30,
    "GBP": 20,
    "CHF": 10,
    "JPY": 3,
    "XAU": 2
  }
}
```

### Get Voting Power
```http
GET /api/voting/polls/{uuid}/voting-power
Authorization: Bearer {token}
```

### Get Poll Results
```http
GET /api/voting/polls/{uuid}/results
Authorization: Bearer {token}
```

### Get Voting Dashboard
```http
GET /api/voting/dashboard
Authorization: Bearer {token}
```

### Create Poll (Admin)
```http
POST /api/polls
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Q3 2024 GCU Basket Composition",
  "description": "Vote on the asset allocation for the next quarter",
  "type": "basket_composition",
  "start_date": "2024-09-01",
  "end_date": "2024-09-07",
  "voting_power_strategy": "AssetWeightedVotingStrategy"
}
```

## GCU Trading

### Buy GCU
```http
POST /api/v2/gcu/buy
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 1000.00,
  "currency": "EUR",
  "account_uuid": "123e4567-e89b-12d3-a456-426614174000"
}
```

Response:
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

### Sell GCU
```http
POST /api/v2/gcu/sell
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": 100.00,
  "currency": "EUR",
  "account_uuid": "123e4567-e89b-12d3-a456-426614174000"
}
```

Response:
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

### Get Trading Quote
```http
GET /api/v2/gcu/quote?operation=buy&amount=1000&currency=EUR
Authorization: Bearer {token}
```

Response:
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

### Get Trading Limits
```http
GET /api/v2/gcu/trading-limits
Authorization: Bearer {token}
```

Response:
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

### Get GCU Info (Public)
```http
GET /api/v2/gcu
```

Response:
```json
{
  "data": {
    "code": "GCU",
    "name": "Global Currency Unit",
    "symbol": "Ǥ",
    "current_value": 1.0975,
    "value_currency": "USD",
    "last_rebalanced": "2024-09-01T00:00:00Z",
    "next_rebalance": "2024-09-01T00:00:00Z",
    "composition": [
      {
        "asset_code": "USD",
        "asset_name": "US Dollar",
        "weight": 0.25,
        "value_contribution": 0.2500
      }
    ],
    "statistics": {
      "total_supply": 10000000,
      "holders_count": 1234,
      "24h_change": 0.25,
      "7d_change": 1.50,
      "30d_change": 2.75
    }
  }
}
```

## CGO Investment Platform

### Create Investment
```http
POST /api/cgo/investments
Authorization: Bearer {token}
Content-Type: application/json

{
  "tier": "explorer",
  "amount": 10000,
  "payment_method": "stripe"
}
```

Response:
```json
{
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "user_id": 1,
    "tier": "explorer",
    "amount": 10000,
    "currency": "USD",
    "payment_method": "stripe",
    "payment_status": "pending",
    "kyc_status": "pending",
    "status": "pending",
    "round_id": 1,
    "created_at": "2024-09-07T10:00:00Z"
  },
  "message": "Investment created successfully"
}
```

### Get Investment Details
```http
GET /api/cgo/investments/{uuid}
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "user_id": 1,
    "tier": "explorer",
    "amount": 10000,
    "currency": "USD",
    "payment_method": "stripe",
    "payment_status": "completed",
    "payment_reference": "pi_1234567890",
    "kyc_status": "approved",
    "kyc_level": "enhanced",
    "status": "active",
    "round": {
      "id": 1,
      "name": "Seed Round",
      "start_date": "2024-09-01",
      "end_date": "2024-09-30",
      "target_amount": 5000000,
      "raised_amount": 1250000
    },
    "agreement_path": "cgo/agreements/investment-550e8400.pdf",
    "certificate_path": "cgo/certificates/certificate-550e8400.pdf",
    "created_at": "2024-09-07T10:00:00Z",
    "updated_at": "2024-09-07T10:30:00Z"
  }
}
```

### Create Stripe Checkout Session
```http
POST /api/cgo/payments/stripe/checkout
Authorization: Bearer {token}
Content-Type: application/json

{
  "investment_uuid": "550e8400-e29b-41d4-a716-446655440000"
}
```

Response:
```json
{
  "data": {
    "checkout_url": "https://checkout.stripe.com/pay/cs_test_a1b2c3d4e5",
    "session_id": "cs_test_a1b2c3d4e5",
    "expires_at": "2024-09-07T11:00:00Z"
  }
}
```

### Create Coinbase Commerce Charge
```http
POST /api/cgo/payments/coinbase/charge
Authorization: Bearer {token}
Content-Type: application/json

{
  "investment_uuid": "550e8400-e29b-41d4-a716-446655440000"
}
```

Response:
```json
{
  "data": {
    "charge_id": "66BEOV2A",
    "hosted_url": "https://commerce.coinbase.com/charges/66BEOV2A",
    "code": "66BEOV2A",
    "pricing": {
      "bitcoin": {
        "amount": "0.00236000",
        "currency": "BTC"
      },
      "ethereum": {
        "amount": "0.039000",
        "currency": "ETH"
      },
      "usdc": {
        "amount": "10000.000000",
        "currency": "USDC"
      }
    },
    "expires_at": "2024-09-07T11:00:00Z"
  }
}
```

### Verify Payment
```http
POST /api/cgo/payments/verify
Authorization: Bearer {token}
Content-Type: application/json

{
  "investment_uuid": "550e8400-e29b-41d4-a716-446655440000"
}
```

### Get Investment Agreement
```http
GET /api/cgo/investments/{uuid}/agreement
Authorization: Bearer {token}
```

Returns PDF file of the investment agreement.

### Get Investment Certificate
```http
GET /api/cgo/investments/{uuid}/certificate
Authorization: Bearer {token}
```

Returns PDF file of the investment certificate.

### Request Refund
```http
POST /api/cgo/investments/{uuid}/refund
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "changed_mind",
  "reason_details": "I've decided to invest in a different tier"
}
```

Response:
```json
{
  "data": {
    "refund_id": "770e8400-e29b-41d4-a716-446655440000",
    "investment_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "amount": 10000,
    "currency": "USD",
    "status": "pending",
    "reason": "changed_mind",
    "reason_details": "I've decided to invest in a different tier",
    "requested_at": "2024-09-07T12:00:00Z"
  },
  "message": "Refund request submitted successfully"
}
```

### Get Refund Status
```http
GET /api/cgo/refunds/{refund_id}
Authorization: Bearer {token}
```

### Stripe Webhook Handler
```http
POST /api/cgo/webhooks/stripe
Content-Type: application/json
Stripe-Signature: {webhook_signature}

{webhook_payload}
```

### Coinbase Commerce Webhook Handler
```http
POST /api/cgo/webhooks/coinbase
Content-Type: application/json
X-CC-Webhook-Signature: {webhook_signature}

{webhook_payload}
```

## Custodian Integration

### List Custodians
```http
GET /api/custodians
Authorization: Bearer {token}
```

### Get Custodian Balance
```http
GET /api/custodians/{id}/balance
Authorization: Bearer {token}
```

### Trigger Reconciliation
```http
POST /api/custodians/{id}/reconcile
Authorization: Bearer {token}
```

### Get Custodian Transactions
```http
GET /api/custodians/{id}/transactions?limit=100
Authorization: Bearer {token}
```

### Get Custodian Health
```http
GET /api/custodians/{id}/health
Authorization: Bearer {token}
```

## Webhooks

### Register Webhook
```http
POST /api/webhooks
Authorization: Bearer {token}
Content-Type: application/json

{
  "url": "https://example.com/webhook",
  "events": ["transaction.completed", "transfer.failed"],
  "secret": "webhook-secret-key"
}
```

### List Webhooks
```http
GET /api/webhooks
Authorization: Bearer {token}
```

### Delete Webhook
```http
DELETE /api/webhooks/{id}
Authorization: Bearer {token}
```

## Error Responses

All endpoints follow a consistent error response format:

```json
{
  "message": "Validation failed",
  "errors": {
    "amount": ["The amount must be greater than 0"],
    "asset_code": ["The selected asset code is invalid"]
  }
}
```

Common HTTP status codes:
- `200 OK` - Successful request
- `201 Created` - Resource created successfully
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Missing or invalid authentication
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation errors
- `500 Internal Server Error` - Server error

## Rate Limiting

API requests are rate limited to:
- 60 requests per minute for authenticated users
- 30 requests per minute for unauthenticated users

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests per minute
- `X-RateLimit-Remaining`: Remaining requests
- `X-RateLimit-Reset`: Unix timestamp when limit resets

## Pagination

List endpoints support pagination with these parameters:
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 100)

Paginated responses include metadata:
```json
{
  "data": [...],
  "links": {
    "first": "https://api.finaegis.org/accounts?page=1",
    "last": "https://api.finaegis.org/accounts?page=10",
    "prev": null,
    "next": "https://api.finaegis.org/accounts?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```
## Basket API

### List Baskets

```
GET /api/v2/baskets
```

Query parameters:
- `type` (string): Filter by basket type (fixed/dynamic)
- `is_active` (boolean): Filter by active status

Response:
```json
{
  "data": [
    {
      "code": "GCU",
      "name": "Global Currency Unit",
      "type": "dynamic",
      "is_active": true,
      "rebalance_frequency": "monthly",
      "last_rebalanced_at": "2024-06-21T00:00:00Z",
      "components": [
        {
          "asset_code": "USD",
          "weight": 35.0,
          "min_weight": 30.0,
          "max_weight": 40.0
        }
      ]
    }
  ]
}
```

### Get Basket Details

```
GET /api/v2/baskets/{code}
```

### Get Basket Value

```
GET /api/v2/baskets/{code}/value
```

Response:
```json
{
  "basket_code": "GCU",
  "value": 1.0234,
  "currency": "USD",
  "calculated_at": "2024-06-21T10:00:00Z",
  "components": [
    {
      "asset_code": "USD",
      "weight": 35.0,
      "value": 0.3582,
      "exchange_rate": 1.0
    }
  ]
}
```

### Create Basket

```
POST /api/v2/baskets
```

Request body:
```json
{
  "code": "STABLE_BASKET",
  "name": "Stable Currency Basket",
  "type": "fixed",
  "rebalance_frequency": "monthly",
  "components": [
    {
      "asset_code": "USD",
      "weight": 40.0
    },
    {
      "asset_code": "EUR",
      "weight": 35.0
    },
    {
      "asset_code": "GBP",
      "weight": 25.0
    }
  ]
}
```

### Rebalance Basket

```
POST /api/v2/baskets/{code}/rebalance
```

### Account Basket Operations

#### Get Account Basket Holdings

```
GET /api/v2/accounts/{uuid}/baskets
```

#### Decompose Basket

```
POST /api/v2/accounts/{uuid}/baskets/decompose
```

Request body:
```json
{
  "basket_code": "GCU",
  "amount": 10000
}
```

#### Compose Basket

```
POST /api/v2/accounts/{uuid}/baskets/compose
```

Request body:
```json
{
  "basket_code": "GCU",
  "amount": 10000
}
```

## Compliance API

### KYC Management

#### Get KYC Status

```
GET /api/compliance/kyc/status
```

Response:
```json
{
  "status": "approved",
  "level": "enhanced",
  "submitted_at": "2024-06-20T10:00:00Z",
  "approved_at": "2024-06-20T11:00:00Z",
  "expires_at": "2027-06-20T11:00:00Z",
  "needs_kyc": false,
  "documents": [
    {
      "id": "123",
      "type": "passport",
      "status": "verified",
      "uploaded_at": "2024-06-20T10:00:00Z"
    }
  ]
}
```

#### Get KYC Requirements

```
GET /api/compliance/kyc/requirements?level=enhanced
```

#### Submit KYC Documents

```
POST /api/compliance/kyc/submit
```

Request body (multipart/form-data):
```
documents[0][type]=passport
documents[0][file]=@passport.jpg
documents[1][type]=selfie
documents[1][file]=@selfie.jpg
```

### GDPR API

#### Get Consent Status

```
GET /api/compliance/gdpr/consent
```

#### Update Consent

```
POST /api/compliance/gdpr/consent
```

Request body:
```json
{
  "marketing": true,
  "data_retention": true
}
```

#### Request Data Export

```
POST /api/compliance/gdpr/export
```

#### Request Account Deletion

```
POST /api/compliance/gdpr/delete
```

Request body:
```json
{
  "confirm": true,
  "reason": "No longer using the service"
}
```

## Custodian API

### List Custodians

```
GET /api/custodians
```

### Get Custodian Balance

```
GET /api/custodians/{custodian}/balance?account={account_id}&asset_code={asset}
```

### Initiate Custodian Transfer

```
POST /api/custodians/{custodian}/transfer
```

Request body:
```json
{
  "from_account": "account123",
  "to_account": "account456",
  "amount": 10000,
  "asset_code": "EUR",
  "reference": "TRANSFER123"
}
```

## Transaction Projections API

### Get Account Transaction History

```
GET /api/v2/accounts/{account}/transaction-projections
```

Query parameters:
- `asset_code`: Filter by asset
- `type`: Filter by transaction type
- `start_date`: Start date for range
- `end_date`: End date for range
- `page`: Page number
- `per_page`: Items per page

### Get Transaction Summary

```
GET /api/v2/accounts/{account}/transaction-projections/summary
```

### Get Balance History

```
GET /api/v2/accounts/{account}/transaction-projections/balance-history
```

### Export Transactions

```
GET /api/v2/accounts/{account}/transaction-projections/export
```

Returns CSV file with transaction history.

### Search Transactions

```
GET /api/v2/transaction-projections/search
```

Query parameters:
- `q`: Search query
- `account_uuid`: Filter by account
- `asset_code`: Filter by asset
- `types[]`: Filter by transaction types
- `start_date`: Start date
- `end_date`: End date

## Stablecoin API

### List Stablecoins

```
GET /api/v2/stablecoins
```

### Get Stablecoin Metrics

```
GET /api/v2/stablecoins/{code}/metrics
```

### Mint Stablecoins

```
POST /api/v2/stablecoin-operations/mint
```

Request body:
```json
{
  "stablecoin_code": "USDX",
  "account_uuid": "account123",
  "amount": 100000,
  "collateral_asset_code": "USD",
  "collateral_amount": 150000
}
```

### Get Liquidation Opportunities

```
GET /api/v2/stablecoin-operations/liquidation/opportunities
```

## User Voting API

### Get Active Polls

```
GET /api/voting/polls
```

### Submit Vote

```
POST /api/voting/polls/{uuid}/vote
```

Request body (for basket allocation):
```json
{
  "allocations": {
    "USD": 35,
    "EUR": 30,
    "GBP": 20,
    "CHF": 10,
    "JPY": 3,
    "XAU": 2
  }
}
```

### Get Voting Dashboard

```
GET /api/voting/dashboard
```

Response:
```json
{
  "stats": {
    "total_polls": 24,
    "participated": 18,
    "participation_rate": 75
  },
  "active_polls": [...],
  "voting_history": [...],
  "next_poll_date": "2024-09-01"
}
```

## Bank Allocation

### Get User Bank Allocation
```http
GET /api/users/{uuid}/bank-allocation
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "allocations": [
      {
        "bank_name": "Paysera",
        "allocation_percentage": 40.0,
        "priority": 1
      },
      {
        "bank_name": "Deutsche Bank",
        "allocation_percentage": 35.0,
        "priority": 2
      }
    ]
  }
}
```

### Update User Bank Allocation
```http
PUT /api/users/{uuid}/bank-allocation
Authorization: Bearer {token}
Content-Type: application/json

{
  "allocations": [
    {
      "bank_name": "Paysera",
      "allocation_percentage": 30.0,
      "priority": 1
    },
    {
      "bank_name": "Deutsche Bank",
      "allocation_percentage": 40.0,
      "priority": 2
    },
    {
      "bank_name": "Santander",
      "allocation_percentage": 30.0,
      "priority": 3
    }
  ]
}
```

### Get Available Banks
```http
GET /api/bank-allocation/banks
Authorization: Bearer {token}
```

### Get Bank Health Status
```http
GET /api/bank-allocation/health
Authorization: Bearer {token}
```

### Get Allocation Strategies
```http
GET /api/bank-allocation/strategies
Authorization: Bearer {token}
```

### Get User Allocation History
```http
GET /api/users/{uuid}/bank-allocation/history
Authorization: Bearer {token}
```

## Batch Processing

### Create Batch Transaction
```http
POST /api/batch-processing/transactions
Authorization: Bearer {token}
Content-Type: application/json

{
  "batch_id": "BATCH_001",
  "transactions": [
    {
      "from_account_uuid": "account1",
      "to_account_uuid": "account2",
      "amount": 1000,
      "asset_code": "USD",
      "reference": "TXN_001"
    }
  ]
}
```

### Get Batch Status
```http
GET /api/batch-processing/batches/{batch_id}
Authorization: Bearer {token}
```

### List User Batches
```http
GET /api/batch-processing/batches
Authorization: Bearer {token}
```

### Get Batch Statistics
```http
GET /api/batch-processing/statistics
Authorization: Bearer {token}
```

### Process Pending Batches (Admin)
```http
POST /api/batch-processing/process-pending
Authorization: Bearer {token}
```

### Cancel Batch
```http
DELETE /api/batch-processing/batches/{batch_id}
Authorization: Bearer {token}
```

## Transaction Reversal

### Create Reversal Request
```http
POST /api/transaction-reversal/request
Authorization: Bearer {token}
Content-Type: application/json

{
  "transaction_id": "txn_123",
  "reason": "duplicate_payment",
  "description": "Accidental duplicate transaction"
}
```

### Get Reversal Status
```http
GET /api/transaction-reversal/requests/{request_id}
Authorization: Bearer {token}
```

### List User Reversal Requests
```http
GET /api/transaction-reversal/requests
Authorization: Bearer {token}
```

### Approve Reversal (Admin)
```http
POST /api/transaction-reversal/requests/{request_id}/approve
Authorization: Bearer {token}
```

### Reject Reversal (Admin)
```http
POST /api/transaction-reversal/requests/{request_id}/reject
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "Transaction cannot be reversed after 24 hours"
}
```

### Get Reversal Statistics (Admin)
```http
GET /api/transaction-reversal/statistics
Authorization: Bearer {token}
```

## Regulatory Reporting

### Generate CTR Report
```http
POST /api/regulatory-reporting/ctr
Authorization: Bearer {token}
Content-Type: application/json

{
  "start_date": "2024-06-01",
  "end_date": "2024-06-30",
  "threshold": 10000
}
```

### Generate SAR Report
```http
POST /api/regulatory-reporting/sar
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_uuid": "user123",
  "suspicious_activities": [
    {
      "type": "structuring",
      "description": "Multiple transactions just below reporting threshold"
    }
  ]
}
```

### Get Compliance Summary
```http
GET /api/regulatory-reporting/compliance-summary
Authorization: Bearer {token}
```

### Get KYC Report
```http
GET /api/regulatory-reporting/kyc-report
Authorization: Bearer {token}
```

### List Reports
```http
GET /api/regulatory-reporting/reports
Authorization: Bearer {token}
```

### Download Report
```http
GET /api/regulatory-reporting/reports/{report_id}/download
Authorization: Bearer {token}
```

## Daily Reconciliation

### Trigger Reconciliation
```http
POST /api/daily-reconciliation/trigger
Authorization: Bearer {token}
Content-Type: application/json

{
  "date": "2024-06-23",
  "force": false
}
```

### Get Reconciliation Status
```http
GET /api/daily-reconciliation/status
Authorization: Bearer {token}
```

### Get Reconciliation Report
```http
GET /api/daily-reconciliation/report/{date}
Authorization: Bearer {token}
```

### Get Discrepancies
```http
GET /api/daily-reconciliation/discrepancies
Authorization: Bearer {token}
```

### Resolve Discrepancy
```http
POST /api/daily-reconciliation/discrepancies/{id}/resolve
Authorization: Bearer {token}
Content-Type: application/json

{
  "resolution": "manual_adjustment",
  "notes": "Corrected posting error"
}
```

### Get Historical Reports
```http
GET /api/daily-reconciliation/history
Authorization: Bearer {token}
```

## Bank Alerting

### Get Active Alerts
```http
GET /api/bank-alerting/alerts
Authorization: Bearer {token}
```

### Create Alert Rule
```http
POST /api/bank-alerting/rules
Authorization: Bearer {token}
Content-Type: application/json

{
  "type": "balance_threshold",
  "condition": "below",
  "threshold": 10000,
  "asset_code": "USD",
  "notification_channels": ["email", "sms"]
}
```

### Get Alert Rules
```http
GET /api/bank-alerting/rules
Authorization: Bearer {token}
```

### Update Alert Rule
```http
PUT /api/bank-alerting/rules/{rule_id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "is_active": false
}
```

### Acknowledge Alert
```http
POST /api/bank-alerting/alerts/{alert_id}/acknowledge
Authorization: Bearer {token}
```

### Get Alert Statistics
```http
GET /api/bank-alerting/statistics
Authorization: Bearer {token}
```

## Basket Performance

### Get Performance Metrics
```http
GET /api/basket-performance/{basket_code}/metrics
Authorization: Bearer {token}
```

Response:
```json
{
  "basket_code": "GCU",
  "performance": {
    "1d": 0.12,
    "7d": 1.45,
    "30d": 3.21,
    "90d": 8.75,
    "365d": 12.34
  },
  "volatility": {
    "30d": 2.1,
    "90d": 3.2
  },
  "sharpe_ratio": 1.85
}
```

### Get Performance History
```http
GET /api/basket-performance/{basket_code}/history?period=30d
Authorization: Bearer {token}
```

### Get Benchmark Comparison
```http
GET /api/basket-performance/{basket_code}/benchmark
Authorization: Bearer {token}
```

### Generate Performance Report
```http
POST /api/basket-performance/{basket_code}/report
Authorization: Bearer {token}
Content-Type: application/json

{
  "start_date": "2024-09-01",
  "end_date": "2024-06-01",
  "format": "pdf"
}
```

## Stablecoin Operations

### Mint Stablecoins
```http
POST /api/stablecoin-operations/mint
Authorization: Bearer {token}
Content-Type: application/json

{
  "stablecoin_code": "FGUSDC",
  "account_uuid": "account123",
  "amount": 10000,
  "collateral_asset_code": "USD",
  "collateral_amount": 15000
}
```

### Burn Stablecoins
```http
POST /api/stablecoin-operations/burn
Authorization: Bearer {token}
Content-Type: application/json

{
  "stablecoin_code": "FGUSDC",
  "account_uuid": "account123",
  "amount": 5000
}
```

### Add Collateral
```http
POST /api/stablecoin-operations/add-collateral
Authorization: Bearer {token}
Content-Type: application/json

{
  "position_id": "pos123",
  "collateral_asset_code": "USD",
  "amount": 5000
}
```

### Get Liquidation Opportunities
```http
GET /api/stablecoin-operations/liquidation/opportunities
Authorization: Bearer {token}
```

### Execute Liquidation
```http
POST /api/stablecoin-operations/liquidation/execute
Authorization: Bearer {token}
Content-Type: application/json

{
  "position_id": "pos123",
  "liquidation_amount": 2000
}
```

### Get System Health
```http
GET /api/stablecoin-operations/system-health
Authorization: Bearer {token}
```

## User Voting

### Get Active Polls
```http
GET /api/user-voting/polls
Authorization: Bearer {token}
```

### Submit Vote
```http
POST /api/user-voting/polls/{poll_uuid}/vote
Authorization: Bearer {token}
Content-Type: application/json

{
  "allocations": {
    "USD": 35,
    "EUR": 30,
    "GBP": 20,
    "CHF": 10,
    "JPY": 3,
    "XAU": 2
  }
}
```

### Get Voting Power
```http
GET /api/user-voting/polls/{poll_uuid}/voting-power
Authorization: Bearer {token}
```

### Get Poll Results
```http
GET /api/user-voting/polls/{poll_uuid}/results
Authorization: Bearer {token}
```

### Get Voting Dashboard
```http
GET /api/user-voting/dashboard
Authorization: Bearer {token}
```

## KYC Management

### Get KYC Status
```http
GET /api/kyc/status
Authorization: Bearer {token}
```

### Submit KYC Documents
```http
POST /api/kyc/submit
Authorization: Bearer {token}
Content-Type: multipart/form-data

documents[0][type]=passport
documents[0][file]=@passport.jpg
documents[1][type]=selfie
documents[1][file]=@selfie.jpg
```

### Get KYC Requirements
```http
GET /api/kyc/requirements?level=enhanced
Authorization: Bearer {token}
```

### Upload Document
```http
POST /api/kyc/documents
Authorization: Bearer {token}
Content-Type: multipart/form-data

type=passport
file=@document.jpg
```

### Get Document Status
```http
GET /api/kyc/documents/{document_id}
Authorization: Bearer {token}
```

## GDPR Compliance

### Get Consent Status
```http
GET /api/gdpr/consent
Authorization: Bearer {token}
```

### Update Consent
```http
POST /api/gdpr/consent
Authorization: Bearer {token}
Content-Type: application/json

{
  "marketing": true,
  "data_retention": true,
  "analytics": false
}
```

### Request Data Export
```http
POST /api/gdpr/export
Authorization: Bearer {token}
Content-Type: application/json

{
  "format": "json",
  "include_transactions": true
}
```

### Request Account Deletion
```http
POST /api/gdpr/delete
Authorization: Bearer {token}
Content-Type: application/json

{
  "confirm": true,
  "reason": "No longer using the service"
}
```

### Get Data Processing Activities
```http
GET /api/gdpr/processing-activities
Authorization: Bearer {token}
```

### Get Privacy Settings
```http
GET /api/gdpr/privacy-settings
Authorization: Bearer {token}
```

## Exchange & Trading

### List Trading Pairs
```http
GET /api/exchange/markets
Authorization: Bearer {token}
```

Response:
```json
{
  "data": [
    {
      "symbol": "BTC/USD",
      "base_currency": "BTC",
      "quote_currency": "USD",
      "status": "active",
      "min_order_size": "0.0001",
      "max_order_size": "100",
      "price_precision": 2,
      "volume_precision": 8,
      "volume_24h": "125.5",
      "last_price": "45678.90"
    }
  ]
}
```

### Get Order Book
```http
GET /api/exchange/orderbook?base=BTC&quote=USD&depth=20
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "base": "BTC",
    "quote": "USD",
    "bids": [
      {"price": "45600.00", "amount": "0.5", "total": "22800.00"},
      {"price": "45595.00", "amount": "1.2", "total": "54714.00"}
    ],
    "asks": [
      {"price": "45610.00", "amount": "0.3", "total": "13683.00"},
      {"price": "45615.00", "amount": "0.8", "total": "36492.00"}
    ],
    "spread": "10.00",
    "timestamp": "2024-09-07T10:00:00Z"
  }
}
```

### Place Order
```http
POST /api/exchange/orders
Authorization: Bearer {token}
Content-Type: application/json

{
  "type": "buy",
  "order_type": "limit",
  "base_currency": "BTC",
  "quote_currency": "USD",
  "amount": "0.5",
  "price": "45000",
  "time_in_force": "GTC"
}
```

Response:
```json
{
  "data": {
    "order_id": "ord_123456",
    "type": "buy",
    "order_type": "limit",
    "status": "open",
    "base_currency": "BTC",
    "quote_currency": "USD",
    "amount": "0.5",
    "price": "45000",
    "filled": "0",
    "remaining": "0.5",
    "created_at": "2024-09-07T10:00:00Z"
  }
}
```

### Place Market Order
```http
POST /api/exchange/orders
Authorization: Bearer {token}
Content-Type: application/json

{
  "type": "sell",
  "order_type": "market",
  "base_currency": "BTC",
  "quote_currency": "USD",
  "amount": "0.1"
}
```

### Cancel Order
```http
DELETE /api/exchange/orders/{order_id}
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "order_id": "ord_123456",
    "status": "cancelled",
    "cancelled_at": "2024-09-07T10:05:00Z"
  }
}
```

### Get Order Status
```http
GET /api/exchange/orders/{order_id}
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "order_id": "ord_123456",
    "type": "buy",
    "order_type": "limit",
    "status": "partially_filled",
    "base_currency": "BTC",
    "quote_currency": "USD",
    "amount": "0.5",
    "price": "45000",
    "filled": "0.3",
    "remaining": "0.2",
    "trades": [
      {
        "trade_id": "trd_789",
        "amount": "0.3",
        "price": "45000",
        "timestamp": "2024-09-07T10:02:00Z"
      }
    ],
    "created_at": "2024-09-07T10:00:00Z",
    "updated_at": "2024-09-07T10:02:00Z"
  }
}
```

### List Open Orders
```http
GET /api/exchange/orders?status=open&base=BTC&quote=USD
Authorization: Bearer {token}
```

### Get Trade History
```http
GET /api/exchange/trades?base=BTC&quote=USD&limit=100
Authorization: Bearer {token}
```

Response:
```json
{
  "data": [
    {
      "trade_id": "trd_789",
      "order_id": "ord_123456",
      "type": "buy",
      "base_currency": "BTC",
      "quote_currency": "USD",
      "amount": "0.3",
      "price": "45000",
      "total": "13500",
      "fee": "13.50",
      "fee_currency": "USD",
      "timestamp": "2024-09-07T10:02:00Z"
    }
  ],
  "pagination": {
    "total": 256,
    "per_page": 100,
    "current_page": 1
  }
}
```

### Get Market Statistics
```http
GET /api/exchange/stats?base=BTC&quote=USD&period=24h
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "symbol": "BTC/USD",
    "period": "24h",
    "open": "44500.00",
    "high": "46200.00",
    "low": "44100.00",
    "close": "45678.90",
    "volume": "125.5",
    "volume_quote": "5734572.50",
    "change": "1178.90",
    "change_percent": "2.65",
    "trades_count": 3456
  }
}
```

### Get Price Chart Data
```http
GET /api/exchange/candles?base=BTC&quote=USD&interval=1h&limit=24
Authorization: Bearer {token}
```

Response:
```json
{
  "data": [
    {
      "timestamp": "2024-09-07T10:00:00Z",
      "open": "45600.00",
      "high": "45750.00",
      "low": "45550.00",
      "close": "45700.00",
      "volume": "5.25"
    }
  ]
}
```

## Liquidity Pools

### List All Pools
```http
GET /api/v2/liquidity/pools
Authorization: Bearer {token}
```

Response:
```json
{
  "data": [
    {
      "id": "pool-uuid",
      "base_currency": "BTC",
      "quote_currency": "USD",
      "fee_rate": "0.003",
      "tvl": "800000",
      "volume_24h": "150000",
      "apy": "12.5",
      "provider_count": 45,
      "is_active": true
    }
  ]
}
```

### Get Pool Details
```http
GET /api/v2/liquidity/pools/{pool_id}
Authorization: Bearer {token}
```

### Create Pool
```http
POST /api/v2/liquidity/pools
Authorization: Bearer {token}
Content-Type: application/json

{
  "base_currency": "ETH",
  "quote_currency": "USD",
  "fee_rate": "0.003",
  "initial_base_amount": "10",
  "initial_quote_amount": "20000"
}
```

### Add Liquidity
```http
POST /api/v2/liquidity/pools/{pool_id}/add
Authorization: Bearer {token}
Content-Type: application/json

{
  "base_amount": "1.5",
  "quote_amount": "3000",
  "min_shares": "100"
}
```

### Remove Liquidity
```http
POST /api/v2/liquidity/pools/{pool_id}/remove
Authorization: Bearer {token}
Content-Type: application/json

{
  "shares": "250",
  "min_base_amount": "0.5",
  "min_quote_amount": "1000"
}
```

### Execute Swap
```http
POST /api/v2/liquidity/pools/{pool_id}/swap
Authorization: Bearer {token}
Content-Type: application/json

{
  "input_currency": "USD",
  "input_amount": "1000",
  "min_output_amount": "0.45"
}
```

Response:
```json
{
  "data": {
    "output_amount": "0.48523",
    "output_currency": "ETH",
    "fee_amount": "3.00",
    "price_impact": "0.25",
    "execution_price": "2062.15"
  }
}
```

### Get Pool Metrics
```http
GET /api/v2/liquidity/pools/{pool_id}/metrics
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "pool_id": "pool-uuid",
    "base_currency": "ETH",
    "quote_currency": "USD",
    "base_reserve": "100.5",
    "quote_reserve": "205000",
    "total_shares": "10000",
    "spot_price": "2039.80",
    "tvl": "410000",
    "volume_24h": "85000",
    "fees_24h": "255",
    "apy": "22.7",
    "provider_count": 32
  }
}
```

### Get Provider Positions
```http
GET /api/v2/liquidity/providers/{provider_id}/positions
Authorization: Bearer {token}
```

### Calculate Provider APY
```http
GET /api/v2/liquidity/pools/{pool_id}/providers/{provider_id}/apy
Authorization: Bearer {token}
```

### Claim Rewards
```http
POST /api/v2/liquidity/pools/{pool_id}/rewards/claim
Authorization: Bearer {token}
```

### Generate Market Making Orders
```http
POST /api/v2/liquidity/pools/{pool_id}/market-make
Authorization: Bearer {token}
Content-Type: application/json

{
  "depth": 5,
  "spread_factor": 1.0,
  "order_size_multiplier": 1.2,
  "max_order_value": "10000"
}
```

Response:
```json
{
  "data": {
    "orders_generated": 10,
    "buy_orders": [
      {"price": "2045.00", "quantity": "0.5", "value": "1022.50"},
      {"price": "2040.00", "quantity": "0.6", "value": "1224.00"}
    ],
    "sell_orders": [
      {"price": "2055.00", "quantity": "0.5", "value": "1027.50"},
      {"price": "2060.00", "quantity": "0.6", "value": "1236.00"}
    ],
    "total_buy_value": "5213.50",
    "total_sell_value": "5287.50",
    "spread": "0.0049"
  }
}
```

### Rebalance Pool
```http
POST /api/v2/liquidity/pools/{pool_id}/rebalance
Authorization: Bearer {token}
Content-Type: application/json

{
  "strategy": "conservative",
  "threshold": 0.05,
  "max_slippage": 0.02
}
```

Response:
```json
{
  "data": {
    "rebalance_needed": true,
    "current_ratio": "0.0489",
    "target_ratio": "0.0500",
    "deviation": "0.022",
    "trades_executed": [
      {
        "direction": "buy",
        "asset": "ETH",
        "amount": "0.25",
        "price": "2050.00"
      }
    ],
    "new_ratio": "0.0498",
    "gas_used": "0.25",
    "timestamp": "2024-09-07T15:00:00Z"
  }
}
```

### Get Liquidity Provider Position
```http
GET /api/v2/liquidity/pools/{pool_id}/position
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "pool_id": "pool-uuid",
    "shares": "250.00",
    "share_percent": "2.5",
    "value": {
      "base_amount": "2.51",
      "quote_amount": "5125.50",
      "total_usd": "10251.00"
    },
    "rewards": {
      "pending": "125.50",
      "claimed": "250.00",
      "currency": "USD"
    },
    "impermanent_loss": {
      "amount": "-45.23",
      "percent": "-0.44"
    },
    "position_opened": "2024-09-01T10:00:00Z"
  }
}
```

### Get Pool Analytics
```http
GET /api/v2/liquidity/pools/{pool_id}/analytics?period=7d
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "pool_id": "pool-uuid",
    "period": "7d",
    "metrics": {
      "volume": "175000",
      "fees_collected": "525",
      "unique_traders": 234,
      "transactions": 1567,
      "average_trade_size": "111.66",
      "tvl_change": "+12.5%",
      "apy": "22.7%"
    },
    "liquidity_changes": [
      {
        "date": "2024-09-01",
        "adds": "25000",
        "removes": "10000",
        "net_change": "+15000"
      }
    ],
    "price_history": [
      {
        "timestamp": "2024-09-01T00:00:00Z",
        "price": "2039.80",
        "volume": "25000"
      }
    ]
  }
}
```

### Distribute Rewards (Admin)
```http
POST /api/v2/liquidity/pools/{pool_id}/rewards/distribute
Authorization: Bearer {token}
Content-Type: application/json

{
  "reward_amount": "1000",
  "reward_currency": "USD",
  "distribution_type": "proportional"
}
```

### Update Pool Parameters (Admin)
```http
PATCH /api/v2/liquidity/pools/{pool_id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "fee_rate": "0.002",
  "is_active": true,
  "min_liquidity": "1000",
  "max_price_impact": "0.05"
}
```

## Stablecoin Operations

### Mint Stablecoins
```http
POST /api/stablecoins/mint
Authorization: Bearer {token}
Content-Type: application/json

{
  "stablecoin": "EUSD",
  "amount": "10000",
  "collateral": [
    {
      "asset": "ETH",
      "amount": "5"
    }
  ]
}
```

### Burn Stablecoins
```http
POST /api/stablecoins/burn
Authorization: Bearer {token}
Content-Type: application/json

{
  "stablecoin": "EUSD",
  "amount": "5000"
}
```

### Get Collateral Positions
```http
GET /api/stablecoins/positions
Authorization: Bearer {token}
```

### Add Collateral
```http
POST /api/stablecoins/positions/{position_id}/collateral
Authorization: Bearer {token}
Content-Type: application/json

{
  "asset": "BTC",
  "amount": "0.1"
}
```

### Liquidate Position
```http
POST /api/stablecoins/liquidate/{position_id}
Authorization: Bearer {token}
```

## P2P Lending

### Apply for Loan
```http
POST /api/loans/apply
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": "10000",
  "currency": "USD",
  "term_months": 12,
  "purpose": "business_expansion",
  "interest_rate": "10.5",
  "collateral": [
    {
      "type": "crypto",
      "asset": "BTC",
      "amount": "0.5"
    }
  ]
}
```

Response:
```json
{
  "data": {
    "application_id": "app_123456",
    "status": "pending",
    "amount": "10000",
    "currency": "USD",
    "term_months": 12,
    "interest_rate": "10.5",
    "monthly_payment": "877.84",
    "total_interest": "534.08",
    "created_at": "2024-09-07T10:00:00Z"
  }
}
```

### List Available Loans (For Lenders)
```http
GET /api/loans/marketplace?min_rate=8&max_term=24&currency=USD
Authorization: Bearer {token}
```

Response:
```json
{
  "data": [
    {
      "application_id": "app_123456",
      "borrower_rating": "A",
      "amount": "10000",
      "currency": "USD",
      "term_months": 12,
      "interest_rate": "10.5",
      "purpose": "business_expansion",
      "collateral_ratio": "150%",
      "credit_score": 750,
      "funding_progress": "60%",
      "days_remaining": 5
    }
  ]
}
```

### List My Loans
```http
GET /api/loans?status=active&role=borrower
Authorization: Bearer {token}
```

Query parameters:
- `status`: Filter by loan status (pending, active, completed, defaulted)
- `role`: Your role in the loan (borrower, lender)
- `page`: Page number
- `per_page`: Items per page

Response:
```json
{
  "data": [
    {
      "loan_id": "loan_789",
      "role": "borrower",
      "status": "active",
      "principal_amount": "10000",
      "outstanding_balance": "8234.56",
      "next_payment_due": "2024-09-01",
      "next_payment_amount": "877.84",
      "payments_made": 2,
      "payments_remaining": 10
    }
  ]
}
```

### Get Loan Details
```http
GET /api/loans/{loan_id}
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "loan_id": "loan_789",
    "application_id": "app_123456",
    "status": "active",
    "borrower": {
      "id": "user_123",
      "rating": "A",
      "loans_completed": 5,
      "default_rate": "0%"
    },
    "lender": {
      "id": "user_456",
      "total_funded": "250000",
      "active_loans": 12
    },
    "terms": {
      "principal_amount": "10000",
      "currency": "USD",
      "interest_rate": "10.5",
      "term_months": 12,
      "monthly_payment": "877.84"
    },
    "balance": {
      "outstanding_principal": "8234.56",
      "outstanding_interest": "123.44",
      "total_outstanding": "8358.00",
      "paid_principal": "1765.44",
      "paid_interest": "290.24"
    },
    "collateral": [
      {
        "type": "crypto",
        "asset": "BTC",
        "amount": "0.5",
        "current_value": "22839.45",
        "ltv_ratio": "43.8%"
      }
    ],
    "created_at": "2024-06-01T10:00:00Z",
    "funded_at": "2024-06-05T14:30:00Z"
  }
}
```

### Fund Loan (Lender)
```http
POST /api/loans/{application_id}/fund
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": "10000",
  "accept_terms": true
}
```

Response:
```json
{
  "data": {
    "loan_id": "loan_789",
    "status": "active",
    "funded_amount": "10000",
    "expected_return": "10534.08",
    "first_payment_date": "2024-09-01",
    "maturity_date": "2026-07-01"
  }
}
```

### Make Repayment
```http
POST /api/loans/{loan_id}/repay
Authorization: Bearer {token}
Content-Type: application/json

{
  "amount": "1000",
  "payment_method": "account_balance"
}
```

Response:
```json
{
  "data": {
    "payment_id": "pay_321",
    "loan_id": "loan_789",
    "amount": "1000",
    "principal_paid": "877.84",
    "interest_paid": "122.16",
    "remaining_balance": "7356.72",
    "next_payment_due": "2024-09-01",
    "status": "completed",
    "timestamp": "2024-09-07T10:00:00Z"
  }
}
```

### Get Repayment Schedule
```http
GET /api/loans/{loan_id}/schedule
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "loan_id": "loan_789",
    "schedule": [
      {
        "payment_number": 1,
        "due_date": "2024-09-01",
        "payment_amount": "877.84",
        "principal": "794.51",
        "interest": "83.33",
        "balance": "9205.49",
        "status": "paid"
      },
      {
        "payment_number": 2,
        "due_date": "2024-09-01",
        "payment_amount": "877.84",
        "principal": "801.13",
        "interest": "76.71",
        "balance": "8404.36",
        "status": "pending"
      }
    ],
    "total_payments": "10534.08",
    "total_interest": "534.08"
  }
}
```

### Calculate Early Repayment
```http
GET /api/loans/{loan_id}/early-repayment
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "outstanding_principal": "8234.56",
    "accrued_interest": "45.67",
    "prepayment_penalty": "82.35",
    "total_payoff_amount": "8362.58",
    "savings": "171.42",
    "penalty_rate": "1%"
  }
}
```

### Request Loan Refinancing
```http
POST /api/loans/{loan_id}/refinance
Authorization: Bearer {token}
Content-Type: application/json

{
  "new_interest_rate": "8.5",
  "new_term_months": 18,
  "reason": "lower_rate"
}
```

### Get Credit Score
```http
GET /api/loans/credit-score
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "score": 750,
    "rating": "A",
    "factors": {
      "payment_history": 35,
      "credit_utilization": 25,
      "account_age": 15,
      "loan_diversity": 10,
      "recent_inquiries": 15
    },
    "loan_eligibility": {
      "max_amount": "50000",
      "min_interest_rate": "8.5%",
      "max_term_months": 60
    },
    "last_updated": "2024-09-01T00:00:00Z"
  }
}
```

### Report Default (Admin)
```http
POST /api/loans/{loan_id}/default
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "missed_payments",
  "missed_payment_count": 3,
  "recovery_action": "liquidate_collateral"
}
```

## Blockchain Wallets

### Generate Wallet
```http
POST /api/wallets/generate
Authorization: Bearer {token}
Content-Type: application/json

{
  "chain": "ethereum",
  "label": "Trading Wallet"
}
```

### Get Wallet Balance
```http
GET /api/wallets/{chain}/balance?address={address}
Authorization: Bearer {token}
```

### Send Transaction
```http
POST /api/wallets/{chain}/send
Authorization: Bearer {token}
Content-Type: application/json

{
  "from": "0x1234...",
  "to": "0x5678...",
  "amount": "1.5",
  "gas_price": "fast"
}
```

### Get Transaction History
```http
GET /api/wallets/{chain}/transactions?address={address}&limit=50
Authorization: Bearer {token}
```

### Estimate Gas
```http
POST /api/wallets/{chain}/estimate-gas
Authorization: Bearer {token}
Content-Type: application/json

{
  "from": "0x1234...",
  "to": "0x5678...",
  "amount": "1.5",
  "data": "0x..."
}
```

## External Exchange Integration

### Get Arbitrage Opportunities
```http
GET /api/external/arbitrage?base=BTC&quote=USD
Authorization: Bearer {token}
```

### Get External Prices
```http
GET /api/external/prices?base=ETH&quote=USD
Authorization: Bearer {token}
```

### Execute Arbitrage
```http
POST /api/external/arbitrage/execute
Authorization: Bearer {token}
Content-Type: application/json

{
  "opportunity_id": "arb_123",
  "amount": "1000"
}
```

### Sync External Prices
```http
POST /api/external/sync-prices
Authorization: Bearer {token}
Content-Type: application/json

{
  "base": "BTC",
  "quote": "USD",
  "max_deviation": 0.01
}
```

Response:
```json
{
  "data": {
    "synced": true,
    "internal_price": "45678.90",
    "external_prices": {
      "binance": "45680.00",
      "kraken": "45677.50",
      "coinbase": "45679.25"
    },
    "average_external": "45678.92",
    "deviation": "0.00004",
    "adjustments_made": false
  }
}
```

### Get Exchange Status
```http
GET /api/external/exchanges/status
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "exchanges": [
      {
        "name": "binance",
        "status": "connected",
        "last_sync": "2024-09-07T10:00:00Z",
        "rate_limit_remaining": 1180,
        "active_pairs": 125
      },
      {
        "name": "kraken",
        "status": "connected",
        "last_sync": "2024-09-07T09:59:45Z",
        "rate_limit_remaining": 295,
        "active_pairs": 89
      },
      {
        "name": "coinbase",
        "status": "rate_limited",
        "last_sync": "2024-09-07T09:55:00Z",
        "rate_limit_remaining": 0,
        "reset_at": "2024-09-07T10:05:00Z",
        "active_pairs": 67
      }
    ]
  }
}
```

## Error Responses

All API endpoints follow a consistent error response format:

```json
{
  "error": {
    "code": "INSUFFICIENT_FUNDS",
    "message": "Insufficient balance in account",
    "details": {
      "required": "1000.00",
      "available": "750.00",
      "currency": "USD"
    }
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `UNAUTHORIZED` | 401 | Authentication required or invalid token |
| `FORBIDDEN` | 403 | Insufficient permissions |
| `NOT_FOUND` | 404 | Resource not found |
| `VALIDATION_ERROR` | 422 | Request validation failed |
| `INSUFFICIENT_FUNDS` | 422 | Not enough balance |
| `RATE_LIMITED` | 429 | Too many requests |
| `INTERNAL_ERROR` | 500 | Server error |

## Rate Limiting

API requests are rate limited based on your account tier:

- **Basic**: 100 requests per minute
- **Pro**: 1,000 requests per minute
- **Enterprise**: 10,000 requests per minute

Rate limit information is included in response headers:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1625097600
```

## Pagination

List endpoints support pagination with the following parameters:

- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 20, max: 100)

Pagination metadata is included in responses:

```json
{
  "data": [...],
  "pagination": {
    "total": 256,
    "per_page": 20,
    "current_page": 1,
    "last_page": 13,
    "from": 1,
    "to": 20
  }
}
```

## Webhooks

Webhooks can be configured to receive real-time notifications for events. See the [Webhooks](#webhooks) section for available events and payload formats.

## API Versioning

The API uses URL-based versioning. The current version is `v2`. Version 1 endpoints are deprecated but still available at `/api/v1/*`.

---

**Last Updated**: 2024-09-07  
**API Version**: 2.0  
**Documentation Version**: 8.0