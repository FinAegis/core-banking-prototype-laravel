# FinAegis API Integration Examples

This document gives practical examples of calls to the FinAegis API in several languages and frameworks. Replace `your-finaegis-host.example` with the base URL of the FinAegis deployment you operate.

Credentials go in the `Authorization: Bearer` header. The version 2 routes accept a Sanctum token or an API key that starts with `fak_`. The version 1 routes that some examples use (`/api/exchange/convert`, `/api/voting/...` and `/api/bank-allocations`) accept only a Sanctum token. Amounts in deposit, withdrawal and transfer requests are in major units, for example `100.00` dollars. Balances in responses are in minor units, for example cents.

## Table of Contents
- [Quick Start](#quick-start)
- [Authentication Examples](#authentication-examples)
- [Account Management](#account-management)
- [GCU Operations](#gcu-operations)
- [Multi-Bank Distribution](#multi-bank-distribution)
- [Webhook Integration](#webhook-integration)
- [Error Handling](#error-handling)
- [Production Best Practices](#production-best-practices)

## Quick Start

### cURL Example
```bash
# Get API status
curl https://your-finaegis-host.example/api/v2/status

# Get GCU information
curl https://your-finaegis-host.example/api/v2/gcu

# Authenticated request
curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://your-finaegis-host.example/api/v2/accounts
```

### JavaScript/Node.js Quick Start
```javascript
// Using fetch (Node.js 18+ or browser)
const response = await fetch('https://your-finaegis-host.example/api/v2/gcu');
const gcuInfo = await response.json();
console.log(`Current GCU value: ${gcuInfo.data.symbol}${gcuInfo.data.current_value}`);
```

## Authentication Examples

### Node.js with Axios
```javascript
const axios = require('axios');

class FinAegisClient {
  constructor(apiKey) {
    this.client = axios.create({
      baseURL: 'https://your-finaegis-host.example/api/v2',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json'
      }
    });
  }

  async getAccounts() {
    const response = await this.client.get('/accounts');
    return response.data;
  }
}

const client = new FinAegisClient(process.env.FINAEGIS_API_KEY);
const accounts = await client.getAccounts();
```

### Python with Requests
```python
import requests
import os

class FinAegisClient:
    def __init__(self, api_key):
        self.base_url = 'https://your-finaegis-host.example/api/v2'
        self.headers = {
            'Authorization': f'Bearer {api_key}',
            'Content-Type': 'application/json'
        }
    
    def get_accounts(self):
        response = requests.get(
            f'{self.base_url}/accounts',
            headers=self.headers
        )
        response.raise_for_status()
        return response.json()

client = FinAegisClient(os.environ['FINAEGIS_API_KEY'])
accounts = client.get_accounts()
```

### PHP with Guzzle
```php
use GuzzleHttp\Client;

class FinAegisClient {
    private $client;
    
    public function __construct($apiKey) {
        $this->client = new Client([
            'base_uri' => 'https://your-finaegis-host.example/api/v2/',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ]
        ]);
    }
    
    public function getAccounts() {
        $response = $this->client->get('accounts');
        return json_decode($response->getBody(), true);
    }
}

$client = new FinAegisClient($_ENV['FINAEGIS_API_KEY']);
$accounts = $client->getAccounts();
```

## Account Management

### Create and Fund an Account (Node.js)
```javascript
async function createAndFundAccount(userEmail, initialDeposit) {
  try {
    // Step 1: Create account
    const accountResponse = await fetch('https://your-finaegis-host.example/api/v2/accounts', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${API_KEY}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        name: `${userEmail} GCU Wallet`
      })
    });

    const account = await accountResponse.json();
    console.log('Account created:', account.data.uuid);

    // Step 2: Fund account with GCU
    // Direct deposits work only where LEDGER_DIRECT_FUNDING_ENABLED is on
    // (local, testing and demo environments by default).
    const depositResponse = await fetch(
      `https://your-finaegis-host.example/api/v2/accounts/${account.data.uuid}/deposit`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${API_KEY}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          amount: initialDeposit, // Major units, for example 1000.00
          asset_code: 'GCU',
          description: 'Initial deposit'
        })
      }
    );

    const deposit = await depositResponse.json();
    console.log(deposit.message); // Deposit initiated successfully

    return account.data;
  } catch (error) {
    console.error('Error creating account:', error);
    throw error;
  }
}

// Usage
const newAccount = await createAndFundAccount('user@example.com', 1000); // Ǥ1,000
```

### Multi-Currency Account Operations (Python)
```python
import asyncio
from decimal import Decimal

import aiohttp

class MultiCurrencyAccount:
    def __init__(self, api_key, account_uuid):
        self.api_key = api_key
        self.account_uuid = account_uuid
        self.base_url = 'https://your-finaegis-host.example/api/v2'
    
    async def get_all_balances(self):
        async with aiohttp.ClientSession() as session:
            async with session.get(
                f'{self.base_url}/accounts/{self.account_uuid}/balances',
                headers={'Authorization': f'Bearer {self.api_key}'}
            ) as response:
                data = await response.json()
                return data['data']['balances']
    
    async def convert_to_gcu(self, from_asset, amount):
        # The conversion runs on the version 1 endpoint POST /api/exchange/convert.
        # It works only where LEDGER_DIRECT_FUNDING_ENABLED is on.
        v1_url = self.base_url.replace('/api/v2', '/api')
        async with aiohttp.ClientSession() as session:
            # Get a quote first. The quote endpoint takes minor units (cents for EUR).
            amount_minor = int(Decimal(str(amount)) * 100)
            async with session.get(
                f'{self.base_url}/exchange-rates/{from_asset}/GCU/convert',
                params={'amount': str(amount_minor)},
                headers={'Authorization': f'Bearer {self.api_key}'}
            ) as response:
                quote = await response.json()
            
            # Execute the conversion (amount in major units)
            async with session.post(
                f'{v1_url}/exchange/convert',
                headers={
                    'Authorization': f'Bearer {self.api_key}',
                    'Content-Type': 'application/json'
                },
                json={
                    'account_uuid': self.account_uuid,
                    'from_currency': from_asset,
                    'to_currency': 'GCU',
                    'amount': str(amount)
                }
            ) as response:
                return quote, await response.json()

# Usage
async def main():
    account = MultiCurrencyAccount(API_KEY, ACCOUNT_UUID)
    
    # Get all balances
    balances = await account.get_all_balances()
    for balance in balances:
        print(f"{balance['asset_code']}: {balance['formatted']}")
    
    # Convert EUR to GCU
    result = await account.convert_to_gcu('EUR', 500)
    print(f"Converted €500 to GCU: {result}")

asyncio.run(main())
```

## GCU Operations

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

The GCU examples need the Basket and Governance modules. The production site switches both off through `MODULES_DISABLED`, so run these examples against a demo or local deployment.

### GCU demo basket vote (simulated) (React)
```jsx
import React, { useState, useEffect } from 'react';

function GCUVotingComponent({ apiKey }) {
  const [activePolls, setActivePolls] = useState([]);
  const [votingPower, setVotingPower] = useState(0);
  const [allocations, setAllocations] = useState({
    USD: 40,
    EUR: 30,
    GBP: 15,
    CHF: 10,
    JPY: 3,
    XAU: 2
  });

  useEffect(() => {
    fetchActivePolls();
  }, []);

  // GET /api/voting/polls returns each poll with the uuid that the vote endpoint needs
  const fetchActivePolls = async () => {
    const response = await fetch('https://your-finaegis-host.example/api/voting/polls', {
      headers: { 'Authorization': `Bearer ${apiKey}` }
    });
    const data = await response.json();
    setActivePolls(data.data);
  };

  const submitVote = async (pollId) => {
    // Validate allocations sum to 100%
    const total = Object.values(allocations).reduce((sum, val) => sum + val, 0);
    if (total !== 100) {
      alert('Allocations must sum to 100%');
      return;
    }

    // Basket votes go to the version 1 endpoint POST /api/voting/polls/{uuid}/vote
    const response = await fetch(`https://your-finaegis-host.example/api/voting/polls/${pollId}/vote`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ allocations })
    });

    if (response.ok) {
      alert('Vote submitted successfully!');
      fetchActivePolls(); // Refresh
    }
  };

  const handleAllocationChange = (asset, value) => {
    setAllocations(prev => ({
      ...prev,
      [asset]: parseFloat(value)
    }));
  };

  return (
    <div className="gcu-voting">
      <h2>GCU Basket Voting</h2>
      
      {activePolls.map(poll => (
        <div key={poll.uuid} className="poll-card">
          <h3>{poll.title}</h3>
          <p>{poll.description}</p>
          <p>Voting ends: {new Date(poll.end_date).toLocaleString()}</p>
          
          <div className="allocation-inputs">
            {Object.entries(allocations).map(([asset, value]) => (
              <div key={asset}>
                <label>{asset}: </label>
                <input
                  type="number"
                  min="0"
                  max="100"
                  step="0.1"
                  value={value}
                  onChange={(e) => handleAllocationChange(asset, e.target.value)}
                />%
              </div>
            ))}
          </div>
          
          <p>Total: {Object.values(allocations).reduce((sum, val) => sum + val, 0)}%</p>
          
          <button onClick={() => submitVote(poll.uuid)}>
            Submit Vote
          </button>
        </div>
      ))}
    </div>
  );
}
```

### GCU demo value tracking (simulated) (Vue.js)
```vue
<template>
  <div class="gcu-tracker">
    <h2>GCU Value Tracker</h2>
    
    <div class="current-value">
      <h3>Current Value</h3>
      <p class="value">{{ gcuSymbol }}{{ currentValue }}</p>
      <p class="change" :class="changeClass">
        {{ change24h > 0 ? '+' : '' }}{{ change24h }}%
      </p>
    </div>
    
    <div class="chart-container">
      <canvas ref="chartCanvas"></canvas>
    </div>
    
    <div class="composition">
      <h3>Current Composition</h3>
      <ul>
        <li v-for="component in composition" :key="component.asset_code">
          {{ component.asset_name }}: {{ component.weight }}%
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
import Chart from 'chart.js/auto';

