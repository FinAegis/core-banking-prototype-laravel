# API Documentation

This directory contains REST API documentation for the FinAegis platform.

## Contents

- **[REST_API_REFERENCE.md](REST_API_REFERENCE.md)** - Consolidated REST API reference with all endpoints
- **[API_VOTING_ENDPOINTS.md](API_VOTING_ENDPOINTS.md)** - Specific documentation for voting and governance APIs
- **[WEBHOOK_INTEGRATION.md](WEBHOOK_INTEGRATION.md)** - Webhook configuration and integration guide
- **[BIAN_API_DOCUMENTATION.md](BIAN_API_DOCUMENTATION.md)** - BIAN standard API documentation
- **[OPENAPI_COVERAGE_100_PERCENT.md](OPENAPI_COVERAGE_100_PERCENT.md)** - OpenAPI specification coverage analysis
- **[SERVICES_REFERENCE.md](SERVICES_REFERENCE.md)** - Service layer reference documentation

## Purpose

These documents provide:
- Complete API endpoint reference
- Request/response examples
- Authentication requirements
- Error handling patterns
- Rate limiting information
- Webhook integration guidance
- API best practices

## Current API Status (September 2024)

### Recently Added API Endpoints
- ✅ **CGO Investment APIs**: Complete investment platform endpoints
  - `POST /api/cgo/investments` - Create investment
  - `GET /api/cgo/investments/{uuid}` - Get investment details
  - `POST /api/cgo/payments/stripe/checkout` - Create Stripe checkout
  - `POST /api/cgo/payments/coinbase/charge` - Create Coinbase charge
  - `POST /api/cgo/webhooks/stripe` - Stripe webhook handler
  - `POST /api/cgo/webhooks/coinbase` - Coinbase webhook handler
  
- ✅ **GCU Trading APIs**: Buy/sell operations
  - `POST /api/gcu/buy` - Buy GCU
  - `POST /api/gcu/sell` - Sell GCU
  - `GET /api/gcu/price` - Get current GCU price
  - `GET /api/gcu/balance` - Get user's GCU balance

- ✅ **Voting System APIs**: Democratic governance
  - `GET /api/polls` - List polls
  - `POST /api/polls` - Create poll
  - `POST /api/polls/{uuid}/vote` - Submit vote
  - `GET /api/polls/{uuid}/results` - Get results

- ✅ **Authentication Enhancements**
  - `POST /api/auth/2fa/enable` - Enable 2FA
  - `POST /api/auth/2fa/verify` - Verify 2FA code
  - `GET /api/auth/oauth/redirect` - OAuth redirect
  - `GET /api/auth/oauth/callback` - OAuth callback

### API Coverage
- **Total Controllers**: 40+
- **Documented Endpoints**: 95%
- **OpenAPI Coverage**: 100% (see OPENAPI_COVERAGE_100_PERCENT.md)
- **Test Coverage**: 88%