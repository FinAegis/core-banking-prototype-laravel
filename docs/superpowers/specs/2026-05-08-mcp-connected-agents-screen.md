# Spec: Connected AI agents screen

**Status:** Ready for implementation. Draft scoped to backend + mobile work; web UI is a follow-up.

## Context

We shipped the public MCP server in v7.11 and the web Privy email-OTP login in v7.12. Users now sign up on web from a Claude Connectors Directory click and authorize Claude/Cursor/Continue.dev/etc. to act on their wallet via OAuth scopes with per-token spending limits.

Today there is no UI for managing those grants. Users cannot see which AI clients they've authorized, what scopes each has, or revoke them — that's a gap in trust posture (the Google Account "connected apps" pattern is what users expect from any OAuth-issuing platform) and a regulatory gap (GDPR-style data-subject control over third-party access).

This ticket adds:

1. A backend list/revoke endpoint exposing per-grant metadata
2. A mobile "Connections" screen surfacing those grants with a revoke button
3. (Future, separate ticket) the same UI on web

## Backend

### `GET /api/v1/grants`

Lists active grants for the authenticated user. Active = not revoked, not expired.

**Request:** Sanctum-authenticated, `read` scope.

**Query:** `?include_revoked=1` to also return revoked grants for audit.

**Response:**

```json
{
  "data": [
    {
      "id": "01HXYZ...",
      "client_name": "Claude Desktop",
      "client_id": "client_abc123",
      "scopes": ["accounts:read", "payments:write", "transactions:read"],
      "spending_limit": {
        "daily_minor": 50000,
        "currency": "USD"
      },
      "spending_used_today_minor": 12345,
      "issued_at": "2026-05-01T12:00:00Z",
      "last_used_at": "2026-05-07T08:30:00Z",
      "expires_at": null
    }
  ]
}
```

Fields:

- `id` — grant uuid (from `oauth_access_tokens.id`)
- `client_name` — display name set during DCR registration; reserved-name policy from `config('mcp.dcr.reserved_name_substrings')` already prevents brand spoofing
- `client_id` — for support diagnosis only; not displayed in mobile UI
- `scopes` — array of scope strings from `config('mcp.scopes')`
- `spending_limit` — null if no limit; else `{daily_minor: int, currency: string}`
- `spending_used_today_minor` — resets at user-local midnight (see Timezone section)
- `issued_at` / `last_used_at` / `expires_at` — ISO8601 strings; `last_used_at` null if never invoked
- `is_revoked` field is intentionally omitted from default list response (filter applied server-side)

### `DELETE /api/v1/grants/{id}`

Revoke a grant. Deletes the `oauth_access_token` row, invalidates any refresh token bound to it, writes an audit log entry. Idempotent — a revoke on an already-revoked grant returns 204.

**Request:** Sanctum-authenticated, `delete` scope, owns the grant.

**Response:** `204 No Content` on success, `404` if not found / not owned.

### Backing data

- Source: `oauth_access_tokens` (Passport)
- New table: `mcp_grant_metadata` — projection sourcing `client_name`, daily spend, `last_used_at`. Updated by the existing `ToolInvocationLogger` / `SpendingEnforcedToolCallSaga`.
- Migration to add the table + a backfill projector for existing tokens (small data set today, reasonable to backfill in one pass).

### Timezone

Mobile + web both need to display a "spending resets at midnight" hint that matches the server-enforced reset. Two pieces:

1. **`users.timezone`** column (IANA tz name, default `UTC`). New migration. Settable from the Profile screen on mobile.
2. **Mobile login plumbing**: `/api/v1/auth/privy-login` accepts an optional `timezone` field in the request body (`Intl.DateTimeFormat().resolvedOptions().timeZone`). Server stores it on `users.timezone` if missing or different. Backwards-compat: missing field is a no-op.
3. **Server-enforced reset**: `SpendingEnforcedToolCallSaga`'s daily reset uses `users.timezone` for the boundary computation rather than UTC.

## Mobile

- Path: `app/(profile)/connections.tsx` — naming chosen to avoid collision with existing `app/flows/agents/` (which is the user's own Virtuals AI agents that spend on their behalf via x402). "Connections" is the third-party-app metaphor users already understand from Google/Apple ID.
- Service: `src/services/grants.ts` — `listGrants()`, `revokeGrant(id)`. Uses existing API client.
- Type stubs: mobile dev pre-stubs based on this ticket; types are dead code until the screen ships.
- UI: list of cards per grant. Each card shows `client_name`, scopes (chips), `spending_used_today_minor` / `spending_limit.daily_minor` as a progress bar with reset countdown, last-used relative time. Tap to expand for full detail + revoke button.
- Revoke confirmation: native modal "Revoke access for Claude Desktop? It will need to authorize again to continue." Two buttons. Optimistic update (remove from list immediately, refetch on error).
- Empty state: "No connected AI assistants yet" with a link to `/connect` on the web.

## Web

Out of scope for this ticket. Web equivalent ships in a follow-up — same data, different UI tree.

## Acceptance criteria

- [ ] `GET /api/v1/grants` returns active grants only by default; `?include_revoked=1` includes revoked
- [ ] `DELETE /api/v1/grants/{id}` revokes the grant + invalidates refresh tokens + writes audit entry; idempotent
- [ ] `mcp_grant_metadata` table populated for existing tokens via backfill projector
- [ ] `users.timezone` column added; mobile sends device tz on `/api/v1/auth/privy-login`; server stores it
- [ ] `SpendingEnforcedToolCallSaga` daily reset uses `users.timezone` not UTC
- [ ] Mobile `(profile)/connections.tsx` lists grants, supports revoke, shows spend + reset countdown
- [ ] Tests: feature tests on the two endpoints + a unit test for the timezone-aware spending reset

## Non-goals

- Per-grant transaction history (separate ticket if it ships)
- Web UI (separate ticket)
- Granting new permissions retroactively (always done via fresh OAuth flow)
- Push approval for high-value MCP — see the design doc at `docs/superpowers/specs/2026-05-08-mcp-push-approval.md`
