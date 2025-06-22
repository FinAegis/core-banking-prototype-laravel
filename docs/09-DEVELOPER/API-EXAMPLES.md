# FinAegis API Integration Examples

This document provides practical examples of integrating with the FinAegis API in various programming languages and frameworks.

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
curl https://api.finaegis.com/v2/status

# Get GCU information
curl https://api.finaegis.com/v2/gcu

# Authenticated request
curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://api.finaegis.com/v2/accounts
```

### JavaScript/Node.js Quick Start
```javascript
// Using fetch (Node.js 18+ or browser)
const response = await fetch('https://api.finaegis.com/v2/gcu');
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
      baseURL: 'https://api.finaegis.com/v2',
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
        self.base_url = 'https://api.finaegis.com/v2'
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
            'base_uri' => 'https://api.finaegis.com/v2/',
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
    const accountResponse = await fetch('https://api.finaegis.com/v2/accounts', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${API_KEY}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        name: `${userEmail} GCU Wallet`,
        type: 'savings',
        metadata: {
          user_email: userEmail,
          created_via: 'api'
        }
      })
    });

    const account = await accountResponse.json();
    console.log('Account created:', account.data.uuid);

    // Step 2: Fund account with GCU
    const depositResponse = await fetch(
      `https://api.finaegis.com/v2/accounts/${account.data.uuid}/deposit`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${API_KEY}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          amount: initialDeposit * 100, // Convert to cents
          asset_code: 'GCU',
          reference: 'Initial deposit'
        })
      }
    );

    const deposit = await depositResponse.json();
    console.log('Deposit completed:', deposit.data.transaction_id);

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
import aiohttp

class MultiCurrencyAccount:
    def __init__(self, api_key, account_uuid):
        self.api_key = api_key
        self.account_uuid = account_uuid
        self.base_url = 'https://api.finaegis.com/v2'
    
    async def get_all_balances(self):
        async with aiohttp.ClientSession() as session:
            async with session.get(
                f'{self.base_url}/accounts/{self.account_uuid}/balances',
                headers={'Authorization': f'Bearer {self.api_key}'}
            ) as response:
                data = await response.json()
                return data['data']['balances']
    
    async def convert_to_gcu(self, from_asset, amount):
        async with aiohttp.ClientSession() as session:
            # Get exchange rate
            async with session.get(
                f'{self.base_url}/exchange-rates/{from_asset}/GCU',
                headers={'Authorization': f'Bearer {self.api_key}'}
            ) as response:
                rate_data = await response.json()
                rate = rate_data['data']['rate']
            
            # Perform conversion
            gcu_amount = int(amount * rate * 100)  # Convert to cents
            
            # Execute conversion via transfer
            async with session.post(
                f'{self.base_url}/accounts/{self.account_uuid}/convert',
                headers={
                    'Authorization': f'Bearer {self.api_key}',
                    'Content-Type': 'application/json'
                },
                json={
                    'from_asset': from_asset,
                    'to_asset': 'GCU',
                    'amount': int(amount * 100)
                }
            ) as response:
                return await response.json()

# Usage
async def main():
    account = MultiCurrencyAccount(API_KEY, ACCOUNT_UUID)
    
    # Get all balances
    balances = await account.get_all_balances()
    for balance in balances:
        print(f"{balance['asset_code']}: {balance['formatted_balance']}")
    
    # Convert EUR to GCU
    result = await account.convert_to_gcu('EUR', 500)
    print(f"Converted €500 to GCU: {result}")

asyncio.run(main())
```

## GCU Operations

### GCU Basket Voting (React)
```jsx
import React, { useState, useEffect } from 'react';

