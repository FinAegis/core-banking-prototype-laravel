# Partner Integration Guide

## Overview

The Zelta SDK is a client for paid APIs. When a server answers with HTTP 402 Payment Required, the SDK pays and retries the request. It supports x402, which settles in USDC on-chain, and the Machine Payments Protocol (MPP), which supports several payment rails. Settlement design is under discussion; see [VERTEXSMS_X402_INTEGRATION §6](partners/VERTEXSMS_X402_INTEGRATION.md#6-money-flow). A partner integrates the SDK once and can then use every supported rail without handling the protocols directly.

The SDK gives partners these things:

- It handles payment challenges automatically when a server returns 402.
- It supports x402 (on-chain USDC) and MPP (Stripe, Tempo, Lightning, Card) in one client.
- It returns every response as a `PaymentResult` that says whether a payment was made.

Integration adapters for third-party APIs; no partnership or endorsement implied.

## Prerequisites

- PHP 8.4+ (required by Zelta SDK v1.0.0)
- Composer 2.x
- Zelta API credentials (contact partnerships@zelta.app)
- A signer for x402 payments that implements `Zelta\Contracts\SignerInterface`

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
    apiKey: 'fak_your_api_key',
    autoPay: true,
    timeoutSeconds: 30,
);

$client = new ZeltaClient(
    config: $config,
    payment: new AutoDetectHandler(
        // x402 network preference order, as CAIP-2 ids
        new X402PaymentHandler($signer, ['eip155:8453', 'solana:mainnet', 'eip155:1']),
        // MPP rail preference order
        new MppPaymentHandler(['stripe', 'x402', 'tempo', 'lightning']),
    ),
);
```

### 3. Make a paid API call

```php
$result = $client->get('/v1/premium/resource');
// SDK automatically handles 402 payment negotiation:
// 1. Server returns 402 with payment requirements
// 2. SDK detects protocol (x402 or MPP) from response headers
// 3. SDK completes payment and retries the request once
// 4. You receive the successful response
```

### 4. Handle the response

```php
$data = $result->body;          // decoded JSON body
$status = $result->statusCode;  // HTTP status code
$paid = $result->paid;          // true when the SDK paid for this call
```

## Payment Protocols

### x402 (USDC On-Chain)

The x402 protocol pays for a request directly on-chain in USDC, using the HTTP 402 status code. When a server returns 402 with x402 headers, the SDK builds and signs a payment, then retries the original request with proof of payment.

| Property | Detail |
|----------|--------|
| **Supported networks** | Base, Ethereum, Avalanche and Solana, plus their test networks (`config/x402.php`) |
| **Settlement** | Facilitator-based, typically < 30 seconds |
| **Token** | USDC (native on each chain) |
| **Best for** | Crypto-native partners, high-value transactions, programmatic M2M payments |

Configuration endpoint: `/.well-known/x402-configuration` on `api.zelta.app` (`/api/.well-known/x402-configuration` on the main domain)

### MPP (Machine Payments Protocol)

MPP puts several payment rails behind one negotiation flow. The server lists the rails it accepts, and the SDK picks one in the order the partner configured.

| Property | Detail |
|----------|--------|
| **Rails** | Stripe, Tempo, Lightning, Card, x402 (fallback) |
| **Settlement** | Varies by rail: instant (Lightning), 1-3 business days (Card/Stripe) |
| **Currencies** | USD, EUR, GBP + USDC via x402 fallback |
| **Best for** | Fiat-accepting partners, consumer-facing products, mass market |

Configuration endpoint: `/.well-known/mpp-configuration` on `api.zelta.app` (`/api/.well-known/mpp-configuration` on the main domain)

MPP is off by default (`MPP_ENABLED=false`). See [MACHINE_PAYMENTS_PROTOCOL.md](MACHINE_PAYMENTS_PROTOCOL.md).

### Choosing a Protocol

| Scenario | Recommended |
|----------|-------------|
| Crypto-native users, API-to-API payments | x402 |
| Consumer checkout, subscription billing | MPP |
| Mixed audience, maximum reach | AutoDetectHandler (both) |

## Webhooks

Partners cannot register webhooks themselves. Webhooks are platform configuration, so only a platform administrator can create them, through `POST /api/v2/webhooks`. `GET /api/v2/webhooks/events` lists the events a webhook can subscribe to. They cover accounts, transactions, transfers, baskets, polls, votes and exchange rates. There are no payment or subscription events for x402 or MPP payments.

If an administrator sets up a webhook for you, each delivery carries these headers:

| Header | Content |
|--------|---------|
| `X-Webhook-ID` | The webhook's id |
| `X-Webhook-Event` | The event type |
| `X-Webhook-Delivery` | The delivery's id |
| `X-Webhook-Signature` | `sha256=` followed by the hex HMAC-SHA256 of the raw body, keyed by the webhook secret |

Always verify the signature before processing a delivery:

```php
$payload = file_get_contents('php://input');
$receivedSignature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

if (! hash_equals($expectedSignature, $receivedSignature)) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
// Process the verified event...
```

Use `hash_equals()` for timing-safe comparison to prevent timing attacks.

A failed delivery is retried after 1 minute, 5 minutes and 15 minutes, and then every 15 minutes for up to 24 hours. Your endpoint must return a 2xx status code within the webhook's timeout, which is 30 seconds by default. A non-2xx response or a timeout triggers a retry.

## Idempotency

All payment-initiating requests should include an idempotency key to prevent duplicate charges:

```php
$result = $client->post('/v1/payments', [
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

The API limits requests per minute by endpoint type. These are the defaults in `config/rate_limiting.php`:

| Endpoint Type | Default limit |
|---------------|---------------|
| Queries | 100 requests/minute |
| Transactions | 30 requests/minute |
| Authentication | 3 requests/minute |
| Public endpoints | 60 requests/minute |

A request made with a Banking-as-a-Service partner key is limited by the partner's tier instead, at 60, 300 or 1,000 requests per minute. That figure is multiplied by a factor for the endpoint type, for example 0.5 for transactions.

When a limit is reached, the API returns HTTP 429 with a `Retry-After` header in seconds. The SDK does not retry a 429. It returns a `PaymentResult` with `statusCode` 429 and does not expose the response headers, so implement backoff in your application:

```php
$result = $client->get('/v1/resource');

if ($result->statusCode === 429) {
    sleep($backoffSeconds);
    // Retry...
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

The SDK throws `PaymentRequiredException` when it cannot handle a 402 response, for example when no handler is configured or `autoPay` is false. It throws `PaymentFailedException` when the retry after payment still fails.

## Testing

Outside production, the payment rail adapters return simulated responses, so tests need no external payment keys. Ask the Zelta team for test credentials and a test base URL before you start.

```php
$testConfig = new PaymentConfig(
    baseUrl: 'https://your-test-host.example',
    apiKey: 'fak_your_test_key',
);

$testClient = new ZeltaClient(config: $testConfig, payment: $handler);
```

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
$result = $client->get('/v1/resource');
// Done -- 402 negotiation is automatic
```

## Support

| Channel | Contact |
|---------|---------|
| Technical support | developers@zelta.app |
| Partnership inquiries | partnerships@zelta.app |
| SDK issues | https://github.com/FinAegis/core-banking-prototype-laravel/issues |
| Documentation | https://zelta.app/developers |
