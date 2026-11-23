# FinAegis SDK Developer Guide

This guide explains how to call the FinAegis version 2 API from your code. It covers the official SDKs for JavaScript, Python and PHP, the payment SDK, the command-line tool and the MCP connector. The GCU endpoints run on the GCU software demo, and their balances are simulated.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

## Table of Contents
- [Getting Started](#getting-started)
- [Authentication](#authentication)
- [API Endpoints](#api-endpoints)
- [Webhooks](#webhooks)
- [SDK Libraries](#sdk-libraries)
- [Code Examples](#code-examples)
- [Best Practices](#best-practices)
- [Error Handling](#error-handling)

## Getting Started

### Base URL
Use the base URL of your own FinAegis deployment, for example `http://localhost:8000/api/v2`. On an `api.*` subdomain the same routes are served as `https://api.your-host.example/v2`.

### API Version
`GET /api/v2` reports the API version `2.0.0`.

### Rate Limits
Rate limits depend on the type of endpoint. Sign-in endpoints allow 5 requests per minute, transactions allow 30 per minute and queries allow 100 per minute. A blocked client gets HTTP 429. [API-INTEGRATION-GUIDE.md](API-INTEGRATION-GUIDE.md) lists every limit.

## Authentication

The API accepts two kinds of credentials. Both are sent in the same header:

```
Authorization: Bearer your_token_or_api_key
```

### 1. API Keys
API keys suit server-to-server integrations. A signed-in user creates them on the `/api-keys` page, and administrators manage them in the admin panel. API keys start with `fak_`.

### 2. Personal Access Tokens
Sanctum tokens suit user-specific operations. You get one from `POST /api/v2/auth/login`, or you create one on the API tokens page of your profile (`/user/api-tokens`).

The SDKs call this credential `apiKey` (or `api_key`) and send it as a bearer token.

## API Endpoints

### Public Endpoints

#### API Status
```http
GET /api/v2/status
```

Response:
```json
{
  "status": "operational",
  "timestamp": "2026-11-23T10:00:00Z",
  "components": {
    "api": "operational",
    "database": "operational",
    "redis": "operational",
    "bank_connectors": {
      "mock": "operational"
    }
  },
  "metrics": {
    "response_time_ms": 4,
    "uptime_percentage": 99.95
  }
}
```

The `bank_connectors` list shows the custodian connectors that are registered on the deployment. The `uptime_percentage` value is a fixed placeholder, not a measured figure.

#### GCU Information
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
    "composition": [
      {
        "asset_code": "USD",
        "asset_name": "US Dollar",
        "weight": 40.0,
        "value_contribution": 0.4
      },
      {
        "asset_code": "EUR",
        "asset_name": "Euro",
        "weight": 30.0,
        "value_contribution": 0.3293
      }
    ]
  }
}
```

### Authenticated Endpoints

#### Create Account
```http
POST /api/v2/accounts
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "My Account"
}
```

A new account starts at zero. A non-zero `initial_balance` is rejected with HTTP 422.

#### Get Account Balances
```http
GET /api/v2/accounts/{account_uuid}/balances
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
    "balances": [
      {
        "asset_code": "USD",
        "balance": 50000,
        "formatted": "500.00 USD",
        "asset": { "code": "USD", "name": "US Dollar", "type": "fiat", "symbol": "$", "precision": 2 }
      }
    ],
    "summary": {
      "total_assets": 1,
      "total_usd_equivalent": "500.00"
    }
  }
}
```

Balances are returned in minor units, for example cents.

#### Transfer Funds
```http
POST /api/v2/transfers
Content-Type: application/json
Authorization: Bearer {token}

{
  "from_account": "123e4567-e89b-12d3-a456-426614174000",
  "to_account": "987fcdeb-51a2-43d1-b012-123456789abc",
  "amount": "100.00",
  "asset_code": "GCU",
  "reference": "Payment for services"
}
```

The transfer `amount` is in major units. The API converts it with the precision of the asset.

## Webhooks

### Setting Up Webhooks

Only administrators can manage webhooks. A webhook is platform-wide, so it receives the subscribed events for every account on the deployment.

#### Create a Webhook
```http
POST /api/v2/webhooks
Content-Type: application/json
Authorization: Bearer {token}

{
  "url": "https://your-app.com/webhooks/finaegis",
  "events": [
    "account.created",
    "transaction.created",
    "transfer.completed"
  ],
  "description": "Production webhook for transactions"
}
```

The response includes a webhook secret for signature verification. The secret is shown only once:
```json
{
  "data": {
    "id": "8d7e6f5a-4b3c-4d2e-9f10-a1b2c3d4e5f6",
    "url": "https://your-app.com/webhooks/finaegis",
    "events": ["account.created", "transaction.created", "transfer.completed"],
    "secret": "whsec_[your_webhook_secret]",
    "is_active": true
  }
}
```

### Webhook Payload Format

The payload is a flat JSON object. It holds the event fields together with `event` and `timestamp`:
```json
{
  "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
  "type": "deposit",
  "amount": 10000,
  "currency": "USD",
  "balance_after": 160000,
  "event": "transaction.created",
  "timestamp": "2026-11-23T10:00:00+00:00"
}
```

### Signature Verification

Every webhook includes a signature header:
```
X-Webhook-Signature: sha256=5257a869e7ecebeda32affa62cdca3fa51cad7e8f7fb5d59e
```

Verify it in Node.js against the raw request body:
```javascript
const crypto = require('crypto');

function verifyWebhookSignature(payload, signature, secret) {
  const expectedSignature = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
  
  return signature.length === expectedSignature.length &&
    crypto.timingSafeEqual(
      Buffer.from(signature),
      Buffer.from(expectedSignature)
    );
}
```

## SDK Libraries

### Official SDKs

| Package | Registry | Source in the monorepo |
|---------|----------|------------------------|
| `@finaegis/sdk` | npm | `sdks/javascript/` |
| `finaegis` | PyPI | `sdks/python/` |
| `finaegis/php-sdk` | Packagist | `sdks/php/` |
| `finaegis/payment-sdk` | Packagist | `packages/zelta-sdk/` |
| `@finaegis/cli` | npm | `packages/zelta-cli/` |
| `@finaegis/mcp` | npm | `packages/finaegis-mcp-stdio/` |

The PHP packages are published from read-only mirror repositories under the `FinAegis` GitHub organisation. Issues and pull requests belong in the monorepo.

The JavaScript, Python and PHP SDKs choose a base URL from `environment`. The `production` value points to `https://api.zelta.app/v2`, `sandbox` points to `https://api-sandbox.zelta.app/v2` and `local` points to `http://localhost:8000/api/v2`. For a deployment you run yourself, pass its base URL instead.

#### Node.js/TypeScript
```bash
npm install @finaegis/sdk
```

```javascript
const { FinAegis } = require('@finaegis/sdk');

const client = new FinAegis({
  apiKey: 'your_api_key',
  baseURL: 'https://your-finaegis-host.example/api/v2'
});

// Get GCU information
const gcuInfo = await client.gcu.getInfo();

// Create a transfer (amount in major units)
const transfer = await client.transfers.create({
  from_account: 'account_uuid_1',
  to_account: 'account_uuid_2',
  amount: 100.00,
  asset_code: 'GCU'
});
```

#### Python
```bash
pip install finaegis
```

```python
from finaegis import FinAegis

client = FinAegis(
    api_key='your_api_key',
    base_url='https://your-finaegis-host.example/api/v2'
)

# Get account balances
balances = client.accounts.get_balances('account_uuid')

# Create a webhook (administrators only)
webhook = client.webhooks.create(
    name='Transactions',
    url='https://your-app.com/webhooks',
    events=['transaction.created', 'transfer.completed']
)
```

The Python SDK also reads the key from the `FINAEGIS_API_KEY` environment variable.

#### PHP
```bash
composer require finaegis/php-sdk
```

```php
use FinAegis\Client;

$client = new Client('your_api_key', [
    'base_url' => 'https://your-finaegis-host.example/api/v2',
]);

// Get exchange rate
$rate = $client->exchangeRates->get('EUR', 'GCU');

// Deposit funds (only where LEDGER_DIRECT_FUNDING_ENABLED is on)
$transaction = $client->accounts->deposit(
    'account_uuid',
    500.00,
    'EUR'
);
```

#### Payment SDK (x402 and MPP)
```bash
composer require finaegis/payment-sdk
```

The payment SDK handles HTTP 402 responses for you. When an endpoint asks for payment through x402 or MPP, the client pays and retries the request. Its namespace is `Zelta\` and its main class is `Zelta\ZeltaClient`. The package README in `packages/zelta-sdk/README.md` has a full example.

#### Command-line tool
```bash
npm install -g @finaegis/cli
zelta auth:login --key <api-token>
```

The CLI is called `zelta`. It needs PHP 8.4 or newer on the `PATH`. It manages payments, SMS, wallets and API monetisation from the terminal. Run `zelta list` to see every command.

#### MCP connector
```bash
npx -y @finaegis/mcp --login
```

The MCP connector links Claude Desktop, Cursor and other MCP clients to the hosted MCP server at `https://mcp.zelta.app/mcp`. It signs in through OAuth and stores the token locally.

### Community SDKs
There are no official Ruby, Go or Java SDKs. Community SDKs are welcome.

## Code Examples

### Complete Integration Example (Node.js)

```javascript
const { FinAegis } = require('@finaegis/sdk');
const express = require('express');

const app = express();
const client = new FinAegis({
  apiKey: process.env.FINAEGIS_API_KEY,
  baseURL: process.env.FINAEGIS_BASE_URL
});

// Create an account. The API always opens it for the owner of the API key or token.
// The SDK type asks for user_uuid, but the server ignores that field.
app.post('/accounts', express.json(), async (req, res) => {
  try {
    const account = await client.accounts.create({
      user_uuid: process.env.FINAEGIS_USER_UUID,
      name: req.body.name
    });

    res.json({ accountId: account.data.uuid });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Handle incoming webhooks
app.post('/webhooks/finaegis', express.raw({ type: 'application/json' }), async (req, res) => {
  const signature = req.headers['x-webhook-signature'] || '';
  const webhookSecret = await getWebhookSecret();

  // Verify signature
  if (!verifyWebhookSignature(req.body, signature, webhookSecret)) {
    return res.status(401).send('Invalid signature');
  }

  const event = JSON.parse(req.body);

  switch (event.event) {
    case 'transaction.created':
      await handleTransactionCreated(event);
      break;
    case 'transfer.completed':
      await handleTransferCompleted(event);
      break;
  }

  res.status(200).send('OK');
});

// Quote a conversion to GCU (simulated demo values).
// The quote endpoint reads the amount in minor units, for example cents.
app.post('/quote-gcu', express.json(), async (req, res) => {
  const { amount, fromCurrency } = req.body;

  try {
    const quote = await client.exchangeRates.convert(fromCurrency, 'GCU', amount);
    res.json(quote.data);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});
```

### Multi-Bank Distribution Example

Bank allocation runs through the version 1 API at `/api/bank-allocations`. It belongs to the Banking demo module, which the production site switches off. The bank connectors are integration adapters for third-party APIs, and no partnership or endorsement is implied.

```javascript
const baseUrl = 'https://your-finaegis-host.example/api';
const headers = {
  'Authorization': `Bearer ${process.env.FINAEGIS_API_KEY}`,
  'Content-Type': 'application/json'
};

// Read the user's bank allocation
const allocation = await fetch(`${baseUrl}/bank-allocations`, { headers })
  .then(r => r.json());

// Preview how an amount would be split across the banks (amount in major units)
const preview = await fetch(`${baseUrl}/bank-allocations/distribution-preview`, {
  method: 'POST',
  headers,
  body: JSON.stringify({ amount: 1000.00, asset_code: 'USD' })
}).then(r => r.json());
```

## Best Practices

### 1. Error Handling
Always handle errors. The JavaScript SDK throws a `FinAegisError` that carries the HTTP status in `statusCode` and the response body in `data`:

```javascript
try {
  const result = await client.transfers.create({...});
} catch (error) {
  if (error.statusCode === 422 && error.data?.error === 'INSUFFICIENT_FUNDS') {
    // Handle insufficient funds
  } else if (error.statusCode === 429) {
    // Back off exponentially
    await sleep(Math.pow(2, retryCount) * 1000);
  } else {
    // Log and handle other errors
    console.error('Unexpected error:', error);
  }
}
```

### 2. Idempotency
The version 2 transfer endpoint does not deduplicate requests. Do not resend a transfer blindly after a timeout. Check the account's transfers first with `client.accounts.getTransfers(accountUuid)`. The JavaScript and Python SDKs retry a `POST` that ends in a server error on their own. In the Python SDK, pass `max_retries=0` to stop that. In the JavaScript SDK, `maxRetries: 0` does not help, because the client treats `0` as unset and falls back to three retries.

### 3. Webhook Reliability
- Respond with 200 OK quickly.
- Process webhook events asynchronously.
- Retry failed processing on your side.
- Store processed delivery IDs from the `X-Webhook-Delivery` header to skip duplicates.

### 4. Security
- Never expose API keys in client-side code.
- Keep secrets in environment variables.
- Verify every webhook signature.
- Use HTTPS for all API calls.
- Rotate API keys regularly.

### 5. Performance
- Cache exchange rates for a few minutes.
- Use webhook events instead of polling.
- Reuse one client instance so connections are kept alive.

## Error Handling

### Error Response Format
```json
{
  "message": "Insufficient funds",
  "error": "INSUFFICIENT_FUNDS",
  "current_balance": 5000,
  "requested_amount": 10000
}
```

### Common Error Codes
- `UNAUTHORIZED_TRANSFER`: The source account belongs to someone else (HTTP 403).
- `INSUFFICIENT_FUNDS`: The balance is too low (HTTP 422).
- `SOURCE_ACCOUNT_FROZEN` and `DESTINATION_ACCOUNT_FROZEN`: One of the accounts is frozen (HTTP 422).
- `ACCOUNT_FROZEN`: A deposit or withdrawal hit a frozen account (HTTP 422).
- `TRANSFER_FAILED`: The transfer workflow could not start (HTTP 422).
- Validation errors return HTTP 422 with an `errors` object per field.
- A missing or invalid credential returns HTTP 401, and too many requests return HTTP 429.

### Retry Strategy
```javascript
async function apiCallWithRetry(fn, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      return await fn();
    } catch (error) {
      if (error.statusCode === 429 || error.statusCode >= 500) {
        const delay = Math.pow(2, i) * 1000;
        await new Promise(resolve => setTimeout(resolve, delay));
        continue;
      }
      throw error;
    }
  }
  throw new Error('Max retries exceeded');
}
```

The JavaScript and Python SDKs already retry failed requests up to three times by default. The JavaScript SDK retries network errors and server errors. The Python SDK retries status 429 and server errors. Both retry `POST` requests too, so a transfer that ends in a server error can be sent again by the SDK itself.

## Support

- Your deployment serves the interactive API reference at `/api/documentation`.
- Questions and bug reports go to the GitHub issues of `FinAegis/core-banking-prototype-laravel`.
- Security issues are reported privately through GitHub Security Advisories, as described in `SECURITY.md`.

## Package Versions

The package versions in the repository are `@finaegis/sdk` 1.0.5, `finaegis` 1.0.2 for Python and `@finaegis/mcp` 0.1.6. Release tags follow the table in `CLAUDE.md`, for example `js-sdk-v*` for the JavaScript SDK and `py-sdk-v*` for the Python SDK.
