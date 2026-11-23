# API Integration Guide

## Overview

This guide explains how to integrate with the API of a FinAegis deployment that you operate. It covers multi-asset accounts, transfers, exchange rates, webhooks and the GCU demo.

The version 2 API lives under `/api/v2` on the main domain. On an `api.*` subdomain the same routes are served without the `/api` prefix, as `/v2/...`. The examples below use the main domain.

## Quick Start

### 1. Get Your API Credentials

You can call the API with either of two credentials:

- A Sanctum personal access token. You get one from `POST /api/auth/login` or `POST /api/v2/auth/login`, or you create one on the API tokens page of your profile.
- An API key. A signed-in user creates API keys on the `/api-keys` page, and administrators manage them in the admin panel. API keys start with `fak_`.

Both credentials go in the `Authorization: Bearer` header.

### 2. Make Your First Request

```bash
# List the accounts of the signed-in user
curl -X GET https://your-finaegis-host.example/api/v2/accounts \
  -H "Authorization: Bearer your_token_or_api_key" \
  -H "Accept: application/json"
```

### 3. Set Up Webhooks (Optional)

```bash
# Register a webhook endpoint (the URL must use https://)
curl -X POST https://your-finaegis-host.example/api/v2/webhooks \
  -H "Authorization: Bearer your_token_or_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-app.com/webhooks/finaegis",
    "events": ["account.created", "transaction.created"]
  }'
```

Only administrators can manage webhooks. A webhook is platform-wide, so it receives the subscribed events for every account on the deployment. The response contains a `secret` that starts with `whsec_`. It is shown only once, so store it safely.

## Authentication

### Bearer Credentials

Every authenticated request carries a token or an API key in the `Authorization` header:

```http
Authorization: Bearer your_token_or_api_key
```

Write endpoints need the `write` ability and delete endpoints need the `delete` ability. Read endpoints need `read`.

### Request Signing

The API does not verify signed requests. Use HTTPS and keep your tokens and API keys on the server side.

### Rate Limiting

Rate limits depend on the type of endpoint. A limit applies where a route uses the `api.rate_limit` middleware with that type. The limits are fixed in `ApiRateLimitMiddleware`. `RATE_LIMITING_ENABLED=false` switches them off. The `limits` section of `config/rate_limiting.php` and its `RATE_LIMIT_*` variables are not read by this middleware.

| Endpoint type | Limit | Block after the limit |
|---------------|-------|-----------------------|
| Sign-in endpoints (`auth`) | 5 requests per 60 seconds | 5 minutes |
| Transactions (`transaction`) | 30 requests per 60 seconds | 60 seconds |
| Queries (`query`) | 100 requests per 60 seconds | 30 seconds |
| Admin (`admin`) | 200 requests per 60 seconds | 60 seconds |
| Public endpoints (`public`) | 60 requests per 60 seconds | 30 seconds |
| Webhooks (`webhook`) | 1,000 requests per 60 seconds | 5 minutes |

The limit is counted per client and per endpoint. Some money routes also use `transaction.rate_limit`, which applies its own hourly and daily limits.

Endpoints that apply these limits return these headers:
  - `X-RateLimit-Limit`: Maximum requests allowed
  - `X-RateLimit-Remaining`: Requests remaining
  - `X-RateLimit-Reset`: Unix timestamp when limit resets

## Core Endpoints

### Accounts

#### List Accounts
```http
GET /api/v2/accounts
```

**Response:**
```json
{
  "data": [
    {
      "uuid": "123e4567-e89b-12d3-a456-426614174000",
      "user_uuid": "9b1d2c3e-0000-4000-8000-000000000001",
      "name": "Main Account",
      "balance": 150000,
      "frozen": false,
      "created_at": "2026-11-01T10:00:00Z",
      "updated_at": "2026-11-01T10:00:00Z"
    }
  ]
}
```

#### Create Account
```http
POST /api/v2/accounts
```

**Request:**
```json
{
  "name": "Trading Account"
}
```

