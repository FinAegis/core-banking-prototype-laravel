# Webhook Security Implementation

## Overview

This document describes the webhook signature validation implementation that protects all webhook endpoints in the FinAegis Core Banking Platform. This security enhancement addresses the critical vulnerability where webhook endpoints were broadly exempted from CSRF protection without proper signature validation.

## Security Architecture

### Middleware Implementation

The `ValidateWebhookSignature` middleware provides centralized signature validation for all webhook providers:

- **Stripe**: HMAC-SHA256 with timestamp validation
- **Coinbase Commerce**: HMAC-SHA256 signature
- **Paysera**: HMAC-SHA256 signature
- **Santander**: HMAC-SHA512 with timestamp
- **Open Banking**: OAuth state parameter validation

### Key Features

1. **Provider-Specific Validation**: Each webhook provider has its own validation logic matching their signature schemes
2. **Timestamp Protection**: Prevents replay attacks by validating timestamps (5-minute tolerance)
3. **Constant-Time Comparison**: Uses `hash_equals()` to prevent timing attacks
4. **Graceful Failure**: Returns 403 with error message on validation failure

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

All webhook routes are automatically protected by the middleware:

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
```

## Implementation Details

### Stripe Signature Validation

Stripe uses a complex signature format with timestamps:

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

Simple HMAC-SHA256 validation:

```php
$expectedSignature = hash_hmac('sha256', $payload, $secret);
return hash_equals($expectedSignature, $signature);
```

### Santander Validation

HMAC-SHA512 with timestamp included in the signature:

```php
$dataToSign = $timestamp . '.' . $payload;
$expectedSignature = hash_hmac('sha512', $dataToSign, $secret);
return hash_equals($expectedSignature, $signature);
```

## Testing

### Feature Tests

The implementation includes comprehensive feature tests that validate:

1. **Valid Signatures**: Each provider's valid signature format is accepted
2. **Invalid Signatures**: Malformed or incorrect signatures are rejected
3. **Expired Timestamps**: Old timestamps are rejected (Stripe and Santander)
4. **Missing Headers**: Requests without signature headers are rejected
5. **State Validation**: OAuth callbacks validate state parameters

### Unit Tests

Unit tests cover:

1. **Signature Algorithms**: Each provider's signature computation
2. **Error Handling**: Missing configuration, unknown providers
3. **Edge Cases**: Empty payloads, malformed headers

### Running Tests

```bash
# Run all webhook validation tests
php artisan test --filter ValidateWebhookSignature

# Run specific test suite
php artisan test tests/Feature/Middleware/ValidateWebhookSignatureTest.php
php artisan test tests/Unit/Middleware/ValidateWebhookSignatureTest.php
```

## Security Best Practices

1. **Never Log Secrets**: The middleware never logs webhook secrets
2. **Rotate Secrets Regularly**: Update webhook secrets periodically
3. **Monitor Failed Validations**: Log and alert on validation failures
4. **Use HTTPS Only**: Webhooks should only be received over HTTPS
5. **Validate Payloads**: Always validate webhook payload structure after signature validation

## Migration Guide

### From Manual Validation

If you were manually validating signatures in controllers:

1. Remove manual validation code from controllers
2. Add the middleware to your routes
3. Ensure webhook secrets are configured
4. Test with your webhook providers

### CSRF Token Updates

The CSRF exemptions have been updated from broad path exemptions to specific endpoints:

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
   - Check webhook secret configuration
   - Verify signature header name matches provider
   - Ensure payload hasn't been modified

2. **Timestamp Errors**
   - Check server time synchronization
   - Verify timestamp format from provider
   - Adjust tolerance if needed (default: 5 minutes)

3. **Missing Configuration**
   - Ensure all webhook secrets are in `.env`
   - Clear config cache: `php artisan config:clear`

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