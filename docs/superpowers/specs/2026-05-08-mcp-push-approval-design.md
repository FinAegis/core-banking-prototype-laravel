# High-value MCP push approval — design

**Status:** Reviewed with mobile-side agent (2026-05-08). Four open questions resolved (see "Resolved decisions" section). Ready for implementation tickets.

**Goal:** When a Claude/Cursor/etc. agent connected to Zelta via MCP attempts a write tool whose financial impact crosses a threshold, hold the operation, push a confirmation request to the user's mobile app, and resume only after the user biometric-approves it. Reject after a configurable window, returning a deterministic terminal state to the agent.

**Non-goal:** Replacing the existing per-token spending limit. The spending limit is the *static* contract set on consent ("this agent can move at most $500/day"). Push approval is the *dynamic* gate ("any single move ≥$50 needs a fresh tap"). Both apply.

## Why now

We launched public MCP in v7.11 and web Privy login in v7.12. Token grants live indefinitely until revoked, so an agent that gets compromised between sessions can drain up to the daily spending cap before the user notices. Push approval converts the worst case from "lose the daily cap" to "ignore one push and lose nothing." Strong trust signal at the v7.13 marketing surface.

## Threshold model

Three layers, applied in order. First match wins.

1. **Per-grant override** (highest priority) — when the user issues consent for a new client, the OAuth consent screen offers an "approval threshold" slider with options `none`, `$10`, `$50`, `$200`, `$1000`, custom. Default `$50`. Stored on `oauth_access_tokens.approval_threshold_minor` (new column).
2. **Per-tool override** — `config('mcp.tools.<name>.approval_threshold_minor')`. For tools where a higher floor makes sense (e.g. `payment.transfer` defaults to `$50`, `exchange.trade` defaults to `$200` because exchange is rarely used for small amounts). Empty if no override.
3. **System default** — `config('mcp.spending.default_approval_threshold_minor', 5000)` (= $50). Floor for any tool not otherwise specified.

Reuse the X402 spending-limit table shape — the schema already has `daily_minor` + `per_tx_minor` slots; we add a third column `approval_threshold_minor`. Migration is small.

## Hold-and-notify protocol

`202 + Retry-After` polling, NOT streaming. Reasons:

- **Streaming exhausts a PHP-FPM worker per pending approval.** Mobile dev's call window is "a few seconds to several minutes" (Doze, push delivery, user opening modal). Holding workers that long under any meaningful concurrency exhausts our pool.
- **Polling gives idempotency for free.** The agent's retry within the polling window resolves to the same approval state via the in-flight idempotency lock we already use.
- **Polling matches the X402 pattern.** Mobile already has a `X402PaymentModal` UX; the approval flow is shaped the same.

### Sequence

```
agent              backend                  mobile
  |                   |                       |
  |-- tools/call ---->|                       |
  |   payment.transfer|                       |
  |   amount=200      |                       |
  |                   |--- evaluate threshold |
  |                   |--- create approval row|
  |                   |--- push to mobile --->|
  |                   |                       |--- biometric
  |<-- 202 Accepted --|                       |    sheet
  |   Retry-After: 5  |                       |
  |   approval_id:..  |                       |
  |                   |                       |
  |--- tools/call --->|                       |
  |   (same idempot.  |                       |
  |    key)           |                       |
  |<-- 202 Accepted --|                       |
  |   Retry-After: 5  |                       |
  |                   |                       |
  |                   |<-- POST approval ---  |
  |                   |    biometric attest.  |
  |                   |--- resume saga        |
  |                   |--- execute tool       |
  |                   |--- audit log          |
  |                   |                       |
  |--- tools/call --->|                       |
  |<-- 200 result ---|                       |
```

### MCP wire shape

When a tool call hits the threshold:

```json
HTTP/1.1 202 Accepted
Retry-After: 5

{
  "jsonrpc": "2.0",
  "id": <client_request_id>,
  "result": {
    "_meta": {
      "approval_status": "pending",
      "approval_id": "01HXYZ...",
      "approval_expires_at": "2026-05-08T12:05:00Z"
    }
  }
}
```

Subsequent polls with the same idempotency key:

- Still pending → same 202 shape
- Approved → real tool result, 200, idempotency key locks the result
- Rejected → MCP error code (see below)
- Timed out → MCP error code

## Mobile push payload

```json
{
  "type": "mcp_approval_required",
  "approval_id": "01HXYZ...",
  "client_name": "Claude Desktop",
  "tool_name": "payment.transfer",
  "summary": "Send $200.00 to Alex's account",
  "amount_minor": 20000,
  "currency": "USD",
  "expires_at": "2026-05-08T12:05:00Z",
  "deep_link": "zelta://approvals/01HXYZ..."
}
```

