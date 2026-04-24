# Reviewer / Demo Account Provisioning — Design

**Date:** 2026-04-24
**Status:** Draft — pending implementation plan
**Owner:** Platform / Infrastructure
**Motivation:** Google Play and App Store reviewers cannot complete Ondato KYC or phone verification. We need a durable, auditable way to provision pre-seeded review/demo accounts that bypass security checks in a scoped, reversible, auditable fashion.

---

## 1. Goals and non-goals

### Goals
- Provision one (or more) user accounts with enough seeded state that an external reviewer can exercise gated features without going through real KYC/SMS/attestation flows.
- Bypass specific security checks (device attestation, rate limits, sanctions screening, SMS OTP, KYC level) in a scoped, explicit, logged fashion.
- Keep production data clean: no forged Ondato/sanctions rows masquerading as real. Only *content* the reviewer needs to see on screen (balances, one card, one TrustCert, rewards) is real-looking.
- Support the general case — any future "persona" (demo investor, auditor, load-test user) can be added without refactoring.
- Safe to run in production, with explicit guardrails and audit trail.
- Idempotent: re-running the command updates the same logical state, never duplicates.
- Reversible: disable revokes all bypasses and sessions; re-enable flips bypasses back on; purge hard-deletes on explicit confirm.

### Non-goals
- Not a customer-facing feature. No public marketing surface, no features-page entry.
- Not a replacement for staging environments. Reviewer accounts are production artefacts needed only because reviewers test against production.
- Not a generic impersonation / "login as user" tool. Reviewers log in themselves with shared credentials.
- Not a way to bypass authorization (RBAC). Reviewers get a normal non-admin user with specific security bypasses, not elevated permissions.

---

## 2. High-level architecture

```
app/Domain/AccountProvisioning/
├── Contracts/AccountProfile.php              -- interface
├── Profiles/ReviewerAccountProfile.php       -- one concrete profile (Phase 1)
├── Services/
│   ├── AccountFlagsService.php               -- flag lookup + cache (consumed by middleware/services)
│   └── AccountProvisioningService.php        -- orchestrator invoked by the command
├── Seeders/                                  -- per-concern sub-seeders
│   ├── WalletSeeder.php                      -- EVM (Polygon) + Solana
│   ├── BalanceSeeder.php                     -- USDC shielded + unshielded
│   ├── TrustCertSeeder.php                   -- one approved cert
│   ├── CardSeeder.php                        -- one active virtual card
│   └── RewardsSeeder.php                     -- XP + one completed quest
└── Models/AccountFlag.php                    -- new account_flags table
```

Plus:

- `app/Console/Commands/AccountProvisionReviewerCommand.php`
- `app/Console/Commands/AccountDisableReviewerCommand.php`
- `app/Console/Commands/AccountListReviewersCommand.php`
- `app/Console/Commands/AccountPurgeReviewerCommand.php`

Flag enforcement plugs in at ~5 existing call sites (listed in §4) — no new middleware. Each enforcement point imports `AccountFlagsService` and short-circuits when its flag is set, emitting a `bypass.fired` structured log.

---

## 3. Data model: `account_flags`

New table, 1:1 with `users`:

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT PK | |
| `user_id` | FK `users.id`, **unique** | cascade delete |
| `is_review_account` | bool, **indexed** | master tag — all queries filter on this |
| `bypass_device_attestation` | bool | skip Apple App Attest / Play Integrity |
| `bypass_rate_limit` | bool | skip `ApiRateLimitMiddleware` + `GraphQLRateLimitMiddleware` |
| `bypass_sanctions_screening` | bool | treat as cleared in AML activities |
| `bypass_sms_otp` | bool | accept any OTP code (or skip entirely) |
| `suppress_notifications` | bool | no outbound email/SMS/push |
| `kyc_override_level` | tinyint nullable | 0=none, 1=basic, 2=enhanced; `null` = use real KYC |
| `note` | string(255) | short free-text ("App Store review 2026-Q2") |
| `expires_at` | timestamp nullable | auto-disables after this date |
| `created_by` | FK `users.id` | the admin operator who ran the command |
| `disabled_at` | timestamp nullable | when bypasses were revoked |
| `created_at`, `updated_at` | timestamps | |

### Rationale