export default {
  data() {
    return {
      gcuSymbol: 'Ǥ',
      currentValue: 0,
      change24h: 0,
      composition: [],
      chart: null
    };
  },
  computed: {
    changeClass() {
      return this.change24h >= 0 ? 'positive' : 'negative';
    }
  },
  async mounted() {
    await this.fetchGCUInfo();
    await this.fetchAndDrawChart();
    
    // Update every 5 minutes
    setInterval(() => {
      this.fetchGCUInfo();
    }, 300000);
  },
  methods: {
    async fetchGCUInfo() {
      const response = await fetch('https://your-finaegis-host.example/api/v2/gcu');
      const data = await response.json();
      
      this.currentValue = data.data.current_value;
      this.composition = data.data.composition;
      this.change24h = data.data.statistics['24h_change'];
    },
    
    async fetchAndDrawChart() {
      const response = await fetch(
        'https://your-finaegis-host.example/api/v2/gcu/value-history?period=7d&interval=hourly'
      );
      const data = await response.json();
      
      const ctx = this.$refs.chartCanvas.getContext('2d');
      
      this.chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.data.map(d => new Date(d.timestamp).toLocaleString()),
          datasets: [{
            label: 'GCU Value (USD)',
            data: data.data.map(d => d.value),
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false
        }
      });
    }
  }
};
</script>
```

## Multi-Bank Distribution

Integration adapters for third-party APIs; no partnership or endorsement implied. In the GCU demo, distributions are simulated.

Bank allocation belongs to the Banking module and uses the version 1 API under `/api/bank-allocations`. The production site switches the Banking module off. A user keeps a list of banks with a percentage each. The preview endpoint shows how an amount would be split. It does not move money.

### Bank Allocation Management (TypeScript)
```typescript
interface BankAllocation {
  bank_code: string;
  bank_name: string;
  allocation_percentage: number;
  is_primary: boolean;
  status: string;
}

