# Public MCP Server — Design

**Status:** Approved (brainstorming locked 2026-04-27)
**Target release:** v7.11.0
**Owner:** Backend / AI Framework
**MCP spec target:** `2025-11-25`

---

## 1. Context

FinAegis already ships an internal MCP tool framework: `app/Domain/AI/MCP/` exposes ~22 tools (Account, Payment, Exchange, Compliance, AgentProtocol, x402, MachinePay, AP2, SMS, Transaction) via a REST-flavored controller at `/api/ai/mcp/tools/*`. Our partner doc (`docs/partners/VERTEXSMS_Q13_BIDIRECTIONAL_MCP.md`) and our developer portal (`resources/views/developers/mcp-tools.blade.php`) market FinAegis as "MCP-compatible," and VertexSMS recently hit `https://zelta.app/.well-known/mcp-manifest.json` looking for the discovery surface — and got a 404.

The gap between the marketing claim and reality is now blocking partnerships. We have the tool catalog, but **no real Anthropic-spec MCP wire protocol, no OAuth 2.1 authorization, and no spec-compliant discovery**. External MCP clients (Claude Desktop, Cursor, Continue.dev) cannot connect to us.

This spec closes that gap.

## 2. Goals

- Stand up a public MCP server at `https://mcp.zelta.app/mcp` that any spec-compliant MCP client (Claude Desktop, Cursor, Continue.dev, custom agents) can connect to.
- Implement the Anthropic MCP spec version `2025-11-25`: streamable-HTTP transport, JSON-RPC 2.0 envelope, OAuth 2.1 authorization with RFC 9728 Protected Resource Metadata, Dynamic Client Registration.
- Expose a curated v1 catalog of **12 tools + 4 resources** drawn from the existing internal tool framework. Reuse — do not reimplement — the tool layer.
- Ship two distribution channels: the remote URL above, plus an `@finaegis/mcp` npm package for stdio-based clients and CI testing.
- Update all customer-facing surfaces (developer portal, marketing site, docs, CHANGELOG) so the public MCP claim is real and discoverable.

## 3. Non-goals

- We are not exposing all 22 internal tools. Sensitive ones (KYC, AML, Withdraw, AgentEscrow, AgentMandate, Visa CLI surface) stay internal pending a per-tool risk audit.
- We are not building a new authorization server. Laravel Passport ^13 is already installed and serves as the AS — we only add DCR and the discovery metadata it doesn't emit by default.
- We are not implementing AP2 mandate enforcement at v1. The spending-limit primitive is a *coarser* form of the same idea; full AP2 is a phase-2 follow-up.
- We are not implementing user step-up confirmation (push-to-mobile-to-confirm) at v1. Spending limits + audit log + idempotency are sufficient.
- We are not submitting to public registries (Smithery, Anthropic Connector Directory, Cursor) at v1. Those are scheduled fast-follows after ~7-10 days of clean operation.

## 4. Locked decisions

| # | Topic | Choice | Rationale |
|---|---|---|---|
| 1 | Tool catalog v1 | Balanced — 12 tools | Conservative enough to ship safely, broad enough for a coherent agent banking story |
| 2 | Auth flows | User-delegated **and** client-credentials | User-delegated is the consumer path (Claude Desktop). Client-credentials is the partner path (VertexSMS-style sub-accounts) |
| 3 | Topology | Single server at `https://mcp.zelta.app/mcp` | 12 tools = single-server territory; lowest configuration friction; reversible later |
| 4 | OAuth Authorization Server | Laravel Passport ^13 (already installed) | Existing infra emits `oauth/authorize`, `oauth/token`, `oauth/device`, refresh — only DCR + discovery metadata are missing |
| 5 | Scope granularity | Domain-grained — 10 scopes | Matches Stripe / Plaid / GitHub; readable consent screens; finer than coarse `read/write/admin` without per-tool noise |
| 6 | Write safety | Idempotency keys + per-token daily spending limit + audit log | Sufficient for v1; phase-2 adds threshold-based step-up confirmation |
| 7 | Distribution | Remote URL + `@finaegis/mcp` npm wrapper at v1; registry submissions scheduled 7-10 days post-launch | Wrapper doubles as conformance harness; registry listings require operational stability first |

