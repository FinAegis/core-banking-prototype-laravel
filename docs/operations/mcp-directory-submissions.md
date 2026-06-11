# MCP directory submissions runbook

Operator runbook for publishing the Zelta MCP server to public directories. Bundled per the design spec (`docs/superpowers/specs/2026-04-27-mcp-server-design.md` §8.5) as a fast-follow after `@finaegis/mcp` reached operational stability.

## Overview

| Directory | Type | Status | Listing URL when live |
|---|---|---|---|
| Official MCP Registry | Protocol-level | ☐ TODO | `https://registry.modelcontextprotocol.io/v0/servers?search=zelta` |
| Anthropic Claude Connectors Directory | Official Claude.ai | ☐ TODO | Listed in-product under Connectors |
| Smithery | Community | ☐ TODO | `https://smithery.ai/server/app.zelta/mcp` |
| mcp.so | Community | ☐ TODO | `https://mcp.so/server/zelta` |
| PulseMCP | Community | ☐ TODO | `https://www.pulsemcp.com/servers/zelta` |

Update this file when each listing goes live.

## 1. Official MCP Registry

The official registry at `registry.modelcontextprotocol.io` is the protocol-level discovery surface used by clients such as Claude Code's MCP add-server flow.

**Manifest:** `packages/finaegis-mcp-stdio/server.json` (committed in this repo).

The namespace `app.zelta/mcp` is the reverse-DNS of `zelta.app` and requires a one-time DNS challenge before the first publish.

### One-time setup — DNS namespace verification

1. Install the publisher CLI:
   ```bash
   npm i -g @modelcontextprotocol/publisher
   ```

2. Initiate the DNS challenge:
   ```bash
   mcp-publisher login dns --domain zelta.app
   ```
   The CLI prints a TXT record value, e.g. `mcp-verify=<random-token>`.

3. Add the TXT record at the `_mcp-verify.zelta.app` host in Cloudflare. Wait for propagation (typically < 5 minutes — `dig TXT _mcp-verify.zelta.app +short` should return the value).

4. Confirm the challenge:
   ```bash
   mcp-publisher login dns --confirm
   ```

5. Once verified, the TXT record can be removed (the registry caches the verification).

### Publishing a version

Run from the repo root:

```bash
mcp-publisher publish packages/finaegis-mcp-stdio/server.json
```

The CLI validates the JSON against `https://static.modelcontextprotocol.io/schemas/2025-12-11/server.schema.json`, confirms npm-package ownership of `@finaegis/mcp`, and pushes the entry. New versions overwrite — the `version` field in `server.json` MUST track the npm package version for ownership verification to succeed.

### Update procedure on a new release

1. Cut the npm release as usual (`mcp-v*` tag → `mcp-release.yml` workflow → publishes `@finaegis/mcp@<v>`).
2. Bump `version` in `packages/finaegis-mcp-stdio/server.json` to match the new npm version.
3. Bump the `version` inside the `packages[0]` entry to the same value.
4. Re-run `mcp-publisher publish packages/finaegis-mcp-stdio/server.json`.

## 2. Anthropic Claude Connectors Directory

The Connectors Directory is Anthropic's curated in-product listing — visible to every Claude.ai and Claude Desktop user. Highest reach, slowest review (typically two weeks).

**Submission URL:** `https://clau.de/mcp-directory-submission`

**Escalation contact (firewall blocks etc.):** `mcp-review@anthropic.com`

### Pre-submission checklist

All of these must be true before submitting (rejection rate is high; the form does not save partial drafts):

