# Partner Integration Guide

## Overview

Zelta is a payment protocol abstraction layer that lets partners accept both crypto (USDC on-chain via x402) and fiat (multi-rail via MPP) payments through a single SDK. Partners integrate once and gain access to all supported payment rails without managing protocol-level complexity.

What partners get:

- **Transparent 402 payment negotiation** -- the SDK handles payment challenges automatically
- **Multi-protocol support** -- x402 (on-chain USDC) and MPP (Stripe, Tempo, Lightning, Card) in one client
- **Webhook-driven settlement** -- real-time notifications for payment lifecycle events
- **Sandbox environment** -- full test environment with no real value at risk

## Prerequisites

- PHP 8.4+ (required by Zelta SDK v1.0.0)
- Composer 2.x
- Zelta API credentials (contact partnerships@zelta.app)
- Webhook endpoint for payment notifications (HTTPS required in production)

## Quick Start (5 minutes)

### 1. Install SDK

```bash
composer require finaegis/payment-sdk
```

For Laravel projects, the service provider auto-registers via `extra.laravel.providers`.

### 2. Configure

```php
use Zelta\DataObjects\PaymentConfig;
use Zelta\Handlers\AutoDetectHandler;
use Zelta\Handlers\X402PaymentHandler;
use Zelta\Handlers\MppPaymentHandler;
use Zelta\ZeltaClient;

$config = new PaymentConfig(
    baseUrl: 'https://api.zelta.app',
    apiKey: 'zk_live_your_api_key',
    preferredNetwork: 'base',  // ethereum, polygon, arbitrum, base, solana
    autoPay: true,
    timeoutSeconds: 30,
);

$client = new ZeltaClient(
    config: $config,
    payment: new AutoDetectHandler(
        new X402PaymentHandler($signer),
        new MppPaymentHandler($config),
    ),
);
```

### 3. Make a paid API call

```php
$response = $client->get('/v1/premium/resource');
// SDK automatically handles 402 payment negotiation:
// 1. Server returns 402 with payment requirements
// 2. SDK detects protocol (x402 or MPP) from response headers
// 3. SDK completes payment and retries the request
// 4. You receive the successful response
```

### 4. Handle the response

```php
$data = json_decode($response->getBody()->getContents(), true);
```

## Payment Protocols

### x402 (USDC On-Chain)

The x402 protocol enables direct on-chain USDC payments via the HTTP 402 status code. When a server returns 402 with x402 headers, the SDK constructs and submits a payment transaction, then retries the original request with proof of payment.

| Property | Detail |
|----------|--------|
| **Supported networks** | Ethereum, Polygon, Arbitrum, Base, Solana |
| **Settlement** | Facilitator-based, typically < 30 seconds |
| **Token** | USDC (native on each chain) |
| **Best for** | Crypto-native partners, high-value transactions, programmatic M2M payments |

Configuration endpoint: `/.well-known/x402-configuration`

### MPP (Multi-Payment Protocol)

MPP aggregates multiple payment rails behind a single negotiation flow. The server advertises available rails and the SDK selects the optimal one based on partner configuration.

| Property | Detail |
|----------|--------|
| **Rails** | Stripe, Tempo, Lightning, Card, x402 (fallback) |
| **Settlement** | Varies by rail: instant (Lightning), 1-3 business days (Card/Stripe) |
| **Currencies** | USD, EUR, GBP + USDC via x402 fallback |
| **Best for** | Fiat-accepting partners, consumer-facing products, mass market |

Configuration endpoint: `/.well-known/mpp-configuration`

### Choosing a Protocol

| Scenario | Recommended |
|----------|-------------|
| Crypto-native users, API-to-API payments | x402 |
| Consumer checkout, subscription billing | MPP |
| Mixed audience, maximum reach | AutoDetectHandler (both) |

## Webhook Integration

### Setup

Register your webhook URL via the API:

```bash
curl -X POST https://api.zelta.app/api/v1/webhooks/register \
  -H "Authorization: Bearer zk_live_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://your-app.com/webhooks/zelta",
    "events": ["payment.completed", "payment.failed", "payment.refunded"]
  }'
```

### Signature Verification

All webhook payloads are signed with HMAC-SHA256. Always verify before processing:

