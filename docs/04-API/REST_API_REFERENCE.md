# FinAegis REST API Reference

This document consolidates all REST API endpoints for the FinAegis Core Banking Platform.

## Table of Contents
- [Authentication](#authentication)
- [Account Management](#account-management)
- [Asset Management](#asset-management)
- [Transaction Management](#transaction-management)
- [Transfer Operations](#transfer-operations)
- [Exchange Rates](#exchange-rates)
- [Governance & Voting](#governance--voting)
- [Custodian Integration](#custodian-integration)
- [Webhooks](#webhooks)

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
  "title": "Q3 2025 GCU Basket Composition",
  "description": "Vote on the asset allocation for the next quarter",
  "type": "basket_composition",
  "start_date": "2025-07-01",
  "end_date": "2025-07-07",
  "voting_power_strategy": "AssetWeightedVotingStrategy"
}
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
    "first": "https://api.finaegis.com/accounts?page=1",
    "last": "https://api.finaegis.com/accounts?page=10",
    "prev": null,
    "next": "https://api.finaegis.com/accounts?page=2"
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
      "last_rebalanced_at": "2025-06-21T00:00:00Z",
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
  "calculated_at": "2025-06-21T10:00:00Z",
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
  "submitted_at": "2025-06-20T10:00:00Z",
  "approved_at": "2025-06-20T11:00:00Z",
  "expires_at": "2027-06-20T11:00:00Z",
  "needs_kyc": false,
  "documents": [
    {
      "id": "123",
      "type": "passport",
      "status": "verified",
      "uploaded_at": "2025-06-20T10:00:00Z"
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
  "next_poll_date": "2025-07-01"
}
```
EOF < /dev/null