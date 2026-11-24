# MCP Tool Reference

The public MCP server has 14 tools and 4 resources in its catalog. In production, `https://mcp.zelta.app/mcp` exposes 10 of these tools. The tools `account.create`, `payment.transfer`, `exchange.trade` and `sms.send` are off by default when `APP_ENV=production` (`MCP_TOOL_*`), and `exchange.trade` also needs `TRADING_ENABLED`. A disabled tool does not appear in `tools/list`, and calling it returns `-32004`.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

> The running server is the source of truth for schemas. Call `tools/list` with any authenticated client to get the current `inputSchema` and `outputSchema` of every enabled tool. The summaries below follow the tool classes in `app/Domain/AI/MCP/Tools/` and `app/Domain/MCP/Tools/`.

## Conventions

- Every write tool needs an `idempotency_key`. The schema asks for a UUID, and the server accepts at most 128 characters. The same key with the same arguments replays the cached result. The same key with different arguments returns `-32002`.
- The payment tools are `payment.transfer`, `ramp.start` and `sms.send`. They run through the spending-limit saga and can return `-32003 SPENDING_LIMIT_EXCEEDED`. When no daily limit is saved for the user's approval of the client, the call gets `-32003` with `error_code: NO_POLICY`. The limit is saved when the user approves the client on the consent screen. `exchange.trade` is a write tool but does not count against the spending limit.
- Account UUIDs match `^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$`. Currency codes match `^[A-Z]{3,10}$`.
- Every tool can return `-32001` (unauthenticated), `-32000` (missing scope), `-32004` (switched off by the operator) and `-32006` (user-bound tool called without a user).

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

A tool that fails returns `isError: true` inside `result`. That is not a JSON-RPC `error` envelope. Protocol errors about authentication, scope, idempotency and spending do use the `error` envelope.

---

## `account.balance` — read

**Scope:** `accounts:read`

Get the current balance of an account, optionally scoped to a single asset. Only the account owner can read it. Team membership or an admin role does not give access. For someone else's account the tool answers `Account not found`.

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

Create a new account for the signed-in user. The tool never creates accounts for other users, whatever the caller's role. The account opens empty, and a positive `initial_deposit` is refused. The tool is off by default in production (`MCP_TOOL_ACCOUNT_CREATE`).

**Inputs**

| Field | Type | Required | Notes |
|---|---|---|---|
| `name` | string | yes | 3–100 chars |
| `currency` | enum | yes | `USD` / `EUR` / `GBP` / `JPY` / `CHF` / `CAD` / `AUD` / `NZD` / `CNY` / `SGD`; schema default `USD` |
| `type` | enum | yes | `checking` / `savings` / `investment` / `trading` / `escrow` / `loan`; schema default `checking` |
| `user_uuid` | string (UUID) | no | Must be the caller's own user UUID; another user's UUID is refused |
| `metadata` | object | no | `{purpose, description, tags[]}` |
| `settings` | object | no | `{overdraft_protection, auto_sweep, minimum_balance, monthly_fee, interest_rate, notification_settings}` |
| `idempotency_key` | string (UUID) | yes | |

**Outputs**

`account_uuid`, `account_number`, `name`, `type`, `currency`, `balance`, `status`, `created_at`, `message`.

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

Move money between two accounts. The source account must belong to the caller. The tool is off by default in production (`MCP_TOOL_PAYMENT_TRANSFER`).

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

**Note on amount precision.** The saga converts `amount` from major units to integer minor units with `bcmath`. It accepts numbers, but a number that PHP turns into scientific notation, such as `1e-5`, is rejected with `AMOUNT_INVALID`. Send the amount as a string, such as `"100.50"`, to avoid floating-point drift.

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

List the caller's transactions with optional filters.

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

`transactions[]` (each `{id, date, amount, asset, category, merchant, type, status, description}`), `summary` (`total_inflow`, `total_outflow`, `net_change`, `transaction_count`, `average_transaction`), `explanation`, `total_count`.