A new account always starts at zero. A non-zero `initial_balance` is rejected with HTTP 422. You fund an account through a supported deposit method.

#### Get Balances
```http
GET /api/v2/accounts/{uuid}/balances
```

### Transfers

#### Create Transfer
```http
POST /api/v2/transfers
```

**Request:**
```json
{
  "from_account": "123e4567-e89b-12d3-a456-426614174000",
  "to_account": "987fcdeb-51a2-43d1-b012-123456789abc",
  "amount": "100.00",
  "asset_code": "USD",
  "reference": "Invoice #1234"
}
```

The `amount` is in major units, such as dollars rather than cents. The API converts it with the precision of the asset. `from_account_uuid` and `to_account_uuid` are accepted as alternative field names. You can only send from an account you own.

**Response (HTTP 201):**
```json
{
  "data": {
    "uuid": "5f0c1d2e-3a4b-4c5d-8e6f-708192a3b4c5",
    "status": "pending",
    "from_account": "123e4567-e89b-12d3-a456-426614174000",
    "to_account": "987fcdeb-51a2-43d1-b012-123456789abc",
    "amount": "100.00",
    "asset_code": "USD",
    "reference": "Invoice #1234",
    "created_at": "2026-11-23T14:31:00Z"
  },
  "message": "Transfer initiated successfully"
}
```

The transfer runs as a workflow, so it starts as `pending`.

#### Currency Conversion

The version 2 API gives conversion quotes. It does not move money between assets:

```http
GET /api/v2/exchange-rates/{from}/{to}/convert?amount=1000
```

The quote endpoint reads `amount` in minor units of the source asset, so `1000` means 10.00 for a currency with two decimals.

The version 1 endpoint `POST /api/exchange/convert` converts between two balances of one account. It takes `account_uuid`, `from_currency`, `to_currency` and `amount`. It only works when `LEDGER_DIRECT_FUNDING_ENABLED` is on, which is the default in local, testing and demo environments only.

### GCU Operations

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

The GCU trading endpoints belong to the Basket module. The production site switches that module off through `MODULES_DISABLED`.

#### Get GCU Information
```http
GET /api/v2/gcu
```

**Response:**
```json
{
  "data": {
    "code": "GCU",
    "name": "Global Currency Unit",
    "symbol": "Ǥ",
    "current_value": 1.0975,
    "value_currency": "USD",
    "last_rebalanced": "2026-11-01T00:00:00Z",
    "next_rebalance": "2026-12-01T00:00:00Z",
    "composition": [
      { "asset_code": "USD", "asset_name": "US Dollar", "weight": 40.0, "value_contribution": 0.4 },
      { "asset_code": "EUR", "asset_name": "Euro", "weight": 30.0, "value_contribution": 0.3293 }
    ],
    "statistics": {
      "24h_change": 0.12,
      "7d_change": -0.35
    }
  }
}
```

#### GCU demo conversion (simulated)
```http
POST /api/v2/gcu/buy
```

**Request:**
```json
{
  "amount": 1000,
  "currency": "USD",
  "account_uuid": "123e4567-e89b-12d3-a456-426614174000"
}
```

`currency` is one of `EUR`, `USD`, `GBP` or `CHF`, and the minimum `amount` is 100.

### Exchange Rates

#### Get Current Rate
```http
GET /api/v2/exchange-rates/{from}/{to}
```

**Response:**
```json
{
  "data": {
    "from_asset": "USD",
    "to_asset": "EUR",
    "rate": "0.92345000",
    "inverse_rate": "1.0829097406",
    "source": "api",
    "valid_at": "2026-11-23T14:33:00Z",
    "expires_at": null,
    "is_active": true,
    "age_minutes": 3,
    "metadata": null
  }
}
```

## Webhooks

### Setting Up Webhooks