**Push priority:** `priority: high` on the FCM payload. Android Doze can delay non-priority pushes by minutes — for a 5-minute approval window that's catastrophic. Mobile dev confirmed `priority: high` is the right call.

**Other push categories** (balance changed, transaction received, etc.) stay at `priority: normal` so the elevated priority remains a signal rather than the default.

## Mobile UX

`useApprovalQueue` hook (state outlives modal mount lifecycle) → `<X402PaymentModal>` component reused for the sheet UI:

- Approval state lives in a global Zustand-style store, populated by FCM push handler and WebSocket fallback (existing infra).
- Pushes that arrive while another modal is open enqueue a new approval. Badge on the modal header: "2 approvals waiting" (FIFO order, newest grays out behind).
- User taps approve → biometric prompt → POST `/api/v1/grants/{grant_id}/approvals/{approval_id}` with `{decision: "approve", biometric_attestation: <signed token>, idempotency_key}`.
- User taps reject → POST same endpoint with `{decision: "reject"}`.
- Modal swipe-to-dismiss does NOT auto-reject; it just closes the modal. The approval stays in the queue (and in the dedicated `/approvals` list reachable from the home screen).

## Stale push handling

Auto-reject after **5 minutes** by default (configurable per-tool via `config('mcp.tools.<name>.approval_timeout_seconds')`).

Why 5 minutes:

- Long enough to cover Android Doze (typically 1-3 min, sometimes longer)
- Long enough for the user to see, switch apps, biometric-auth
- Short enough that the agent doesn't hold a turn for a meaningfully long time
- Matches typical OS-level push-notification visibility windows

After timeout, the approval row's status flips to `timed_out` server-side. Subsequent polls return the timeout error code.

## Error code reference

| Code | HTTP | Meaning | Agent-facing message |
|---|---|---|---|
| (no error) | 200 | Approved + tool executed | (tool result) |
| `MCP_APPROVAL_PENDING` | 202 | Awaiting user response | Retry-After polling |
| `MCP_USER_REJECTED` | 403 | User explicitly rejected | "User declined this action." |
| `MCP_USER_TIMEOUT` | 408 | Approval window expired | "User did not respond in time. Try again or use a smaller amount." |
| `MCP_APPROVAL_INVALIDATED` | 410 | Grant revoked while approval pending | "Authorization was revoked." |

`MCP_USER_REJECTED` and `MCP_USER_TIMEOUT` are deliberately distinct — the agent's response to "user said no" is different from "user is unreachable." Telemetry on the rate of each is a quality signal for the threshold tuning.

## Server-side data model

New tables:

```sql
CREATE TABLE mcp_approval_requests (
    id CHAR(26) PRIMARY KEY,                       -- ULID
    user_id BIGINT NOT NULL,
    grant_id CHAR(36) NOT NULL,                    -- oauth_access_tokens.id
    tool_name VARCHAR(64) NOT NULL,
    idempotency_key VARCHAR(64) NOT NULL,
    amount_minor BIGINT NOT NULL,
    currency CHAR(3) NOT NULL,
    summary TEXT NOT NULL,                          -- denormalized human-readable summary
    status ENUM('pending', 'approved', 'rejected', 'timed_out', 'invalidated') DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    decided_at TIMESTAMP NULL,
    decided_via ENUM('biometric_modal', 'auto_timeout', 'grant_revoked') NULL,
    INDEX idx_user_status (user_id, status),
    INDEX idx_expires_at (expires_at),              -- for the auto-timeout sweep
    UNIQUE KEY idempotency_key (grant_id, idempotency_key)
);
```

Plus the new column on `oauth_access_tokens`:

```sql
ALTER TABLE oauth_access_tokens
    ADD COLUMN approval_threshold_minor BIGINT NULL DEFAULT NULL;
```

`null` = use per-tool / system default; explicit value overrides (including `0` = always require, `INT_MAX` = never).

## Auto-timeout sweep

Cron at `*/1 * * * *`: `UPDATE mcp_approval_requests SET status='timed_out' WHERE status='pending' AND expires_at < NOW()`. Cheap; index on `expires_at`.

## Idempotency interaction

The MCP saga's existing idempotency lock (`SpendingEnforcedToolCallSaga::handle()`) coordinates approval lookup. On the first call:

1. Acquire idempotency lock
2. Evaluate threshold → triggers approval flow
3. Insert `mcp_approval_requests` row, send push
4. Return 202 + approval_id
5. Hold lock until approval terminal status

