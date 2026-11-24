# Webhook Security Implementation

## Overview

This document explains how FinAegis checks the signature of incoming webhooks. Webhook endpoints cannot use CSRF tokens, so each one must prove its sender in another way. Earlier versions exempted broad paths from CSRF protection without checking signatures. The current design checks a signature or a shared secret on every provider webhook.

There are two ways the check is done. Older provider webhooks use the shared `ValidateWebhookSignature` middleware. Newer integrations verify the signature inside their own verifier class or controller.

## Security Architecture

### Middleware Implementation

The `ValidateWebhookSignature` middleware (alias `webhook.signature`) checks these providers:

| Provider | Scheme |
|----------|--------|
| Stripe | HMAC-SHA256 with a timestamp check (`Stripe-Signature`) |
| Coinbase Commerce | HMAC-SHA256 (`X-CC-Webhook-Signature`) |
| Paysera | HMAC-SHA256 (`X-Paysera-Signature`) |
| Santander | HMAC-SHA512 with a timestamp check (`X-Santander-Signature`, `X-Santander-Timestamp`) |
| Open Banking | Single-use OAuth `state` value kept in the cache |
| Marqeta | Basic authentication and HMAC-SHA256 (`X-Marqeta-Webhook-Signature`) |

### Verification Outside the Middleware

These webhooks verify the sender in their own code:

| Endpoint | Check |
|----------|-------|
| `POST /api/v1/webhooks/bridge` | `BridgeWebhookVerifier` checks an RSA-SHA256 signature in `X-Webhook-Signature` (`t=<unix_ms>,v0=<base64>`) against `BRIDGE_WEBHOOK_PUBLIC_KEY`. A legacy HMAC scheme keyed by `BRIDGE_WEBHOOK_SECRET` remains as a fallback. |
| `POST /api/v1/webhooks/lepo` | `LepoWebhookVerifier` checks an HMAC-SHA256 of the raw body in `X-LepoFinance-Signature`. |
| `POST /api/v1/webhooks/fincard` | `FinCardWebhookVerifier` checks a base64 RSA-SHA256 signature of the raw body against FinCard's public key. |
| `POST /api/webhooks/helius/solana` | The controller compares the secret in the `Authorization` header. |
| `POST /api/webhooks/alchemy/address-activity` | The controller checks `X-Alchemy-Signature` with the signing key stored in `webhook_endpoints`. |
| `POST /api/webhooks/hyperswitch`, `POST /api/webhooks/stripe/kyc`, `POST /api/webhooks/stripe/subscriptions`, `POST /api/webhooks/visa-cli/payment` | Each controller verifies the provider's signature itself. |
| `POST /api/v1/webhooks/vertexsms/dlr` | The controller accepts either a URL token (`?t=...`) or an HMAC-SHA256 signature in `X-VertexSMS-Signature`. |
| `POST /api/webhooks/apple/notifications`, `POST /api/webhooks/google/play` | The subscription controllers verify the store's signed notification. Apple notifications are checked against the Apple Root CA G3 certificate. |

Several of these webhooks also deduplicate events through the `processed_webhook_events` table.

### Key Features

1. Each provider has its own check that matches its signature scheme.
2. The Stripe and Santander checks reject timestamps older than 5 minutes, which stops replayed requests.
3. Signatures are compared with `hash_equals()`, which prevents timing attacks.
4. A failed check returns HTTP 403 with an error message.

## Configuration

### Environment Variables

Add these webhook secrets to your `.env` file:

```env
# Stripe
STRIPE_WEBHOOK_SECRET=whsec_your_stripe_webhook_secret

# Coinbase Commerce
COINBASE_COMMERCE_WEBHOOK_SECRET=your_coinbase_webhook_secret

# Paysera
PAYSERA_WEBHOOK_SECRET=your_paysera_webhook_secret

# Santander
SANTANDER_WEBHOOK_SECRET=your_santander_webhook_secret
```

### Route Protection

These routes use the middleware:

```php
// API routes
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->middleware('webhook.signature:stripe');

Route::post('/api/webhooks/coinbase-commerce', [CoinbaseWebhookController::class, 'handleWebhook'])
    ->middleware('webhook.signature:coinbase');

Route::post('/api/webhooks/custodian/paysera', [CustodianWebhookController::class, 'paysera'])
    ->middleware('webhook.signature:paysera');

Route::post('/api/webhooks/custodian/santander', [CustodianWebhookController::class, 'santander'])
    ->middleware('webhook.signature:santander');

// The whole /api/webhooks/card-issuer/* group (authorization, settlement, transaction)
Route::post('/api/webhooks/card-issuer/authorization', [JitFundingWebhookController::class, 'handleAuthorization'])
    ->middleware(['api.rate_limit:webhook', 'webhook.signature:marqeta']);
```

