# MCP Tool Reference

The 12 tools and 4 resources exposed at `https://mcp.zelta.app/mcp`.

> **Canonical schemas live on the wire.** Call `tools/list` (any authenticated client) for the up-to-date `inputSchema` / `outputSchema` of every enabled tool. The summaries below are durable contract notes; field-level detail is read off the running server.

## Conventions

- All write tools require an `idempotency_key` (UUID, ≤128 chars). Same key + same args replays the cached result; same key + different args returns `-32002`.
- All payment-impact tools (`payment.transfer`, `exchange.trade`, `ramp.start`, `sms.send`) flow through the spending-limit saga and may return `-32003 SPENDING_LIMIT_EXCEEDED`.
- Account UUIDs match `^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$`. Currency codes match `^[A-Z]{3,10}$`.
- Common errors on every tool: `-32001` (unauthenticated), `-32004` (operator-disabled), `-32006` (user-bound tool called without a user-context token).

## Tool calls

Every tool is invoked via JSON-RPC `tools/call`:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "account.balance",
    "arguments": { "account_uuid": "..." }
  }
}
```

Response envelope:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "content":  [ { "type": "text", "text": "..." } ],
    "isError":  false,
    "structuredContent": { ... }
  }
}
```

`isError: true` is the in-band failure signal — it does NOT translate to a JSON-RPC `error` envelope. Wire-protocol errors (auth, scope, idempotency, spending) DO use `error`.

---

## `account.balance` — read

**Scope:** `accounts:read`

Get the current balance of an account, optionally scoped to a single asset.

**Inputs**

| Field | Type | Required | Notes |
|---|---|---|---|
| `account_uuid` | string (UUID) | yes | The account to read |
| `asset_code` | string (`^[A-Z]{3,10}$`) | no | If omitted, returns all asset balances |

**Outputs**

`account_uuid`, `balances[]` (each `{asset_code, balance, formatted, last_updated}`), `total_value_usd`.

---

## `account.create` — write

**Scope:** `accounts:write` · **Idempotent:** required

Open a new account for the authenticated user (or for `user_uuid` if explicitly passed and authorized).

**Inputs**

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | yes | 3–100 chars |
| `currency` | enum | no | `USD` / `EUR` / `GBP` / `JPY` / `CHF` / `CAD` / `AUD` / `NZD` / `CNY` / `SGD`; default `USD` |
| `type` | enum | no | `checking` / `savings` / `investment` / `trading` / `escrow` / `loan`; default `checking` |
| `initial_deposit` | number | no | In minor units; default `0` |
| `metadata` | object | no | `{purpose, description, tags[]}` |
| `idempotency_key` | string (UUID) | yes | |

**Outputs**

`account_uuid`, `account_number`, `name`, `type`, `currency`, `balance`, `status`, `created_at`, `initial_deposit`, `deposit_status`, `deposit_reference`, `message`.

---

## `payment.status` — read

**Scope:** `payments:read`

Look up a payment by transaction id or external reference.

**Inputs**

| Field | Type | Required | Notes |
|---|---|---|---|
| `transaction_id` | string | yes | UUID OR external reference (`^[A-Z0-9-]+$`) |
| `type` | enum | no | `transaction` / `transfer` / `both` (default `both`) |
| `include_details` | boolean | no | default `true` |
| `include_related` | boolean | no | default `false` (set to `true` for grouped transfers) |

**Outputs**

Full status envelope: `status`, `subtype`, `amount`, `currency`, `from_account`, `to_account`, timestamps, `failure_reason`, `retry_count`, `metadata`, `related_transactions[]`, `status_history[]`, `next_steps[]`.

---

## `payment.transfer` — write, payment-impact

**Scope:** `payments:write` · **Idempotent:** required · **Spending-limit:** yes

Move money between two accounts.

**Inputs**

| Field | Type | Required | Notes |
|---|---|---|---|
| `from_account_uuid` | string (UUID) | yes | |
| `to_account_uuid` | string (UUID) | yes | |
| `amount` | number | yes | Major units, ≥ `0.01` |
| `currency` | string (`^[A-Z]{3,10}$`) | yes | |
| `reference` | string | no | ≤255 chars |
| `description` | string | no | ≤500 chars |
| `idempotency_key` | string (UUID) | yes | |

**Outputs**

`transfer_id`, `from_new_balance`, `to_new_balance`, `formatted_*_balance`, `timestamp`, `status`, `fee`.

**Note on amount precision.** The saga converts `amount` (major units) to integer minor units via `bcmath`. Floats are accepted but a value that round-trips through PHP's float→string in scientific notation (e.g., `1e-5`) is rejected as `AMOUNT_INVALID`. Send strings (`"100.50"`) for any amount that risks IEEE-754 drift.

