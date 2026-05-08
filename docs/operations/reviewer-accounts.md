# Reviewer / Demo Account Operator Runbook

Operator-only runbook for provisioning scoped demo accounts used by external reviewers (Apple/Google app review, partner demos, internal QA personas). Paired with the security model at `docs/security/account-flags.md`.

## When to use

- App-store review cycles (Apple App Review, Google Play review) where the reviewer cannot complete live KYC or device attestation.
- Partner demo access for sales or integration walkthroughs against production infrastructure.
- Internal QA personas that need a realistic seeded account.

This is **not** for customer-support impersonation, "login as user" debugging, or any situation where a real user already owns the account. Use the Filament admin audit tools for those flows instead.

## Pre-flight checklist

- [ ] You (the operator) have the `admin` Spatie role — `php artisan user:admins` lists the set.
- [ ] You know the target environment (`local`, `staging`, `production`) and have explicit approval if it is production.
- [ ] A 1Password vault is open and ready to receive the generated credentials.
- [ ] The mobile / app / partner team has given you the exact email the reviewer will use.
- [ ] An expiry date is agreed in advance (default 60 days, hard cap 90 days).

## Provisioning

```bash
php artisan account:provision-reviewer \
  --email=appreview-2026-q2@example-reviewer.invalid \
  --operator-email=ops-admin@finaegis.com \
  --expires-days=60 \
  --note="Apple App Review 2026-Q2 submission"
# production only, append:
#   --allow-production
```

The account display name is hardcoded to `App Reviewer`. Additional flags: `--password=…` (rotate an existing account), `--region=US`, `--rotate-password`, `--force-convert` (blocked in production), `--dry-run`.

Expected JSON output shape:

```json
{
  "email": "appreview-2026-q2@example-reviewer.invalid",
  "password": "<GENERATED-ONCE-ONLY>",
  "user_id": 41234,
  "flags": {
    "is_review_account": true,
    "bypass_device_attestation": true,
    "bypass_rate_limit": true,
    "bypass_sanctions_screening": true,
    "bypass_sms_otp": true,
    "suppress_notifications": true,
    "kyc_override_level": 2,
    "note": "Apple App Review 2026-Q2 submission",
    "expires_at": "2026-06-23T12:00:00Z",
    "created_by": 7
  },
  "expires_at": "2026-06-23T12:00:00Z",
  "operator": "ops-admin@finaegis.com",
  "dry_run": false,
  "mobile_token": "<PLAINTEXT-SANCTUM-TOKEN-ONCE-ONLY>"
}
```

The `mobile_token` field is only present when `--issue-mobile-token` is passed (see "Issuing a mobile reviewer token" below). Without that flag, the key is omitted entirely.

Re-running the command with the same `--email` is idempotent — `"password": "unchanged"` is emitted instead when no password rotation was requested.

Copy the full JSON into a new 1Password item titled `reviewer:<email>`; share the vault entry with the mobile team. **Never paste the password (or `mobile_token`) into email, Slack, Linear, Jira, or commit logs.** Both are displayed once and not recoverable.

## Issuing a mobile reviewer token

### Why this exists

Mobile went Privy-OTP-only on the login screen in v7.12.0. Email + password login is no longer reachable in shipped builds — Privy delivers the OTP, but reviewer emails use the RFC 2606 `.invalid` TLD by policy and never receive mail. App-store reviewers (Apple App Review, Google Play review) therefore cannot complete sign-in unless we hand them a long-lived credential that bypasses OTP.

### How it works

The `--issue-mobile-token` flag mints a Sanctum personal-access token (`reviewer-mobile`, abilities `read,write,delete`) with an expiry equal to the reviewer flag's `expires_at`, hard-capped at 90 days. The reviewer pastes the token into a hidden "Reviewer access" gesture on the mobile login screen; the app stores it like any other access token. Mobile owns the gesture; ops owns the token issuance and revocation.

### Command

```bash
php artisan account:provision-reviewer \
  --email=appreview-2026-q2@example-reviewer.invalid \
  --operator-email=ops-admin@finaegis.com \
  --expires-days=60 \
  --note="Apple App Review 2026-Q2 submission" \
  --issue-mobile-token
# production only, append:
#   --allow-production
```

The output adds a `mobile_token` field:

```json
{
  "email": "appreview-2026-q2@example-reviewer.invalid",
  "password": "<GENERATED-ONCE-ONLY>",
  "user_id": 41234,
  "expires_at": "2026-07-07T12:00:00Z",
  "operator": "ops-admin@finaegis.com",
  "dry_run": false,
  "mobile_token": "12|abcDEF...token-shown-once...XYZ987"
}
```

Use `--dry-run --issue-mobile-token` to preview without writing — the field renders as `"mobile_token": "(would-be-issued)"`.

### Storage rule

Same as the password: paste into the 1Password item titled `reviewer:<email>`, share the vault entry with the mobile team, never log/Slack/email/commit it. The plaintext token is displayed once and not recoverable.