interface DistributionLine {
  bank_code: string;
  bank_name: string;
  allocation_percentage: number;
  amount: number; // major units
  is_primary: boolean;
}

class BankAllocationClient {
  constructor(
    private apiKey: string,
    private baseUrl: string = 'https://your-finaegis-host.example/api'
  ) {}

  private headers() {
    return {
      'Authorization': `Bearer ${this.apiKey}`,
      'Content-Type': 'application/json'
    };
  }

  // GET /api/bank-allocations
  async getAllocations(): Promise<BankAllocation[]> {
    const response = await fetch(`${this.baseUrl}/bank-allocations`, {
      headers: this.headers()
    });
    const data = await response.json();
    return data.data.allocations;
  }

  // POST /api/bank-allocations/distribution-preview (amount in major units)
  async previewDistribution(amount: number, assetCode: string = 'USD'): Promise<DistributionLine[]> {
    const response = await fetch(`${this.baseUrl}/bank-allocations/distribution-preview`, {
      method: 'POST',
      headers: this.headers(),
      body: JSON.stringify({ amount, asset_code: assetCode })
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Preview failed');
    }

    const data = await response.json();
    return data.data.distribution;
  }
}

// Usage
const allocationClient = new BankAllocationClient(API_KEY);

const allocations = await allocationClient.getAllocations();
const preview = await allocationClient.previewDistribution(10000.00, 'USD');

console.log('Distribution preview:', preview);
// Example output:
// [
//   { bank_code: 'BANK_A', allocation_percentage: 40, amount: 4000, ... },
//   { bank_code: 'BANK_B', allocation_percentage: 30, amount: 3000, ... },
//   { bank_code: 'BANK_C', allocation_percentage: 30, amount: 3000, ... }
// ]
```

## Webhook Integration

### Express.js Webhook Handler
```javascript
const express = require('express');
const crypto = require('crypto');

const app = express();

// Middleware to capture raw body for signature verification
app.use('/webhooks/finaegis', express.raw({ type: 'application/json' }));

// Webhook endpoint
app.post('/webhooks/finaegis', async (req, res) => {
  try {
    // Verify signature
    const signature = req.headers['x-webhook-signature'];
    const webhookSecret = process.env.FINAEGIS_WEBHOOK_SECRET;
    
    if (!verifyWebhookSignature(req.body, signature, webhookSecret)) {
      return res.status(401).json({ error: 'Invalid signature' });
    }
    
    // Parse event
    const event = JSON.parse(req.body);
    
    // Process event asynchronously
    setImmediate(() => processWebhookEvent(event));
    
    // Always respond immediately
    res.status(200).json({ received: true });
  } catch (error) {
    console.error('Webhook error:', error);
    res.status(400).json({ error: 'Invalid webhook payload' });
  }
});

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

async function processWebhookEvent(event) {
  console.log(`Processing ${event.event} event`);
  
  try {
    // The payload is flat: the event fields sit next to `event` and `timestamp`
    switch (event.event) {
      case 'account.created':
        await handleAccountCreated(event);
        break;
        
      case 'transaction.created':
        await handleTransactionCreated(event);
        break;
        
      case 'transfer.completed':
        await handleTransferCompleted(event);
        break;
        
      case 'balance.low':
        await handleLowBalance(event);
        break;
        
      default:
        console.log(`Unhandled event type: ${event.event}`);
    }
  } catch (error) {
    console.error(`Error processing ${event.event}:`, error);
    // Consider implementing retry logic or dead letter queue
  }
}

async function handleTransactionCreated(data) {
  // data: account_uuid, type ('deposit' or 'withdrawal'), amount (minor units),
  // currency, balance_after, hash, event, timestamp
  await db.recordAccountActivity(
    data.account_uuid,
    data.type,
    data.amount,
    data.balance_after
  );
  
  // Update analytics
  await analytics.track('transaction_created', {
    account_uuid: data.account_uuid,
    amount: data.amount,
    type: data.type
  });
}
```

### Django Webhook View
```python
import hmac
import hashlib
import json
from django.http import JsonResponse
from django.views.decorators.csrf import csrf_exempt
from django.views.decorators.http import require_POST
from django.conf import settings
import logging

logger = logging.getLogger(__name__)

@csrf_exempt
@require_POST
def finaegis_webhook(request):
    try:
        # Verify signature
        signature = request.headers.get('X-Webhook-Signature', '')
        if not verify_webhook_signature(
            request.body,
            signature,
            settings.FINAEGIS_WEBHOOK_SECRET
        ):
            return JsonResponse({'error': 'Invalid signature'}, status=401)
        
