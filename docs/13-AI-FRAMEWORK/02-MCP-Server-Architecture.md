# MCP Server Architecture

How the public MCP server at `https://mcp.zelta.app/mcp` is wired internally.

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
            │   - SseStreamManager              │
            │   - McpToolAdapter                │
            └─────────────────┬─────────────────┘
                              │ delegates tool execution to
                              ▼
            ┌───────────────────────────────────┐
            │   app/Domain/AI/MCP/              │   business logic (untouched)
            │   - ToolRegistry                  │
            │   - MCPToolInterface              │
            │   - registered tool classes       │
            └───────────────────────────────────┘
```

The existing `app/Domain/AI/MCP/` framework is the *internal* tool catalog. The new `app/Domain/MCP/` is purely the **public wire-protocol gateway** — it receives JSON-RPC, validates OAuth, looks up the tool in the existing `ToolRegistry`, and marshals the response into the JSON-RPC envelope. The existing tool classes are not modified. The internal `/api/ai/mcp/tools/*` REST surface continues to work unchanged.

## Code surface

```
app/Domain/MCP/
├── Server/
│   ├── StreamableHttpController.php             # POST /mcp + GET /mcp (SSE)
│   ├── JsonRpcRouter.php                        # method dispatch
│   ├── SseStreamManager.php                     # server→client streaming
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
│   └── SpendingLimitService.php                 # reserve / release / commit
├── Sagas/
│   └── SpendingEnforcedToolCallSaga.php         # reserve → exec → release-on-failure
├── Resources/
│   └── ResourceRegistry.php                     # 4 read-context primitives
├── Audit/
│   └── ToolInvocationLogger.php                 # writes to mcp_tool_invocations
└── Exceptions/                                  # mapped to JSON-RPC error codes

config/mcp.php                                   # tool catalog, scopes, kill-switches, spending defaults

packages/finaegis-mcp-stdio/                     # npm wrapper (TypeScript)
```

## Subdomain wiring

`mcp.zelta.app` is a CNAME to the same Laravel app. The route group registers via `Route::domain(config('mcp.host'))->group(...)` in `bootstrap/app.php`. The middleware stack on this group is **deliberately minimal** — no CSRF, no Sanctum, no web session — just OAuth bearer + rate limit + audit. The main app's middleware does not bleed in.

## Request lifecycle (write tool)

1. Client `POST /mcp` with bearer token + `tools/call` envelope.
2. `McpOAuthGuard` resolves the token, asserts the requested tool's scope is granted, and calls `Auth::shouldUse('api')` so the underlying tool sees the bearer-token user via `Auth::user()`.
3. `JsonRpcRouter` looks up the tool in the catalog. Disabled tools → `-32004`. User-context tools called with a client_credentials grant → `-32006`.
4. For write tools, the router requires an `idempotency_key` (UUID, ≤128 chars) and dispatches into `IdempotencyCache::remember()`, which:
   - Returns the cached result if `(token, tool, key)` exists with the same `args_hash`.
   - Returns `-32002` if `args_hash` differs (key reused with different args).
   - Acquires an atomic Redis SET-NX lock to prevent two concurrent retries from both executing. A loser sees `-32005`.
5. For payment tools, `SpendingEnforcedToolCallSaga` runs:
   - **Reserve**: take the amount against the per-token daily window. Over-limit → `-32003` with `data.window_resets_at`.
   - **Execute**: run the tool through `McpToolAdapter` against the existing `MCPToolInterface` implementation.
   - **Release on failure**: any thrown exception or `isError === true` envelope reverses the reservation. The reservation only sticks on success.
6. `ToolInvocationLogger` writes `mcp_tool_invocations` (token, client, user, tool, args_hash, settlement amount/currency, status, ip, ua, request id).
7. The result envelope is cached and returned. The lock is released in a `finally`.

## Idempotency

Redis-backed cache, key `mcp:idem:{token_id}:{tool_name}:{idempotency_key}`, TTL 24h.

| Scenario | Behavior |
|---|---|
| First call with key K, args A | Execute, cache `{args_hash, result}` keyed by K, return result |
| Retry with K + A (matching `args_hash`) | Return cached result; do NOT re-execute |
| Retry with K + A' (different `args_hash`) | `-32002 IDEMPOTENCY_KEY_REUSED` |
| Concurrent retry while first call is in flight | `-32005 IDEMPOTENCY_KEY_IN_FLIGHT` (atomic SET-NX) |

The lock TTL defaults to 300s (covers Lightning HTLC + congested L1 confirmation). Tunable via `MCP_IDEMPOTENCY_LOCK_TTL_SECONDS`. Setting it shorter risks double-charge if a slow rail's first call stalls past the TTL and a concurrent retry then acquires the lock.

## Spending limits

Per-token, **not** per-scope. Set at consent time via the slider on the Blade consent screen (defaults: $50 / $500 / $2k / $10k / unlimited). Stored on `mcp_token_policies` (`daily_limit_minor`, `daily_limit_currency`, `daily_spend_minor`, `daily_window_start_at`).

The saga performs the boundary conversion from major-unit input arguments (e.g. `amount: "100.50"`) to integer minor units via `bcmath`. This avoids IEEE-754 drift that would let `(int)(0.1 * 100) == 9` understate a charge by a cent. The whitelist rejects scientific-notation strings, hex/octal numerics, negative numbers, NaN/Inf — anything `bcmath` could misinterpret.

Reservations are atomic: a check-and-increment under a row lock. Two concurrent calls cannot both pass through a single-cent-remaining limit.

## Kill-switches

Config-driven, deploy-free toggles via `php artisan config:cache`:

```php
'enabled' => env('MCP_ENABLED', true),                 // global
'tools' => [
    'payment.transfer' => ['enabled' => env('MCP_TOOL_PAYMENT_TRANSFER', true), ...],
    'exchange.trade'   => ['enabled' => env('MCP_TOOL_EXCHANGE_TRADE', true),   ...],
    'sms.send'         => ['enabled' => env('MCP_TOOL_SMS_SEND', true),         ...],
    // ...
],
```

Disabled tools are *omitted from `tools/list`* (so agents don't propose calling them) AND return `-32004 TOOL_DISABLED` if invoked directly. Emergency response: flip env, `php artisan config:cache`, restart workers — problem isolated within seconds, no deploy required.

## Rate limits

Tiered, expressed as Laravel `RateLimiter` rules in `config/mcp.php`:

| Tier | Limit | Why |
|---|---|---|
| Per token (aggregate) | 60/min, 600/hr, 5000/day | aggregate budget |
| Reads | 120/min | cheap, latency-sensitive |
| Writes | 30/min | settlement is expensive |
| `sms.send` specifically | 10/min | x402 settlement + carrier round-trip |
| Unauthed discovery | 60/min/IP | DoS protection on `.well-known/*` |

Per-`client_id` overrides supported for trusted partners.

## Observability

Wires into existing infrastructure. No new tooling.

- **Metrics** (Prometheus via existing OpenTelemetry export):
  - `mcp_tool_invocations_total{tool, status}`
  - `mcp_tool_duration_seconds{tool}` (histogram)
  - `mcp_oauth_consents_total{scope, decision}`
  - `mcp_active_sessions` (gauge — live SSE connections)
  - `mcp_spending_limit_hits_total{client_id}`
- **Logs** — structured JSON; correlation id is the JSON-RPC request `id`.
- **Traces** — every tool invocation wrapped in a span tagged with `mcp.tool`, `mcp.client_id`, `mcp.scope`.
- **Admin UI** — Filament resources for `OAuth Clients`, `Tool Invocations`, `Active Sessions`. Block / revoke / kill from the UI.

## Settlement attribution

Every $-impact tool invocation writes `settlement_amount_minor` + `settlement_currency` to `mcp_tool_invocations`. This feeds:

- The MCP revenue dashboard in Filament.
- The regulatory / AML feed — every fund movement traces back to a token + scope + client at audit time.

## SSE posture

`config/mcp.php` defaults `sse.enabled = false`. Long-lived SSE inside PHP-FPM pins a worker for the connection's lifetime; under load that exhausts the pool. Set `MCP_SSE_ENABLED=true` only when running under Octane/Swoole or a dedicated SSE FPM pool. The MCP spec allows POST-only servers to return 405 on GET, and we do exactly that when SSE is off.

## Where next

- [03-MCP-Quickstart.md](03-MCP-Quickstart.md) — connect a client end-to-end
- [04-MCP-OAuth-Reference.md](04-MCP-OAuth-Reference.md) — handshake, scopes, DCR, PKCE
- [05-MCP-Tool-Reference.md](05-MCP-Tool-Reference.md) — full tool + resource schemas