On subsequent polls (same idempotency key):

1. Lock is held → look up approval row by `(grant_id, idempotency_key)`
2. Pending → return 202
3. Approved → resume saga, execute tool, return 200, release lock
4. Rejected/timed_out/invalidated → return error code, release lock

The 202 + Retry-After polling means the lock-hold time is bounded by `expires_at + tool execution`. With a 5-min approval window + sub-second tool execution, lock-holds are reasonable.

## Spending-limit interaction

Approval is in addition to the spending limit. Order of checks in the saga:

1. **Spending limit** check (existing) — `daily_minor` + `per_tx_minor`. Reject with `MCP_SPENDING_LIMIT_EXCEEDED` if exceeded.
2. **Approval threshold** check (new) — if `amount >= threshold`, hold for approval.
3. Tool execution.

The reservation made by the spending-limit check is held during approval. If the user rejects, the reservation is released. If the user times out, same — reservation released.

## Telemetry

Add to existing `ToolInvocationLogger`:

- `approval_required` boolean
- `approval_decided_via` (biometric_modal / auto_timeout / grant_revoked / null if not approval-gated)
- `approval_decision_latency_ms`

Dashboards (Grafana):

- Approval rate by tool / amount bucket
- Time-to-decide percentiles (p50 / p95 / p99)
- Timeout rate (high timeout rate signals the threshold is too low)

## Rollout

Behind feature flag `MCP_PUSH_APPROVAL_ENABLED` (default false). Phases:

1. Ship with flag off; tables migrated; approval logic dormant.
2. Internal smoke test: enable for staff users only via a per-user override.
3. Open beta: enable for all users with `approval_threshold_minor` defaulting to `null` (no threshold) — users opt in via Profile → Connections settings.
4. General availability: flip default threshold to `$50` for new grants. Existing grants keep `null` (no threshold) until user opts in.

## Resolved decisions (after mobile dev review)

The four open questions from the original draft have been resolved in dialogue with the mobile-side agent. Decisions are normative for v1.

### Threshold UI on consent screen — three-tier picker

**Not a slider.** Slider rewards misuse: too easy to set $0.01 (tap fatigue) or $10000 (effective bypass). Three labelled tiers + an "Advanced" affordance for custom amounts:

- **None** — no approval gating, full discretion of the daily spending cap
- **Standard** — $50 default
- **Strict** — $10 default
- **Advanced** — custom integer in user's preferred currency

Matches the Apple Pay / Google Pay pattern for analogous limits. Stored on `oauth_access_tokens.approval_threshold_minor` as before; the picker UI just selects from three named presets.

### Push permission interaction — foreground-only WebSocket fallback, auto-reject otherwise

Mobile already has a `private-user.{userId}` channel; adding `onApprovalEvent` is a one-liner when the implementation lands. **Foreground only** — RN has no background WebSocket, so when push permissions are off AND the app is backgrounded, neither path delivers. The auto-reject-on-`expires_at` sweep is the only honest answer for that combination.

To reduce surprise:

- **Inline banner on the consent screen** when the user is selecting a non-`None` threshold and their OS push permission is off — "Approval-gated tools won't work in the background unless you allow notifications."
- **Inline banner on the Connections screen** for any active grant whose threshold is non-`None` and whose owning user has notifications disabled.

### Concurrent-request batching — defer to v2

v1 ships FIFO single modal with a "N pending" badge. **No batch-approve.** A user with five pending approvals could rubber-stamp without reading; that's the exact attack vector this feature defends against. When v2 ships, each approval still needs its own biometric attestation regardless.

### Web UI for approvals — defer to v2

Most MCP traffic today comes from desktop apps that hold their own browser session (Claude Desktop, Cursor, Continue.dev). MVP delivery: **mobile push only**. Web-on-the-same-machine users with an active mobile session still get the push delivered to their phone. Mobile-app-less web users set their threshold to `None` on consent if they want to bypass entirely.

Revisit at adoption ≥ X% (X TBD — pick when we have telemetry on the rate of "web-only user hits a threshold and times out").

## Build sequence

1. Migrations + threshold model + saga changes
2. Feature flag + dormant code path
3. Push delivery + auto-timeout sweep
4. Mobile `useApprovalQueue` + reuse `X402PaymentModal`
5. Approval submission endpoint + biometric attestation verification
6. Telemetry + Grafana dashboard
7. Internal smoke test
8. Open beta

Estimated 2 sprints with one engineer + mobile dev support.