- [ ] `https://mcp.zelta.app/mcp` returns HTTP 200 to a `tools/list` request with a valid Bearer token
- [ ] `https://mcp.zelta.app/.well-known/oauth-protected-resource` resolves and points at the Passport AS
- [ ] `https://zelta.app/.well-known/oauth-authorization-server` returns valid AS metadata
- [ ] DCR endpoint at `https://zelta.app/oauth/register` accepts a fresh registration
- [ ] Privacy policy live at `https://zelta.app/legal/privacy`
- [ ] Terms of service live at `https://zelta.app/legal/terms`
- [ ] Every tool in `config('mcp.tools')` returns a `title` and either `readOnlyHint: true` or `destructiveHint: true` in `tools/list` (this is the #1 rejection cause)
- [ ] Test account credentials prepared (an OAuth-grant-capable Zelta account with a small balance and permissive spending limit)
- [ ] 3–5 PNG screenshots, ≥1000px wide, response-only (no prompt visible)
- [ ] Server logo SVG in `resources/img/zelta-logo.svg` (or hosted equivalent)

### Form values to paste

| Field | Value |
|---|---|
| Server name | Zelta |
| Server URL | `https://mcp.zelta.app/mcp` |
| Tagline | Send payments, manage accounts, and trade across multiple rails — all from your AI assistant. |
| Description (long) | Zelta is a multi-rail payments and wallet platform. The MCP server exposes 14 tools — accounts, wallet, payments, transactions, exchange, on/offramp, and SMS — gated by OAuth 2.1 scopes with per-token daily spending limits on payments, ramp sessions, and SMS sends, idempotent writes, and a full audit trail. |
| Auth type | OAuth 2.1 (with DCR, RFC 7591) |
| Transport | streamable-http |
| Capabilities | tools, resources |
| Privacy policy | `https://zelta.app/legal/privacy` |
| Terms of service | `https://zelta.app/legal/terms` |
| Documentation | `https://finaegis.org/features/mcp` |
| Support contact | `support@zelta.app` |
| GA date | (date this PR merges) |
| Tested surfaces | Claude Desktop, Claude Code, Continue.dev, Cursor |

### Tool list (paste into the form's tool inventory section)

| Public tool name | Title | Annotation | Scope |
|---|---|---|---|
| `account.balance` | Get account balance | readOnlyHint | accounts:read |
| `account.create` | Create a new account | destructiveHint | accounts:write |
| `payment.status` | Look up payment status | readOnlyHint | payments:read |
| `payment.transfer` | Send a payment | destructiveHint (idempotent, spending-limited) | payments:write |
| `transactions.query` | Query transaction history | readOnlyHint | transactions:read |
| `spending.analysis` | Spending analysis by category | readOnlyHint | transactions:read |
| `exchange.quote` | Get an exchange rate quote | readOnlyHint | exchange:read |
| `exchange.trade` | Execute an exchange trade | destructiveHint (idempotent; not yet spending-limited — fiat notional unknown until the order fills) | exchange:write |
| `ramp.start` | Start an on/offramp session | destructiveHint (idempotent, spending-limited) | ramp:write |
| `ramp.status` | Check on/offramp session status | readOnlyHint | ramp:read |
| `mpp.discovery` | Multi-rail payment route discovery | readOnlyHint | (none — public) |
| `sms.send` | Send an SMS (paid per-message via x402) | destructiveHint (idempotent, spending-limited — flat per-message charge) | sms:send |
| `wallet.addresses` | List wallet addresses | readOnlyHint | accounts:read |
| `wallet.activity` | Recent wallet activity | readOnlyHint | accounts:read |

## 3. Smithery

Submission is a PR against `https://github.com/smithery-ai/registry`.

**Manifest:** `packages/finaegis-mcp-stdio/smithery.yaml` (committed in this repo).

### Procedure

1. Fork `smithery-ai/registry` to a personal GitHub account.
2. Create a new directory in the fork: `servers/app.zelta/mcp/` (mirror the official-registry namespace).
3. Copy `packages/finaegis-mcp-stdio/smithery.yaml` to `servers/app.zelta/mcp/smithery.yaml`.
4. Add a `README.md` in the same directory linking back to `https://finaegis.org/features/mcp`.
5. Open a PR titled `Add Zelta MCP server`. Reference this repo + the `mcp-v*` tag of the latest published `@finaegis/mcp`.
6. Listing typically appears within 48 hours of merge.

## 4. mcp.so

Open-issue submission.

**Procedure:**
1. Go to `https://github.com/chatmcp/mcp-directory/issues/new` (or the form on `https://mcp.so/submit`).
2. Title: `[New Server] Zelta — multi-rail payments MCP`.
3. Body: copy the description from `server.json` and link to:
   - Repo: `https://github.com/FinAegis/core-banking-prototype-laravel`
   - npm: `https://www.npmjs.com/package/@finaegis/mcp`
   - Docs: `https://finaegis.org/features/mcp`

## 5. PulseMCP

`https://www.pulsemcp.com/submit`

Form fields are similar to Anthropic's but the bar is lower — same description, same logo, same docs link suffices. No tool annotation gate.

## After submission — measurement

Confirm listings are live and discoverable by searching from a fresh shell:

```bash
# Official registry
curl -s "https://registry.modelcontextprotocol.io/v0/servers?search=zelta" | jq '.servers[].server.name'

# Smithery
curl -s "https://smithery.ai/api/servers?q=zelta" | jq '.[].name'

# mcp.so — visual check at https://mcp.so/?q=zelta
# PulseMCP — visual check at https://www.pulsemcp.com/servers?q=zelta
```

Track inbound DCR registrations per directory by tagging the `client_name` prefix during submission (e.g. `Anthropic Connectors:` for Claude in-product flows). Use this query to attribute new clients:

```sql
SELECT client_name, COUNT(*)
FROM oauth_clients
WHERE created_at > '2026-05-01'
GROUP BY client_name
ORDER BY COUNT(*) DESC;
```