- **Separate table** (not columns on `users`): keeps the hot row clean, centralises audit, makes `AccountFlag::where('is_review_account', true)` trivial.
- **`kyc_override_level` as tinyint**, not bool: reviewers may need different gates depending on tier (basic-tier reviewer sees different screens than enhanced). A bool collapses that distinction.
- **`expires_at` default 60 days, hard cap 90 days**: covers both Play + App Store review cycles plus resubmission slack. Daily scheduled job sweeps expired rows. Forced ceiling prevents "forever bypass" by accident.
- **No "bypass content"** — content (cards, TrustCerts, balances, rewards) is real seeded rows, so UI renders normally.

---

## 4. Enforcement integration points

Each bypass is consumed at a specific, audited location. The pattern is identical at every site:

```php
if ($this->flags->hasReviewBypass($user, 'device_attestation')) {
    logger()->info('bypass.fired', ['user_id' => $user->id, 'bypass' => 'device_attestation', 'reason' => 'review_account']);
    return AttestationResult::bypassed('review_account');
}
```

| Flag | Enforcement site | Behavior when set |
|---|---|---|
| `bypass_device_attestation` | `app/Domain/Mobile/Services/AppleAttestationVerifier.php`, `GoogleIntegrityVerifier.php` | Return verified result with `source=review_bypass` |
| `bypass_rate_limit` | `app/Http/Middleware/ApiRateLimitMiddleware.php`, `GraphQLRateLimitMiddleware.php` | Skip limiter `hit()`, forward request |
| `bypass_sanctions_screening` | `app/Domain/AgentProtocol/Workflows/Activities/PerformAmlScreeningActivity.php` + any direct sanctions call on auth/send paths | Return `cleared` result |
| `bypass_sms_otp` | SMS OTP verification path in auth controllers | Accept any code, or skip entirely if flag set |
| `suppress_notifications` | Central notification dispatcher (`shouldSend($user, $channel)`) | Short-circuit before queue push |
| `kyc_override_level` | KYC gates consume `User::effectiveKycLevel()`, which reads the override | Returns override level in preference to real KYC state |

### Design rules
- **No middleware "sniffs" the review account** to blanket-skip security. Every bypass is explicit and named.
- **`User::effectiveKycLevel()`** is the single accessor for KYC decisions. The flag is the implementation; the accessor is the API. No call site should branch on `is_review_account`.
- **All bypass hits emit `bypass.fired` structured log + a counter metric.** Unexpected volume fires an alert.
- **`AccountFlagsService` caches per request** (`$cache[userId]`) so the N calls a single request makes don't become N queries.

---

## 5. The `AccountProfile` interface

```php
interface AccountProfile
{
    public function provision(User $user, ProvisioningContext $ctx): void;
    public function name(): string;      // e.g. 'reviewer'
    public function flags(): array;      // flag column => value, written to account_flags
}
```

`ReviewerAccountProfile::provision()` runs every sub-seeder in a `DB::transaction()`. Each sub-seeder is idempotent (`updateOrCreate` / `firstOrCreate`); re-running the command updates in place.

Why a generic profile system for one profile: the flag infrastructure (§3, §4) is clearly reusable regardless. Putting the profile interface in place now costs ~40 lines and means any future persona (demo investor, auditor, load-test user) is a single file, not a refactor. YAGNI respected — no second profile ships in Phase 1.

---

## 6. CLI surface

### `account:provision-reviewer`

```
php artisan account:provision-reviewer
    --email=appreview@finaegis.com        (required)
    --password=<generated 20-char if omitted>
    --region=US
    --expires-days=60                      (0 = no expiry; hard cap 90)
    --note="App Store review 2026-Q2"
    --operator-email=<admin>               (required in CLI; resolves created_by)
    --allow-production                     (required if APP_ENV=production)
    --rotate-password                      (only rotate, do not reseed state)
    --dry-run
```

Output: a single JSON block to stdout. Operator pipes to 1Password / clipboard. Nothing logged to file.

```json
{
  "email": "appreview@finaegis.com",
  "password": "…20 chars…",
  "user_id": 12345,
  "flags": { "is_review_account": true, "bypass_rate_limit": true, "...": "..." },
  "expires_at": "2026-06-23T00:00:00Z",
  "operator": "admin@finaegis.com"
}
```

Password is shown only when newly generated or rotated; re-runs that don't touch the password emit `"password": "unchanged"`.

### Companion commands

- **`account:list-reviewers`** — tabular list of all `is_review_account=true` users with `expires_at`, `disabled_at`, operator, note.
- **`account:disable-reviewer --email=X | --all-expired`** — sets all bypass flags false, revokes all Sanctum tokens and sessions, keeps the user row for audit. `--re-enable` flips bypasses back on (reversible).
- **`account:purge-reviewer --email=X --confirm`** — soft-deletes the user, cascades through `AccountFlag`, revokes Sanctum tokens, and emits an `account.purged` audit event. Only allowed when `is_review_account=true`. Blocked in production without `--allow-production`. Hard-delete is deliberately not supported from the CLI — if a full removal is required, the operator runs the existing GDPR-erasure flow against the user.

