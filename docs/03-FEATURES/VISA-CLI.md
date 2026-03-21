# Visa CLI Integration

**Domain:** `app/Domain/VisaCli/`
**Status:** Beta
**Since:** v6.2.0

## Overview

Visa CLI enables AI agents and developers to make programmatic Visa card payments without managing API keys directly. The integration wraps the [Visa CLI](https://visacli.sh/) beta tool and provides:

- **MCP tools** for AI agent autonomous payments
- **Payment gateway** for partner invoice collection
- **Card enrollment bridge** to the CardIssuance domain
- **Artisan CLI commands** for operational management
- **Event-sourced audit trail** for all payment activity

## Architecture

The VisaCli domain follows the same DDD patterns as x402 and CardIssuance:

```
app/Domain/VisaCli/
  Contracts/          VisaCliClientInterface, VisaCliPaymentGatewayInterface
  DataObjects/        PaymentRequest, PaymentResult, Card, Status
  Enums/              VisaCliPaymentStatus, VisaCliCardStatus
  Events/             PaymentInitiated, Completed, Failed, CardEnrolled, Removed
  Exceptions/         VisaCliException, PaymentException, EnrollmentException
  Listeners/          SyncVisaCliCardToCardIssuance
  Models/             VisaCliEnrolledCard, VisaCliPayment, VisaCliSpendingLimit
  Services/           DemoVisaCliClient, VisaCliProcessClient, PaymentService,
                      SpendingLimitService, CardEnrollmentService, PaymentGatewayService
```

**Drivers:**
- `demo` (default) — Cache-based mock for development and testing
- `process` — Calls the real `visa-cli` binary via Symfony Process

## Configuration

```env
VISACLI_ENABLED=false
VISACLI_DRIVER=demo
VISACLI_BINARY_PATH=visa-cli
VISACLI_GITHUB_TOKEN=
VISACLI_DAILY_LIMIT=10000       # $100.00/day in cents
VISACLI_PER_TX_LIMIT=1000       # $10.00/tx in cents
VISACLI_WEBHOOK_SECRET=
```

## MCP Tools

| Tool | Category | Cacheable | Description |
|------|----------|-----------|-------------|
| `visacli.payment` | visacli | No | Execute Visa card payment with spending limit enforcement |
| `visacli.cards` | visacli | Yes (300s) | List enrolled cards available for payments |

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/partner/v1/billing/invoices/{id}/pay` | Pay partner invoice via Visa CLI |
| POST | `/webhooks/visa-cli/payment` | Inbound payment status webhook (HMAC verified) |

## Artisan Commands

| Command | Description |
|---------|-------------|
| `visa:status` | Show initialization state, enrolled cards, recent payments |
| `visa:enroll --user=` | Interactive card enrollment (non-production only) |
| `visa:pay {url} --amount= --card= --agent=` | Execute payment with confirmation |

## Spending Limits

Each agent has configurable limits stored in `visa_cli_spending_limits`:

- **Daily limit** — Maximum total spend per 24h period (auto-resets)
- **Per-transaction limit** — Maximum single payment amount
- **Auto-pay flag** — Whether agent can pay without human approval

Limits use atomic row-level locking (`lockForUpdate()`) to prevent race conditions on concurrent payments.

## Event Sourcing

All payment events implement `ShouldBeStored` and are registered in `config/event-sourcing.php`:

- `visacli_payment_initiated`
- `visacli_payment_completed`
- `visacli_payment_failed`
- `visacli_card_enrolled`
- `visacli_card_removed`

## Security

- **SSRF prevention:** Payment URLs validated against blocked internal/metadata addresses
- **Input sanitization:** Card IDs validated against alphanumeric pattern
- **Webhook HMAC:** SHA-256 signature verification with replay protection (5-min window)
- **Production enforcement:** Unsigned webhooks rejected in production environment
- **Log redaction:** GitHub tokens and sensitive data stripped from log output
- **Atomic spending:** Check-and-reserve pattern prevents budget overruns

## Database Tables

- `visa_cli_enrolled_cards` — Enrolled cards with user FK and status tracking
- `visa_cli_payments` — Payment records with optional invoice FK
- `visa_cli_spending_limits` — Agent budget controls (mirrors x402 pattern)

## Testing

52 tests covering:
- Unit: DemoClient, PaymentService, SpendingLimitService, PaymentGateway, MCP tools
- Feature: Artisan command smoke tests
- Integration: End-to-end payment flow, invoice collection