## 5. Architecture

Two-layer design that reuses everything we already built.

```
                       External MCP clients
        (Claude Desktop, Cursor, Continue, npm wrapper)
                              │
                              ▼
                  https://mcp.zelta.app/mcp
                              │
            ┌─────────────────┴─────────────────┐
            │   NEW: app/Domain/MCP/Server/     │   wire protocol layer
            │   - StreamableHttp transport       │
            │   - JSON-RPC 2.0 dispatcher        │
            │   - SSE stream manager             │
            │   - OAuth bearer guard             │
            └─────────────────┬─────────────────┘
                              │ delegates tool execution to
                              ▼
            ┌───────────────────────────────────┐
            │  EXISTING: app/Domain/AI/MCP/     │   business logic (untouched)
            │  - ToolRegistry                    │
            │  - MCPToolInterface                │
            │  - 22 registered tools             │
            └───────────────────────────────────┘
```

**Key separation:** the existing `app/Domain/AI/MCP/` framework is the *internal* tool catalog. The new `app/Domain/MCP/` is purely the **public wire-protocol gateway**: it receives JSON-RPC, validates OAuth, looks up the tool in the existing `ToolRegistry`, and marshals the response into the JSON-RPC envelope. The 22 existing tool files are not modified. The existing `/api/ai/mcp/tools/*` REST surface continues to work for first-party AI features.

### 5.1 New code surface

```
app/Domain/MCP/
├── Server/
│   ├── StreamableHttpController.php             # POST /mcp + GET /mcp (SSE)
│   ├── JsonRpcRouter.php                        # method dispatch
│   ├── SseStreamManager.php                     # server→client streaming + reconnection
│   └── McpToolAdapter.php                       # bridges MCPToolInterface → JSON-RPC envelope
├── Auth/
│   ├── McpOAuthGuard.php                        # bearer validation + scope enforcement
│   ├── DynamicClientRegistrationController.php  # POST /oauth/register (DCR)
│   └── ConsentScreenController.php              # custom consent UI listing scoped tools + spending limit
├── Discovery/
│   ├── ProtectedResourceMetadataController.php  # GET /.well-known/oauth-protected-resource
│   └── AuthorizationServerMetadataController.php # GET /.well-known/oauth-authorization-server
├── Audit/
│   └── ToolInvocationLogger.php                 # writes to mcp_tool_invocations
└── Routes/
    └── api.php                                  # subdomain-scoped route group

config/mcp.php                                   # tool registry, scopes, kill-switches, spending defaults

database/migrations/
├── 2026_04_28_000001_create_mcp_tool_invocations_table.php
├── 2026_04_28_000002_create_mcp_token_policies_table.php
└── 2026_04_28_000003_extend_oauth_clients_for_dcr.php

bootstrap/app.php                                # register mcp.zelta.app subdomain group

packages/finaegis-mcp-stdio/                     # NEW: npm wrapper package (TypeScript)
├── src/index.ts                                 # stdio ↔ remote streamable-HTTP relay
├── src/stdio-relay.ts
├── src/oauth-helper.ts
├── src/config.ts
├── package.json                                 # name: @finaegis/mcp
└── README.md
```

**Approximate size:** ~1500 LoC PHP + ~250 LoC TypeScript wrapper + ~400 LoC tests. Heavy lifting is reuse.

### 5.2 Subdomain wiring

`mcp.zelta.app` is a CNAME to the same Laravel app server. The route group is registered via `Route::domain(config('mcp.host'))->group(...)` in `bootstrap/app.php`. The middleware stack on this group is **deliberately minimal** — no CSRF, no Sanctum, no web session — just OAuth bearer + rate limit + audit. The main app's middleware does not bleed in.

