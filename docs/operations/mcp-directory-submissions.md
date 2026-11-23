# MCP directory submissions runbook

This runbook is for operators. It explains how to publish the Zelta MCP server to public directories. The design spec (`docs/superpowers/specs/2026-04-27-mcp-server-design.md` §8.5) planned these listings as a follow-up once `@finaegis/mcp` was stable in operation.

## Overview

| Directory | Type | Status | Listing URL when live |
|---|---|---|---|
| Official MCP Registry | Protocol-level | ☐ TODO | `https://registry.modelcontextprotocol.io/v0/servers?search=zelta` |
| Anthropic Claude Connectors Directory | Official Claude.ai | ☐ TODO | Listed in-product under Connectors |
| Smithery | Community | ☐ TODO | `https://smithery.ai/server/app.zelta/mcp` |
| mcp.so | Community | ☐ TODO | `https://mcp.so/server/zelta` |
| PulseMCP | Community | ☐ TODO | `https://www.pulsemcp.com/servers/zelta` |

Update this file when a listing goes live.

## 1. Official MCP Registry

The official registry at `registry.modelcontextprotocol.io` is where MCP clients discover servers at the protocol level. Clients such as Claude Code use it when a user adds a server.

**Manifest:** `packages/finaegis-mcp-stdio/server.json` (committed in this repo).

The namespace `app.zelta/mcp` is `zelta.app` written in reverse-DNS form. It needs a one-time DNS challenge before the first publish.

### One-time setup to verify the DNS namespace

1. Install the publisher CLI:
   ```bash
   npm i -g @modelcontextprotocol/publisher
   ```

2. Initiate the DNS challenge:
   ```bash
   mcp-publisher login dns --domain zelta.app
   ```
   The CLI prints a TXT record value, e.g. `mcp-verify=<random-token>`.

3. Add the TXT record at the `_mcp-verify.zelta.app` host in Cloudflare. Wait for it to propagate, which usually takes less than five minutes. `dig TXT _mcp-verify.zelta.app +short` should then return the value.

4. Confirm the challenge:
   ```bash
   mcp-publisher login dns --confirm
   ```

5. After the verification succeeds, you can remove the TXT record, because the registry remembers the verification.

### Publishing a version

Run from the repo root:

```bash
mcp-publisher publish packages/finaegis-mcp-stdio/server.json
```

The CLI validates the JSON against `https://static.modelcontextprotocol.io/schemas/2025-12-11/server.schema.json`, confirms that we own the npm package `@finaegis/mcp` and pushes the entry. A new version replaces the old one. The `version` field in `server.json` MUST match the npm package version, or the ownership check fails.

### Update procedure on a new release

1. Cut the npm release as usual. An `mcp-v*` tag starts the `mcp-release.yml` workflow, which publishes `@finaegis/mcp@<v>`.
2. Bump `version` in `packages/finaegis-mcp-stdio/server.json` to match the new npm version.
3. Bump the `version` inside the `packages[0]` entry to the same value.
4. Re-run `mcp-publisher publish packages/finaegis-mcp-stdio/server.json`.

## 2. Anthropic Claude Connectors Directory

The Connectors Directory is Anthropic's curated listing inside its products. Every Claude.ai and Claude Desktop user can see it. It reaches the most people and has the slowest review, which usually takes two weeks.

**Submission URL:** `https://clau.de/mcp-directory-submission`

**Escalation contact (for example when a firewall blocks the review):** `mcp-review@anthropic.com`

### Pre-submission checklist

All of these must be true before you submit. Many submissions are rejected, and the form does not save partial drafts.