---

## `spending.analysis` — read

**Scope:** `transactions:read`

Get a spending breakdown by category over a date range.

**Inputs:** `query` (free-form hint), `account_uuid`, `date_from`, `date_to`, `category`, `asset_code`. All are optional.

**Outputs:** `period` (`from`, `to`), `total_spent`, `total_earned`, `by_category` (each `{total, count}`), `top_merchants[]` (each `{merchant, total, count}`), `trends` (`direction`, `change_percent`), `insights[]`.

---

## `exchange.quote` — read

**Scope:** `exchange:read`

Get an exchange rate for `from_currency` to `to_currency`.

**Inputs:** `from_currency`, `to_currency` (both required), `amount` (default 1), `include_fees` (default `false`).

**Outputs:** `from_currency`, `to_currency`, `exchange_rate`, `amount`, `converted_amount`, `formatted_rate`, `formatted_amount`, `fee_amount`, `total_amount`, `timestamp`, `provider`, `inverse_rate`, `spread_percentage`. The tool returns no quote id, and a quote does not reserve a price.

---

## `exchange.trade` — write

**Scope:** `exchange:write` · **Idempotent:** required

Place an order on the exchange. The tool is off by default in production (`MCP_TOOL_EXCHANGE_TRADE`). It is also off whenever the trading engine is off (`TRADING_ENABLED=false`), whatever `MCP_TOOL_EXCHANGE_TRADE` says. It trades only on accounts the caller owns.

**Inputs:** `account_uuid`, `type` (`buy` / `sell`), `order_type` (`market` / `limit` / `stop` / `stop-limit`), `base_currency`, `quote_currency`, `amount`, `price` (for limit orders), `stop_price` (for stop orders), `time_in_force` (`GTC` / `IOC` / `FOK` / `GTD`, default `GTC`), `idempotency_key`.

**Outputs:** `order_id`, `account_uuid`, `type`, `order_type`, `base_currency`, `quote_currency`, `amount`, `price`, `status`, `filled_amount`, `remaining_amount`, `average_fill_price`, `fee_amount`, `fee_currency`, `created_at`, `updated_at`, `time_in_force`. The result is an order placement, so it usually comes back as `pending` with nothing filled yet.

> `exchange.trade` does not count against the daily spending limit. The limit would have to use the cost in the quote currency, which is the amount times the market price. The market price is not in the tool arguments, and the result reports an order placement rather than a settled amount. The saga can cover this tool once its result reports a settled value.

---

## `ramp.start` — write

**Scope:** `ramp:write` · **Idempotent:** required

Start an onramp or offramp session with a third-party provider (integration adapter for the Bridge API; no partnership or endorsement implied). Converting stablecoins to fiat, where available, will be provided by licensed third parties under their own terms. Providers with a hosted page return a checkout URL that the user opens in a browser.

**Inputs:** `type` (`on` / `off`), `fiat_currency` (`USD` / `EUR` / `GBP`), `fiat_amount` (numeric string in major units, for example `"100.00"`), `crypto_currency` (`USDC` / `EURC` / `USDT`), `wallet_address`, optional `quote_id`, `idempotency_key`.

**Outputs:** `session_id`, `checkout_url`, `provider`, `status`.

Production uses the Bridge provider (`RAMP_PROVIDER=bridge`). Bridge takes bank transfers into USDC on Polygon, so `checkout_url` is `null` and the tool output does not include the bank deposit instructions. Bridge offramp (`type: off`) is not available in v1, so the call fails with `isError: true`. The spending limit counts `fiat_amount` in `fiat_currency`.

---

## `ramp.status` — read

**Scope:** `ramp:read`

Get the current state of a ramp session. While the session is still running, the tool refreshes it from the provider.

**Inputs:** `session_id`.

**Outputs:** `session_id`, `status`, `provider`, `crypto_amount`, `updated_at`.

---

## `mpp.discovery` — read, public

**Scope:** none (still needs a user-bound token)

