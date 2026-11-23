# Developer Integration Documentation

This folder is for developers who integrate with the API of a FinAegis deployment. FinAegis is an open-source reference platform, so every example points at a deployment you run yourself.

## Contents

- **[API-INTEGRATION-GUIDE.md](API-INTEGRATION-GUIDE.md)** explains authentication, the version 2 endpoints, webhooks and error handling.
- **[API-EXAMPLES.md](API-EXAMPLES.md)** has longer examples in several languages.
- **[SDK-GUIDE.md](SDK-GUIDE.md)** describes the published SDKs, the CLI and the MCP connector.
- **[AI-AGENT-COMPATIBILITY.md](AI-AGENT-COMPATIBILITY.md)** explains how coding agents find project guidance in the `AGENTS.md` files.
- **[Postman collection](../../public/postman/Zelta-API.postman_collection.json)** is generated from the OpenAPI spec. See Testing below.

## Purpose

These documents show how to sign in and call the API. They also show how to receive and verify webhooks, how to use the SDKs and how to test endpoints with Postman.

## Quick Start

### 1. Authentication

The API uses Laravel Sanctum tokens. You get a token by signing in with an email and password:

```bash
# Get an access token (returned as data.access_token)
curl -X POST https://your-finaegis-host.example/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com", "password": "password"}'
```

### 2. Making API Calls

```bash
# Get account balance
curl -X GET https://your-finaegis-host.example/api/accounts/{uuid}/balance \
  -H "Authorization: Bearer {token}"
```

### 3. Webhook Integration

The platform signs each outgoing webhook with HMAC-SHA256. The `X-Webhook-Signature` header carries the value `sha256=<hex digest>`:

```php
// Verify webhook signature
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

if (!hash_equals($expectedSignature, $signature)) {
    throw new Exception('Invalid webhook signature');
}
```

## Integration Features

### Core Banking APIs

The core APIs manage accounts, balances, transactions and transfers between accounts.

### Multi-Asset Support

The asset APIs manage assets, exchange rates and currency conversion. Basket operations belong to the Basket demo module.

### GCU demo (simulated conversions)
_The Global Currency Unit (GCU) is a software demonstration: it is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" are simulated._

The GCU demo endpoints give simulated buy and sell conversions, price quotes, trading limits and market data.

### CGO demo module (reference software; not an offer of securities)

The CGO demo module shows an investment intake flow. It covers creating an investment, payment processing, identity checks, agreement downloads and refund requests.

### Governance APIs

The governance APIs list polls, accept votes, return results and calculate voting power.

The Basket, Cgo and Governance modules are switched off on the production site through `MODULES_DISABLED`. They run in demo mode and on deployments that enable them.

## SDKs Available

### Official SDKs

| Language | Package | Source |
|----------|---------|--------|
| PHP | `finaegis/php-sdk` (Packagist) | `sdks/php/` |
| JavaScript and TypeScript | `@finaegis/sdk` (npm) | `sdks/javascript/` |
| Python | `finaegis` (PyPI) | `sdks/python/` |
| PHP payment SDK for x402 and MPP | `finaegis/payment-sdk` (Packagist) | `packages/zelta-sdk/` |
| Command-line tool | `@finaegis/cli` (npm) | `packages/zelta-cli/` |
| MCP connector | `@finaegis/mcp` (npm) | `packages/finaegis-mcp-stdio/` |

[SDK-GUIDE.md](SDK-GUIDE.md) shows how to install and use each one.

### Community SDKs

Community SDKs are welcome. Please follow the SDK guide when you build one.

## Testing

### Postman Collection

You can import the Postman collection to test the API quickly:

1. Open Postman.
2. Import `public/postman/Zelta-API.postman_collection.json`.
3. Set the environment variables `base_url` and `token`.
4. Send requests.

The collection is **generated** from the OpenAPI spec, so do not edit it by hand.
To regenerate it after API changes, run:

```bash
APP_URL=http://localhost php artisan l5-swagger:generate
npx openapi-to-postmanv2 -s storage/api-docs/api-docs.json \
  -o public/postman/Zelta-API.postman_collection.json -p
```

### Test Environment

Use your own deployment as the base URL, for example `https://your-finaegis-host.example`.
Rate limits depend on the endpoint type. Queries allow 100 requests per minute and transactions allow 30 per minute. [API-INTEGRATION-GUIDE.md](API-INTEGRATION-GUIDE.md) lists all of them.

## Support

### Developer Resources

- The interactive API documentation is served by your deployment at `/api/documentation`.
- The GraphQL endpoint is served at `/graphql`.
- Questions and bug reports go to the GitHub issues of `FinAegis/core-banking-prototype-laravel`.

### Security Issues

Please report security issues privately through GitHub Security Advisories, as described in `SECURITY.md`.
