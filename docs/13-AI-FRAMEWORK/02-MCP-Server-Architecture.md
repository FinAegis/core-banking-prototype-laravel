# MCP Server Architecture

This page explains how the public MCP server at `https://mcp.zelta.app/mcp` works inside the application. MCP is the Model Context Protocol, the standard that AI clients use to call tools on a server.

## Two-layer design

```
                       External MCP clients
        (Claude Desktop, Cursor, Continue, npm wrapper)
                              │
                              ▼
                  https://mcp.zelta.app/mcp
                              │
            ┌─────────────────┴─────────────────┐
            │   app/Domain/MCP/Server/          │   wire-protocol layer
            │   - StreamableHttpController      │
            │   - JsonRpcRouter                 │
            │   - McpToolCatalog                │
            │   - SseStreamManager              │
            │   - McpToolAdapter                │
            └─────────────────┬─────────────────┘
                              │ delegates tool execution to
                              ▼
            ┌───────────────────────────────────┐
            │   app/Domain/AI/MCP/              │   business logic
            │   - ToolRegistry                  │
            │   - MCPToolInterface              │
            │   - registered tool classes       │
            └───────────────────────────────────┘
```

The public server has two layers. The `app/Domain/AI/MCP/` framework is the internal tool registry. It holds every tool class the platform has, including tools that the public server does not expose. The `app/Domain/MCP/` domain is the public gateway. It receives JSON-RPC requests, checks the OAuth token, looks up the tool in `ToolRegistry` and wraps the result in a JSON-RPC response.

The public catalog in `config/mcp.php` (the `tools` key) decides which registry tools the public server offers. It has 14 tools. The production server exposes 10 of them, because `account.create`, `payment.transfer`, `exchange.trade` and `sms.send` are off in production.

The internal REST endpoints under `/api/ai/mcp/tools/*` are a separate surface. They are served by `MCPToolsController`, which returns fixed sample data for its own six demo tools. They do not call `ToolRegistry` and they are not part of the public server.

## Code surface

```
app/Domain/MCP/
├── Server/
│   ├── StreamableHttpController.php             # POST /mcp + GET /mcp (SSE, off by default)
│   ├── JsonRpcRouter.php                        # method dispatch
│   ├── McpToolCatalog.php                       # enabled / requires_config checks
│   ├── McpRequestContext.php                    # token, client, user and scopes for one request
│   ├── EnsureMcpEnabled.php                     # 503 when MCP_ENABLED=false
│   ├── SseStreamManager.php                     # server→client streaming (heartbeat only)
│   └── McpToolAdapter.php                       # MCPToolInterface → JSON-RPC envelope
├── Auth/
│   ├── McpOAuthGuard.php                        # bearer validation + scope enforcement
│   ├── DynamicClientRegistrationController.php  # POST /oauth/register (RFC 7591)
│   └── ConsentScreenController.php              # custom consent UI
├── Discovery/
│   ├── ProtectedResourceMetadataController.php  # /.well-known/oauth-protected-resource
│   └── AuthorizationServerMetadataController.php
├── Policy/
│   ├── IdempotencyCache.php                     # Redis-backed, atomic SET-NX lock
│   ├── ConsentSpendingPolicy.php                # saves the limit chosen on the consent screen
│   └── SpendingLimitService.php                 # reserve / release
├── Sagas/
│   └── SpendingEnforcedToolCallSaga.php         # reserve → exec → release-on-failure
├── Resources/
│   └── ResourceRegistry.php                     # 4 read-context primitives
├── Tools/
│   ├── Ramp/                                    # ramp.start, ramp.status
│   └── Wallet/                                  # wallet.addresses, wallet.activity
├── Audit/
│   └── ToolInvocationLogger.php                 # writes to mcp_tool_invocations
├── Routes/
│   └── api.php                                  # routes on the mcp.* host
└── Exceptions/                                  # mapped to JSON-RPC error codes

config/mcp.php                                   # tool catalog, scopes, kill-switches, spending defaults

packages/finaegis-mcp-stdio/                     # npm wrapper @finaegis/mcp (TypeScript)
```

## Subdomain wiring

`mcp.zelta.app` points to the same Laravel app. `bootstrap/app.php` loads `app/Domain/MCP/Routes/api.php` when the request host starts with `mcp.`, and every route in that file is bound to `config('mcp.host')`. The middleware on this host is kept small on purpose. It has no CSRF check, no Sanctum and no web session.

The MCP host serves these routes:

