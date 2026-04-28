# MCP OAuth Reference

How clients authenticate to `https://mcp.zelta.app/mcp`. Standards: OAuth 2.1, RFC 7591 (Dynamic Client Registration), RFC 8252 (native apps), RFC 9728 (protected resource metadata), RFC 7636 (PKCE).

## Connection handshake

```
1. Client → POST https://mcp.zelta.app/mcp                     (no token)
   Server → 401 + WWW-Authenticate: Bearer
            resource_metadata="https://mcp.zelta.app/.well-known/oauth-protected-resource"

2. Client → GET /.well-known/oauth-protected-resource
   Server → { "authorization_servers": ["https://zelta.app"],
              "resource":             "https://mcp.zelta.app",
              "scopes_supported":     [ ...10 scopes... ] }

3. Client → GET https://zelta.app/.well-known/oauth-authorization-server
   Server → OIDC-aligned metadata (token_endpoint, authorization_endpoint,
                                   registration_endpoint, code_challenge_methods, ...)

4. Client → POST https://zelta.app/oauth/register             (RFC 7591 DCR)
   Server → { client_id, client_secret, ... }

5. Client → user-agent → /oauth/authorize?response_type=code&...
            (PKCE S256, scope=accounts:read+payments:write+...)
            User sees consent screen, picks scopes + spending limit, Approve.

6. Client → POST /oauth/token with code + code_verifier
            ← { access_token, refresh_token, expires_in, scope }

7. Client → POST /mcp { "jsonrpc": "2.0", "method": "initialize", ... }
            Authorization: Bearer <access_token>
            ← { protocolVersion, capabilities, serverInfo }

8. Tool calls via JSON-RPC over POST /mcp
   Optional server-pushed events via SSE on GET /mcp (when MCP_SSE_ENABLED=true)
```

## Two grant types

- **`authorization_code`** (default) — interactive user consent, refresh-token rollover. Used by Claude Desktop, Cursor, Continue.dev, the npm relay.
- **`client_credentials`** — partner pre-registers a confidential client and exchanges directly for a token bound to a *partner sub-account* (no user). Same scopes, same spending-limit policy mechanism. Used by VertexSMS-style integrations where the partner has its own funded sub-account. **Not allowed for user-bound tools** — those return `-32006 USER_CONTEXT_REQUIRED`. Currently only `mpp.discovery` is callable in this mode.

## Scope catalog

10 scopes plus one always-public tool.

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
| (no scope — public) | `mpp.discovery` |

Two intentional splits:

- **`sms:send` is split out from `payments:write`** so users can grant SMS-only access without exposing payments.
- **`transactions:read` is split out from `payments:read`** because transaction history reveals materially more (full ledger, balances over time, counterparties) than per-payment lookups.

## Dynamic Client Registration (RFC 7591)

`POST https://zelta.app/oauth/register`

```json
{
  "client_name":   "My Agent",
  "redirect_uris": ["http://127.0.0.1:53682/callback"],
  "grant_types":   ["authorization_code", "refresh_token"]
}
```

Response:

```json
{
  "client_id":     "...",
  "client_secret": "...",
  "client_id_issued_at": 1714502400,
  "redirect_uris": ["http://127.0.0.1:53682/callback"],
  "grant_types":   ["authorization_code", "refresh_token"]
}
```

### Redirect URI rules

The server allowlists three forms — anything else is rejected at registration:

- **`https://...`** — for hosted web clients.
- **`http://127.0.0.1[:port]`** or **`http://[::1][:port]`** — RFC 8252 §7.3 loopback for native apps. Note: `http://localhost` is **not** accepted; use the literal IP.
- **`com.example.app://...`** — reverse-DNS native scheme (custom URL scheme). Must contain at least one dot.

Schemes like `javascript:`, `data:`, `file:` are explicitly rejected.

### Client name brand policy

Names containing reserved brand substrings (zelta, finaegis, anthropic, claude, openai, gpt, stripe, paysera, visa, mastercard, official, admin, system, support, security) are rejected. Operators can add to this list via `MCP_DCR_RESERVED_NAMES`. The point is to block the most obvious phishing path on the consent screen.

## PKCE

Required on every `authorization_code` flow. Server only supports `S256` (`plain` is rejected).

```
verifier  = base64url(random(32))
challenge = base64url(sha256(verifier))
```

Send `code_challenge` + `code_challenge_method=S256` on `/oauth/authorize`. Send `code_verifier` on `/oauth/token`. The server rebuilds the challenge from the verifier and compares — mismatch → exchange fails.

## Refresh tokens

Every `authorization_code` flow returns a refresh token. Exchange:

```
POST /oauth/token
  grant_type=refresh_token
  refresh_token=<rt>
  client_id=<id>
  client_secret=<secret>
```

Response includes a fresh access token and may include a new refresh token (rolling refresh). The npm relay implements this transparently.

## Token policies

Each issued token has a row in `mcp_token_policies`:

```
token_id (FK) | daily_limit_minor | daily_limit_currency | daily_spend_minor | daily_window_start_at | scoped_tools
```

- `daily_limit_minor` — what the user picked on the consent slider ($50 / $500 / $2k / $10k / unlimited).
- `daily_spend_minor` — running total in the current 24h window. Reset by a daily job at the window boundary.
- `scoped_tools` — JSON array of tool names the user explicitly approved. `null` means "all tools allowed by the granted scopes" (the default). Operators can pre-narrow via per-client policy.

## Error envelopes

Auth errors return JSON-RPC envelopes (200 status with `error` field) **except** for `-32001 UNAUTHENTICATED`, which returns 401 + `WWW-Authenticate` header to drive the discovery handshake described above.

| Code | Meaning |
|---|---|
| `-32001` | `UNAUTHENTICATED` — missing / expired bearer (401 + WWW-Authenticate) |
| `-32006` | `USER_CONTEXT_REQUIRED` — `client_credentials` grant tried a user-bound tool |

## Where next

- [05-MCP-Tool-Reference.md](05-MCP-Tool-Reference.md) — tool schemas with examples
- [02-MCP-Server-Architecture.md](02-MCP-Server-Architecture.md) — how the request flows internally