### Scheduled job

`account:disable-reviewer --all-expired` runs daily at 00:10 UTC via the `schedule()` method. Fires telemetry on any disable.

---

## 7. Production safety gates

1. **`APP_ENV=production` requires `--allow-production`.** Command aborts with a red banner otherwise. Sticky audit flag, logged, cannot be satisfied via `.env`.
2. **Email collision with an existing non-review user** → abort. Explicit `--force-convert` required to flip a real user into a review account, and `--force-convert` is *blocked in production entirely*.
3. **Operator must be admin**: `--operator-email` must resolve to a user with `isAdmin() === true`. Populates `created_by`. The command will not run with a non-admin operator.
4. **Default expiry 60 days, hard cap 90.** Enforced by the command itself, not the database.
5. **Bypass observability**: every bypass hit emits `bypass.fired` + counter. Prometheus alert for anomalous volume.
6. **Disabled account behaviour**: `disabled_at IS NOT NULL` short-circuits bypasses even if flag columns somehow remain true. Defence in depth.

---

## 8. Testing strategy

- **Unit**
  - Each seeder (Wallet, Balance, TrustCert, Card, Rewards): assert idempotency (two calls = one row) and correct state.
  - `AccountFlagsService`: flag lookup, per-request cache behaviour, null-safety when user has no flag row.
  - `ReviewerAccountProfile::flags()`: correct default flag payload.

- **Feature**
  - One test per bypass in §4's table: request the enforcement site *without* flag → denied; *with* flag → allowed and `bypass.fired` captured via log assertion.
  - Command happy path: `artisan('account:provision-reviewer', …)` produces user + flags + balances + card + cert. Second run produces identical state.
  - Safety gates: production without flag aborts; email collision aborts; `--force-convert` in prod aborts; non-admin operator aborts.
  - Expiry flow: scheduled `account:disable-reviewer --all-expired` disables expired rows only, leaves non-expired alone.
  - Credential output: newly generated password printed; re-run without `--rotate-password` prints `"unchanged"`.

- **Bars**
  - PHPStan Level 8 clean.
  - Pest parallel, `--stop-on-failure`.
  - ≥90% line coverage on the new domain.

---

## 9. Documentation

- **`docs/operations/reviewer-accounts.md`** — operator runbook: when to use, the 1Password flow, disable/purge steps, what each flag does, incident response if `bypass.fired` volume spikes.
- **`docs/security/account-flags.md`** — security model, audit guarantees, expiry semantics, why this exists, what it explicitly does *not* authorise.
- **`CLAUDE.md`** — add a "Reviewer/demo accounts" row to the CI/CD table, note `account:provision-reviewer` in Essential Commands, bump domain count (56 → 57) once `AccountProvisioning/` lands.
- **Serena memory**: `account_provisioning_domain` covering the flag model, profile interface, enforcement points, safety gates.
- **Auto-memory (MEMORY.md)**: reference pointer to `docs/operations/reviewer-accounts.md`.
- **Public site**: **no change.** Internal operator tool — no features-page entry, no version-badge bump for this feature alone (the next genuine customer-facing feature can bump it).

---

## 10. Release flow

1. Feature branch: `feat/account-provisioning-reviewer`.
2. PR to `main`. Required green:
   - `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php`
   - `XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G`
   - `./vendor/bin/pest --parallel --stop-on-failure`
   - `post-phase-review` skill run before PR open (per global CLAUDE.md).
3. Merge into `main`. No SDK/package release — this is an internal tool, no public surface.
4. Post-merge: run `php artisan account:provision-reviewer` in staging (rehearsal), then production with `--allow-production`; hand credentials to mobile team via 1Password.
5. `docs/VERSION_ROADMAP.md` entry under the current patch version.

---

## 11. Out of scope for this spec

- Additional profiles (demo investor, auditor, load-test user) — deferred until a concrete second use case lands.
- A Filament admin UI for creating/disabling review accounts — CLI is sufficient for operator-only use.
- Automatic credential delivery (1Password API, Slack DM) — manual handoff is acceptable and lower-risk for this volume.
- Tenant-specific reviewer accounts — v1 provisions in the default tenant only. Multi-tenant support added when asked.

---

## 12. Open questions

None at spec-writing time. All major design choices were resolved during brainstorming.