```php
$payload = file_get_contents('php://input');
$receivedSignature = $_SERVER['HTTP_X_ZELTA_SIGNATURE'] ?? '';

$expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

if (! hash_equals($expectedSignature, $receivedSignature)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
// Process the verified event...
```

Use `hash_equals()` for timing-safe comparison to prevent timing attacks.

### Webhook Events

| Event | Description |
|-------|-------------|
| `payment.completed` | Payment successfully processed and settled |
| `payment.failed` | Payment failed (insufficient funds, network error, etc.) |
| `payment.refunded` | Payment refunded to the payer |
| `subscription.created` | Recurring payment subscription started |
| `subscription.cancelled` | Recurring payment subscription stopped |

### Retry Policy

Failed webhook deliveries are retried with exponential backoff:

- Attempt 1: immediate
- Attempt 2: 1 minute
- Attempt 3: 5 minutes

Your endpoint must return a 2xx status code within 10 seconds. Non-2xx responses or timeouts trigger a retry.

## Idempotency

All payment-initiating requests should include an idempotency key to prevent duplicate charges:

```php
$response = $client->post('/v1/payments', [
    'headers' => [
        'Idempotency-Key' => 'partner-order-abc123',
    ],
    'json' => [
        'amount' => 1000,   // cents
        'currency' => 'usd',
    ],
]);
```

Keys are valid for 24 hours. Duplicate requests within that window return the original response.

## Rate Limits

| Endpoint Type | Limit |
|---------------|-------|
| General API requests | 1,000 requests/minute per API key |
| Payment initiations | 100 requests/minute per API key |
| Webhook registrations | 10 requests/minute per API key |

When rate-limited, the API returns HTTP 429 with a `Retry-After` header (in seconds). The SDK does not auto-retry rate limits -- implement backoff in your application:

```php
use GuzzleHttp\Exception\ClientException;

try {
    $response = $client->get('/v1/resource');
} catch (ClientException $e) {
    if ($e->getResponse()->getStatusCode() === 429) {
        $retryAfter = (int) $e->getResponse()->getHeaderLine('Retry-After');
        sleep($retryAfter);
        // Retry...
    }
}
```

## Error Handling

| HTTP Status | Meaning | Action |
|-------------|---------|--------|
| 400 | Bad request (validation) | Fix request parameters |
| 401 | Invalid API key | Check credentials |
| 402 | Payment required | Handled automatically by SDK |
| 403 | Forbidden | Check permissions/scopes |
| 404 | Resource not found | Verify endpoint/resource ID |
| 429 | Rate limited | Respect `Retry-After` header |
| 500 | Server error | Retry with backoff, contact support if persistent |

The SDK throws `PaymentRequiredException` if a 402 response cannot be handled (e.g., no handler configured) and `PaymentFailedException` if the payment itself fails.

## Testing

| Property | Detail |
|----------|--------|
| **Sandbox URL** | `https://sandbox.api.zelta.app` |
| **Test API key prefix** | `zk_test_` |
| **Test tokens** | No real value -- all sandbox payments use test tokens |

```php
$testConfig = new PaymentConfig(
    baseUrl: 'https://sandbox.api.zelta.app',
    apiKey: 'zk_test_your_test_key',
);

$testClient = new ZeltaClient(config: $testConfig, payment: $handler);
```

Sandbox mirrors production behavior exactly, including 402 challenges, webhook delivery, and rate limits.

## Migration from Direct API

If you previously integrated directly with x402 or MPP endpoints, the SDK replaces your manual 402 handling:

```php
// Before: manual 402 handling
$response = $http->get('https://api.zelta.app/v1/resource');
if ($response->getStatusCode() === 402) {
    // Parse payment requirements...
    // Construct payment...
    // Retry with proof...
}

// After: SDK handles it
$response = $client->get('/v1/resource');
// Done -- 402 negotiation is automatic
```

## Support

| Channel | Contact |
|---------|---------|
| Technical support | developers@zelta.app |
| Partnership inquiries | partnerships@zelta.app |
| Status page | https://status.zelta.app |
| SDK issues | https://github.com/FinAegis/core-banking-prototype-laravel/issues |
| Documentation | https://zelta.app/developers |