1. **Register an endpoint**
```bash
curl -X POST https://your-finaegis-host.example/api/v2/webhooks \
  -H "Authorization: Bearer your_token_or_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-app.com/webhooks/finaegis",
    "events": [
      "account.created",
      "account.frozen",
      "transaction.created",
      "transfer.completed"
    ]
  }'
```

Webhook management needs an administrator account. `GET /api/v2/webhooks/events` is public and lists the event names you can subscribe to.

2. **Verify webhook signatures**

Each delivery carries `X-Webhook-Signature: sha256=<hex digest>`. The digest is an HMAC-SHA256 of the raw request body, keyed with your webhook secret. Deliveries also carry `X-Webhook-ID`, `X-Webhook-Event` and `X-Webhook-Delivery`.

```javascript
const crypto = require('crypto');

function verifyWebhookSignature(rawBody, signature, secret) {
  const expectedSignature = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(rawBody)
    .digest('hex');

  return signature.length === expectedSignature.length &&
    crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expectedSignature));
}

// Express.js example: keep the raw body for signature checks
app.post('/webhooks/finaegis', express.raw({ type: 'application/json' }), (req, res) => {
  const signature = req.headers['x-webhook-signature'] || '';

  if (!verifyWebhookSignature(req.body, signature, webhookSecret)) {
    return res.status(401).send('Invalid signature');
  }

  // Process webhook
  const event = JSON.parse(req.body);
  console.log(`Received ${event.event} event:`, event);

  res.status(200).send('OK');
});
```

### Webhook Events

The payload is a flat JSON object. It holds the event fields together with `event` and `timestamp`.

#### Account Events
```json
{
  "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
  "name": "Main Account",
  "user_uuid": "9b1d2c3e-0000-4000-8000-000000000001",
  "balance": 0,
  "event": "account.created",
  "timestamp": "2026-11-23T14:35:00+00:00"
}
```

#### Transaction Events
```json
{
  "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
  "type": "deposit",
  "amount": 10000,
  "currency": "USD",
  "balance_after": 160000,
  "hash": "3f1c...",
  "event": "transaction.created",
  "timestamp": "2026-11-23T14:36:00+00:00"
}
```

The platform currently sends `account.created`, `account.frozen`, `account.unfrozen`, `account.closed`, `transaction.created`, `transfer.created`, `transfer.completed`, `balance.low` and `balance.negative`. The subscription endpoint accepts more event names than it sends today.

## Error Handling

### Error Response Format

Errors return a `message` and, for business errors, an `error` code:

```json
{
  "message": "Insufficient funds",
  "error": "INSUFFICIENT_FUNDS",
  "current_balance": 5000,
  "requested_amount": 10000
}
```

Validation errors use the standard Laravel shape with an `errors` object per field and HTTP 422.

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| (validation) | 422 | Request validation failed |
| (unauthenticated) | 401 | Missing or invalid token or API key |
| `UNAUTHORIZED_TRANSFER` | 403 | The source account belongs to someone else |
| (not found) | 404 | Resource not found |
| `INSUFFICIENT_FUNDS` | 422 | Not enough balance |
| `SOURCE_ACCOUNT_FROZEN` / `DESTINATION_ACCOUNT_FROZEN` | 422 | One of the accounts is frozen |
| (rate limited) | 429 | Too many requests |
| (server error) | 500 | Server error |

### Retry Strategy

```javascript
async function apiRequestWithRetry(options, maxRetries = 3) {
  let lastError;
  
  for (let i = 0; i < maxRetries; i++) {
    try {
      const response = await fetch(options.url, options);
      
      if (response.status === 429) {
        // Rate limited - wait and retry
        const retryAfter = response.headers.get('Retry-After') || 60;
        await sleep(retryAfter * 1000);
        continue;
      }
      
      if (response.status >= 500) {
        // Server error - exponential backoff
        await sleep(Math.pow(2, i) * 1000);
        continue;
      }
      
      return response;
    } catch (error) {
      lastError = error;
      await sleep(Math.pow(2, i) * 1000);
    }
  }
  
  throw lastError;
}
```

