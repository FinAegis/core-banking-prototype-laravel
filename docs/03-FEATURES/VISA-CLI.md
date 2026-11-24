# Card-Payment CLI Adapter (visa-cli)

**Domain:** `app/Domain/VisaCli/`
**Status:** Beta
**Since:** v6.2.0

## Overview

This module wraps the third-party [visa-cli](https://visacli.sh/) beta tool. The tool is a third-party project. It is not affiliated with or endorsed by Visa.
The module lets AI agents and developers pay by card from code, with cards the person already holds.
It is an integration adapter for a third-party tool.

*Named third-party providers: Integration adapters for third-party APIs; no partnership or endorsement implied.*

The module is off by default (`VISACLI_ENABLED=false`) and uses a simulated client unless `VISACLI_DRIVER=process` is set.

The module has five parts.
It offers two tools for AI agents in the platform's internal AI tool registry.
It can pay partner invoices through a payment gateway.
It copies enrolled cards into the CardIssuance domain.
It has Artisan commands for operators.
It records every payment as stored events, which gives an audit trail.

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

| Driver | Behaviour |
|--------|-----------|
| `demo` (default) | A simulated client that keeps its state in the cache, for development and tests |
| `process` | Runs the `visa-cli` binary through Symfony Process |

## Configuration

```env
VISACLI_ENABLED=false
VISACLI_DRIVER=demo
VISACLI_BINARY_PATH=visa-cli
VISACLI_GITHUB_TOKEN=
VISACLI_DAILY_LIMIT=10000       # $100.00/day in cents
VISACLI_PER_TX_LIMIT=1000       # $10.00/tx in cents
VISACLI_AUTO_PAY=false
VISACLI_WEBHOOK_SECRET=
```

## AI agent tools

These tools live in `app/Domain/AI/MCP/Tools/VisaCli/` and belong to the internal AI tool registry.
They are not part of the public MCP server at `mcp.zelta.app`, whose catalog is `config/mcp.php`.

| Tool | Category | Cacheable | Description |
|------|----------|-----------|-------------|
| `visacli.payment` | visacli | No | Execute a card payment via the third-party Visa CLI tool, with spending limit enforcement |
| `visacli.cards` | visacli | Yes (300s) | List enrolled cards available for payments |

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/partner/v1/billing/invoices/{id}/pay` | Pay a partner invoice through Visa CLI |
| POST | `/api/webhooks/visa-cli/payment` | Inbound payment status webhook, verified with HMAC |

The invoice route belongs to the FinancialInstitution partner API.
The production Zelta configuration lists `FinancialInstitution` in `MODULES_DISABLED`, so the invoice route is off on the production site.

## Artisan Commands

| Command | Description |
|---------|-------------|
| `visa:status` | Show initialization state, enrolled cards, recent payments |
| `visa:enroll --user=` | Interactive card enrollment (non-production only) |
| `visa:pay {url} --amount= --card= --agent=` | Execute payment with confirmation |

## Spending Limits

Each agent has its own limits in `visa_cli_spending_limits`.
The daily limit caps the total spend in a 24-hour period and resets on its own.
The per-transaction limit caps a single payment.
The auto-pay flag says whether the agent may pay without a person's approval.

The limit check locks the row with `lockForUpdate()`, so two payments at the same time cannot both pass the check.

## Event Sourcing

All payment events extend `ShouldBeStored` and are registered in `config/event-sourcing.php`:

- `visacli_payment_initiated`
- `visacli_payment_completed`
- `visacli_payment_failed`
- `visacli_card_enrolled`
- `visacli_card_removed`

## Security

Payment URLs are checked against a short list of internal and cloud metadata addresses to reduce server-side request forgery. The list covers `localhost`, loopback, `10.*`, `192.168.*` and the cloud metadata hosts, but not every private range.
Card ids may only contain letters, digits, underscores and dashes.
Webhooks are verified with an HMAC SHA-256 signature. A webhook whose timestamp is more than 5 minutes off is rejected.
In production a webhook is always rejected when `VISACLI_WEBHOOK_SECRET` is not set.
GitHub tokens (`ghp_...`) are removed from the binary's error output before it is logged.
Spending is checked and reserved in one step, so a budget cannot be overrun.

## Database Tables

| Table | Contents |
|-------|----------|
| `visa_cli_enrolled_cards` | Enrolled cards, linked to a user, with their status |
| `visa_cli_payments` | Payment records, optionally linked to an invoice |
| `visa_cli_spending_limits` | Agent budgets, in the same shape as the x402 limits |

## Testing

52 tests cover the module.
Unit tests cover the demo client, the payment, spending limit and gateway services and the two agent tools.
Feature tests run the Artisan commands.
Integration tests run a full payment and an invoice payment.
