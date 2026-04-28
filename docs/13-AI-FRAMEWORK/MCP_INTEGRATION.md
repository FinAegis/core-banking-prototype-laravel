# MCP Integration Guide

> **As of v7.11.0**, FinAegis ships a public Model Context Protocol server at `https://mcp.zelta.app/mcp` that any spec-compliant MCP client (Claude Desktop, Cursor, Continue.dev) can connect to.

## Quick Connect

### Claude Desktop / Cursor (remote URL)

Paste into your client config:

```json
{ "mcpServers": { "zelta": { "url": "https://mcp.zelta.app/mcp" } } }
```

### Stdio-only clients (npm)

```bash
npx -y @finaegis/mcp
```

First launch opens a browser for OAuth consent. The token persists in your OS keychain — subsequent launches are silent. Run `npx @finaegis/mcp --logout` to clear it.

## Discovery

- `https://mcp.zelta.app/.well-known/oauth-protected-resource` — RFC 9728 protected-resource metadata
- `https://zelta.app/.well-known/oauth-authorization-server` — RFC 8414 authorization-server metadata
- `https://zelta.app/oauth/register` — RFC 7591 dynamic client registration

## Tool Catalog (v1)

| Tool | Scope | Purpose |
|---|---|---|
| `account.balance` | `accounts:read` | Read balance |
| `account.create` | `accounts:write` | Open new account |
| `payment.status` | `payments:read` | Check payment status |
| `payment.transfer` | `payments:write` | Send a payment (subject to spending limit) |
| `transactions.query` | `transactions:read` | List transactions |
| `spending.analysis` | `transactions:read` | Categorized spend |
| `exchange.quote` | `exchange:read` | Get an exchange quote |
| `exchange.trade` | `exchange:write` | Execute exchange |
| `ramp.start` | `ramp:write` | Start onramp/offramp |
| `ramp.status` | `ramp:read` | Poll ramp status |
| `mpp.discovery` | (public) | List supported payment rails |
| `sms.send` | `sms:send` | Send SMS, paid per-message via x402 |

## Resources (read-context)

| URI | Scope | Purpose |
|---|---|---|
| `account://profile` | `accounts:read` | Authenticated user profile |
| `account://balance/{currency}` | `accounts:read` | Single-currency balance |
| `transactions://recent` | `transactions:read` | 50 most recent transactions |
| `transaction://{id}` | `transactions:read` | One transaction by id |

## Spending Limits

Every access token has a per-token daily spending limit (default $500/24h, configurable on the consent screen at issuance time). Reservations are taken atomically before the underlying tool runs and rolled back if the tool reports an error. Exceeding the limit returns `-32003 SPENDING_LIMIT_EXCEEDED`.

## Idempotency

Every write tool requires `idempotency_key` (UUID, ≤128 chars). The server caches the result for 24h.

| State | Outcome |
|---|---|
| Same key + same args | Cached replay (read of original result) |
| Same key + different args | `-32002 IDEMPOTENCY_KEY_REUSED` |
| Concurrent retry of in-flight key | `-32005 IDEMPOTENCY_KEY_IN_FLIGHT` (retry shortly) |

## Error codes (JSON-RPC `error.code`)

| Code | Meaning |
|---|---|
| `-32001` | `UNAUTHENTICATED` — missing / expired bearer; refresh and retry. Returned with 401 + `WWW-Authenticate: Bearer resource_metadata=...` |
| `-32002` | `IDEMPOTENCY_KEY_REUSED` — same key sent with different args; pick a fresh key |
| `-32003` | `SPENDING_LIMIT_EXCEEDED` — daily limit hit; wait for window reset (see `data.window_resets_at`) |
| `-32004` | `TOOL_DISABLED` — operator-disabled via `MCP_TOOL_*` flag |
| `-32005` | `IDEMPOTENCY_KEY_IN_FLIGHT` — concurrent retry of an in-progress write; retry after a short delay |
| `-32006` | `USER_CONTEXT_REQUIRED` — client_credentials grant tried to call a user-bound tool |
| `-32099` | `TRANSPORT_ERROR` — synthesized client-side by the npm relay on network / TLS failure |

## Internal use (still supported)

The legacy internal REST endpoints `/api/ai/mcp/tools/*` continue to work for first-party AI integrations. The public MCP server is a separate surface — wire-protocol JSON-RPC, OAuth-authorized, scope-gated, idempotent. New integrations should target the public surface unless there's a specific reason to use the internal one.

## Where next

- [02-MCP-Server-Architecture.md](02-MCP-Server-Architecture.md) — request lifecycle, saga pattern, idempotency cache, audit log
- [03-MCP-Quickstart.md](03-MCP-Quickstart.md) — connect a client end-to-end in five minutes
- [04-MCP-OAuth-Reference.md](04-MCP-OAuth-Reference.md) — scopes, DCR, PKCE, token policies, refresh
- [05-MCP-Tool-Reference.md](05-MCP-Tool-Reference.md) — full schema for every tool and resource