| Route | Middleware |
|---|---|
| `GET /healthz` | none |
| `GET /.well-known/oauth-protected-resource` | `throttle:mcp.discovery` |
| `GET` and `POST /mcp` | `EnsureMcpEnabled`, `mcp.oauth`, `throttle:mcp.aggregate` |

The OAuth endpoints live on the main app host (`https://zelta.app`) in `routes/web.php`. These are `POST /oauth/register` and `GET /.well-known/oauth-authorization-server`, next to the Laravel Passport routes `/oauth/authorize` and `/oauth/token`.

## Request lifecycle (write tool)

1. The client sends `POST /mcp` with a bearer token and a `tools/call` envelope.
2. `McpOAuthGuard` resolves the Passport token. It answers `401` with `-32001` when the token is missing, revoked or not bound to a user. It then calls `Auth::shouldUse('api')` so the tool sees the token's user through `Auth::user()`.
3. `JsonRpcRouter` looks up the tool in the catalog. An unknown tool returns `-32601 TOOL_NOT_FOUND`. A disabled tool returns `-32004`. A token without the tool's scope gets `-32000 INSUFFICIENT_SCOPE`. A user-context tool called without a user returns `-32006`.
4. A write tool needs an `idempotency_key` of at most 128 characters. A missing key returns `-32602 IDEMPOTENCY_KEY_REQUIRED`, and a longer key returns `-32602 IDEMPOTENCY_KEY_TOO_LONG`. The router then calls `IdempotencyCache::remember()`, which works as follows.
   - It returns the cached result if the same token, tool and key were used before with the same `args_hash`.
   - It returns `-32002` if the key was used before with different arguments.
   - It takes an atomic Redis SET-NX lock, so two concurrent retries cannot both run. The second call gets `-32005`.
5. A payment tool (`payment.transfer`, `ramp.start`, `sms.send`) runs inside `SpendingEnforcedToolCallSaga`.
   - The saga first reserves the amount against the daily limit of the user's approval of this app. A call over the limit gets `-32003` with `data.window_resets_at`.
   - It then runs the tool through `McpToolAdapter`.
   - If the tool throws or returns `isError: true`, the saga releases the reservation. The reservation stays only when the tool succeeds.
6. `ToolInvocationLogger` writes a row to `mcp_tool_invocations`. The row holds the token, client, user, tool, `args_hash`, idempotency key, status, error code and duration. It also takes the IP address, user agent and `X-Request-ID` from the request.
7. The result is cached and returned. The lock is released in a `finally` block.

`exchange.trade` is a write tool but not a payment tool. It does not go through the spending-limit saga, because its cost in the quote currency is not known from the tool arguments.

## Idempotency

The cache is Redis-backed. The key is `mcp:idem:{token_id}:{tool_name}:{idempotency_key}` and the result is kept for 24 hours.

| Scenario | Behavior |
|---|---|
| First call with key K, args A | Execute, cache `{args_hash, result}` keyed by K, return result |
| Retry with K + A (matching `args_hash`) | Return cached result; do NOT re-execute |
| Retry with K + A' (different `args_hash`) | `-32002 IDEMPOTENCY_KEY_REUSED` |
| Concurrent retry while first call is in flight | `-32005 IDEMPOTENCY_KEY_IN_FLIGHT` (atomic SET-NX) |

The lock lasts 300 seconds by default. That covers a Lightning payment or a slow L1 confirmation. You can change it with `MCP_IDEMPOTENCY_LOCK_TTL_SECONDS`. A shorter lock is risky. If a slow first call outlives the lock, a concurrent retry can take the lock and charge twice.

## Spending limits

A spending limit belongs to a user's approval of one app, not to a single token and not to a scope. The consent screen offers a daily limit of $50, $500, $2,000, $10,000 or no limit. When the user approves, the `CaptureMcpConsentLimit` middleware saves the chosen value through `ConsentSpendingPolicy`. A missing or unknown value gets the default of $500 (`MCP_DEFAULT_DAILY_LIMIT_MINOR=50000`). "No limit" is stored as `PHP_INT_MAX`.

The limit is stored in `mcp_token_policies` (`daily_limit_minor`, `daily_limit_currency`, `daily_spend_minor`, `daily_window_start_at`). The row key is `grant:{client_id}:{user_id}`, so refreshed and re-issued tokens share one allowance. A repeated approval changes the limit and keeps what was already spent in the current window. Before v7.18.1 nothing saved the limit, so an approval from that time has no row. Without a row the saga refuses every payment tool with `-32003` and `error_code: NO_POLICY`. Passport skips the consent screen while the user still holds a valid token with the same scopes for that client. So the limit is saved only once those tokens have expired or the client asks for consent again with `prompt=consent`.