## 6. OAuth flow + scope catalog

### 6.1 Connection handshake

What happens when a Claude Desktop user pastes the URL:

```
1. Client → GET https://mcp.zelta.app/mcp                      (no token)
2. Server → 401 + WWW-Authenticate: Bearer
            resource_metadata="https://mcp.zelta.app/.well-known/oauth-protected-resource"
3. Client → GET /.well-known/oauth-protected-resource
   Server → { "authorization_servers": ["https://zelta.app"],
              "resource": "https://mcp.zelta.app",
              "scopes_supported": [...9 scopes...] }
4. Client → GET https://zelta.app/.well-known/oauth-authorization-server
   Server → OIDC-aligned metadata (Passport native + our additions)
5. Client → POST https://zelta.app/oauth/register   (DCR — new endpoint)
   Server → { client_id, client_secret, ... }
6. Client → opens browser → /oauth/authorize?scope=payments:write+sms:send&...
            User sees consent screen
            User clicks Approve
7. Client → POST /oauth/token                        (Passport native)
            ← access_token + refresh_token
8. Client → POST /mcp { "jsonrpc": "2.0", "method": "initialize", ... }
            Authorization: Bearer <token>
            ← capabilities exchange + tool/resource lists
9. Tool calls flow as JSON-RPC over POST /mcp
   Server pushes events via SSE on GET /mcp
```

### 6.2 Two flows shipped

- **User-delegated** (above) — default for Claude Desktop, Cursor, etc.
- **Client-credentials** — partner pre-registers a confidential client, calls `POST /oauth/token` with `grant_type=client_credentials`, receives a token bound to a *partner sub-account* (not a user). Same scopes, same spending-limit policy mechanism. Used by VertexSMS-style integrations where the partner has its own funded sub-account.

### 6.3 Scope catalog and tool mapping

10 scopes total, plus one always-public tool.

| Scope | Tools |
|---|---|
| `accounts:read` | `account.balance` |
| `accounts:write` | `account.create` |
| `payments:read` | `payment.status` |
| `payments:write` | `payment.transfer` |
| `transactions:read` | `transactions.query`, `spending.analysis` |
| `exchange:read` | `exchange.quote` |
| `exchange:write` | `exchange.trade` |
| `ramp:read` | `ramp.status` |
| `ramp:write` | `ramp.start` |
| `sms:send` | `sms.send` |
| (no scope — public tool) | `mpp.discovery` |

Two design choices baked in:

- **`sms:send` is split out from `payments:write`** so users can grant SMS-only access to narrow agents without granting payment access.
- **`transactions:read` is split out from `payments:read`** because transaction history reveals materially more (full history, balances over time, counterparties) than per-payment status lookups. Some users will want to grant balance + payment-status visibility without exposing the full ledger; this scope split lets them.

### 6.4 Tool catalog v1 — 12 tools

| Tool | Scope | Type | Write-safety policy |
|---|---|---|---|
| `account.balance` | `accounts:read` | read | — |
| `account.create` | `accounts:write` | write | `idempotency_key` required |
| `payment.status` | `payments:read` | read | — |
| `payment.transfer` | `payments:write` | write | `idempotency_key` + per-token spending limit |
| `transactions.query` | `transactions:read` | read | — |
| `spending.analysis` | `transactions:read` | read | — |
| `exchange.quote` | `exchange:read` | read | — |
| `exchange.trade` | `exchange:write` | write | `idempotency_key` + per-token spending limit |
| `ramp.start` | `ramp:write` | write | returns Stripe Bridge checkout URL; `idempotency_key` required |
| `ramp.status` | `ramp:read` | read | — |
| `mpp.discovery` | (public) | read | — |
| `sms.send` | `sms:send` | write | `idempotency_key` + x402 settlement per message |

Two of the twelve are **new wrappers** for existing controllers; the other ten reuse existing tool classes from `App\Domain\AI\MCP\Tools\*`:

- **NEW: `ramp.start`** — wraps `App\Http\Controllers\Api\V1\RampController::session()` (Stripe Bridge onramp/offramp).
- **NEW: `ramp.status`** — wraps `RampController::getSession()`.

The other 10 are bridged via `McpToolAdapter` from existing classes:

- `account.balance` → `AccountBalanceTool`
- `account.create` → `CreateAccountTool`
- `payment.status` → `PaymentStatusTool`
- `payment.transfer` → `TransferTool`
- `transactions.query` → `TransactionQueryTool`
- `spending.analysis` → `SpendingAnalysisTool`
- `exchange.quote` → `QuoteTool`
- `exchange.trade` → `TradeTool`
- `mpp.discovery` → `MppDiscoveryTool`
- `sms.send` → `SmsSendTool`

### 6.5 Resources — 4 read-context primitives

| Resource URI | Scope | Returns |
|---|---|---|
| `account://profile` | `accounts:read` | user's primary account metadata |
| `account://balance/{currency}` | `accounts:read` | live balance for a currency |
| `transactions://recent?limit=N` | `transactions:read` | last N transactions |
| `transaction://{id}` | `transactions:read` | single transaction lookup |

Resources are read-only context the agent can pull into its window without firing a tool call (cheaper, cached, more LLM-friendly for browsing).

### 6.6 Spending limits

Spending limits are attached to the access token at consent time, not the OAuth scope.

- **Default** per-token aggregate: **$500 / 24h rolling window**, applied across `payment.transfer` + `exchange.trade` + `ramp.start` + `sms.send` settlement.
- **Consent UI** offers a slider: $50 / $500 / $2k / $10k / "no limit — I trust this app".
- **Server-side enforced.** Rejected calls return JSON-RPC error `-32001 SPENDING_LIMIT_EXCEEDED` with `data.limit_remaining_minor` and `data.window_resets_at`.
- Configurable per-client overrides (trusted partners) via `mcp_token_policies.daily_limit_minor`.

### 6.7 Consent screen

Custom Blade view rendered by `ConsentScreenController` over Passport's `/oauth/authorize`. Lists requested scopes in plain English ("Send payments up to $500/day", "Read your transaction history", "Send SMS messages"). Shows requesting client's name + logo (from DCR registration metadata). Includes the spending-limit slider. Renders the FinAegis brand styling, not Passport's default.

## 7. Operational layer

### 7.1 New tables

```
mcp_tool_invocations
  id, token_id (FK), client_id (FK), user_id (nullable, FK)
  tool_name, args_hash (sha256 hex), idempotency_key (nullable)
  result_status (success|error|rate_limited|spending_limit), error_code (nullable)
  settlement_amount_minor (nullable), settlement_currency (nullable)
  ip, user_agent, request_id, created_at
  INDEX (token_id, created_at)
  INDEX (idempotency_key, token_id, tool_name)

mcp_token_policies
  id, token_id (unique FK to oauth_access_tokens)
  daily_limit_minor, daily_limit_currency
  daily_spend_minor, daily_window_start_at        -- rolls every 24h via job
  scoped_tools (JSON array — null = all tools allowed by token's scopes)
  created_at, updated_at

oauth_clients (extend Passport's existing table)
  + dcr_metadata_uri (nullable)
  + client_logo_url, client_terms_url, client_privacy_url
  + registration_method (enum: dcr | manual | passport_native)
```

### 7.2 Idempotency

Redis-backed cache, key pattern `mcp:idem:{token_id}:{tool_name}:{idempotency_key}`, TTL 24h.

| Scenario | Behavior |
|---|---|
| First call with key K, args A | Execute, cache `{args_hash, result}` keyed by K, return result |
| Retry with K + A (matching args_hash) | Return cached result; do NOT re-execute |
| Retry with K + A' (different args_hash) | Return JSON-RPC error `-32002 IDEMPOTENCY_KEY_REUSED` |
| Multi-step writes (e.g., `payment.transfer` triggers async settlement) | Cache `"in_progress"` with poll URL; replace on settlement |