The Stripe route overrides Laravel Cashier's default webhook route in `WebhookServiceProvider`.

## Implementation Details

### Stripe Signature Validation

Stripe sends a signature header that carries a timestamp and one or more signatures:

```php
// Signature format: t=timestamp,v1=signature
$elements = explode(',', $signature);
$timestamp = null;
$signatures = [];

foreach ($elements as $element) {
    $parts = explode('=', $element, 2);
    if ($parts[0] === 't') {
        $timestamp = $parts[1];
    } elseif ($parts[0] === 'v1') {
        $signatures[] = $parts[1];
    }
}

// Validate timestamp (5-minute tolerance)
if (abs(time() - (int) $timestamp) > 300) {
    return false;
}

// Compute expected signature
$signedPayload = $timestamp . '.' . $payload;
$expectedSignature = hash_hmac('sha256', $signedPayload, $secret);
```

### Coinbase Commerce Validation

Coinbase Commerce signs the raw body with HMAC-SHA256:

```php
$expectedSignature = hash_hmac('sha256', $payload, $secret);
return hash_equals($expectedSignature, $signature);
```

### Santander Validation

Santander signs the timestamp and the body together with HMAC-SHA512:

```php
$dataToSign = $timestamp . '.' . $payload;
$expectedSignature = hash_hmac('sha512', $dataToSign, $secret);
return hash_equals($expectedSignature, $signature);
```

## Testing

### Feature Tests

The feature tests check that a valid signature is accepted for each provider. They check that malformed or wrong signatures are rejected and that Stripe and Santander reject old timestamps. They also check that requests without a signature header are rejected and that OAuth callbacks validate the `state` value.

The verifier classes have their own tests in `tests/Feature/Api/Bridge/BridgeWebhookVerifierTest.php`, `tests/Feature/Api/FinCard/FinCardWebhookVerifierTest.php` and `tests/Feature/CardIssuance/LepoWebhookTest.php`.

### Running Tests

```bash
# Run all webhook validation tests
./vendor/bin/pest --filter ValidateWebhookSignature

# Run specific test files
./vendor/bin/pest tests/Feature/Middleware/ValidateWebhookSignatureTest.php
./vendor/bin/pest tests/Feature/Api/Bridge/BridgeWebhookVerifierTest.php
```

## Security Best Practices

1. Never log webhook secrets. The middleware does not log them.
2. Rotate webhook secrets regularly.
3. Log failed validations and alert on them.
4. Receive webhooks over HTTPS only.
5. Validate the payload structure after the signature check. Store only whitelisted fields, never the raw payload.

## Migration Guide

### From Manual Validation

To move a provider that fits one of the middleware schemes from controller code to the middleware, follow four steps:

1. Remove the manual validation code from the controller.
2. Add the middleware to the route.
3. Make sure the webhook secret is configured.
4. Test with the provider.

Providers with their own scheme, such as Bridge, Lepo and FinCard, keep their verifier class.

### CSRF Token Updates

The CSRF exemptions in `App\Http\Middleware\VerifyCsrfToken` list specific endpoints instead of broad paths:

```php
// Before (vulnerable)
protected $except = [
    'stripe/*',      // All Stripe paths exempted
    'webhook/*',     // All webhook paths exempted
];

// After (secure)
protected $except = [
    'stripe/webhook',        // Only specific webhook endpoint
    'api/webhooks/*',        // Only API webhook endpoints
    'paysera/callback',      // OAuth callbacks use state validation
    'openbanking/callback',
];
```

## Troubleshooting

### Common Issues

1. **403 Invalid Signature**
   - Check the webhook secret or public key in the configuration.
   - Check that the signature header name matches the provider.
   - Make sure nothing changed the raw body before the check, such as a proxy.

2. **Timestamp Errors**
   - Check that the server clock is synchronized.
   - Check the timestamp format the provider sends.
   - The tolerance is 5 minutes.

3. **Missing Configuration**
   - Make sure all webhook secrets are in `.env`.
   - Clear the config cache with `php artisan config:clear`.
   - Run `php artisan ops:verify-env` before a deploy. It checks the Bridge and Lepo webhook settings, among others.

### Debug Mode

For debugging, you can temporarily log signature details (never in production):

```php
Log::debug('Webhook validation', [
    'provider' => $provider,
    'has_signature' => !empty($signature),
    'has_secret' => !empty($secret),
    'timestamp_valid' => $timestampValid ?? null,
]);
```

## References

- [Stripe Webhook Security](https://stripe.com/docs/webhooks/signatures)
- [Coinbase Commerce Webhooks](https://commerce.coinbase.com/docs/api/#webhooks)
- [OWASP Webhook Security](https://cheatsheetseries.owasp.org/cheatsheets/Webhook_Security_Cheat_Sheet.html)