- [ ] `https://mcp.zelta.app/mcp` returns HTTP 200 to a `tools/list` request with a valid Bearer token
- [ ] `https://mcp.zelta.app/.well-known/oauth-protected-resource` resolves and points at the Passport AS
- [ ] `https://zelta.app/.well-known/oauth-authorization-server` returns valid AS metadata
- [ ] DCR endpoint at `https://zelta.app/oauth/register` accepts a fresh registration
- [ ] Privacy policy live at `https://zelta.app/legal/privacy`
- [ ] Terms of service live at `https://zelta.app/legal/terms`
- [ ] Every tool in `config('mcp.tools')` returns a `title` and either `readOnlyHint: true` or `destructiveHint: true` in `tools/list` (this is the #1 rejection cause)
- [ ] Test account credentials prepared (an OAuth-grant-capable Zelta account with a small balance, at least one registered wallet address, and a permissive spending limit for `ramp.start`)
- [ ] The tool inventory below matches a live `tools/list` on `mcp.zelta.app` (production disables `account.create`, `payment.transfer`, `exchange.trade` and `sms.send`; a reviewer who calls a listed-but-disabled tool gets `-32004 TOOL_DISABLED`)
- [ ] 3–5 PNG screenshots, ≥1000px wide, response-only (no prompt visible)
- [ ] Server logo SVG in `resources/img/zelta-logo.svg` (or hosted equivalent)

### Form values to paste

| Field | Value |
|---|---|
| Server name | Zelta |
| Server URL | `https://mcp.zelta.app/mcp` |
| Tagline | Connect your AI assistant to your Zelta account. |
| Description (long) | The Zelta MCP server exposes 10 tools — account balances, wallet addresses and activity, payment status, transaction history and spending analysis, exchange quotes, on/off-ramp sessions, and payment-route discovery — gated by OAuth 2.1 scopes with a daily spending limit on ramp sessions that the user chooses when connecting, idempotent writes, and a full audit trail. Converting stablecoins to fiat, where available, will be provided by licensed third parties under their own terms. |
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

The 10 tools `mcp.zelta.app` exposes in production:

| Public tool name | Title | Annotation | Scope |
|---|---|---|---|
| `account.balance` | Get account balance | readOnlyHint | accounts:read |
| `payment.status` | Look up payment status | readOnlyHint | payments:read |
| `transactions.query` | Query transaction history | readOnlyHint | transactions:read |
| `spending.analysis` | Spending analysis by category | readOnlyHint | transactions:read |
| `exchange.quote` | Get an exchange rate quote | readOnlyHint | exchange:read |
| `ramp.start` | Start an on/offramp session | destructiveHint (idempotent, spending-limited) | ramp:write |
| `ramp.status` | Check on/offramp session status | readOnlyHint | ramp:read |
| `mpp.discovery` | Discover multi-rail payment routes | readOnlyHint | (none — public) |
| `wallet.addresses` | List wallet addresses | readOnlyHint | accounts:read |
| `wallet.activity` | Recent wallet activity | readOnlyHint | accounts:read |

The inventory leaves out `account.create`, `payment.transfer`, `exchange.trade` and `sms.send`. These tools depend on the deployment and are off in production (`MCP_TOOL_*=false` in `.env.zelta.example`, and `exchange.trade` also needs `TRADING_ENABLED`), so `mcp.zelta.app` does not list them in `tools/list`. If a deployment turns them on, update this inventory, `packages/finaegis-mcp-stdio/server.json` and `smithery.yaml` together.

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

Submission is an open issue.

**Procedure:**
1. Go to `https://github.com/chatmcp/mcp-directory/issues/new` (or the form on `https://mcp.so/submit`).
2. Title: `[New Server] Zelta — non-custodial wallet MCP`.
3. Body: copy the description from `server.json` and link to:
   - Repo: `https://github.com/FinAegis/core-banking-prototype-laravel`
   - npm: `https://www.npmjs.com/package/@finaegis/mcp`
   - Docs: `https://finaegis.org/features/mcp`

## 5. PulseMCP

`https://www.pulsemcp.com/submit`

The form fields are similar to Anthropic's, but the bar is lower. The same description, logo and documentation link are enough. PulseMCP does not check tool annotations.

## Checking the listings after submission

Search from a fresh shell to confirm that the listings are live and can be found.

```bash
# Official registry
curl -s "https://registry.modelcontextprotocol.io/v0/servers?search=zelta" | jq '.servers[].server.name'

# Smithery
curl -s "https://smithery.ai/api/servers?q=zelta" | jq '.[].name'

# mcp.so — visual check at https://mcp.so/?q=zelta
# PulseMCP — visual check at https://www.pulsemcp.com/servers?q=zelta
```

You can count new Dynamic Client Registration (DCR) registrations for each directory. Give the `client_name` a prefix during submission, for example `Anthropic Connectors:` for the flows inside Claude. This query then attributes new clients:

```sql
SELECT client_name, COUNT(*)
FROM oauth_clients
WHERE created_at > '2026-05-01'
GROUP BY client_name
ORDER BY COUNT(*) DESC;
```
