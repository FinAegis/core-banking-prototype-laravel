# API Integration Guide

## Overview

This guide provides comprehensive documentation for integrating with the FinAegis API. Our RESTful API enables you to build applications that interact with multi-asset accounts, process payments, manage GCU holdings, and more.

## Quick Start

### 1. Get Your API Credentials

```bash
# Request API access at https://developers.finaegis.org
# You'll receive:
- API Key: your_api_key_here
- API Secret: your_api_secret_here
- Environment URLs:
  - Sandbox: https://sandbox-api.finaegis.org
  - Production: https://api.finaegis.org
```

### 2. Make Your First Request

```bash
# Get account information
curl -X GET https://sandbox-api.finaegis.org/v2/accounts \
  -H "Authorization: Bearer your_api_key_here" \
  -H "Content-Type: application/json"
```

### 3. Setup Webhooks (Optional)

```bash
# Register a webhook endpoint
curl -X POST https://sandbox-api.finaegis.org/v2/webhooks \
  -H "Authorization: Bearer your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-app.com/webhooks/finaegis",
    "events": ["account.created", "transaction.completed"]
  }'
```

## Authentication

### API Key Authentication

All API requests must include your API key in the Authorization header:

```http
Authorization: Bearer your_api_key_here
```

### Request Signing (Optional but Recommended)

For enhanced security, sign your requests:

```javascript
const crypto = require('crypto');

function signRequest(method, path, body, secret) {
  const timestamp = Math.floor(Date.now() / 1000);
  const payload = `${timestamp}.${method}.${path}.${body || ''}`;
  const signature = crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
  
  return {
    'X-Signature': signature,
    'X-Timestamp': timestamp
  };
}

// Example usage
const headers = signRequest('POST', '/v2/transfers', JSON.stringify(data), apiSecret);
```

### Rate Limiting

- **Default limit**: 60 requests per minute
- **Burst limit**: 120 requests per minute (short bursts)
- **Headers returned**:
  - `X-RateLimit-Limit`: Maximum requests allowed
  - `X-RateLimit-Remaining`: Requests remaining
  - `X-RateLimit-Reset`: Unix timestamp when limit resets

## Core Endpoints

### Accounts

#### List Accounts
```http
GET /v2/accounts
```

**Response:**
```json
{
  "data": [
    {
      "id": "acc_123456",
      "name": "Main Account",
      "type": "personal",
      "status": "active",
      "balances": {
        "USD": 150000,
        "EUR": 50000,
        "GCU": 10000
      },
      "created_at": "2025-01-15T10:00:00Z"
    }
  ],
  "meta": {
    "total": 1,
    "page": 1,
    "per_page": 20
  }
}
```

#### Create Account
```http
POST /v2/accounts
```

**Request:**
```json
{
  "name": "Trading Account",
  "type": "business",
  "base_currency": "USD"
}
```

**Response:**
```json
{
  "data": {
    "id": "acc_789012",
    "name": "Trading Account",
    "type": "business",
    "status": "pending_verification",
    "created_at": "2025-06-22T14:30:00Z"
  }
}
```

### Transactions

#### Create Transfer
```http
POST /v2/transfers
```

**Request:**
```json
{
  "from_account_id": "acc_123456",
  "to_account_id": "acc_789012",
  "amount": 10000,
  "currency": "USD",
  "reference": "Invoice #1234",
  "metadata": {
    "invoice_id": "1234",
    "client": "ACME Corp"
  }
}
```

**Response:**
```json
{
  "data": {
    "id": "txn_345678",
    "status": "completed",
    "from_account_id": "acc_123456",
    "to_account_id": "acc_789012",
    "amount": 10000,
    "currency": "USD",
    "reference": "Invoice #1234",
    "fee": 0,
    "created_at": "2025-06-22T14:31:00Z",
    "completed_at": "2025-06-22T14:31:01Z"
  }
}
```

#### Currency Conversion
```http
POST /v2/conversions
```

**Request:**
```json
{
  "account_id": "acc_123456",
  "from_currency": "USD",
  "to_currency": "EUR",
  "amount": 1000,
  "type": "sell"  // "sell" or "buy"
}
```

