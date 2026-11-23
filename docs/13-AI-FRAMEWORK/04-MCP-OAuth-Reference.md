# MCP OAuth Reference

This page explains how clients authenticate to `https://mcp.zelta.app/mcp`. The server follows OAuth 2.1 with Dynamic Client Registration (RFC 7591), the native-app rules (RFC 8252), protected resource metadata (RFC 9728) and PKCE (RFC 7636). The authorization server is Laravel Passport on the main app host, `https://zelta.app`.

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
            User sees the requested scopes and a spending-limit choice, Approve.

6. Client → POST /oauth/token with code + code_verifier
            ← { access_token, refresh_token, expires_in, scope }

7. Client → POST /mcp { "jsonrpc": "2.0", "method": "initialize", ... }
            Authorization: Bearer <access_token>
            ← { protocolVersion, capabilities, serverInfo }

8. Tool calls via JSON-RPC over POST /mcp
   Optional server-pushed events via SSE on GET /mcp (when MCP_SSE_ENABLED=true)
```

## Grant types

The `authorization_code` grant is the one that works. The user consents in the browser, and the client gets an access token and a refresh token. Cursor, Continue.dev and the npm relay (which Claude Desktop uses) rely on it.

The authorization server metadata also lists `client_credentials`, and `POST /oauth/register` accepts it in `grant_types`. It cannot call the MCP server today, for two reasons. First, registration always creates an authorization-code client and only echoes the requested grant types back. Second, `McpOAuthGuard` accepts only tokens that belong to a user. A token without a user gets `401` with `-32001`, so it cannot call any tool, including `mpp.discovery`. The router also checks `requires_user` and returns `-32006 USER_CONTEXT_REQUIRED`, as a second guard.

## Scope catalog

The catalog has 10 scopes. One tool, `mpp.discovery`, needs no scope, but it still needs a user-bound token.

| Scope | Tools |
|---|---|
| `accounts:read` | `account.balance`, `wallet.addresses`, `wallet.activity` |
| `accounts:write` | `account.create` |
| `payments:read` | `payment.status` |
| `payments:write` | `payment.transfer` |
| `transactions:read` | `transactions.query`, `spending.analysis` |
| `exchange:read` | `exchange.quote` |
| `exchange:write` | `exchange.trade` |
| `ramp:read` | `ramp.status` |
| `ramp:write` | `ramp.start` |
| `sms:send` | `sms.send` |
| (no scope) | `mpp.discovery` |

The production server has `account.create`, `payment.transfer`, `exchange.trade` and `sms.send` switched off. A token can hold their scopes, but the tools do not appear in `tools/list`.

Two scopes are split out on purpose.

- `sms:send` is separate from `payments:write`. A user can grant SMS access without granting payments.
- `transactions:read` is separate from `payments:read`. Transaction history shows much more than a single payment lookup, such as the full ledger, balances over time and counterparties.

## Dynamic Client Registration (RFC 7591)

`POST https://zelta.app/oauth/register`

```json
{
  "client_name":   "My Agent",
  "redirect_uris": ["http://127.0.0.1:53682/callback"],
  "grant_types":   ["authorization_code", "refresh_token"]
}
```

The server answers `201`:

```json
{
  "client_id":     "...",
  "client_secret": "...",
  "client_name":   "My Agent",
  "redirect_uris": ["http://127.0.0.1:53682/callback"],
  "grant_types":   ["authorization_code", "refresh_token"],
  "token_endpoint_auth_method": "client_secret_basic",
  "client_id_issued_at": 1714502400
}
```

The endpoint is open to anyone and limited only by rate. Every registered client is a confidential client with a secret.

### Redirect URI rules

The server accepts three forms of redirect URI and rejects anything else at registration.

- `https://...` is for hosted web clients.
- `http://127.0.0.1[:port]` or `http://[::1][:port]` is the loopback form for native apps (RFC 8252 §7.3). `http://localhost` is not accepted, so use the literal IP address.
- `com.example.app://...` is a reverse-DNS custom scheme for native apps. It must contain at least one dot.

Schemes such as `javascript:`, `data:` and `file:` are rejected.

### Client name brand policy

A `client_name` that contains a reserved word is rejected. The default words are zelta, finaegis, anthropic, claude, openai, gpt, stripe, paysera, visa, mastercard, official, admin, system, support and security. Operators can set their own list with `MCP_DCR_RESERVED_NAMES`. The rule blocks the most obvious phishing trick, where a client shows a trusted brand name on the consent screen.

## PKCE

Send PKCE on every `authorization_code` flow. The metadata advertises only the `S256` method.

```
verifier  = base64url(random(32))
challenge = base64url(sha256(verifier))
```

Send `code_challenge` and `code_challenge_method=S256` to `/oauth/authorize`. Send `code_verifier` to `/oauth/token`. The server rebuilds the challenge from the verifier and compares the two. If they do not match, the exchange fails.

## Refresh tokens

Every `authorization_code` flow returns a refresh token. Exchange it like this:

```
POST /oauth/token
  grant_type=refresh_token
  refresh_token=<rt>
  client_id=<id>
  client_secret=<secret>
```

The response has a new access token and may have a new refresh token. The npm relay refreshes tokens on its own.

The authorization server metadata lists `https://zelta.app/oauth/tokens` as `revocation_endpoint`, but the app has no such route yet.

## Token policies

Spending limits are stored in `mcp_token_policies`:

```
token_id | daily_limit_minor | daily_limit_currency | daily_spend_minor | daily_window_start_at | scoped_tools
```

- `token_id` holds the key `grant:{client_id}:{user_id}`. The limit belongs to the user's approval of the client, so refreshed and re-issued tokens share one allowance.
- `daily_limit_minor` is the daily limit in minor units. The consent screen offers $50, $500, $2,000, $10,000 or no limit. "No limit" is stored as `PHP_INT_MAX`, and a missing or unknown value gets the default of $500.
- `daily_spend_minor` is the total reserved in the current 24-hour window. The window restarts on the first reservation after it ends. No scheduled job resets it.
- `scoped_tools` is a JSON column for a list of approved tools. No code reads it yet.

The `CaptureMcpConsentLimit` middleware writes the row when the user approves the client on the consent screen. A repeated approval changes the limit and keeps what was already spent in the current window. A payment tool called without a saved row returns `-32003` with `error_code: NO_POLICY`. That happens for an approval made before v7.18.1. Passport skips the consent screen while the user still holds a valid token with the same scopes for that client. So the limit is saved only once those tokens have expired or the client asks for consent again with `prompt=consent`.

## Error envelopes

Most auth errors come back as JSON-RPC envelopes with HTTP status 200 and an `error` field. The exception is `-32001 UNAUTHENTICATED`. It returns HTTP 401 with a `WWW-Authenticate` header, which starts the discovery handshake described above.

| Code | Meaning |
|---|---|
| `-32001` | `UNAUTHENTICATED` — missing, expired, revoked or user-less bearer (401 + WWW-Authenticate) |
| `-32000` | `INSUFFICIENT_SCOPE` — the token lacks the tool's scope (`data.required`, `data.granted`) |
| `-32006` | `USER_CONTEXT_REQUIRED` — a token without a user called a user-bound tool |

## Where next

- [05-MCP-Tool-Reference.md](05-MCP-Tool-Reference.md) lists the tool schemas with examples.
- [02-MCP-Server-Architecture.md](02-MCP-Server-Architecture.md) explains how a request flows inside the server.
