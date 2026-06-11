# Developer Integration Documentation

This directory contains documentation for developers integrating with the FinAegis platform APIs.

## Contents

- **[API-INTEGRATION-GUIDE.md](API-INTEGRATION-GUIDE.md)** - Complete guide for API integration
- **[API-EXAMPLES.md](API-EXAMPLES.md)** - Practical API usage examples
- **[SDK-GUIDE.md](SDK-GUIDE.md)** - SDK usage guide for multiple programming languages
- **[Postman collection](../../public/postman/Zelta-API.postman_collection.json)** - generated from the OpenAPI spec (see Testing below)

## Purpose

These documents help external developers:
- Integrate with FinAegis APIs
- Understand authentication and authorization
- Handle webhooks and events
- Use SDKs in various languages
- Test API endpoints with Postman
- Implement best practices

## Quick Start

### 1. Authentication
All API requests require authentication using Laravel Sanctum tokens:

```bash
# Get auth token
curl -X POST https://api.finaegis.org/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'
```

### 2. Making API Calls
```bash
# Get account balance
curl -X GET https://api.finaegis.org/api/accounts/{uuid}/balance \
  -H "Authorization: Bearer {token}"
```

### 3. Webhook Integration
```php
// Verify webhook signature
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

if (!hash_equals($signature, $expectedSignature)) {
    throw new Exception('Invalid webhook signature');
}
```

## Integration Features

### Core Banking APIs
- Account management
- Transaction processing
- Balance inquiries
- Transfer operations

### Multi-Asset Support
- Asset management
- Exchange rates
- Currency conversion
- Basket operations

### GCU Trading
- Buy/sell operations
- Price quotes
- Trading limits
- Market data

### CGO Investment Platform
- Investment creation
- Payment processing
- KYC verification
- Agreement downloads
- Refund requests

### Governance APIs
- Poll listing
- Vote submission
- Results retrieval
- Voting power calculation

## SDKs Available

### Official SDKs
- **PHP SDK**: Full feature support
- **JavaScript/Node.js SDK**: Coming soon
- **Python SDK**: Coming soon
- **Java SDK**: Coming soon

### Community SDKs
Community-contributed SDKs are welcome! Please follow our SDK guidelines.

## Testing

### Postman Collection
Import the Postman collection for quick API testing:
1. Open Postman
2. Import `public/postman/Zelta-API.postman_collection.json`
3. Set environment variables for `base_url` and `token`
4. Start testing!

The collection is **generated** from the OpenAPI spec — do not edit it by hand.
To regenerate after API changes:

```bash
APP_URL=http://localhost php artisan l5-swagger:generate
npx openapi-to-postmanv2 -s storage/api-docs/api-docs.json \
  -o public/postman/Zelta-API.postman_collection.json -p
```

### Test Environment
- Base URL: `https://test-api.finaegis.org`
- Test credentials available upon request
- Rate limits: 100 requests per minute

## Support

### Developer Resources
- API Documentation: https://api.finaegis.org/documentation
- Status Page: https://status.finaegis.org
- Developer Forum: https://developers.finaegis.org

### Contact
- Technical Support: developers@finaegis.org
- Security Issues: security@finaegis.org