**Response:**
```json
{
  "data": {
    "id": "conv_567890",
    "status": "completed",
    "from_amount": 1000,
    "from_currency": "USD",
    "to_amount": 923.45,
    "to_currency": "EUR",
    "rate": 0.92345,
    "fee": 0.10,
    "created_at": "2025-06-22T14:32:00Z"
  }
}
```

### GCU Operations

#### Get GCU Information
```http
GET /v2/gcu
```

**Response:**
```json
{
  "data": {
    "current_value": 2.31,
    "base_currency": "USD",
    "composition": {
      "USD": 0.35,
      "EUR": 0.30,
      "GBP": 0.20,
      "CHF": 0.10,
      "JPY": 0.03,
      "XAU": 0.02
    },
    "last_rebalanced": "2025-06-10T00:00:00Z",
    "next_rebalance": "2025-07-10T00:00:00Z"
  }
}
```

#### Buy GCU
```http
POST /v2/gcu/buy
```

**Request:**
```json
{
  "account_id": "acc_123456",
  "source_currency": "USD",
  "amount": 1000,
  "bank_allocation": {
    "paysera": 40,
    "deutsche_bank": 30,
    "santander": 30
  }
}
```

### Exchange Rates

#### Get Current Rate
```http
GET /v2/exchange-rates/{from}/{to}
```

**Response:**
```json
{
  "data": {
    "from": "USD",
    "to": "EUR",
    "rate": 0.92345,
    "inverse_rate": 1.08291,
    "timestamp": "2025-06-22T14:33:00Z",
    "provider": "market_aggregator"
  }
}
```

## Webhooks

### Setting Up Webhooks

1. **Register Endpoint**
```bash
curl -X POST https://api.finaegis.org/v2/webhooks \
  -H "Authorization: Bearer your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-app.com/webhooks/finaegis",
    "events": [
      "account.created",
      "account.updated",
      "transaction.created",
      "transaction.completed",
      "transaction.failed",
      "conversion.completed",
      "gcu.rebalanced"
    ]
  }'
```

2. **Verify Webhook Signatures**
```javascript
function verifyWebhookSignature(payload, signature, secret) {
  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
  
  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expectedSignature)
  );
}

// Express.js example
app.post('/webhooks/finaegis', (req, res) => {
  const signature = req.headers['x-finaegis-signature'];
  const payload = JSON.stringify(req.body);
  
  if (!verifyWebhookSignature(payload, signature, webhookSecret)) {
    return res.status(401).send('Invalid signature');
  }
  
  // Process webhook
  const event = req.body;
  console.log(`Received ${event.type} event:`, event.data);
  
  res.status(200).send('OK');
});
```

### Webhook Events

#### Account Events
```json
{
  "id": "evt_123456",
  "type": "account.created",
  "created_at": "2025-06-22T14:35:00Z",
  "data": {
    "account_id": "acc_123456",
    "name": "Main Account",
    "type": "personal",
    "status": "active"
  }
}
```

#### Transaction Events
```json
{
  "id": "evt_234567",
  "type": "transaction.completed",
  "created_at": "2025-06-22T14:36:00Z",
  "data": {
    "transaction_id": "txn_345678",
    "type": "transfer",
    "amount": 10000,
    "currency": "USD",
    "status": "completed"
  }
}
```

## Error Handling

### Error Response Format
```json
{
  "error": {
    "code": "insufficient_funds",
    "message": "Insufficient funds in account",
    "details": {
      "available_balance": 500,
      "requested_amount": 1000,
      "currency": "USD"
    }
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `invalid_request` | 400 | Request validation failed |
| `unauthorized` | 401 | Invalid or missing API key |
| `forbidden` | 403 | Access denied to resource |
| `not_found` | 404 | Resource not found |
| `insufficient_funds` | 400 | Not enough balance |
| `rate_limit_exceeded` | 429 | Too many requests |
| `internal_error` | 500 | Server error |

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

- **Node.js**: `npm install @finaegis/node-sdk`
- **Python**: `pip install finaegis`
- **PHP**: `composer require finaegis/php-sdk`
- **Ruby**: `gem install finaegis`
- **Go**: `go get github.com/finaegis/go-sdk`

### SDK Example (Node.js)

```javascript
const FinAegis = require('@finaegis/node-sdk');