`idempotency_key` is required on every write tool — JSON-RPC schema enforces it. Reads silently ignore the field if sent.

### 7.3 Kill-switches

Config-driven, deploy-free toggles via `php artisan config:cache`:

```php
// config/mcp.php
'enabled' => env('MCP_ENABLED', true),                      // global
'tools' => [
    'payment'  => ['transfer' => ['enabled' => env('MCP_TOOL_PAYMENT_TRANSFER', true)]],
    'exchange' => ['trade'    => ['enabled' => env('MCP_TOOL_EXCHANGE_TRADE', true)]],
    'sms'      => ['send'     => ['enabled' => env('MCP_TOOL_SMS_SEND', true)]],
    // ...
],
'scopes' => [
    'payments:write' => ['enabled' => env('MCP_SCOPE_PAYMENTS_WRITE', true)],
    // ...
],
```

Disabled tools are *omitted from `tools/list`* (so agents don't propose using them) AND return `-32004 TOOL_DISABLED` if invoked directly. Disabled scopes 401 every call. Emergency response is "flip env, restart workers, problem isolated within seconds, no deploy required."

### 7.4 Rate limits

Three tiers, expressed as Laravel `RateLimiter` rules:

| Tier | Limit | Why |
|---|---|---|
| Per token (default) | 60/min, 600/hr, 5000/day | aggregate budget |
| Read tool family | 120/min | cheap, latency-sensitive |
| Write tool family | 30/min | settlement is expensive |
| `sms.send` specifically | 10/min | x402 settlement + carrier round-trip |
| Unauthed discovery | 60/min/IP | DoS protection on `.well-known/*` |

Exceeding returns `-32003 RATE_LIMITED` with `data.retry_after_seconds`. All limits configurable via `config/mcp.php`; per-`client_id` overrides supported for trusted partners.

### 7.5 Observability

Wires into existing infrastructure. No new tooling.

- **Metrics** (Prometheus via existing OpenTelemetry export):
  - `mcp_tool_invocations_total{tool, status}` — counter
  - `mcp_tool_duration_seconds{tool}` — histogram
  - `mcp_oauth_consents_total{scope, decision}` — counter
  - `mcp_active_sessions` — gauge (live SSE connections)
  - `mcp_spending_limit_hits_total{client_id}` — counter
- **Logs** — structured JSON via existing `Log` facade; correlation ID is the JSON-RPC request `id`.
- **Traces** — wrap each tool invocation in a span using existing distributed-tracing infra; tag spans with `mcp.tool`, `mcp.client_id`, `mcp.scope`.
- **Admin UI** — extend Filament admin with three resources: `MCP > OAuth Clients`, `MCP > Tool Invocations`, `MCP > Active Sessions`. Each row drillable. Block / revoke / kill from the UI.

### 7.6 Settlement attribution

Every $-impact tool invocation logs `settlement_amount_minor` + `settlement_currency` to `mcp_tool_invocations`. This feeds:

- The MCP revenue dashboard (Filament).
- The regulatory / AML feed (we must attribute every fund movement to a token + scope + client at audit time).

## 8. Distribution

### 8.1 The npm wrapper — `@finaegis/mcp`

```
packages/finaegis-mcp-stdio/
├── src/
│   ├── index.ts              # entrypoint — `npx -y @finaegis/mcp`
│   ├── stdio-relay.ts        # stdio JSON-RPC ↔ remote HTTPS bridge
│   ├── oauth-helper.ts       # opens browser, captures redirect, persists token to OS keychain
│   └── config.ts             # default URL, env var overrides (MCP_SERVER_URL, MCP_TOKEN_PATH)
├── package.json              # name: "@finaegis/mcp", bin: "./dist/index.js"
├── tsconfig.json
└── README.md
```

Built on Anthropic's official `@modelcontextprotocol/sdk`. We use their `StdioServerTransport` for stdin/stdout and an HTTP fetch loop for the upstream side. ~250 LoC TS + ~50 LoC tests.

**OAuth flow inside the wrapper:** first run opens system browser to `/oauth/authorize`, captures the redirect on `localhost:randomport`, exchanges code for token, persists token to OS keychain (`keytar` on macOS / Windows, `libsecret` on Linux). Subsequent runs read silently. Refresh handled in-process. No `--token` flag needed.

### 8.2 Why ship the wrapper even though most clients already support remote

- Older Claude Desktop versions (≤ 2025-09 builds) are stdio-only.
- Cursor on Windows has had streamable-HTTP regressions; stdio is rock solid.
- Local dev / CI: conformance tests can spin up the wrapper headlessly.
- Air-gapped enterprise: stdio + outbound HTTPS, not direct remote MCP.
- Marketing: "Add to Claude in one command: `npx -y @finaegis/mcp`" reads better than a JSON config snippet.

### 8.3 Release pipeline

Reuses existing `monorepo-split.yml`. One new job:

```yaml
mcp-stdio:
  source: packages/finaegis-mcp-stdio/
  mirror: FinAegis/mcp                  # new mirror repo
  npm_package: "@finaegis/mcp"
  release_tag_trigger: "mcp-v*"
```

Required secrets that already exist: `NPM_TOKEN` (Automation), `MIRROR_PAT` extended to cover `FinAegis/mcp`. Same CHANGELOG / release-notes pattern as `@finaegis/cli`.

### 8.4 Versioning

| Surface | Versioning | Rationale |
|---|---|---|
| Wire-protocol server | Tied to FinAegis app (current v7.10.8 → ships at v7.11.0) | Server is part of the monorepo |
| `@finaegis/mcp` (npm) | Independent semver, starts at `0.1.0` (preview) → `1.0.0` at GA | Wrapper has independent release cadence |
| MCP spec version supported | Declared in `initialize` response | Target `2025-11-25`; advertise via `protocolVersion` field |

### 8.5 Registry submissions — scheduled fast-follows, not v1

1. **Smithery** (`smithery.ai`) — submit `smithery.yaml`. Free. Listing typically < 48h after PR. Submit ~7 days after npm `0.1.0` ships.
2. **Anthropic Connector Directory** — partner intake form. Requires privacy policy, terms, support contact, ~couple weeks of operational stability + ~50 active OAuth grants. Submit 2-4 weeks post-Smithery.
3. **Cursor / Continue.dev / Cline default registries** — third-tier, automated PRs. Bundle into the same follow-up PR batch.

Each submission is a 30-minute PR. All four bundled into one "MCP-distribution" follow-up PR scheduled ~7-10 days after the npm package ships.

### 8.6 Marketing copy hook

Primary install line on every customer-facing surface:

```
Add Zelta to Claude Desktop:
  npx -y @finaegis/mcp

Or use the remote URL directly:
  https://mcp.zelta.app/mcp
```

## 9. Documentation deliverables

### 9.1 `docs/` folder

| File | Action |
|---|---|
| `docs/13-AI-FRAMEWORK/MCP_INTEGRATION.md` | Rewrite — replace internal-API focus with public MCP server |
| `docs/13-AI-FRAMEWORK/01-MCP-Integration.md` | Rewrite — same scope shift |
| **NEW** `docs/13-AI-FRAMEWORK/02-MCP-Server-Architecture.md` | Wire protocol, OAuth flow, scopes, file inventory |
| **NEW** `docs/13-AI-FRAMEWORK/03-MCP-Quickstart.md` | Connect Claude / Cursor in 30 seconds |
| **NEW** `docs/13-AI-FRAMEWORK/04-MCP-OAuth-Reference.md` | DCR endpoint + scope catalog + consent flow |
| **NEW** `docs/13-AI-FRAMEWORK/05-MCP-Tool-Reference.md` | All 12 tools + 4 resources, input/output schemas |
| `docs/partners/VERTEXSMS_Q13_BIDIRECTIONAL_MCP.md` | Fix — replace `mcp-manifest.json` claim with the real RFC 9728 discovery URL |
| `docs/VERSION_ROADMAP.md` | Add v7.11.0 — "MCP Server (public, OAuth)" |
| `CLAUDE.md` (project root) | Add MCP commands, routes, env vars, common pitfalls |
| `README.md` (project root) | Add MCP section + npm install line |

### 9.2 Website / developer portal

| File | Action |
|---|---|
| `resources/views/developers/mcp-tools.blade.php` | Full rewrite. Replace "16 banking tools" hero with public MCP server messaging. Sections: 30-second connect (npm + remote URL), OAuth scope list, full v1 catalog of 12 tools with copyable JSON schemas, link to `/.well-known/oauth-protected-resource` |
| **NEW** `resources/views/features/mcp.blade.php` | Dedicated marketing page |
| `resources/views/welcome.blade.php` | Hero badge: "Now MCP-compatible" |
| `resources/views/features/index.blade.php` | Add MCP feature card; bump domain count (56 → 57) |
| `resources/views/about.blade.php` | Update tech-stack mention to include MCP |
| `resources/views/pricing.blade.php` | Add "MCP usage included" line per tier |
| `resources/views/developers/index.blade.php` | Add MCP card linking to `/developers/mcp-tools` |
| Sitemap | Auto-included via `SitemapController` (gated by `SHOW_PROMO_PAGES`) |
| SEO + structured-data | Title, meta description, canonical, OG, Twitter card, JSON-LD on all new/updated pages |
| `.env.production.example` + `.env.zelta.example` | Add `MCP_ENABLED`, `MCP_HOST`, `MCP_DEFAULT_DAILY_LIMIT_USD`, plus per-tool kill switches |

The `post-phase-review` skill runs after this section completes (per the global CLAUDE.md instruction).

## 10. Test strategy

```
tests/Unit/Domain/MCP/
├── JsonRpcRouterTest.php           # method dispatch, error envelope shape
├── McpToolAdapterTest.php          # bridges existing tools correctly
├── DcrValidationTest.php           # client metadata validation
├── DiscoveryMetadataTest.php       # RFC 9728 schema compliance
├── OAuthScopeGuardTest.php         # 401 / 403 paths
├── IdempotencyCacheTest.php        # all four idempotency scenarios
├── SpendingLimitPolicyTest.php     # daily-window rolling, multi-currency
└── KillSwitchTest.php              # disabled tool behavior

tests/Feature/MCP/
├── ConnectionHandshakeTest.php     # 401 → discovery → DCR → authorize → token → initialize
├── ToolsListTest.php               # filtered by scopes; disabled tools omitted
├── ToolsCallTest.php               # success, missing scope, rate limit, spending limit
├── IdempotencyReplayTest.php       # cached result, args mismatch error
├── SseStreamTest.php               # GET /mcp event stream + reconnection
├── PublicDiscoveryTest.php         # .well-known/* unauthenticated
└── ClientCredentialsFlowTest.php   # partner sub-account path

tests/Integration/MCP/              # NEW directory
└── McpStdioConformanceTest.php     # boots npm wrapper via process, runs full protocol contract

packages/finaegis-mcp-stdio/test/   # TS-side tests
├── stdio-relay.test.ts             # Vitest
└── oauth-helper.test.ts
```

Manual pre-release smoke checklist (in PR description, not automated): Claude Desktop on macOS + Windows, Cursor, Continue.dev, raw curl against `/mcp`. ~1h total before tagging release.

## 11. Build sequence — 7 phases, ~14 working days

| Phase | Days | Output |
|---|---|---|
| **1. Foundation** | 3 | Subdomain routing, DCR endpoint, RFC 9728 discovery, custom consent screen, unit tests |
| **2. Wire protocol** | 4 | StreamableHttp transport, JSON-RPC router, McpToolAdapter, audit log, idempotency, spending-limit guard, feature tests |
| **3. Tool catalog v1** | 2 | Verify / wire 12 tools (mostly existing); add `ramp.start` + `ramp.status`; 4 resources; conformance suite |
| **4. npm wrapper** | 2 | `packages/finaegis-mcp-stdio/`, OAuth keychain helper, monorepo-split job, wrapper integration test |
| **5. Documentation + marketing** | 2 | All docs deliverables above + post-phase-review |
| **6. Pre-launch + ship** | 1 | Manual smoke tests, tag `mcp-v0.1.0` → npm publish, tag `v7.11.0` → app release |
| **7. Post-launch follow-ups** (scheduled) | +3 | Smithery PR, Anthropic Connector Directory submission, Cursor/Continue/Cline registry PRs, 2-week scheduled agent to audit grants & open issues |

## 12. Risks and open questions

| Risk | Mitigation |
|---|---|
| Spec churn — MCP spec is moving fast; `2025-11-25` may not be the final form when we ship | Pin `protocolVersion` in `initialize`. Server advertises only what it supports. Spec-version bumps become normal release work. |
| Passport DCR isn't a built-in Passport feature — we have to extend it | Implementation is well-trodden (RFC 7591); ~half-day of code. Risk is low. |
| OAuth consent UX must clearly communicate financial scope to non-technical users (regulator scrutiny) | Custom consent screen with plain-English scope descriptions and explicit spending-limit slider. Legal review before public launch. |
| Stolen access token + spending limit = up to $500/day exfiltration | Spending-limit default is the cap; users can lower at consent. Tokens are revocable from Filament admin and `/oauth/clients` user dashboard. Rate limits add a second wall. |
| First-time partner using `client_credentials` grant has unclear funding source | Partner sub-account provisioning is currently manual (operator-only). Documented in `docs/operations/`; not exposed to self-service at v1. |
| `mpp.discovery` exposed unauthenticated — DoS surface | Per-IP rate limit (60/min) + Cloudflare in front. Already covered. |
| Subdomain DNS / TLS provisioning lead time | Coordinate with infra team in Phase 1; Cloudflare DNS + same-app TLS cert (wildcard or new SAN). 1-2 day lead. |

## 13. Out of scope (deferred to later phases)

- Full AP2 mandate enforcement (per-call signed pre-authorization).
- User step-up confirmation (push-to-mobile-confirm on threshold).
- Sensitive tools: KYC, AML, Withdraw, AgentEscrow, AgentMandate, Visa CLI surface — each needs a per-tool risk audit.
- Self-service partner sub-account provisioning for `client_credentials` flow.
- Multi-server topology split (per-domain MCP servers).
- Public registry submissions (Smithery, Anthropic, Cursor, Continue, Cline) — scheduled fast-follows.
- MCP Prompts primitive support — only Tools and Resources at v1.
- Server-initiated sampling (LLM call-back from server to client).

## 14. Success criteria

- A user with Claude Desktop can connect to `https://mcp.zelta.app/mcp`, complete the OAuth consent flow, and successfully invoke `account.balance` on their own account in under 60 seconds from cold start.
- A partner with `client_credentials` can call `sms.send` and have a real SMS delivered via VertexSMS, with x402 settlement recorded in `mcp_tool_invocations`.
- All 12 v1 tools listed in Section 6.4 succeed against the conformance test suite.
- All 9 scopes in Section 6.3 enforce correctly under the feature tests in Section 10.
- Manual smoke tests pass on Claude Desktop (macOS + Windows), Cursor, Continue.dev, and the npm wrapper before tagging.
- Post-phase-review (per global CLAUDE.md) completes with no critical or important issues open.
- v7.11.0 release ships with the public-facing claim "MCP-compatible" backed by a working public endpoint, a working npm package, and a quickstart that takes < 60 seconds.