The daily window is a rolling 24 hours that starts at `daily_window_start_at`. When a reservation arrives after the window has ended, the service starts a new window and sets the spend to zero. No scheduled job is involved.

The saga converts major-unit amounts (for example `amount: "100.50"`) to integer minor units with `bcmath`. This avoids floating-point drift, where `(int)(0.1 * 100)` gives 9 and undercharges by a cent. The saga rejects scientific notation, hex and octal numbers, negative numbers, NaN and Inf, because `bcmath` could misread them. Such a value returns `-32003` with `error_code: AMOUNT_INVALID`.

A reservation is a check and an increment under a row lock. Two concurrent calls cannot both pass when only one cent of the limit is left.

## Kill-switches

The switches are environment values. After a change, run `php artisan config:cache` and restart the workers. No deploy is needed.

```php
'enabled' => (bool) env('MCP_ENABLED', true),              // global; false answers 503
'tools' => [
    'account.create'   => ['enabled' => env('MCP_TOOL_ACCOUNT_CREATE',   in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true)), ...],
    'payment.transfer' => ['enabled' => env('MCP_TOOL_PAYMENT_TRANSFER', in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true)), ...],
    'exchange.trade'   => ['enabled' => env('MCP_TOOL_EXCHANGE_TRADE',   in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true)), 'requires_config' => 'trading.enabled', ...],
    'sms.send'         => ['enabled' => env('MCP_TOOL_SMS_SEND',         in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true)), ...],
    // every other tool defaults to true
],
```

The four tools above are on by default only when `APP_ENV` is `local`, `testing` or `demo`. An explicit `MCP_TOOL_*` value always wins. `exchange.trade` also stays off while `TRADING_ENABLED` is false. In production, `ops:verify-env` fails while `sms.send` is on, unless `MCP_TOOL_SMS_SEND_ACKNOWLEDGED=true` confirms it.

A disabled tool is left out of `tools/list`, so agents do not offer to call it. A direct call returns `-32004 TOOL_DISABLED`.

## Rate limits

The limits are set in `config/mcp.php` under `rate_limits`. Only some of them are enforced today.

| Limit | Value | Enforced by |
|---|---|---|
| Per token, aggregate | 60/min, 600/hr | `throttle:mcp.aggregate` on `/mcp` |
| Per token, daily aggregate | 5000/day | Not enforced yet (config value only) |
| Reads | 120/min | Not enforced yet (config value only) |
| Writes | 30/min | Not enforced yet (config value only) |
| `sms.send` per user | 10/min | `SmsSendTool` |
| Unauthenticated discovery | 60/min/IP | `throttle:mcp.discovery` on `/.well-known/oauth-protected-resource` |

There are no per-client overrides.

## Observability

The audit trail is the `mcp_tool_invocations` table. The Filament admin panel shows it as a read-only list under the MCP group (`/admin/mcp-tool-invocations`), with filters by status and date. There are no admin pages for OAuth clients or sessions, and you cannot block or revoke a client from the admin panel.

The MCP domain does not export its own Prometheus metrics or tracing spans. The JSON-RPC request `id` and the `X-Request-ID` header are the values to search for in the logs.

## Settlement columns

`mcp_tool_invocations` has `settlement_amount_minor` and `settlement_currency` columns, and the admin list shows them. The router does not fill them yet, so they stay empty. The amount a payment tool reserved is visible in `mcp_token_policies.daily_spend_minor` instead.

## SSE posture

`config/mcp.php` sets `sse.enabled = false` by default. A long-lived SSE connection inside PHP-FPM holds a worker for the whole connection, and under load that empties the pool. Set `MCP_SSE_ENABLED=true` only when you run Octane or Swoole, or a separate FPM pool for SSE. The MCP spec allows a POST-only server to answer `405` on `GET`, and this server does that while SSE is off. When SSE is on, the stream sends heartbeats only.

## Where next

- [03-MCP-Quickstart.md](03-MCP-Quickstart.md) shows how to connect a client.
- [04-MCP-OAuth-Reference.md](04-MCP-OAuth-Reference.md) covers the handshake, scopes, client registration and PKCE.
- [05-MCP-Tool-Reference.md](05-MCP-Tool-Reference.md) lists every tool and resource.
