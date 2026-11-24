# MCP Integration Guide

The Zelta MCP server at `https://mcp.zelta.app/mcp` is the public Model Context Protocol server built on FinAegis. It has been public since v7.11.0. Any client that follows the MCP spec can connect to it, for example Cursor, Continue.dev, or Claude Desktop through the npm relay.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

## Quick Connect

### Remote URL clients

Clients that support remote MCP servers, such as Cursor and Continue.dev, connect to `https://mcp.zelta.app/mcp` directly. [03-MCP-Quickstart.md](03-MCP-Quickstart.md) has the exact settings.

### Stdio-only clients (npm)

```bash
npx -y @finaegis/mcp --login
```

The relay opens a browser for OAuth consent and saves the token. By default it keeps the token in `~/.config/finaegis-mcp/tokens.json` with file mode `0600`. Set `FINAEGIS_MCP_TOKEN_STORE=keychain` (with `keytar` installed globally) to use the operating system keychain instead. Later launches do not ask again. Run `npx @finaegis/mcp --logout` to delete the token.

## Discovery

| URL | Purpose |
|---|---|
| `https://mcp.zelta.app/.well-known/oauth-protected-resource` | RFC 9728 protected-resource metadata |
| `https://zelta.app/.well-known/oauth-authorization-server` | RFC 8414 authorization-server metadata |
| `https://zelta.app/oauth/register` | RFC 7591 dynamic client registration |

## Tool Catalog (v1)

The catalog in `config/mcp.php` has 14 tools. The production server exposes 10 of them. `account.create`, `payment.transfer`, `exchange.trade` and `sms.send` are off in production, and `exchange.trade` also needs `TRADING_ENABLED`.

| Tool | Scope | Purpose |
|---|---|---|
| `account.balance` | `accounts:read` | Read balance |
| `account.create` | `accounts:write` | Create a new account record |
| `payment.status` | `payments:read` | Check payment status |
| `payment.transfer` | `payments:write` | Send a payment (subject to spending limit) |
| `transactions.query` | `transactions:read` | List transactions |
| `spending.analysis` | `transactions:read` | Categorized spend |
| `exchange.quote` | `exchange:read` | Get an exchange quote |
| `exchange.trade` | `exchange:write` | Place an exchange order (not counted against the spending limit) |
| `ramp.start` | `ramp:write` | Start onramp/offramp |
| `ramp.status` | `ramp:read` | Poll ramp status |
| `mpp.discovery` | (no scope) | List supported payment rails |
| `sms.send` | `sms:send` | Send SMS (per-user and platform daily send limits; subject to spending limit) |
| `wallet.addresses` | `accounts:read` | List registered wallet addresses |
| `wallet.activity` | `accounts:read` | Recent wallet activity |

## Resources (read-context)

| URI | Scope | Purpose |
|---|---|---|
| `account://profile` | `accounts:read` | Authenticated user profile |
| `account://balance/{currency}` | `accounts:read` | Single-currency balance |
| `transactions://recent` | `transactions:read` | 25 most recent transactions |
| `transaction://{id}` | `transactions:read` | One transaction by id |

## Spending Limits

The user picks a daily spending limit on the consent screen when approving a client. The choices are $50, $500, $2,000, $10,000 or no limit, and the default is $500. The server saves the limit for the user's approval of that client, so refreshed tokens share the same limit. The window is a rolling 24 hours.

The limit covers `payment.transfer`, `ramp.start` and `sms.send`. The server reserves the amount before the tool runs and releases it if the tool fails. A call over the limit returns `-32003 SPENDING_LIMIT_EXCEEDED`. A call with no saved limit returns `-32003` with `error_code: NO_POLICY`. That happens for an approval made before v7.18.1. The limit is saved on the next consent screen the user sees for that client.

## Idempotency

Every write tool needs an `idempotency_key`. Use a UUID; the server accepts at most 128 characters. The server caches the result for 24 hours.

| State | Outcome |
|---|---|
| Same key + same args | Cached replay (read of original result) |
| Same key + different args | `-32002 IDEMPOTENCY_KEY_REUSED` |
| Concurrent retry of in-flight key | `-32005 IDEMPOTENCY_KEY_IN_FLIGHT` (retry shortly) |

## Error codes (JSON-RPC `error.code`)

| Code | Meaning |
|---|---|
| `-32000` | `INSUFFICIENT_SCOPE` — the token lacks the tool's scope |
| `-32001` | `UNAUTHENTICATED` — missing / expired bearer; refresh and retry. Returned with 401 + `WWW-Authenticate: Bearer resource_metadata=...` |
| `-32002` | `IDEMPOTENCY_KEY_REUSED` — same key sent with different args; pick a fresh key |
| `-32003` | `SPENDING_LIMIT_EXCEEDED` — daily limit hit; wait for window reset (see `data.window_resets_at`) |
| `-32004` | `TOOL_DISABLED` — operator-disabled via `MCP_TOOL_*` flag |
| `-32005` | `IDEMPOTENCY_KEY_IN_FLIGHT` — concurrent retry of an in-progress write; retry after a short delay |
| `-32006` | `USER_CONTEXT_REQUIRED` — a token without a user tried to call a user-bound tool |
| `-32601` | `TOOL_NOT_FOUND` / `METHOD_NOT_FOUND` — unknown tool or JSON-RPC method |
| `-32602` | `IDEMPOTENCY_KEY_REQUIRED` / `IDEMPOTENCY_KEY_TOO_LONG` — write tool called without a key, or with a key over 128 characters |
| `-32099` | `TRANSPORT_ERROR` — synthesized client-side by the npm relay on network / TLS failure |

## Internal REST endpoints

The older endpoints under `/api/ai/mcp/tools/*` need a Sanctum token. They are a demo. `MCPToolsController` lists six sample tools of its own and returns fixed sample data, so these endpoints do not reach the tool registry or real accounts. Build new integrations on the public MCP server, which uses JSON-RPC, OAuth, scopes and idempotency keys.

## Where next

- [02-MCP-Server-Architecture.md](02-MCP-Server-Architecture.md) explains the request lifecycle, the spending-limit saga, the idempotency cache and the audit log.
- [03-MCP-Quickstart.md](03-MCP-Quickstart.md) shows how to connect a client.
- [04-MCP-OAuth-Reference.md](04-MCP-OAuth-Reference.md) covers scopes, client registration, PKCE, token policies and refresh.
- [05-MCP-Tool-Reference.md](05-MCP-Tool-Reference.md) lists the schema of every tool and resource.