const client = new FinAegis({
  apiKey: 'your_api_key',
  apiSecret: 'your_api_secret',
  environment: 'sandbox' // or 'production'
});

// List accounts
const accounts = await client.accounts.list();

// Create transfer
const transfer = await client.transfers.create({
  from: 'acc_123456',
  to: 'acc_789012',
  amount: 100.50,
  currency: 'USD',
  reference: 'Payment for services'
});

// Buy GCU
const gcuPurchase = await client.gcu.buy({
  accountId: 'acc_123456',
  sourceCurrency: 'USD',
  amount: 1000
});

// Set up webhook listener
client.webhooks.listen(3000, (event) => {
  console.log(`Received ${event.type}:`, event.data);
});
```

## Testing

### Sandbox Environment

- Base URL: `https://sandbox-api.finaegis.org`
- Test API keys available in dashboard
- Simulated bank responses
- Accelerated time for testing recurring features

### Test Data

**Test Accounts:**
```json
{
  "personal_account": "acc_test_personal",
  "business_account": "acc_test_business",
  "gcu_account": "acc_test_gcu"
}
```

**Test Cards (for deposits):**
- Success: `4242 4242 4242 4242`
- Insufficient funds: `4000 0000 0000 9995`
- Declined: `4000 0000 0000 0002`

### Integration Testing

```javascript
// Jest example
describe('FinAegis API Integration', () => {
  let client;
  
  beforeAll(() => {
    client = new FinAegis({
      apiKey: process.env.FINAEGIS_TEST_API_KEY,
      environment: 'sandbox'
    });
  });
  
  test('should create and complete transfer', async () => {
    const transfer = await client.transfers.create({
      from: 'acc_test_personal',
      to: 'acc_test_business',
      amount: 100,
      currency: 'USD'
    });
    
    expect(transfer.status).toBe('completed');
    expect(transfer.amount).toBe(100);
  });
});
```

## Best Practices

### 1. Security

- **Never expose API secrets** in client-side code
- **Use webhook signatures** to verify authenticity
- **Implement request signing** for sensitive operations
- **Rotate API keys** regularly
- **Use IP whitelisting** in production

### 2. Performance

- **Cache exchange rates** for up to 5 minutes
- **Use pagination** for large data sets
- **Implement exponential backoff** for retries
- **Batch operations** when possible
- **Use webhooks** instead of polling

### 3. Error Handling

- **Always check response status**
- **Log all errors** with context
- **Implement proper retry logic**
- **Handle edge cases** gracefully
- **Monitor API usage** and errors

### 4. Compliance

- **Store transaction references**
- **Maintain audit logs**
- **Implement data retention policies**
- **Handle PII securely**
- **Follow regional regulations**

## API Changelog

### Version 2.0 (Current)
- Added GCU endpoints
- Enhanced webhook system
- Improved error messages
- Added request signing
- Bulk operations support

### Version 1.0 (Deprecated)
- Basic account operations
- Simple transfers
- Currency conversion
- Exchange rates

## Support

### Resources

- **API Reference**: https://api-docs.finaegis.org
- **Status Page**: https://status.finaegis.org
- **Developer Forum**: https://developers.finaegis.org/forum
- **GitHub**: https://github.com/finaegis

### Contact

- **Email**: api-support@finaegis.org
- **Slack**: finaegis-dev.slack.com
- **Emergency**: +1-888-FINAEGIS (24/7)

## Appendix

### Country Codes (ISO 3166-1)
Used for address and compliance fields.

### Currency Codes (ISO 4217)
All amounts are in minor units (cents, pence, etc.)

### Time Zones
All timestamps are in UTC (ISO 8601 format).

### Idempotency
Use `Idempotency-Key` header for POST requests to prevent duplicates.

---

© 2025 FinAegis. All rights reserved.