        # Parse event
        event = json.loads(request.body)
        
        # Process asynchronously using Celery
        from .tasks import process_webhook_event
        process_webhook_event.delay(event)
        
        return JsonResponse({'received': True})
        
    except json.JSONDecodeError:
        return JsonResponse({'error': 'Invalid JSON'}, status=400)
    except Exception as e:
        logger.error(f'Webhook processing error: {e}')
        return JsonResponse({'error': 'Internal error'}, status=500)

def verify_webhook_signature(payload, signature, secret):
    expected_signature = 'sha256=' + hmac.new(
        secret.encode('utf-8'),
        payload,
        hashlib.sha256
    ).hexdigest()
    
    return hmac.compare_digest(signature, expected_signature)

# Celery task
from celery import shared_task

@shared_task
def process_webhook_event(event):
    # The payload is flat: the event fields sit next to 'event' and 'timestamp'
    event_type = event.get('event')
    
    handlers = {
        'account.created': handle_account_created,
        'transaction.created': handle_transaction_created,
        'transfer.completed': handle_transfer_completed,
        'balance.low': handle_low_balance,
    }
    
    handler = handlers.get(event_type)
    if handler:
        try:
            handler(event)
        except Exception as e:
            logger.error(f'Error handling {event_type}: {e}')
            raise  # Celery will retry
    else:
        logger.warning(f'No handler for event type: {event_type}')
```

## Error Handling

### Comprehensive Error Handler (Go)
```go
package finaegis

import (
    "context"
    "encoding/json"
    "fmt"
    "time"
)

// Business errors return {"message": "...", "error": "INSUFFICIENT_FUNDS", ...}
type APIError struct {
    StatusCode     int    `json:"-"`
    Code           string `json:"error"`
    Message        string `json:"message"`
    CurrentBalance int64  `json:"current_balance,omitempty"`
}

type RetryConfig struct {
    MaxRetries int
    BaseDelay  time.Duration
    MaxDelay   time.Duration
}

func (c *Client) callWithRetry(
    ctx context.Context,
    method string,
    endpoint string,
    body interface{},
    result interface{},
) error {
    config := RetryConfig{
        MaxRetries: 3,
        BaseDelay:  1 * time.Second,
        MaxDelay:   30 * time.Second,
    }
    
    var lastErr error
    
    for attempt := 0; attempt <= config.MaxRetries; attempt++ {
        if attempt > 0 {
            delay := c.calculateBackoff(attempt, config)
            select {
            case <-time.After(delay):
            case <-ctx.Done():
                return ctx.Err()
            }
        }
        
        err := c.makeRequest(method, endpoint, body, result)
        
        if err == nil {
            return nil
        }
        
        lastErr = err
        
        // Check if error is retryable
        if apiErr, ok := err.(*APIError); ok {
            if !c.isRetryable(apiErr) {
                return err
            }
        }
    }
    
    return fmt.Errorf("max retries exceeded: %w", lastErr)
}

func (c *Client) isRetryable(err *APIError) bool {
    // Retry rate limits (429) and server errors (5xx) only
    return err.StatusCode == 429 || err.StatusCode >= 500
}

func (c *Client) calculateBackoff(attempt int, config RetryConfig) time.Duration {
    delay := config.BaseDelay * time.Duration(1<<uint(attempt))
    if delay > config.MaxDelay {
        delay = config.MaxDelay
    }
    
    // Add jitter (±20%)
    jitter := time.Duration(float64(delay) * 0.2 * (2*rand.Float64() - 1))
    return delay + jitter
}

// Usage example
// amount is in major units, for example "100.00"
func TransferWithRetry(client *Client, from, to string, amount string) error {
    ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
    defer cancel()
    
    var result TransferResponse
    
    err := client.callWithRetry(ctx, "POST", "/transfers", map[string]interface{}{
        "from_account": from,
        "to_account":   to,
        "amount":       amount,
        "asset_code":   "GCU",
    }, &result)
    
    if err != nil {
        if apiErr, ok := err.(*APIError); ok {
            switch apiErr.Code {
            case "INSUFFICIENT_FUNDS":
                return fmt.Errorf("not enough balance: need %s, have %d (minor units)",
                    amount,
                    apiErr.CurrentBalance)
            case "SOURCE_ACCOUNT_FROZEN":
                return fmt.Errorf("account %s is frozen", from)
            default:
                return fmt.Errorf("transfer failed: %s", apiErr.Message)
            }
        }
        return err
    }
    
    return nil
}
```

The version 2 transfer endpoint does not deduplicate requests. A retry after a server error can therefore create a second transfer. Check the account's transfers with `GET /api/v2/accounts/{uuid}/transfers` before you retry a transfer.

## Production Best Practices

### 1. Connection Pooling (Node.js)
```javascript
const { Agent } = require('https');
const fetch = require('node-fetch');

class FinAegisClient {
  constructor(apiKey) {
    this.apiKey = apiKey;
    this.baseUrl = 'https://your-finaegis-host.example/api/v2';
    
    // Connection pooling
    this.agent = new Agent({
      keepAlive: true,
      keepAliveMsecs: 30000,
      maxSockets: 50,
      maxFreeSockets: 10,
      timeout: 60000,
      freeSocketTimeout: 30000,
    });
  }

