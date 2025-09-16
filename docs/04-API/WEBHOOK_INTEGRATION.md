# Webhook Integration Guide

This guide explains how to configure and use webhooks in the FinAegis Core Banking Platform.

## Overview

The webhook system allows you to receive real-time notifications when events occur in the banking platform. This enables seamless integration with external systems, payment gateways, and third-party services.

## Available Events

### Account Events
- `account.created` - Triggered when a new account is created
- `account.updated` - Triggered when account details are modified
- `account.frozen` - Triggered when an account is frozen
- `account.unfrozen` - Triggered when an account is unfrozen
- `account.closed` - Triggered when an account is closed

### Transaction Events
- `transaction.created` - Triggered when a transaction is created (deposit/withdrawal)
- `transaction.reversed` - Triggered when a transaction is reversed

### Transfer Events
- `transfer.created` - Triggered when a transfer is initiated
- `transfer.completed` - Triggered when a transfer is successfully completed
- `transfer.failed` - Triggered when a transfer fails

### Balance Alerts
- `balance.low` - Triggered when account balance falls below $10.00
- `balance.negative` - Triggered when account balance becomes negative

## Webhook Configuration

### Via Admin Dashboard

1. Navigate to `/admin` and log in
2. Go to **System → Webhooks**
3. Click **Create Webhook**
4. Fill in the configuration:
   - **Name**: A descriptive name for your webhook
   - **URL**: The HTTPS endpoint that will receive webhook events
   - **Events**: Select which events should trigger this webhook
   - **Secret**: (Optional) A secret key for signature verification
   - **Headers**: (Optional) Custom headers to include in requests
   - **Retry Attempts**: Number of retry attempts on failure (default: 3)
   - **Timeout**: Request timeout in seconds (default: 30)

### Programmatically

```php
use App\Models\Webhook;

$webhook = Webhook::create([
    'name' => 'Payment Gateway Integration',
    'url' => 'https://api.paymentgateway.com/webhooks/banking',
    'events' => ['account.created', 'transaction.created', 'transfer.completed'],
    'headers' => [
        'X-API-Key' => 'your-api-key',
        'X-Client-ID' => 'client-123'
    ],
    'secret' => 'your-webhook-secret',
    'retry_attempts' => 3,
    'timeout_seconds' => 30,
]);
```

## Webhook Payload Structure

All webhook payloads follow a consistent structure:

```json
{
    "event": "account.created",
    "timestamp": "2024-09-14T10:30:00Z",
    "account_uuid": "01234567-89ab-cdef-0123-456789abcdef",
    "data": {
        // Event-specific data
    }
}
```

### Example Payloads

#### Account Created
```json
{
    "event": "account.created",
    "timestamp": "2024-09-14T10:30:00Z",
    "account_uuid": "01234567-89ab-cdef-0123-456789abcdef",
    "name": "John Doe Savings",
    "user_uuid": "fedcba98-7654-3210-fedc-ba9876543210",
    "balance": 0
}
```

#### Transaction Created
```json
{
    "event": "transaction.created",
    "timestamp": "2024-09-14T10:30:00Z",
    "account_uuid": "01234567-89ab-cdef-0123-456789abcdef",
    "type": "deposit",
    "amount": 10000,
    "currency": "USD",
    "balance_after": 15000,
    "hash": "3b7e72573c4b6e5f8d5a3b4c5e7f8a9b0c1d2e3f"
}
```

#### Transfer Completed
```json
{
    "event": "transfer.completed",
    "timestamp": "2024-09-14T10:30:00Z",
    "from_account_uuid": "01234567-89ab-cdef-0123-456789abcdef",
    "to_account_uuid": "fedcba98-7654-3210-fedc-ba9876543210",
    "amount": 5000,
    "currency": "USD",
    "from_balance_after": 10000,
    "to_balance_after": 20000,
    "hash": "4c8f83684d5c7f6e9e6b4d5f6g8h9j0k1l2m3n4"
}
```

