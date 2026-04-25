# AccountProvisioning Domain (v7.10.11)

New domain at `app/Domain/AccountProvisioning/` for scoped reviewer / demo account bypasses (app-store review, partner demos, internal QA personas). Paired docs: `docs/operations/reviewer-accounts.md` and `docs/security/account-flags.md`.

## Domain structure (layer by layer)

- **Models**: `AccountFlag` — 1:1 with `users`, table `account_flags`.
- **Contracts**: `AccountProfile` interface — one `apply(ProvisioningContext)` method.
- **ValueObjects**: `ProvisioningContext` (user, operator, expires_at, note, profile name).
- **Services**: `AccountProvisioningService` (orchestrator: `apply()` / `disable()` / `reEnable()`), `AccountFlagsService` (per-request memoized flag reads + `isActive()` short-circuit on `disabled_at`).
- **Profiles**: one concrete `ReviewerAccountProfile` composing five sub-seeders.
- **Seeders**: `WalletSeeder` (EVM Polygon sha256-derived placeholder + Solana via `SolanaAddressHelper::deriveForUser`), `BalanceSeeder` ($25 USDC unshielded + $10 USDC shielded on Polygon via direct bcmath upsert, bypasses ledger/RAILGUN), `CardSeeder` (one active virtual card, `issuer='review_bypass'`), `TrustCertSeeder` (one active cert, `credential_type='review_bypass'`), `RewardsSeeder` (XP=250, completed 'welcome' quest).
- **Support**: `SuppressNotificationsListener` for the `NotificationSending` event.
- **Events**: `AccountPurged` domain event.

## Flag model — `account_flags` columns

Master tag: `is_review_account`.
Bypasses: `bypass_device_attestation`, `bypass_rate_limit`, `bypass_sanctions_screening`, `bypass_sms_otp` (**reserved, no consumer**), `suppress_notifications`.
KYC: `kyc_override_level` (tinyint 0–3, null = use real KYC).
Audit / lifecycle: `note`, `expires_at`, `created_by` (FK users.id), `disabled_at`.

`AccountFlagsService::isActive()` checks `disabled_at IS NULL` **first** — a single column update kills every bypass.

## Enforcement points (five sites + one reserved)

1. `BiometricJWTService::verifyDeviceAttestationForUser()` — Apple App Attest / Google Play Integrity bypass.
2. `ApiRateLimitMiddleware` + `GraphQLRateLimitMiddleware` — per-user rate limit bypass.
3. `PerformAmlScreeningActivity::execute()` — sanctions screening bypass (`$userId` is plumbed through `KycVerificationRequest` DTO and `AgentKycWorkflow` activity dispatch).
4. `SuppressNotificationsListener` on `NotificationSending` — outbound email / SMS / push suppression.
5. `User::effectiveKycLevel()` — override-or-real accessor.

Reserved: `bypass_sms_otp` — codebase has no dedicated SMS OTP flow; phone verification runs via Ondato KYC, already covered by `kyc_override_level`.

## Safety gates

- Operator must hold `admin` Spatie role (command rejects otherwise).
- Production guard: `--allow-production` required when `APP_ENV=production`.
- Expiry hard cap: 90 days (default 60). Command rejects `--expires-in-days > 90`.
- Email collision: command refuses if an existing user with that email is not already a reviewer account.
- `review_bypass` sentinel strings in `cards.issuer`, `trust_certificates.credential_type`, and `metadata.source` for auditability.
- `bypass.fired` structured log on every bypass hit: `{user_id, bypass, reason: 'review_account', request_id, ts}`.

## CLI inventory

- `account:provision-reviewer --email= --operator-email= [--name=] [--expires-in-days=60] [--note=] [--allow-production]` — JSON-on-success output includes the generated password (one-shot).
- `account:list-reviewers [--json]` — table or machine-readable.
- `account:disable-reviewer --email=X | --all-expired [--re-enable]` — sets / clears `disabled_at`; preserves individual flag columns and `is_review_account` for audit.
- `account:purge-reviewer --email=X --confirm` — anonymizes email to `purged+<uuid>@finaegis.invalid`, sets `disabled_at`, dispatches `AccountPurged`. User row is **not** dropped (no SoftDeletes on User model).

Daily schedule (in `routes/console.php`): `account:disable-reviewer --all-expired` at 00:10 UTC, logs to `storage/logs/account-expiry-sweep.log`.

## What is out of scope in v1

Filament admin UI, multi-tenant provisioning, automated 1Password credential delivery, `bypass_sms_otp` enforcement site.