  async request(endpoint, options = {}) {
    const response = await fetch(`${this.baseUrl}${endpoint}`, {
      ...options,
      agent: this.agent,
      headers: {
        'Authorization': `Bearer ${this.apiKey}`,
        'Content-Type': 'application/json',
        'X-Request-ID': this.generateRequestId(),
        ...options.headers
      }
    });

    if (!response.ok) {
      throw await this.handleError(response);
    }

    return response.json();
  }

  generateRequestId() {
    return `req_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }
}
```

### 2. Caching Strategy (Redis)
```javascript
const Redis = require('ioredis');
const redis = new Redis();

class CachedFinAegisClient extends FinAegisClient {
  async getExchangeRate(from, to) {
    const cacheKey = `exchange_rate:${from}:${to}`;
    
    // Try cache first
    const cached = await redis.get(cacheKey);
    if (cached) {
      return JSON.parse(cached);
    }
    
    // Fetch from API
    const rate = await this.request(`/exchange-rates/${from}/${to}`);
    
    // Cache for 5 minutes
    await redis.setex(cacheKey, 300, JSON.stringify(rate));
    
    return rate;
  }

  async getGCUInfo() {
    const cacheKey = 'gcu:info';
    
    // Try cache with shorter TTL for frequently changing data
    const cached = await redis.get(cacheKey);
    if (cached) {
      return JSON.parse(cached);
    }
    
    const info = await this.request('/gcu');
    
    // Cache for 1 minute
    await redis.setex(cacheKey, 60, JSON.stringify(info));
    
    return info;
  }
}
```

### 3. Monitoring and Observability
```javascript
const { createLogger } = require('winston');
const { StatsD } = require('node-statsd');

const logger = createLogger({
  level: 'info',
  format: winston.format.json(),
  transports: [
    new winston.transports.File({ filename: 'error.log', level: 'error' }),
    new winston.transports.File({ filename: 'combined.log' })
  ]
});

const metrics = new StatsD({
  host: 'localhost',
  port: 8125,
  prefix: 'finaegis_api.'
});

class MonitoredFinAegisClient extends CachedFinAegisClient {
  async request(endpoint, options = {}) {
    const startTime = Date.now();
    const metricName = endpoint.replace(/[^a-zA-Z0-9]/g, '_');
    
    try {
      const result = await super.request(endpoint, options);
      
      // Success metrics
      const duration = Date.now() - startTime;
      metrics.timing(`request.${metricName}.duration`, duration);
      metrics.increment(`request.${metricName}.success`);
      
      logger.info('API request successful', {
        endpoint,
        duration,
        requestId: options.headers?.['X-Request-ID']
      });
      
      return result;
    } catch (error) {
      // Error metrics
      const duration = Date.now() - startTime;
      metrics.timing(`request.${metricName}.duration`, duration);
      metrics.increment(`request.${metricName}.error`);
      metrics.increment(`error.${error.code || 'unknown'}`);
      
      logger.error('API request failed', {
        endpoint,
        duration,
        error: error.message,
        code: error.code,
        requestId: options.headers?.['X-Request-ID']
      });
      
      throw error;
    }
  }
}
```

### 4. Health Check Implementation
```javascript
class HealthChecker {
  constructor(client) {
    this.client = client;
  }

  async checkHealth() {
    const checks = {
      api: await this.checkAPI(),
      authentication: await this.checkAuth(),
      criticalEndpoints: await this.checkCriticalEndpoints()
    };

    const overall = Object.values(checks).every(check => check.status === 'healthy');

    return {
      status: overall ? 'healthy' : 'unhealthy',
      timestamp: new Date().toISOString(),
      checks
    };
  }

  async checkAPI() {
    try {
      const start = Date.now();
      const response = await fetch('https://your-finaegis-host.example/api/v2/status');
      const data = await response.json();
      
      return {
        status: data.status === 'operational' ? 'healthy' : 'degraded',
        responseTime: Date.now() - start,
        details: data
      };
    } catch (error) {
      return {
        status: 'unhealthy',
        error: error.message
      };
    }
  }

  async checkAuth() {
    try {
      await this.client.request('/accounts');
      return { status: 'healthy' };
    } catch (error) {
      return {
        status: 'unhealthy',
        error: error.message
      };
    }
  }

  async checkCriticalEndpoints() {
    const endpoints = ['/gcu', '/exchange-rates/USD/GCU', '/assets'];
    const results = await Promise.allSettled(
      endpoints.map(ep => this.client.request(ep))
    );

    const failed = results.filter(r => r.status === 'rejected').length;

    return {
      status: failed === 0 ? 'healthy' : failed < endpoints.length ? 'degraded' : 'unhealthy',
      total: endpoints.length,
      failed
    };
  }
}

// Usage
const healthChecker = new HealthChecker(client);
const health = await healthChecker.checkHealth();
console.log('System health:', health);
```

## Testing

### Integration Test Example
```javascript
const { describe, it, expect, beforeAll, afterAll } = require('@jest/globals');

describe('FinAegis API Integration Tests', () => {
  let client;
  let testAccountId;

  beforeAll(() => {
    client = new FinAegisClient(process.env.TEST_API_KEY);
  });

  afterAll(async () => {
    // Cleanup
    if (testAccountId) {
      await client.request(`/accounts/${testAccountId}`, { method: 'DELETE' });
    }
  });

  it('should create an account', async () => {
    const account = await client.request('/accounts', {
      method: 'POST',
      body: JSON.stringify({
        name: 'Test Account'
      })
    });

    expect(account.data).toHaveProperty('uuid');
    expect(account.data.name).toBe('Test Account');
    testAccountId = account.data.uuid;
  });

  it('should get GCU exchange rate', async () => {
    const rate = await client.request('/exchange-rates/USD/GCU');
    
    expect(rate.data).toHaveProperty('rate');
    expect(typeof rate.data.rate).toBe('number');
    expect(rate.data.rate).toBeGreaterThan(0);
  });

  it('should handle errors correctly', async () => {
    await expect(
      client.request('/accounts/invalid-uuid')
    ).rejects.toThrow('Account not found');
  });
});
```

This comprehensive guide provides practical examples for integrating with the FinAegis API across different languages and use cases. Remember to always handle errors appropriately, implement proper security measures, and follow the rate limiting guidelines.