# FinAegis SDK Developer Guide

Welcome to the FinAegis API SDK documentation. This guide will help you integrate with the FinAegis Global Currency Unit (GCU) platform.

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

### Base URLs
- **Production**: `https://api.finaegis.org/v2`
- **Sandbox**: `https://sandbox.api.finaegis.org/v2`

### API Version
Current version: `2.0.0`

### Rate Limits
- **Requests per minute**: 60
- **Requests per hour**: 1,000
- **Burst limit**: 100

## Authentication

FinAegis API uses two authentication methods:

### 1. API Key Authentication
For server-to-server integrations:
```
X-API-Key: your_api_key_here
```

### 2. Bearer Token Authentication
For user-specific operations:
```
Authorization: Bearer your_jwt_token_here
```

### Getting API Credentials

1. Sign up at [https://developers.finaegis.org](https://developers.finaegis.org)
2. Create a new application
3. Generate API keys from the dashboard
4. For production access, complete KYC verification

## API Endpoints

### Public Endpoints

#### API Status
```http
GET /v2/status
```

Response:
```json
{
  "status": "operational",
  "timestamp": "2024-06-22T10:00:00Z",
  "components": {
    "api": "operational",
    "database": "operational",
    "redis": "operational",
    "bank_connectors": {
      "paysera": "operational",
      "deutsche_bank": "operational",
      "santander": "degraded"
    }
  }
}
```

#### GCU Information
```http
GET /v2/gcu
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
        "weight": 35.0,
        "value_contribution": 0.35
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
POST /v2/accounts
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "My GCU Account",
  "type": "savings",
  "metadata": {
    "purpose": "international_transfers"
  }
}
```

#### Get Account Balances
```http
GET /v2/accounts/{account_uuid}/balances
Authorization: Bearer {token}
```

Response:
```json
{
  "data": {
    "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
    "balances": [
      {
        "asset_code": "GCU",
        "balance": 100000,
        "formatted_balance": "1,000.00"
      },
      {
        "asset_code": "USD",
        "balance": 50000,
        "formatted_balance": "500.00"
      }
    ]
  }
}
```

#### Transfer Funds
```http
POST /v2/transfers
Content-Type: application/json
Authorization: Bearer {token}

{
  "from_account": "123e4567-e89b-12d3-a456-426614174000",
  "to_account": "987fcdeb-51a2-43d1-b012-123456789abc",
  "amount": 10000,
  "asset_code": "GCU",
  "reference": "Payment for services",
  "metadata": {
    "invoice_number": "INV-2024-001"
  }
}
```

## Webhooks

### Setting Up Webhooks

#### Create a Webhook
```http
POST /v2/webhooks
Content-Type: application/json
Authorization: Bearer {token}

{
  "url": "https://your-app.com/webhooks/finaegis",
  "events": [
    "account.created",
    "transaction.completed",
    "transfer.completed"
  ],
  "description": "Production webhook for transactions"
}
```

Response includes a webhook secret for signature verification:
```json
{
  "data": {
    "id": "wh_123456",
    "url": "https://your-app.com/webhooks/finaegis",
    "events": ["account.created", "transaction.completed", "transfer.completed"],
    "secret": "whsec_[your_webhook_secret]",
    "is_active": true
  }
}
```

### Webhook Payload Format
```json
{
  "event": "transaction.completed",
  "timestamp": "2024-06-22T10:00:00Z",
  "data": {
    "transaction_id": "tx_123456",
    "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
    "amount": 10000,
    "asset_code": "GCU",
    "type": "deposit",
    "status": "completed"
  }
}
```

### Signature Verification

All webhooks include a signature header:
```
X-Webhook-Signature: sha256=5257a869e7ecebeda32affa62cdca3fa51cad7e8f7fb5d59e
```

Verify in Node.js:
```javascript
const crypto = require('crypto');

function verifyWebhookSignature(payload, signature, secret) {
  const expectedSignature = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
  
  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expectedSignature)
  );
}
```

## SDK Libraries

### Official SDKs

#### Node.js/TypeScript
```bash
npm install @finaegis/sdk
```

```javascript
const FinAegis = require('@finaegis/sdk');

const client = new FinAegis({
  apiKey: 'your_api_key',
  environment: 'production' // or 'sandbox'
});

// Get GCU information
const gcuInfo = await client.gcu.getInfo();

// Create a transfer
const transfer = await client.transfers.create({
  fromAccount: 'account_uuid_1',
  toAccount: 'account_uuid_2',
  amount: 10000, // Ǥ100.00
  assetCode: 'GCU'
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
    environment='production'
)

# Get account balances
balances = client.accounts.get_balances('account_uuid')

# Create a webhook
webhook = client.webhooks.create(
    url='https://your-app.com/webhooks',
    events=['transaction.completed', 'transfer.completed']
)
```

#### PHP
```bash
composer require finaegis/sdk
```

```php
use FinAegis\Client;

$client = new Client([
    'apiKey' => 'your_api_key',
    'environment' => 'production'
]);

// Get exchange rate
$rate = $client->exchangeRates->get('EUR', 'GCU');

// Deposit funds
$transaction = $client->transactions->deposit(
    accountUuid: 'account_uuid',
    amount: 50000, // €500.00
    assetCode: 'EUR'
);
```

### Community SDKs
- Ruby: [finaegis-ruby](https://github.com/community/finaegis-ruby)
- Go: [go-finaegis](https://github.com/community/go-finaegis)
- Java: [finaegis-java-sdk](https://github.com/community/finaegis-java-sdk)

## Code Examples

### Complete Integration Example (Node.js)

```javascript
const FinAegis = require('@finaegis/sdk');
const express = require('express');

const app = express();
const client = new FinAegis({
  apiKey: process.env.FINAEGIS_API_KEY,
  environment: 'production'
});

// Create a GCU account for a user
app.post('/users/:userId/create-gcu-account', async (req, res) => {
  try {
    // Create account
    const account = await client.accounts.create({
      name: `User ${req.params.userId} GCU Account`,
      type: 'savings'
    });

    // Set up webhook for this account
    const webhook = await client.webhooks.create({
      url: `${process.env.APP_URL}/webhooks/finaegis`,
      events: [
        'transaction.completed',
        'transfer.completed',
        'basket.rebalanced'
      ]
    });

    // Store webhook secret securely
    await saveWebhookSecret(webhook.secret);

    res.json({
      accountId: account.uuid,
      webhookId: webhook.id
    });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Handle incoming webhooks
app.post('/webhooks/finaegis', express.raw({ type: 'application/json' }), async (req, res) => {
  const signature = req.headers['x-webhook-signature'];
  const webhookSecret = await getWebhookSecret();

  // Verify signature
  if (!verifyWebhookSignature(req.body, signature, webhookSecret)) {
    return res.status(401).send('Invalid signature');
  }

  const event = JSON.parse(req.body);

  switch (event.event) {
    case 'transaction.completed':
      await handleTransactionCompleted(event.data);
      break;
    case 'transfer.completed':
      await handleTransferCompleted(event.data);
      break;
    case 'basket.rebalanced':
      await handleBasketRebalanced(event.data);
      break;
  }

  res.status(200).send('OK');
});

// Convert local currency to GCU
app.post('/convert-to-gcu', async (req, res) => {
  const { amount, fromCurrency } = req.body;

  try {
    // Get exchange rate
    const rate = await client.exchangeRates.get(fromCurrency, 'GCU');
    
    // Calculate GCU amount
    const gcuAmount = Math.floor(amount * rate.rate);

    // Create transaction
    const transaction = await client.transactions.deposit(
      accountUuid: req.user.accountId,
      amount: gcuAmount,
      assetCode: 'GCU',
      metadata: {
        originalAmount: amount,
        originalCurrency: fromCurrency,
        exchangeRate: rate.rate
      }
    );

    res.json({
      gcuAmount: gcuAmount / 100,
      transaction: transaction
    });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});
```

### Multi-Bank Distribution Example

```javascript
// Get user's bank preferences
const preferences = await client.users.getBankPreferences();

// Calculate distribution
const totalAmount = 100000; // Ǥ1,000.00
const distributions = preferences.map(pref => ({
  bank: pref.custodian_code,
  amount: Math.floor(totalAmount * pref.allocation_percentage / 100)
}));

// Initiate distributed deposit
const deposits = await Promise.all(
  distributions.map(dist => 
    client.custodians.deposit({
      custodian: dist.bank,
      amount: dist.amount,
      assetCode: 'GCU'
    })
  )
);
```

## Best Practices

### 1. Error Handling
Always implement proper error handling:

```javascript
try {
  const result = await client.accounts.create({...});
} catch (error) {
  if (error.code === 'INSUFFICIENT_FUNDS') {
    // Handle insufficient funds
  } else if (error.code === 'RATE_LIMIT_EXCEEDED') {
    // Implement exponential backoff
    await sleep(Math.pow(2, retryCount) * 1000);
  } else {
    // Log and handle other errors
    console.error('Unexpected error:', error);
  }
}
```

### 2. Idempotency
Use idempotency keys for critical operations:

```javascript
const transfer = await client.transfers.create({
  fromAccount: 'account1',
  toAccount: 'account2',
  amount: 10000,
  assetCode: 'GCU',
  idempotencyKey: 'transfer_' + orderId
});
```

### 3. Webhook Reliability
- Always respond with 200 OK immediately
- Process webhook events asynchronously
- Implement retry logic for failed processing
- Store processed event IDs to prevent duplicates

### 4. Security
- Never expose API keys in client-side code
- Use environment variables for sensitive data
- Implement webhook signature verification
- Use HTTPS for all API calls
- Rotate API keys regularly

### 5. Performance
- Cache exchange rates (5-minute TTL)
- Batch operations when possible
- Use webhook events instead of polling
- Implement connection pooling

## Error Handling

### Error Response Format
```json
{
  "error": {
    "code": "INSUFFICIENT_FUNDS",
    "message": "Insufficient funds in account",
    "details": {
      "available_balance": 5000,
      "requested_amount": 10000,
      "asset_code": "GCU"
    }
  }
}
```

### Common Error Codes
- `AUTHENTICATION_FAILED`: Invalid or missing API key
- `AUTHORIZATION_FAILED`: Insufficient permissions
- `VALIDATION_ERROR`: Invalid request parameters
- `INSUFFICIENT_FUNDS`: Not enough balance
- `RATE_LIMIT_EXCEEDED`: Too many requests
- `ACCOUNT_FROZEN`: Account is frozen
- `CUSTODIAN_UNAVAILABLE`: Bank connector is down
- `INVALID_ASSET_CODE`: Unsupported asset
- `DUPLICATE_REQUEST`: Idempotency key conflict

### Retry Strategy
```javascript
async function apiCallWithRetry(fn, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      return await fn();
    } catch (error) {
      if (error.code === 'RATE_LIMIT_EXCEEDED' || 
          error.code === 'CUSTODIAN_UNAVAILABLE') {
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

## Support

### Documentation
- API Reference: [https://docs.finaegis.org/api](https://docs.finaegis.org/api)
- Developer Portal: [https://developers.finaegis.org](https://developers.finaegis.org)

### Community
- Discord: [https://discord.gg/finaegis](https://discord.gg/finaegis)
- Stack Overflow: Tag `finaegis`
- GitHub: [https://github.com/finaegis](https://github.com/finaegis)

### Contact
- API Support: api@finaegis.org
- Security Issues: security@finaegis.org
- Partnership: partners@finaegis.org

## Changelog

### v2.0.0 (2024-06-22)
- New V2 API with improved structure
- Webhook system for real-time events
- Multi-bank distribution support
- Enhanced GCU endpoints
- Improved error handling

### v1.0.0 (2024-09-15)
- Initial API release
- Basic account and transaction operations
- Multi-asset support
- Exchange rate endpoints