## SDKs and Libraries

### Official SDKs

- **JavaScript and TypeScript**: `npm install @finaegis/sdk`
- **Python**: `pip install finaegis`
- **PHP**: `composer require finaegis/php-sdk`

There are no official Ruby, Go or Java SDKs. [SDK-GUIDE.md](SDK-GUIDE.md) also covers the payment SDK, the CLI and the MCP connector.

### SDK Example (Node.js)

```javascript
const { FinAegis } = require('@finaegis/sdk');

const client = new FinAegis({
  apiKey: 'your_token_or_api_key',
  baseURL: 'https://your-finaegis-host.example/api/v2'
});

// List accounts
const accounts = await client.accounts.list();

// Create transfer (amount in major units)
const transfer = await client.transfers.create({
  from_account: '123e4567-e89b-12d3-a456-426614174000',
  to_account: '987fcdeb-51a2-43d1-b012-123456789abc',
  amount: 100.50,
  asset_code: 'USD',
  reference: 'Payment for services'
});

// GCU demo information (simulated)
const gcu = await client.gcu.getInfo();
```

## Testing

### Test Environment

Run your own local, testing or demo deployment for integration tests. In those environments `LEDGER_DIRECT_FUNDING_ENABLED` is on by default, so test accounts can be funded through `POST /api/v2/accounts/{uuid}/deposit`.

### Test Data

`php artisan demo:populate` creates demo users with the password `demo123`. They are demo.argentina@gcu.global, demo.nomad@gcu.global, demo.business@gcu.global, demo.investor@gcu.global and demo.user@gcu.global.

### Integration Testing

```javascript
// Jest example
const { FinAegis } = require('@finaegis/sdk');

describe('FinAegis API Integration', () => {
  let client;
  
  beforeAll(() => {
    client = new FinAegis({
      apiKey: process.env.FINAEGIS_TEST_API_KEY,
      environment: 'local'
    });
  });
  
  test('should start a transfer', async () => {
    const transfer = await client.transfers.create({
      from_account: process.env.TEST_FROM_ACCOUNT,
      to_account: process.env.TEST_TO_ACCOUNT,
      amount: 1,
      asset_code: 'USD'
    });
    
    expect(transfer.data.status).toBe('pending');
  });
});
```

## Best Practices

### 1. Security

- Keep tokens and API keys out of client-side code.
- Verify the signature of every webhook.
- Give each token only the abilities it needs.
- Rotate API keys regularly. The `/api-keys` page can regenerate a key.

### 2. Performance

- Cache exchange rates for a few minutes.
- Use webhooks instead of polling.
- Back off exponentially when you retry.

### 3. Error Handling

- Check the response status of every call.
- Log errors together with the request context.
- Retry only on rate limits and server errors.

### 4. Compliance

Store transaction references and keep audit logs on your side. Handle personal data carefully and follow the rules that apply where you operate.

## API Versions

The version 2 API (`/api/v2`) is the public developer API and reports its version as `2.0.0` at `GET /api/v2`. The version 1 routes under `/api` and `/api/v1` are still served and are used by the web app and the mobile app.

## Support

- Your deployment serves the interactive API reference at `/api/documentation`.
- Questions and bug reports go to the GitHub issues of `FinAegis/core-banking-prototype-laravel`.
- Security issues are reported privately through GitHub Security Advisories, as described in `SECURITY.md`.

## Appendix

### Country Codes (ISO 3166-1)
Used for address and compliance fields.

### Amounts
Transfer, deposit and withdrawal requests take amounts in major units, for example `100.00` dollars. Balances in responses are in minor units, for example cents.

### Time Zones
All timestamps are in UTC (ISO 8601 format).

### Idempotency
The version 1 deposit, withdrawal and transfer endpoints and the wallet send endpoints accept an `Idempotency-Key` header. The version 2 transfer endpoint does not deduplicate requests, so avoid blind retries of a `POST /api/v2/transfers`.
