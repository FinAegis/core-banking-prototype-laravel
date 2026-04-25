# Account Flags — Security Model

Security design for `account_flags`, the table that scopes reviewer / demo account bypasses. Operator runbook: `docs/operations/reviewer-accounts.md`.

## Purpose and threat model

External reviewers (Apple App Review, Google Play review, partner demo users, internal QA personas) cannot complete live KYC, device attestation, or sanctions screening, because the verification providers (Ondato, Apple App Attest, Google Play Integrity, ComplyAdvantage) treat their devices and identities as anomalous. Without a scoped bypass, reviewers cannot exercise the app past the first onboarding step and the review is rejected.

`account_flags` opens **narrow, explicit, per-account, reversible** bypasses at five enforcement points. It is **not** a role (no RBAC is replaced), **not** an admin impersonation tool (the operator never assumes the reviewer's session), and **not** a per-request impersonation mechanism (flags are durable state bound to a single user row).

**Threat model coverage**

| Threat | Mitigation |
|---|---|
| Operator maliciously issues reviewer account to collude with external attacker | Operator must hold `admin` Spatie role; `--allow-production` required in prod; `created_by` FK records operator identity on every flag row |
| Reviewer account exfiltrated and abused after the review cycle | 60-day default / 90-day hard cap on `expires_at`; daily sweep auto-disables |
| Bypass flag forgotten and kept alive indefinitely | Same expiry sweep; `disabled_at` short-circuits every check |
| Real customer account silently promoted to reviewer | `is_review_account` is a master tag; promoting a real user into it is an auditable write, and the `AccountFlag.created_by` FK identifies the operator |
| Bypass used as "login as user" for support debugging | Purpose limitation is policy, enforced by the operator runbook (`docs/operations/reviewer-accounts.md`) and by the fact that flags are long-lived on a provisioned account, not attached to a support session |

## The bypass table

One row per `account_flags` column.

| Column | What it skips | Where it is consumed |
|---|---|---|
| `is_review_account` | Master tag (no enforcement effect by itself). | `AccountFlagsService::isReviewAccount()`; audit query key. |
| `bypass_device_attestation` | Apple App Attest / Google Play Integrity verification on login. | `BiometricJWTService::verifyDeviceAttestationForUser()` |
| `bypass_rate_limit` | Per-user API rate limiting (default 60 req/min). | `ApiRateLimitMiddleware`, `GraphQLRateLimitMiddleware` |
| `bypass_sanctions_screening` | ComplyAdvantage OFAC / PEP / sanctions list match during KYC workflow. | `PerformAmlScreeningActivity::execute()` |
| `bypass_sms_otp` | **Reserved** — no consumer; kept so a future SMS OTP path can plug in without a migration. | *(none — this codebase has no dedicated SMS OTP flow; phone verification is via Ondato KYC, covered by `kyc_override_level`)* |
| `suppress_notifications` | Outbound email / SMS / FCM push on system events. | `SuppressNotificationsListener` on the `NotificationSending` event |
| `kyc_override_level` | Real KYC level resolution; returns the override (0–3) instead. `null` = use real KYC. | `User::effectiveKycLevel()` |
| `note` | — | Free-text operator note (audit only). |
| `expires_at` | — | Daily sweep disables when now >= expires_at. |
| `created_by` | — | FK → `users.id`, the operator who issued the flag. |
| `disabled_at` | Short-circuits **every** bypass regardless of the individual columns. | `AccountFlagsService::isActive()` |

## Audit guarantees

Every bypass hit emits a structured `bypass.fired` log line:

```json
{
  "channel": "bypass.fired",
  "user_id": 41234,
  "bypass": "device_attestation",
  "reason": "review_account",
  "request_id": "01HXX...",
  "ts": "2026-04-24T12:00:01Z"
}
```

Additional audit anchors:

- `AccountFlag.created_by` — FK to the operator user; non-null on every row.
- `AccountFlag.is_review_account = true` — master query key for compliance review.
- `review_bypass` sentinel strings in seeded rows:
  - `cards.issuer = 'review_bypass'`
  - `trust_certificates.credential_type = 'review_bypass'`
  - `metadata.source = 'review_bypass'` on balances, rewards, and wallets
- `AccountPurged` domain event on purge — persisted to the event store.

Auditor query: `SELECT users.id, users.email, af.* FROM account_flags af JOIN users ON users.id = af.user_id WHERE af.is_review_account = 1`.

## Expiry and reversibility

- **Default expiry:** 60 days from provisioning.
- **Hard cap:** 90 days — the provisioning command rejects `--expires-in-days > 90`.
- **Daily sweep:** `account:disable-reviewer --all-expired` runs at 00:10 UTC via `routes/console.php`.
- **Manual disable:** sets `disabled_at = now()`; bypass columns are preserved for audit.
- **Re-enable:** clears `disabled_at`. The seeded wallet, card, cert, and rewards are untouched, so re-enabling is idempotent.
- **Purge:** anonymizes `users.email` to `purged+<uuid>@finaegis.invalid` and sets `disabled_at`. The row is not deleted — the User model does not use `SoftDeletes`, and referential integrity across cards, certificates, and balances is preserved.

## What this is NOT

- **Not a web-UI provisioning tool** — CLI only. No Filament screen in v1.
- **Not an admin-impersonation mechanism** — operators never assume the reviewer's session. All bypasses are evaluated against the reviewer's own authenticated context.
- **Not cross-tenant** — flags bind to a single `users.id`; multi-tenant reviewer accounts are out of scope.
- **Not a permission system** — the five flag columns are enforcement *opt-outs* at specific sites; they do not grant any new capability.

## Defense in depth

- `disabled_at` is checked **first** in `AccountFlagsService::isActive()`. Setting `disabled_at` alone is sufficient to kill every bypass even if the individual flag columns remain `true`.
- Per-request cache in `AccountFlagsService` (request-scoped memoization keyed by `user_id`) is invalidated on every flag update via the service's `forget()` call. Stale reads across requests cannot occur.
- Operator must hold the `admin` Spatie role to invoke the provisioning command; non-admin operators are rejected before any DB write.
- `--allow-production` is required when `APP_ENV=production`; the command otherwise refuses.
- `expires_at > now() + 90d` is rejected at the command layer.
- Email collision check — the command refuses if an existing user with that email is **not** already a reviewer account.

## Monitoring

Recommended Prometheus counter:

```
provisioning_bypass_fired_total{bypass="device_attestation|rate_limit|sanctions|notifications|kyc_level", user_id="..."}
```

Alert: `rate(provisioning_bypass_fired_total[1m]) > 100` from a single `user_id` → page on-call security. Suggested route: `#sec-incidents`, severity `high`.

Dashboard: one panel per bypass type showing `sum by (bypass) (rate(provisioning_bypass_fired_total[5m]))`. Baseline should be near-zero outside active review cycles.

## Revocation runbook

See `docs/operations/reviewer-accounts.md#incident-response` — `account:disable-reviewer --email=X` stops all bypasses in under 60 seconds.