## Security

### Signature Verification

If you configure a webhook secret, all requests will include an `X-Webhook-Signature` header containing an HMAC-SHA256 signature of the payload.

```php
// Verify webhook signature in PHP
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$payload = file_get_contents('php://input');
$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(401);
    die('Invalid signature');
}
```

```javascript
// Verify webhook signature in Node.js
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

### Headers

All webhook requests include the following headers:
- `Content-Type: application/json`
- `User-Agent: FinAegis-Webhook/1.0`
- `X-Webhook-ID`: The UUID of the webhook configuration
- `X-Webhook-Event`: The event type
- `X-Webhook-Delivery`: The UUID of this specific delivery attempt
- `X-Webhook-Signature`: HMAC signature (if secret is configured)

## Reliability

### Retry Logic

Failed webhook deliveries are automatically retried with exponential backoff:
- 1st retry: After 1 minute
- 2nd retry: After 5 minutes
- 3rd retry: After 15 minutes

### Automatic Disabling

Webhooks are automatically disabled after 10 consecutive failures to prevent endless retries. You can re-enable them through the admin dashboard.

### Delivery Monitoring

Monitor webhook deliveries through the admin dashboard:
1. Go to **System → Webhooks**
2. Click on a webhook to view details
3. Check the **Delivery History** tab to see:
   - Delivery status (pending/delivered/failed)
   - Response status codes
   - Response times
   - Error messages
   - Retry attempts

## Best Practices

1. **Use HTTPS**: Always use HTTPS endpoints for webhook URLs
2. **Implement Idempotency**: Store and check the delivery UUID to handle duplicate deliveries
3. **Respond Quickly**: Return a 2xx status code as soon as possible (within 30 seconds)
4. **Queue Processing**: Queue webhook payloads for asynchronous processing
5. **Monitor Failures**: Set up alerts for webhook failures in your system
6. **Validate Payloads**: Always validate the payload structure and data
7. **Use Signatures**: Configure webhook secrets for production environments

## Testing Webhooks

### Using the Admin Dashboard

1. Go to **System → Webhooks**
2. Click the **Test** button next to any webhook
3. A test payload will be sent immediately

### Using RequestBin or Webhook.site

For development, you can use services like:
- [RequestBin](https://requestbin.com)
- [Webhook.site](https://webhook.site)

These provide temporary URLs to inspect webhook payloads.

### Local Development with ngrok

For local development, use [ngrok](https://ngrok.com) to expose your local server:

```bash
ngrok http 3000
```

Then use the provided HTTPS URL as your webhook endpoint.

## Troubleshooting

### Common Issues

1. **Webhook not triggering**
   - Ensure the webhook is active
   - Verify the selected events match what's happening
   - Check queue workers are running

2. **Signature verification failing**
   - Ensure you're using the raw request body
   - Verify the secret matches exactly
   - Check for encoding issues

3. **Timeouts**
   - Process webhooks asynchronously
   - Return 200 immediately and queue processing
   - Increase timeout if necessary (max 300 seconds)

4. **High failure rate**
   - Check your endpoint availability
   - Monitor response times
   - Implement proper error handling

## API Reference

### Webhook Model

```php
class Webhook extends Model
{
    protected $fillable = [
        'name',
        'description',
        'url',
        'events',          // array
        'headers',         // array
        'secret',
        'is_active',
        'retry_attempts',
        'timeout_seconds',
    ];
}
```

### WebhookService

```php
// Dispatch webhook manually
app(WebhookService::class)->dispatch('custom.event', [
    'custom_data' => 'value'
]);

// Verify signature
$isValid = app(WebhookService::class)->verifySignature(
    $payload,
    $signature,
    $secret
);
```

## Support

For webhook-related issues:
- Check the delivery history in the admin dashboard
- Review the Laravel logs for detailed error messages
- Contact support with the delivery UUID for investigation