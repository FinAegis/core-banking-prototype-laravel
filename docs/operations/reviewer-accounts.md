# Reviewer and Demo Account Operator Runbook

This runbook is for operators. It explains how to provision limited demo accounts for external reviewers, such as Apple and Google app review, partner demos and internal QA personas. The security model behind these accounts is in `docs/security/account-flags.md`.

## When to use it

- An app-store review cycle needs an account, because the Apple or Google reviewer cannot complete a live identity check or device attestation.
- A partner needs demo access for a sales or integration walkthrough on production infrastructure.
- Internal QA needs a persona with a realistic seeded account.

Do **not** use it for customer-support impersonation, for "login as user" debugging or for any account that a real user already owns. Use the audit tools in the Filament admin panel for those cases.

## Pre-flight checklist

- [ ] You, the operator, have the `admin` Spatie role. `php artisan user:admins` lists the admins.
- [ ] You know the target environment (`local`, `staging` or `production`), and you have explicit approval if it is production.
- [ ] A 1Password vault is open and ready to receive the generated credentials.
- [ ] The mobile, app or partner team has given you the exact email the reviewer will use.
- [ ] An expiry date is agreed in advance. The default is 60 days and the hard cap is 90 days.

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

The account's display name is always `App Reviewer`. The command has more options. `--password=…` sets a given password instead of a generated one, and `--region` sets the reviewer region (default `US`). `--rotate-password` rotates only the password and skips reseeding. `--force-convert` converts a user that is not a review account and is blocked in production. `--dry-run` prints the intended changes and writes nothing.

The JSON output looks like this:

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

The `mobile_token` field appears only when you pass `--issue-mobile-token`, as the next section explains. Without that option the key is left out completely.

Running the command again with the same `--email` is idempotent. When you did not ask for a new password, the output says `"password": "unchanged"`.

Copy the whole JSON into a new 1Password item titled `reviewer:<email>`, and share the vault entry with the mobile team. **Never paste the password or the `mobile_token` into email, Slack, Linear, Jira or commit logs.** Both are shown once and cannot be recovered.

## Issuing a mobile reviewer token

### Why this exists

Since v7.12.0 the mobile login screen accepts only the Privy email code. Login with email and password is no longer reachable in shipped builds. Privy sends the code by email, but by policy reviewer emails use the RFC 2606 `.invalid` domain and never receive mail. App-store reviewers therefore cannot sign in unless we give them a long-lived credential that skips the code.

### How it works

The `--issue-mobile-token` option creates a Sanctum personal access token named `reviewer-mobile` with the abilities `read,write,delete`. It expires when the reviewer flag's `expires_at` expires, and never later than 90 days. The reviewer pastes the token into a hidden "Reviewer access" gesture on the mobile login screen, and the app stores it like any other access token. The mobile team owns the gesture, and operations owns issuing and revoking the token.

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

The output then includes a `mobile_token` field:

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

Use `--dry-run --issue-mobile-token` to preview the result without writing anything. The field then shows `"mobile_token": "(would-be-issued)"`.

### Storage rule

Treat the token like the password. Paste it into the 1Password item titled `reviewer:<email>` and share the vault entry with the mobile team. Never log it, post it in Slack, email it or commit it. The plaintext token is shown once and cannot be recovered.

### Revoking the token

`account:disable-reviewer --email=<email>` revokes the `reviewer-mobile` token together with the bypasses in one step. Check that the output contains the line `revoked-tokens: 1`.

```bash
php artisan account:disable-reviewer \
  --email=appreview-2026-q2@example-reviewer.invalid \
  --operator-email=ops-admin@finaegis.com
# disabled: appreview-2026-q2@example-reviewer.invalid
# revoked-tokens: 1
```

The `--all-expired` sweep does the same for each expired account and reports a total, `Sweep complete. disabled={n} failed={m} tokens-revoked={k}`.

`--re-enable` deliberately does not create a new token. If a review cycle opens again, run `account:provision-reviewer --rotate-password --issue-mobile-token` to issue a fresh one.

### Limitations

- The token lives in `personal_access_tokens` and is **not** bound to a device. If it leaks before the cycle ends, revoke it at once with `account:disable-reviewer --email=<email>`.
- The reviewer email uses the RFC 2606 `.invalid` domain, so operators do **not** need a real mailbox for the reviewer. The token is the only credential the reviewer needs.

## Listing and audit

```bash
php artisan account:list-reviewers              # human-readable table
php artisan account:list-reviewers --json       # machine-readable
```

