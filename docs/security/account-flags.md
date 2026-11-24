# Account Flags — Security Model

Security design for `account_flags`, the table that scopes reviewer / demo account bypasses. Operator runbook: `docs/operations/reviewer-accounts.md`.

## Purpose and threat model

External reviewers (Apple App Review, Google Play review, partner demo users, internal QA personas) cannot complete live KYC, device attestation, or sanctions screening, because the verification providers (Ondato, Apple App Attest, Google Play Integrity, ComplyAdvantage) treat their devices and identities as anomalous. Without a scoped bypass, reviewers cannot exercise the app past the first onboarding step and the review is rejected.

`account_flags` opens **narrow, explicit, per-account, reversible** bypasses at five enforcement points. It is **not** a role (no RBAC is replaced), **not** an admin impersonation tool (the operator never assumes the reviewer's session), and **not** a per-request impersonation mechanism (flags are durable state bound to a single user row).

**Threat model coverage**

| Threat | Mitigation |
|---|---|
| Operator maliciously issues reviewer account to collude with external attacker | Operator must hold `admin` Spatie role; `--allow-production` required in prod; `created_by` FK records operator identity on every flag row |
| Reviewer account exfiltrated and abused after the review cycle | 60-day default and 90-day maximum for `expires_at`; `AccountFlag::isActive()` ignores an expired flag and the daily sweep disables it. `--expires-days=0` creates a flag with no expiry, so the operator must choose that on purpose |
| Bypass flag forgotten and kept alive indefinitely | Same expiry sweep; `disabled_at` short-circuits every check |
| Real customer account silently promoted to reviewer | `is_review_account` is a master tag; promoting a real user into it is an auditable write, and the `AccountFlag.created_by` FK identifies the operator |
| Bypass used as "login as user" for support debugging | Purpose limitation is policy, enforced by the operator runbook (`docs/operations/reviewer-accounts.md`) and by the fact that flags are long-lived on a provisioned account, not attached to a support session |

## The bypass table

One row per `account_flags` column.

| Column | What it skips | Where it is consumed |
|---|---|---|
| `is_review_account` | Master tag (no enforcement effect by itself). | `AccountFlag::isActive()` returns false without it, so no bypass works on an account that is not a review account; audit query key. |
| `bypass_device_attestation` | Apple App Attest / Google Play Integrity verification on login. | `BiometricJWTService::verifyDeviceAttestationForUser()` |
| `bypass_rate_limit` | Per-user API rate limiting (default 60 req/min). | `ApiRateLimitMiddleware`, `GraphQLRateLimitMiddleware` |
| `bypass_sanctions_screening` | ComplyAdvantage OFAC / PEP / sanctions list match during KYC workflow. | `PerformAmlScreeningActivity::execute()` |
| `bypass_sms_otp` | **Reserved** — no consumer; kept so a future SMS OTP path can plug in without a migration. | *(none — this codebase has no dedicated SMS OTP flow; phone verification is via Ondato KYC, covered by `kyc_override_level`)* |
| `suppress_notifications` | Outbound email / SMS / FCM push on system events. | `SuppressNotificationsListener` on the `NotificationSending` event |
| `kyc_override_level` | Real KYC level resolution; returns the override (0–3) instead. `null` = use real KYC. | `User::effectiveKycLevel()` |
| `note` | — | Free-text operator note (audit only). |
| `expires_at` | — | `AccountFlag::isActive()` ignores the flag once it has passed, and the daily sweep sets `disabled_at`. `null` means no expiry. |
| `created_by` | — | FK → `users.id`, the operator who issued the flag. Nullable; set to null if the operator's user row is deleted. |
| `disabled_at` | Short-circuits **every** bypass regardless of the individual columns. | `AccountFlag::isActive()` |

## Audit guarantees

The three bypasses checked through `AccountFlagsService::hasReviewBypass()` write a `bypass.fired` info log entry each time they take effect. They are device attestation, rate limiting and sanctions screening. The entry carries this context, and the log record adds the timestamp:

```json
{
  "message": "bypass.fired",
  "user_id": 41234,
  "bypass": "device_attestation",
  "reason": "review_account"
}
```

The `bypass` value is `device_attestation`, `rate_limit` or `sanctions_screening`. Notification suppression and the KYC level override do not write a log entry when they take effect.

Additional audit anchors:

- `AccountFlag.created_by` is the FK to the operator user. The provisioning command sets it on every row it writes.
- `AccountFlag.is_review_account = true` is the master query key for compliance review.
- Seeded rows carry markers:
  - `cards.issuer = 'review_bypass'`, with `source = 'review_bypass'` in the card's metadata
  - `trust_certificates.credential_type = 'review_bypass'`, with `metadata.source = 'review_bypass'`
  - `token_balances.wallet_id = 'review-<user id>'` on the seeded balances
  - The seeded wallet addresses and rewards carry no marker. They are found through the user id.
- `AccountPurged` is dispatched as a Laravel event on purge. No listener stores it today, so it is not in the event store.

Auditor query: `SELECT users.id, users.email, af.* FROM account_flags af JOIN users ON users.id = af.user_id WHERE af.is_review_account = 1`.

## Expiry and reversibility

- **Default expiry:** 60 days from provisioning.
- **Maximum:** 90 days. The provisioning command only accepts `--expires-days` from 0 to 90. The value 0 creates a flag with no expiry.
- **Daily sweep:** `account:disable-reviewer --all-expired --allow-production` runs at 00:10 UTC via `routes/console.php`.
- **Manual disable:** sets `disabled_at = now()`; bypass columns are preserved for audit.
- **Re-enable:** clears `disabled_at`. The seeded wallet, card, cert, and rewards are untouched, so re-enabling is idempotent.
- **Purge:** anonymizes `users.email` to `purged-<user id>-<random hex>@example.invalid`, replaces the password with a random one and sets `disabled_at`. The row is not deleted — the User model does not use `SoftDeletes`, and referential integrity across cards, certificates, and balances is preserved.

## What this is NOT

- **Not a web-UI provisioning tool** — CLI only. No Filament screen in v1.
- **Not an admin-impersonation mechanism** — operators never assume the reviewer's session. All bypasses are evaluated against the reviewer's own authenticated context.
- **Not cross-tenant** — flags bind to a single `users.id`; multi-tenant reviewer accounts are out of scope.
- **Not a permission system** — the five flag columns are enforcement *opt-outs* at specific sites; they do not grant any new capability.

## Defense in depth

- `AccountFlag::isActive()` checks `is_review_account`, then `disabled_at`, then `expires_at`. Setting `disabled_at` alone is sufficient to kill every bypass even if the individual flag columns remain `true`.
- `AccountFlagsService` is registered as a scoped service, so its cache keyed by `user_id` lasts one request or one job. The provisioning service calls `forget()` after every flag update, and stale reads across requests cannot occur.
- Operator must hold the `admin` Spatie role to invoke the provisioning command; non-admin operators are rejected before any DB write.
- `--allow-production` is required when `APP_ENV=production`; the command otherwise refuses.
- The command rejects an expiry of more than 90 days. An expiry of 0 days means no expiry.
- The command refuses an email that belongs to an existing user who is **not** already a reviewer account. `--force-convert` overrides this outside production and is blocked in production.

## Monitoring

This counter is a recommendation and is not implemented yet. The Prometheus counter would be:

```
provisioning_bypass_fired_total{bypass="device_attestation|rate_limit|sanctions|notifications|kyc_level", user_id="..."}
```

Alert: `rate(provisioning_bypass_fired_total[1m]) > 100` from a single `user_id` → page on-call security. Suggested route: `#sec-incidents`, severity `high`.

Dashboard: one panel per bypass type showing `sum by (bypass) (rate(provisioning_bypass_fired_total[5m]))`. Baseline should be near-zero outside active review cycles.

## Revocation runbook

See `docs/operations/reviewer-accounts.md#incident-response` — `account:disable-reviewer --email=X` stops all bypasses in under 60 seconds.