function GCUVotingComponent({ apiKey }) {
  const [activePolls, setActivePolls] = useState([]);
  const [votingPower, setVotingPower] = useState(0);
  const [allocations, setAllocations] = useState({
    USD: 35,
    EUR: 30,
    GBP: 20,
    CHF: 10,
    JPY: 3,
    XAU: 2
  });

  useEffect(() => {
    fetchActivePolls();
  }, []);

  const fetchActivePolls = async () => {
    const response = await fetch('https://api.finaegis.com/v2/gcu/governance/active-polls');
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

    const response = await fetch(`https://api.finaegis.com/v2/polls/${pollId}/vote`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        option_id: 'basket_allocation',
        metadata: { allocations }
      })
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
        <div key={poll.id} className="poll-card">
          <h3>{poll.title}</h3>
          <p>{poll.description}</p>
          <p>Time remaining: {poll.time_remaining.human_readable}</p>
          
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
          
          <button onClick={() => submitVote(poll.id)}>
            Submit Vote
          </button>
        </div>
      ))}
    </div>
  );
}
```

### GCU Value Tracking (Vue.js)
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
      const response = await fetch('https://api.finaegis.com/v2/gcu');
      const data = await response.json();
      
      this.currentValue = data.data.current_value;
      this.composition = data.data.composition;
      this.change24h = data.data.statistics['24h_change'];
    },
    
    async fetchAndDrawChart() {
      const response = await fetch(
        'https://api.finaegis.com/v2/gcu/value-history?period=7d&interval=hourly'
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

### Bank Allocation Management (TypeScript)
```typescript
interface BankAllocation {
  custodian_code: string;
  allocation_percentage: number;
  is_primary: boolean;
}

interface DistributionResult {
  bank: string;
  amount: number;
  status: 'success' | 'failed';
  transaction_id?: string;
  error?: string;
}

class BankDistributionService {
  constructor(
    private apiKey: string,
    private baseUrl: string = 'https://api.finaegis.com/v2'
  ) {}

  async distributeDeposit(
    accountUuid: string,
    totalAmount: number,
    assetCode: string = 'GCU'
  ): Promise<DistributionResult[]> {
    // Get user's bank preferences
    const allocations = await this.getBankAllocations(accountUuid);
    
    // Calculate distribution
    const distributions = this.calculateDistribution(totalAmount, allocations);
    
    // Execute parallel deposits
    const results = await Promise.allSettled(
      distributions.map(dist => this.depositToBank(accountUuid, dist))
    );
    
    return results.map((result, index) => {
      if (result.status === 'fulfilled') {
        return {
          bank: distributions[index].bank,
          amount: distributions[index].amount,
          status: 'success' as const,
          transaction_id: result.value.transaction_id
        };
      } else {
        return {
          bank: distributions[index].bank,
          amount: distributions[index].amount,
          status: 'failed' as const,
          error: result.reason.message
        };
      }
    });
  }

  private async getBankAllocations(accountUuid: string): Promise<BankAllocation[]> {
    const response = await fetch(
      `${this.baseUrl}/accounts/${accountUuid}/bank-preferences`,
      {
        headers: { 'Authorization': `Bearer ${this.apiKey}` }
      }
    );
    
    const data = await response.json();
    return data.data.allocations;
  }

  private calculateDistribution(
    totalAmount: number,
    allocations: BankAllocation[]
  ): { bank: string; amount: number }[] {
    let remaining = totalAmount;
    const distributions: { bank: string; amount: number }[] = [];
    
    // Sort by allocation percentage descending to minimize rounding errors
    const sorted = [...allocations].sort(
      (a, b) => b.allocation_percentage - a.allocation_percentage
    );
    
    sorted.forEach((allocation, index) => {
      if (index === sorted.length - 1) {
        // Last allocation gets the remaining amount
        distributions.push({
          bank: allocation.custodian_code,
          amount: remaining
        });
      } else {
        const amount = Math.floor(totalAmount * allocation.allocation_percentage / 100);
        distributions.push({
          bank: allocation.custodian_code,
          amount
        });
        remaining -= amount;
      }
    });
    
    return distributions;
  }