### Revoking the token

`account:disable-reviewer --email=<email>` revokes the `reviewer-mobile` token alongside the bypasses, atomically. Confirm the line `revoked-tokens: 1` in the output.

```bash
php artisan account:disable-reviewer \
  --email=appreview-2026-q2@example-reviewer.invalid \
  --operator-email=ops-admin@finaegis.com
# disabled: appreview-2026-q2@example-reviewer.invalid
# revoked-tokens: 1
```

The `--all-expired` sweep does the same per row and reports a total: `Sweep complete. disabled={n} failed={m} tokens-revoked={k}`.

`--re-enable` deliberately does NOT mint a new token. If the cycle re-opens, run `account:provision-reviewer --rotate-password --issue-mobile-token` to issue a fresh one.

### Limitations

- The token lives in `personal_access_tokens` and is **not** bound to a device. If it leaks before the cycle ends, run `account:disable-reviewer --email=<email>` immediately to revoke.
- The reviewer email uses the RFC 2606 `.invalid` TLD — operators do **not** need a real mailbox for the reviewer. The token is the only credential the reviewer ever needs.

## Listing and audit

```bash
php artisan account:list-reviewers              # human-readable table
php artisan account:list-reviewers --json       # machine-readable
```

Sample table output:

```
+-------+--------------------------------+------------+---------------------+---------------------+----------+
| id    | email                          | operator   | created_at          | expires_at          | disabled |
+-------+--------------------------------+------------+---------------------+---------------------+----------+
| 187   | appreview-2026-q2@...          | ops-admin  | 2026-04-24 12:00:00 | 2026-06-23 12:00:00 |          |
| 165   | demo-vertex-sms@...            | ops-admin  | 2026-03-01 09:15:00 | 2026-04-30 09:15:00 | Y        |
+-------+--------------------------------+------------+---------------------+---------------------+----------+
```

Master query for auditors: `SELECT * FROM account_flags WHERE is_review_account = 1`.

## Disable / re-enable

When a review cycle ends:

```bash
php artisan account:disable-reviewer --email=appreview-2026-q2@example-reviewer.invalid
php artisan account:disable-reviewer --all-expired     # bulk sweep
php artisan account:disable-reviewer --email=X --re-enable   # if the cycle re-opens
```

Disable sets `account_flags.disabled_at = now()` and short-circuits every bypass check. The individual `bypass_*` columns and `is_review_account = true` are **preserved** so the historical audit trail stays intact. Re-enable clears `disabled_at` without re-running the provisioning profile — the seeded wallet, card, cert, and rewards remain exactly as they were.

## Purge

```bash
php artisan account:purge-reviewer --email=X --confirm
```

Purge in this codebase means:

- `AccountFlag.disabled_at = now()` (short-circuits all bypasses)
- `User.email` is anonymized to `purged+<uuid>@finaegis.invalid`
- `AccountPurged` domain event is dispatched

The User row is **not** dropped. The User model does not use `SoftDeletes`, so the audit trail and referential integrity with cards, certs, and balances are preserved. For full GDPR Article 17 erasure, follow `docs/15-ADMINISTRATION/` erasure flow — do not extend this command.

## Expiry sweep

A daily scheduled job defined in `routes/console.php` runs at **00:10 UTC**:

```bash
php artisan account:disable-reviewer --all-expired
```

Logs land at `storage/logs/account-expiry-sweep.log`. Verify the sweep ran this morning:

```bash
tail -n 50 storage/logs/account-expiry-sweep.log
php artisan schedule:list | grep account:disable-reviewer
```

If the sweep is missing for >36h, check the Laravel scheduler / cron wiring and the `laravel.schedule` Horizon supervisor.

## Incident response

Symptom: alert fires on `bypass.fired` log volume spike (>100 events/min from one account).

1. Identify the account:
   ```bash
   grep '"bypass.fired"' storage/logs/laravel.log | jq 'select(.user_id == <ID>)' | head
   ```
2. Force-disable in under 60s:
   ```bash
   php artisan account:disable-reviewer --email=<email>
   ```
3. Confirm `account_flags.disabled_at` is populated; confirm next `bypass.fired` is absent within 30s.
4. Escalate: on-call security engineer → head of platform → incident channel. Attach the `bypass.fired` log slice and the `account:list-reviewers --json` snapshot.

## Limitations (v1)

- `bypass_sms_otp` is **reserved** — this codebase has no dedicated SMS OTP flow (phone verification runs via Ondato KYC, already covered by `kyc_override_level`). The column exists so future SMS OTP work can plug in without a migration.
- No Filament admin UI in v1 — all operations are CLI.
- Single-tenant provisioning only; multi-tenant reviewer accounts are out of scope for v1.
- No automated 1Password delivery — operators copy/paste manually.
- Reviewer emails use RFC 2606 reserved TLDs (`.invalid`, `.example`, `.test`) by policy — operators do **not** need a real mailbox; the password and (when issued) `mobile_token` are the only credentials needed.
