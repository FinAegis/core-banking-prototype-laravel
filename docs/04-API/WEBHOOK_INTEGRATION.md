# Webhook Integration Guide

This guide explains how to set up outgoing webhooks in FinAegis and how to receive them.

## Overview

A webhook is an HTTPS request that FinAegis sends to your system when something happens to an account, a transaction or a transfer. Your system can then react without polling the API.

This guide covers outgoing webhooks only. Incoming provider webhooks, such as Bridge, Lepo, FinCard, Helius, Alchemy, Apple and Google, are described in the operations runbooks under `docs/operations/`, for example [bridge-ramp.md](../operations/bridge-ramp.md) and [iap-subscriptions.md](../operations/iap-subscriptions.md).

## Available Events

The admin dashboard offers the events below. They come from `Webhook::EVENTS` in `app/Domain/Webhook/Models/Webhook.php`.

### Account Events
- `account.created` is sent when a new account is created.
- `account.updated` is listed, but no code sends it yet.
- `account.frozen` is sent when an account is frozen.
- `account.unfrozen` is sent when an account is unfrozen.
- `account.closed` is sent when an account is closed.

### Transaction Events
- `transaction.created` is sent when a deposit or withdrawal is recorded.
- `transaction.reversed` is listed, but no code sends it yet.

### Transfer Events
- `transfer.created` is sent when a transfer is recorded.
- `transfer.completed` is sent when a transfer completes. It is sent together with `transfer.created`.
- `transfer.failed` is listed, but no code sends it yet.

### Balance Alerts
- `balance.low` is sent when a deposit leaves the balance below 1,000 minor units ($10.00).
- `balance.negative` is sent when a withdrawal leaves the balance below zero.

The v2 API (`GET /api/v2/webhooks/events`) lists a longer set of event names, including basket, poll and rate events. Only the account, transaction, transfer and balance events marked as sent above are sent today. The amounts in the payloads are in minor units, and `currency` is always `USD`.

## Webhook Configuration

### Via Admin Dashboard

1. Open `/admin` and sign in as an administrator.
2. Go to **System → Webhooks**.
3. Click **Create Webhook**.
4. Fill in the form:
   - **Name** is a label for the webhook.
   - **URL** is the HTTPS endpoint that receives the events.
   - **Events** are the events that trigger this webhook.
   - **Secret** is optional. It turns on signature checks.
   - **Headers** are optional extra headers for each request.
   - **Retry Attempts** sets how often a failed delivery is retried. The default is 3 and the maximum is 10.
   - **Timeout** sets the request timeout in seconds. The default is 30, and the allowed range is 5 to 300.

### Through the API

Platform administrators can also manage webhooks through `POST /api/v2/webhooks` and the related v2 endpoints. See [Webhooks in the REST reference](REST_API_REFERENCE.md#webhooks).

### Programmatically

```php
use App\Domain\Webhook\Models\Webhook;

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

The secret is stored encrypted.

## Webhook Payload Structure

Every payload is a flat JSON object. It holds `event`, `timestamp` and the fields of that event. There is no separate `data` object.

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

When a webhook has a secret, every request carries an `X-Webhook-Signature` header. The header holds an HMAC-SHA256 signature of the raw request body, prefixed with `sha256=`.

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

Every webhook request carries these headers:
- `Content-Type: application/json`
- `User-Agent: {brand}-Webhook/1.0`, where `{brand}` is the configured brand name from `APP_BRAND` or else `APP_NAME` (production Zelta sends `Zelta-Webhook/1.0`)
- `X-Webhook-ID` with the UUID of the webhook configuration
- `X-Webhook-Event` with the event type
- `X-Webhook-Delivery` with the UUID of this delivery
- `X-Webhook-Signature` with the HMAC signature, when a secret is set

## Reliability

### Retry Logic

A failed delivery is retried with growing delays. The first retry comes after 1 minute, the second after 5 minutes and the third after 15 minutes. Retries stop 24 hours after the first attempt.

### Automatic Disabling

A webhook is switched off after 10 failures in a row, so it does not retry forever. An administrator can switch it back on and reset the failure count in the admin dashboard.

### Delivery Monitoring

The admin dashboard shows every delivery:
1. Go to **System → Webhooks**.
2. Open a webhook.
3. Open the **Delivery History** list. It shows the delivery status (pending, delivered or failed), the response status code, the response time, the error message and the number of attempts.

`GET /api/v2/webhooks/{id}/deliveries` returns the same list through the API. The older `GET /api/v1/webhooks/deliveries` endpoint is a placeholder that always returns an empty list.

## Best Practices

1. Use an HTTPS endpoint for every webhook URL.
2. Store the delivery UUID and check it, so that a repeated delivery is processed only once.
3. Return a `2xx` status quickly, within the configured timeout.
4. Put the payload on a queue and process it later.
5. Set up alerts for failed deliveries in your own system.
6. Check the structure and values of every payload.
7. Set a secret for every production webhook, so you can check signatures.

## Testing Webhooks

### Using the Admin Dashboard

1. Go to **System → Webhooks**.
2. Click **Test** next to a webhook.
3. FinAegis queues a `test.webhook` delivery to that webhook.

`POST /api/v1/webhooks/test/{eventType}` returns a sample payload for an event type. It does not send anything.

### Using RequestBin or Webhook.site

During development, services such as these give you a temporary URL where you can inspect the payloads:
- [RequestBin](https://requestbin.com)
- [Webhook.site](https://webhook.site)

### Local Development with ngrok

For local development, [ngrok](https://ngrok.com) exposes your local server on a public HTTPS URL:

```bash
ngrok http 3000
```

Use the HTTPS URL that ngrok prints as the webhook endpoint.

## Troubleshooting

### Common Issues

1. **Webhook not triggering**
   - Check that the webhook is active.
   - Check that the selected events match what is happening.
   - Check that the queue workers are running.

2. **Signature verification failing**
   - Compute the signature over the raw request body.
   - Check that the secret matches exactly.
   - Check for encoding changes in the body.

3. **Timeouts**
   - Process webhooks in the background.
   - Return `200` at once and queue the work.
   - Raise the timeout if needed. The maximum is 300 seconds.

4. **High failure rate**
   - Check that your endpoint is reachable.
   - Watch your response times.
   - Handle errors in your endpoint so it still answers.

## API Reference

### Webhook Model

```php
class Webhook extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'description',
        'url',
        'events',          // array
        'headers',         // array
        'secret',          // encrypted
        'is_active',
        'retry_attempts',
        'timeout_seconds',
        'last_triggered_at',
        'last_success_at',
        'last_failure_at',
        'consecutive_failures',
    ];
}
```

### WebhookService

```php
use App\Domain\Webhook\Services\WebhookService;

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

When a webhook does not arrive, check the delivery history in the admin dashboard and the Laravel logs. The logs hold the detailed error messages. When you report an issue, include the delivery UUID, because it identifies one delivery attempt.
