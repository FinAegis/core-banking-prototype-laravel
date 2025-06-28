# FinAegis JavaScript/TypeScript SDK

Official JavaScript/TypeScript SDK for the FinAegis API.

## Installation

```bash
npm install @finaegis/sdk
# or
yarn add @finaegis/sdk
```

## Quick Start

```typescript
import { FinAegis } from '@finaegis/sdk';

// Initialize the client
const client = new FinAegis({
  apiKey: 'your-api-key',
  environment: 'sandbox' // or 'production'
});

// List accounts
const accounts = await client.accounts.list();

// Create a new account
const account = await client.accounts.create({
  user_uuid: 'user-uuid',
  name: 'My Savings Account',
  initial_balance: 10000 // in cents
});

// Make a transfer
const transfer = await client.transfers.create({
  from_account: 'account-uuid-1',
  to_account: 'account-uuid-2',
  amount: 5000, // in cents
  asset_code: 'USD',
  reference: 'Payment for services'
});
```

## Configuration

```typescript
const client = new FinAegis({
  apiKey: 'your-api-key',
  environment: 'production', // 'production' | 'sandbox' | 'local'
  timeout: 30000, // Request timeout in milliseconds
  maxRetries: 3 // Number of retries for failed requests
});
```

## Resources

### Accounts

```typescript
// List all accounts
const accounts = await client.accounts.list({
  page: 1,
  per_page: 20
});

// Get account details
const account = await client.accounts.get('account-uuid');

// Get account balances
const balances = await client.accounts.getBalances('account-uuid');

// Deposit funds
const deposit = await client.accounts.deposit('account-uuid', 10000, 'USD');

// Withdraw funds
const withdrawal = await client.accounts.withdraw('account-uuid', 5000, 'USD');

// Freeze/unfreeze account
await client.accounts.freeze('account-uuid', 'Suspicious activity');
await client.accounts.unfreeze('account-uuid', 'Investigation completed');
```

### Transfers

```typescript
// Create a transfer
const transfer = await client.transfers.create({
  from_account: 'account-uuid-1',
  to_account: 'account-uuid-2',
  amount: 10000,
  asset_code: 'USD',
  reference: 'Invoice #123'
});

// Get transfer details
const transferDetails = await client.transfers.get('transfer-uuid');
```

### Exchange Rates

```typescript
// Get exchange rate
const rate = await client.exchangeRates.get('USD', 'EUR');

// Convert currency
const conversion = await client.exchangeRates.convert('USD', 'EUR', 100);
```

### GCU (Global Currency Unit)

```typescript
// Get GCU composition
const composition = await client.gcu.getComposition();

// Get value history
const history = await client.gcu.getValueHistory({
  period: '7d',
  interval: 'daily'
});

// Get active governance polls
const polls = await client.gcu.getActivePolls();
```

### Webhooks

```typescript
// Create a webhook
const webhook = await client.webhooks.create({
  name: 'Transaction Updates',
  url: 'https://your-app.com/webhooks',
  events: ['transaction.created', 'transaction.completed'],
  secret: 'your-webhook-secret'
});

// List webhook deliveries
const deliveries = await client.webhooks.getDeliveries('webhook-uuid');
```

## Error Handling

```typescript
import { FinAegisError } from '@finaegis/sdk';

try {
  const account = await client.accounts.get('invalid-uuid');
} catch (error) {
  if (error instanceof FinAegisError) {
    console.error('API Error:', error.message);
    console.error('Status Code:', error.statusCode);
    
    if (error.isNotFoundError()) {
      // Handle 404 errors
    } else if (error.isAuthError()) {
      // Handle authentication errors
    } else if (error.isValidationError()) {
      // Handle validation errors
      console.error('Validation errors:', error.data);
    }
  }
}
```

## TypeScript Support

This SDK is written in TypeScript and provides full type definitions for all API responses.

```typescript
import { Account, Transfer, CreateAccountParams } from '@finaegis/sdk';

// All types are available for import
const createAccount = async (params: CreateAccountParams): Promise<Account> => {
  const response = await client.accounts.create(params);
  return response.data;
};
```

## Advanced Usage

### Custom Requests

```typescript
// Make custom API requests
const customResponse = await client.request({
  method: 'GET',
  path: '/custom-endpoint',
  params: { key: 'value' }
});
```

### Webhook Signature Verification

```typescript
import crypto from 'crypto';

function verifyWebhookSignature(payload: string, signature: string, secret: string): boolean {
  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
  
  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expectedSignature)
  );
}
```

## Examples

### Complete Payment Flow

```typescript
async function processPayment(fromAccountId: string, toAccountId: string, amount: number) {
  try {
    // Check sender balance
    const balances = await client.accounts.getBalances(fromAccountId);
    const usdBalance = balances.data.balances.find(b => b.asset_code === 'USD');
    
    if (!usdBalance || parseFloat(usdBalance.available_balance) < amount) {
      throw new Error('Insufficient balance');
    }
    
    // Create transfer
    const transfer = await client.transfers.create({
      from_account: fromAccountId,
      to_account: toAccountId,
      amount: amount * 100, // Convert to cents
      asset_code: 'USD',
      reference: `Payment on ${new Date().toISOString()}`
    });
    
    console.log('Transfer completed:', transfer.data.uuid);
    return transfer.data;
  } catch (error) {
    console.error('Payment failed:', error);
    throw error;
  }
}
```

## License

MIT