List the Machine Payments Protocol (MPP) rails that are available on this deployment, with the protocol configuration and the priced resources. An agent can use it to choose a rail before it calls a payment tool. The configured rails come from `MPP_SUPPORTED_RAILS` (default `stripe,tempo,lightning,card`).

**Inputs:** `include_resources` (boolean, default `true`).

**Outputs:** `configuration`, `rails[]`, `resources[]`.

---

## `sms.send` — write, payment-impact

**Scope:** `sms:send` · **Idempotent:** required · **Spending-limit:** yes (fixed price per call)

Send one SMS through the platform's SMS provider (integration adapter for the VertexSMS API; no partnership or endorsement implied). The user does not pay per message, and the platform bears the cost. Each call reserves a fixed amount against the user's daily spending limit for the client. The amount is `MCP_SMS_PRICE_MINOR` US cents, 10 by default.

**Inputs:** `to` (E.164 phone), `message` (one SMS part: ≤160 GSM characters, ≤70 otherwise), optional `from` (sender ID, default `Zelta`), optional `check_price_only` (returns the price without sending), `idempotency_key`.

The server applies the same input rules as `POST /api/v1/sms/send`. `to` must have 5 to 19 digits with an optional leading `+`, `from` must have 1 to 20 letters, digits or spaces, and `message` must be at most 1600 characters. Through MCP a message must also fit in one SMS part: 160 GSM characters, or 70 when it has other characters. The daily limits and the spending limit count one unit per call, so one call never bills more than one part. Each user can send at most `mcp.rate_limits.sms_per_minute` messages per minute, and the shared daily limits for unpaid sends also apply. The tool is off by default in production (`MCP_TOOL_SMS_SEND`, `SMS_ENABLED`). With `APP_ENV=production`, `MCP_TOOL_SMS_SEND=true` fails `ops:verify-env` unless `MCP_TOOL_SMS_SEND_ACKNOWLEDGED=true` confirms it.

**Outputs:** `message_id`, `status`, `parts`, `price_usdc`, `country`, `note`.

---

## `wallet.addresses` — read

**Scope:** `accounts:read`

List the caller's registered blockchain wallet addresses. These are the EVM smart-account address on polygon, base, arbitrum and ethereum, and the Solana ed25519 address.

**Inputs:** `chain` (optional filter, e.g. `polygon`, `solana`).

**Outputs:** `addresses[]` (`chain`, `address`, `is_active`, `label`, `created_at`), `count`.

---

## `wallet.activity` — read

**Scope:** `accounts:read`

List the caller's most recent wallet activity. It is the same feed the mobile app shows, with transfers in and out, merchant payments, and shield and unshield events.

**Inputs:** `limit` (1–50, default 10; out-of-range values are clamped).

**Outputs:** `items[]` (`id`, `type`, `amount`, `asset`, `timestamp`, `status`, `protected`), `count`, `has_more`.

---

# Resources

Resources are read-only context that an agent can read without calling a tool.

| URI | Scope | Returns |
|---|---|---|
| `account://profile` | `accounts:read` | Authenticated user profile |
| `account://balance/{currency}` | `accounts:read` | Balance of the user's first account in one currency, in minor units |
| `transactions://recent` | `transactions:read` | 25 most recent transactions across the user's accounts |
| `transaction://{id}` | `transactions:read` | One transaction by id |

The server matches URIs without regard to case (RFC 3986 §3.1).

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
      "text": "{ \"currency\": \"USD\", \"balance_minor\": 12345, \"account_id\": \"...\", \"as_of\": \"2026-11-23T10:00:00+00:00\" }"
    }]
  }
}
```

---

## Where next

- [03-MCP-Quickstart.md](03-MCP-Quickstart.md) shows how to connect a client.
- [04-MCP-OAuth-Reference.md](04-MCP-OAuth-Reference.md) covers the handshake, scopes, client registration and PKCE.
- [02-MCP-Server-Architecture.md](02-MCP-Server-Architecture.md) explains the request lifecycle.