Sample table output:

```
+-----------------------+--------+------------+---------------------------+---------------+-------------------------------------+
| Email                 | Active | Expires    | Disabled                  | Operator      | Note                                |
+-----------------------+--------+------------+---------------------------+---------------+-------------------------------------+
| appreview-2026-q2@... | yes    | 2026-06-23 | —                         | ops-admin@... | Apple App Review 2026-Q2 submission |
| demo-vertex-sms@...   | no     | 2026-04-30 | 2026-04-30T00:10:02+00:00 | ops-admin@... | VertexSMS partner demo              |
+-----------------------+--------+------------+---------------------------+---------------+-------------------------------------+
```

The JSON output has the same fields for each account: `email`, `active`, `expires_at`, `disabled_at`, `operator` and `note`.

Auditors can list every review account with `SELECT * FROM account_flags WHERE is_review_account = 1`.

## Disable and re-enable

When a review cycle ends, disable the account. `--operator-email` is required with `--email` and with `--re-enable`, and not with `--all-expired`. In production, also add `--allow-production`.

```bash
php artisan account:disable-reviewer --email=appreview-2026-q2@example-reviewer.invalid --operator-email=ops-admin@finaegis.com
php artisan account:disable-reviewer --all-expired     # bulk sweep
php artisan account:disable-reviewer --email=X --re-enable --operator-email=ops-admin@finaegis.com   # if the cycle re-opens
```

Disabling sets `account_flags.disabled_at = now()`, and every bypass check then stops early. The individual `bypass_*` columns and `is_review_account = true` are **kept**, so the audit history stays complete. Re-enabling clears `disabled_at` without running the provisioning profile again. The seeded wallet, card, certificate and rewards stay exactly as they were.

## Purge

```bash
php artisan account:purge-reviewer --email=X --operator-email=ops-admin@finaegis.com --confirm
# production only, append:
#   --allow-production
```

In this codebase a purge does four things.

- It disables the account, which sets `AccountFlag.disabled_at = now()` and stops every bypass.
- It replaces `User.email` with an anonymous address of the form `purged-<id>-<random>@example.invalid`.
- It replaces the password with a random one.
- It dispatches the `AccountPurged` domain event.

The user row is **not** deleted. The User model does not use `SoftDeletes`, so keeping the row preserves the audit trail and the links to cards, certificates and balances. For a full GDPR Article 17 erasure, use the platform's account deletion flow (`POST /api/compliance/gdpr/delete`) instead of extending this command.

## Expiry sweep

`routes/console.php` schedules a daily job at **00:10 UTC**:

```bash
php artisan account:disable-reviewer --all-expired
```

The scheduled job adds `--allow-production`, because it runs as the system and not as a human operator. Its log is `storage/logs/account-expiry-sweep.log`. To check that the sweep ran this morning:

```bash
tail -n 50 storage/logs/account-expiry-sweep.log
php artisan schedule:list | grep account:disable-reviewer
```

If the sweep has not run for more than 36 hours, check the Laravel scheduler and its cron entry (`php artisan schedule:run` every minute).

## Incident response

The trigger is an alert on a spike of `bypass.fired` log lines, more than 100 events a minute from one account.

1. Identify the account:
   ```bash
   grep '"bypass.fired"' storage/logs/laravel.log | jq 'select(.user_id == <ID>)' | head
   ```
2. Disable it within 60 seconds:
   ```bash
   php artisan account:disable-reviewer --email=<email> --operator-email=<your-admin-email>
   ```
3. Check that `account_flags.disabled_at` is filled in, and that no new `bypass.fired` line appears within 30 seconds.
4. Escalate to the on-call security engineer, then the head of platform, then the incident channel. Attach the slice of `bypass.fired` log lines and a snapshot of `account:list-reviewers --json`.

## Limitations of the first version

- `bypass_sms_otp` is **reserved**. The codebase has no separate SMS code flow, because phone verification runs through the Ondato identity check, which `kyc_override_level` already covers. The column exists so that future SMS code work can use it without a migration.
- There is no Filament admin screen in this version. Every operation runs on the command line.
- Provisioning works for a single tenant only. Reviewer accounts across several tenants are out of scope for this version.
- 1Password delivery is not automated. Operators copy and paste the credentials by hand.
- By policy, reviewer emails use RFC 2606 reserved domains (`.invalid`, `.example` and `.test`). Operators do **not** need a real mailbox. The password and, when issued, the `mobile_token` are the only credentials needed.