  private async depositToBank(
    accountUuid: string,
    distribution: { bank: string; amount: number }
  ): Promise<{ transaction_id: string }> {
    const response = await fetch(
      `${this.baseUrl}/custodians/${distribution.bank}/deposit`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.apiKey}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          account_uuid: accountUuid,
          amount: distribution.amount,
          asset_code: 'GCU'
        })
      }
    );
    
    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Deposit failed');
    }
    
    const data = await response.json();
    return { transaction_id: data.data.transaction_id };
  }
}

// Usage
const distributionService = new BankDistributionService(API_KEY);

// Distribute Ǥ10,000 across user's configured banks
const results = await distributionService.distributeDeposit(
  accountUuid,
  1000000, // Ǥ10,000 in cents
  'GCU'
);

console.log('Distribution results:', results);
// Example output:
// [
//   { bank: 'paysera', amount: 400000, status: 'success', transaction_id: 'tx_123' },
//   { bank: 'deutsche_bank', amount: 300000, status: 'success', transaction_id: 'tx_124' },
//   { bank: 'santander', amount: 300000, status: 'success', transaction_id: 'tx_125' }
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
    switch (event.event) {
      case 'account.created':
        await handleAccountCreated(event.data);
        break;
        
      case 'transaction.completed':
        await handleTransactionCompleted(event.data);
        break;
        
      case 'transfer.completed':
        await handleTransferCompleted(event.data);
        break;
        
      case 'basket.rebalanced':
        await handleBasketRebalanced(event.data);
        break;
        
      case 'poll.completed':
        await handlePollCompleted(event.data);
        break;
        
      default:
        console.log(`Unhandled event type: ${event.event}`);
    }
  } catch (error) {
    console.error(`Error processing ${event.event}:`, error);
    // Consider implementing retry logic or dead letter queue
  }
}

async function handleTransactionCompleted(data) {
  // Update user balance in your database
  await db.updateUserBalance(
    data.account_uuid,
    data.asset_code,
    data.amount,
    data.type
  );
  
  // Send notification to user
  await notificationService.send({
    user_id: data.user_id,
    type: 'transaction_completed',
    message: `${data.type} of ${data.formatted_amount} ${data.asset_code} completed`
  });
  
  // Update analytics
  await analytics.track('transaction_completed', {
    account_uuid: data.account_uuid,
    amount: data.amount,
    asset_code: data.asset_code,
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
    event_type = event.get('event')
    data = event.get('data', {})
    
    handlers = {
        'account.created': handle_account_created,
        'transaction.completed': handle_transaction_completed,
        'transfer.completed': handle_transfer_completed,
        'basket.rebalanced': handle_basket_rebalanced,
    }
    
    handler = handlers.get(event_type)
    if handler:
        try:
            handler(data)
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

type APIError struct {
    Code    string                 `json:"code"`
    Message string                 `json:"message"`
    Details map[string]interface{} `json:"details,omitempty"`
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
    retryableCodes := map[string]bool{
        "RATE_LIMIT_EXCEEDED":   true,
        "CUSTODIAN_UNAVAILABLE": true,
        "TEMPORARY_ERROR":       true,
        "TIMEOUT":              true,
    }
    
    return retryableCodes[err.Code]
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
func TransferWithRetry(client *Client, from, to string, amount int64) error {
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
                return fmt.Errorf("not enough balance: need %d, have %d",
                    amount,
                    apiErr.Details["available_balance"])
            case "ACCOUNT_FROZEN":
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

## Production Best Practices

### 1. Connection Pooling (Node.js)
```javascript
const { Agent } = require('https');
const fetch = require('node-fetch');

class FinAegisClient {
  constructor(apiKey) {
    this.apiKey = apiKey;
    this.baseUrl = 'https://api.finaegis.com/v2';
    
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
      const response = await fetch('https://api.finaegis.com/v2/status');
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
        name: 'Test Account',
        type: 'savings'
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