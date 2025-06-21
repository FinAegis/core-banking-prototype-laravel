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