**Example call**

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "payment.transfer",
    "arguments": {
      "from_account_uuid": "0a1b2c3d-...",
      "to_account_uuid":   "9e8d7c6b-...",
      "amount": "10.00",
      "currency": "USD",
      "reference": "lunch",
      "idempotency_key": "f47ac10b-58cc-4372-a567-0e02b2c3d479"
    }
  }
}
```

---

## `transactions.query` — read

**Scope:** `transactions:read`

List transactions with filters.

**Inputs**

| Field | Type | Required | Notes |
|---|---|---|---|
| `query` | string | no | Natural-language hint (free-form) |
| `account_uuid` | string (UUID) | no | Scope to one account |
| `date_from` / `date_to` | string (ISO 8601) | no | |
| `amount_min` / `amount_max` | number | no | |
| `category` | string | no | |
| `asset_code` | string | no | |
| `limit` | integer | no | 1–100, default 25 |

**Outputs**

`transactions[]`, `count`, `aggregates` (`total_in`, `total_out`, `net_flow`).

---

## `spending.analysis` — read

**Scope:** `transactions:read`

Categorized spending breakdown over a window.

**Inputs:** account or user filter, `period`, optional `categories[]`, optional `currency`.

**Outputs:** category-level `amount`, `count`, `avg`, `share`; `top_merchants[]`; `trend[]`.

---

## `exchange.quote` — read

**Scope:** `exchange:read`

Get a tradable quote for `from_currency` → `to_currency` at `amount`.

**Outputs:** `quote_id`, `rate`, `expires_at`, `inverse_rate`, `fee`, `slippage_bps`, `from_amount`, `to_amount`.

---

## `exchange.trade` — write

**Scope:** `exchange:write` · **Idempotent:** required

Execute a quote.

**Inputs:** `quote_id`, `from_account_uuid`, `to_account_uuid`, `idempotency_key`.

**Outputs:** `trade_id`, `from_amount`, `to_amount`, `rate`, `fee`, `executed_at`.

> **Spending-limit coverage gap.** `exchange.trade` is intentionally NOT in the saga path: the spending-limit commitment is the quote-currency cost (amount × market price), and market price isn't in the tool arguments. We will wire saga coverage once the trade tool surfaces a settled fiat-equivalent in its result. Tracked as a Phase 7 follow-up.

---

## `ramp.start` — write

**Scope:** `ramp:write` · **Idempotent:** required

Start an onramp/offramp session via Stripe Bridge. Returns a checkout URL the agent or user opens to complete the funding flow.

**Inputs:** `direction` (`onramp` / `offramp`), `amount`, `currency` (fiat), `crypto_currency`, `account_uuid`, `idempotency_key`.

**Outputs:** `session_id`, `checkout_url`, `expires_at`, `status`.

---

## `ramp.status` — read

**Scope:** `ramp:read`

Poll a ramp session.

**Inputs:** `session_id`.

**Outputs:** `status`, `direction`, `amount`, `currency`, `crypto_amount`, `crypto_currency`, `tx_hash`, `completed_at`, `failure_reason`.

---

## `mpp.discovery` — read, public

**Scope:** none (public — but rate-limited per IP)

Returns the catalog of supported payment rails — Stripe, Tempo, Lightning, Card, x402 — with capability metadata. Useful for an agent deciding which rail to recommend before calling a payment tool.

**Inputs:** none.

**Outputs:** `rails[]`, `protocol_versions`.

---

## `sms.send` — write, payment-impact

**Scope:** `sms:send` · **Idempotent:** required · **Spending-limit:** yes (per-message x402 settlement)

Send an SMS via the Zelta SMS rail. Each message settles via x402 micropayment; the cost is debited from the consenting user's daily limit.

**Inputs:** `to` (E.164 phone), `message` (≤1600 chars), `dlr_url` (optional delivery-receipt webhook), `idempotency_key`.

**Outputs:** `message_id`, `status`, `settlement_amount_minor`, `settlement_currency`, `submitted_at`.

---

# Resources

Read-only context the agent can pull into its window without firing a tool call (cheaper, cached, more LLM-friendly for browsing).

| URI | Scope | Returns |
|---|---|---|
| `account://profile` | `accounts:read` | Authenticated user profile |
| `account://balance/{currency}` | `accounts:read` | Single-currency balance |
| `transactions://recent` | `transactions:read` | 50 most recent transactions |
| `transaction://{id}` | `transactions:read` | One transaction by id |

URIs are matched case-insensitively (RFC 3986 §3.1).

## Reading a resource

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "resources/read",
  "params": { "uri": "account://balance/USD" }
}
```

Response:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "result": {
    "contents": [{
      "uri": "account://balance/USD",
      "mimeType": "application/json",
      "text": "{ \"currency\": \"USD\", \"balance_minor\": 12345, \"formatted\": \"$123.45\", ... }"
    }]
  }
}
```

---

## Where next

- [03-MCP-Quickstart.md](03-MCP-Quickstart.md) — connect a client end-to-end
- [04-MCP-OAuth-Reference.md](04-MCP-OAuth-Reference.md) — handshake, scopes, DCR, PKCE
- [02-MCP-Server-Architecture.md](02-MCP-Server-Architecture.md) — request lifecycle internals
