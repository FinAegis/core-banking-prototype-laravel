# Wave 0 — Production-Readiness Go-Live Blockers (Design Spec)

**Date:** 2026-07-04
**Status:** Proposed — awaiting review
**Owner:** Platform / Ops
**Source:** 10-dimension fresh evidence audit of v7.16.0 (2026-07-04). Supersedes nothing; feeds `docs/10-OPERATIONS/PRODUCTION_READINESS_CHECKLIST.md`.

---

## 1. Context

A fresh, evidence-based production-readiness audit of the repo (branch `main`, v7.16.0) reached a two-part verdict:

- **Zelta (the shipping mobile wallet)** is *conditionally* go-live-ready. The core money engineering is genuinely careful (bcmath, integer minor-units, idempotent + signature-verified webhooks, tokenised cards / clean SAQ-A footprint, real Apple JWS validation, real GDPR export/erasure with processor fan-out, non-custodial Wallet Send). The blockers are a small number of **default-config / honesty** gaps, not correctness bugs.
- **FinAegis (the "core banking platform")** is a *reference implementation*: all seven financial standards (ISO 20022, ISO 8583, SWIFT, Open Banking, SEPA, US rails, Interledger) are message-construction or demo-stub with **zero live connectivity**, all correctly disabled in Zelta production — but the public README markets compliance certifications the project does not hold.

The product owner has decided: **reposition FinAegis as an open-source reference/education platform** (not invest to make the standards operational) and **fix the honesty copy**.

This spec covers **Wave 0 only**: the four go-live blockers that must ship before a defensible Zelta soft launch. Each is an independent, isolated PR.

## 1a. Deployment context (confirmed with owner, 2026-07-04)

- **Deploy is a manual `git pull` on a single server** + manual `php artisan migrate` / `config:cache` / `route:cache` / `queue:restart`. The repo's `deploy.yml` GitHub Action (which runs `ops:verify-env` before `migrate`) is **not** the live deploy path.
- **Implication:** `ops:verify-env` does **not** currently gate production deploys, so the env guards this spec adds in 0B/0C (and those from the June remediation) only fire if the owner runs them. **Ops action (fold into the deploy runbook):** add `php artisan ops:verify-env` as a mandatory manual step after `git pull`, before `migrate` — or wire a deploy hook. Cheap, high-value; makes every 0B/0C guard actually enforce.
- **Workers run under Supervisor** (`etc/supervisor.conf`) — see 0A; this is why 0A is a *live* production defect, not a latent one.

## 2. Scope

**In scope (Wave 0):** four PRs — queue-worker coverage; RAILGUN custody-neutral production default; SOC-2 demo-mode production hard-fail; README/site honesty relabel + reference-platform positioning.

**Explicit non-goals (deferred, tracked for later waves):**
- Stripe *web* `DepositController` double-credit / webhook backstop — **P1, Wave 1** (confirmed: the mobile client uses **Bridge + IAP only**, not this path, so it is not a launch blocker).
- GDPR `blockchain_address_transactions` coverage; `railgun_wallets` exclusion justification — Wave 1.
- Backups (`BACKUP_DISK=s3`, restore drill, RPO), Sentry/APM — Wave 1.
- Developer/API doc drift (SDK "not published" banner, `api.finaegis.org`→`api.zelta.app`, OpenAPI 7.16 regen, PyPI license) — Wave 2.
- Operator CLI name collisions, dead-code removal (RAILGUN Phase-3, CQRS bus, dead saga), god-controller splits, PHPStan baseline burn-down, MySQL component tests — Wave 3.
- Making any financial standard operational — **not doing** (reposition decision). Wave 4 = positioning only.

## 3. Wave-0 items

### 0A — Queue-worker coverage (P0, **live in production now**)

**Problem.** `ShouldQueue` listeners/jobs are dispatched to queues the deployed Supervisor does **not** consume, so the work queues forever. Confirmed with the owner (2026-07-04): production runs **Supervisor** off `etc/supervisor.conf`, which consumes only `events, ledger, transactions, default, liquidity_pools`.

**Live blast radius (verified 2026-07-04).** Everything dispatched to an uncovered queue is currently unprocessed in production:
- `mobile` → **transaction push notifications** (`SendTransactionPushNotificationListener`), **security-alert pushes** (`SendSecurityAlertListener`), mobile audit log (`LogMobileAuditEventListener`) — all `implements ShouldQueue`, all wired in `app/Providers/EventServiceProvider.php:43,72`. **For a mobile wallet this is the single most user-visible defect in the audit: push is not being delivered.**
- `broadcasts` → `BroadcastEventListener` (ShouldQueue) — real-time WebSocket updates not broadcast.
- `webhooks` → `WebhookService.php:77` dispatches `ProcessWebhookDelivery` — outbound webhooks not delivered.
- `fraud-batch` → `ProcessAnomalyBatchJob` (ShouldQueue) — scheduled fraud scans dead.
- `exchange` → `CheckArbitrageOpportunitiesJob` (exchange feature; lower impact).
- `proofs` → `GenerateDelegatedProofJob` (custodial RAILGUN; disabled by 0B anyway).
- `transfers` → no active producer found — inventory only, no action.

(`config/horizon.php` is even more incomplete — its ES supervisor binds `[env('EVENT_PROJECTOR_QUEUE_NAME')]` = `[null]` — but Horizon is **not** the deployed runner, so that's a parity/future fix, not the live bug.)

**Caveat to confirm on the box.** The repo's `etc/supervisor.conf` is a template with a hardcoded `/home/yozaz/www/finaegis/core-banking-prototype-laravel/artisan` path; the running config lives in the server's `/etc/supervisor/conf.d/`. Run `supervisorctl status` on the server to confirm the actual worker set — the live config may already differ from the repo file.

**Desired state.** Every queue any code dispatches to has a Supervisor worker; a CI guard fails if a new `onQueue()`/`$queue` literal ever lacks a consumer; `config/horizon.php` is also made correct for parity.

**Approach.**
1. Canonical queue inventory as a single source of truth (a `QueueNames` enum or `config/queue.php` `managed_queues`), read by both configs and the CI guard.
2. **`etc/supervisor.conf` (primary — the live runner):** add programs for `mobile`, `broadcasts`, `webhooks`, `fraud-batch`, `exchange` (and `proofs` until 0B lands); parameterise the hardcoded app path so the file is portable across checkouts.
3. **Server apply step (must be in the PR deploy notes):** copy the updated config into `/etc/supervisor/conf.d/` and run `supervisorctl reread && supervisorctl update && supervisorctl restart all`. A repo edit alone does **not** change running workers.
4. **`config/horizon.php` (parity):** replace `[env('EVENT_PROJECTOR_QUEUE_NAME')]` with an explicit list of the 5 ES queues + add the missing queues, so a future switch to Horizon is already correct.
5. **CI guard** (`tests/Feature/Ops/QueueCoverageTest.php`): enumerate every `onQueue('x')` / `$queue = 'x'` / event queue override in `app/` and assert each is in the inventory AND covered by a Supervisor program — fails the build on any uncovered queue.

**Immediate mitigation (out-of-band, recommended today):** add `queue:work --queue=mobile` (+ `broadcasts`, `webhooks`, `fraud-batch`, `exchange`) programs on the server now and `supervisorctl reread && supervisorctl update` — this restores push notifications immediately, ahead of the full PR.

**Tests.** `QueueCoverageTest` (fails on any uncovered queue); a smoke test that both configs parse and cover the inventory.

---

### 0B — RAILGUN custody-neutral production default (P0, custody honesty)

**Problem.** The *custodial* RAILGUN privacy path is wired and **enabled by both production env templates**, deriving every user's privacy seed server-side from `app.key` — textbook custody, directly contradicting the "non-custodial" product claim. It is inert today only because the bridge sidecar is not deployed and no wallets are funded (operational happenstance, not a code control).

**Evidence (current state).**
- `config/privacy.php:17` → `'provider' => env('ZK_PROVIDER', 'demo')`.
- `.env.production.example:240` and `.env.zelta.example:404` → `ZK_PROVIDER=railgun`.
- `PrivacyController::isRailgunMode()` (`PrivacyController.php:44`) gates the custodial money-movement methods (`shield`/`unshield`/`privateTransfer`/`getViewingKey` + proof endpoints) at `:588,:670,:773,:863,:958,:1041,:1273,:1351` into `RailgunPrivacyService`, whose `generateMnemonic()` derives the seed via `hash_hmac('sha512', user->id, app.key)`.
- The non-custodial Phase-1 endpoints (`POST /privacy/wallet/register`, `GET /privacy/engine-config`, `POST /privacy/rpc/{network}`) are **separate** routes, not gated by `ZK_PROVIDER`, and must stay live.

**Desired state.** Production ships custody-neutral: (a) `ZK_PROVIDER` is not `railgun`, and (b) the custodial money-movement privacy endpoints **cannot execute in production regardless of env value** — they return `501 Not Implemented` — until the on-device non-custodial flow (Phase 2/3) is the sole path. The non-custodial registration / engine-config / RPC endpoints remain fully functional.

**Approach.**
1. Change `ZK_PROVIDER=railgun` → `ZK_PROVIDER=demo` in `.env.production.example:240` and `.env.zelta.example:404` (aligns with the `config/privacy.php` default and removes server-side seed derivation).
2. Add a hard production guard on the **custodial subset only** (shield / unshield / private-transfer / viewing-key / delegated-proof). Preferred: route-level middleware on the custodial route group returning `501 CUSTODIAL_PRIVACY_DISABLED` when `app()->environment('production')`, so it is impossible to re-enable by flipping an env var. Do **not** guard the non-custodial `wallet/register`, `engine-config`, `rpc` routes.
3. Extend the existing `ops:verify-env` "RAILGUN provider consistency" check to **FAIL in production** if `ZK_PROVIDER=railgun`.
4. Do **not** attempt to "fix" custodial shield (per the standing CLAUDE.md guidance — shield moves on-device; a server-derived shield key would re-cement custody).

**Tests.** Feature test: custodial privacy endpoints return `501` under `APP_ENV=production`; non-custodial `register`/`engine-config`/`rpc` still return `200/expected`. Env-verify test: `ops:verify-env` fails when `ZK_PROVIDER=railgun` in production.

**Note.** This is safe — there are no funded custodial wallets. It converts a "deliberately un-deployed" state into an enforced one, and is the natural precursor to the Wave-3 Phase-3 removal of the custodial bridge / `DelegatedProofService` / `encrypted_mnemonic`.

---

### 0C — SOC-2 demo-mode production hard-fail (P0, dangerous default)

**Problem.** The SOC-2 compliance-evidence tooling **fabricates audit evidence by default**, and the default is not overridden anywhere — a production deploy serving the evidence endpoint returns invented data stamped `'app_env' => 'production'`, which is actively dangerous if shown to an auditor or customer.

**Evidence (current state).**
- `config/compliance-certification.php:30` → `'demo_mode' => env('SOC2_DEMO_MODE', true)` (**defaults true**).
- `SOC2_DEMO_MODE` is **absent from every env template** (only `REGTECH_DEMO_MODE=false` is set, at `.env.production.example:418` / `.env.zelta.example:582`).
- `ComplianceCertificationController` demo-branches (`:396,:432,:468,:546,:584`) → `EvidenceCollectionService::getDemoEvidence()` returns hardcoded fake data stamped production.

**Desired state.** In production it is impossible to serve fabricated compliance evidence. Demo mode is opt-in, off by default, and rejected in production.

**Approach.**
1. `config/compliance-certification.php:30` → default `false`.
2. Add `SOC2_DEMO_MODE=false` to `.env.production.example` and `.env.zelta.example` (next to `REGTECH_DEMO_MODE`).
3. Hard-fail: when `app()->environment('production')`, force `demo_mode` off (config-resolution guard) and/or make the controller demo branch `abort(404)` in production, so the flag cannot be re-enabled by env.
4. `ops:verify-env`: FAIL in production if `SOC2_DEMO_MODE=true` (mirrors the existing `REGTECH_DEMO_MODE` / `AI_DEMO_MODE` / `KEY_MANAGEMENT_DEMO_MODE` checks).

**Tests.** Feature test: the evidence endpoint under `APP_ENV=production` does **not** return demo/fabricated data (returns real evidence or `404`). Env-verify test: fails on `SOC2_DEMO_MODE=true` in production.

---

### 0D — README / site honesty relabel + reference-platform positioning (P0, truth-in-labelling)

**Problem.** The always-public README markets **SOC 2 Type II** and **PCI DSS** as achieved certifications, and presents the seven financial standards as operational rails. None of the certifications are held; the standards are reference implementations disabled in production. For a regulated fintech this is a legal/reputational exposure.

**Evidence (current state).**
- `README.md:28` → "Regulatory compliance | Built-in KYC/AML, **SOC 2, PCI DSS**, GDPR (v3.5.0)".
- `README.md:36` → "Compliance certification | **SOC 2 Type II**, PCI DSS readiness, …".
- Standards rows present ISO 20022 / SWIFT / Open Banking / etc. as capabilities.
- Honesty caveat exists but is buried at `README.md:427`.
- Promo pages (`resources/views/security.blade.php`, `compliance.blade.php`, `welcome.blade.php`) — some already hedged ("Compliance-Ready", "Compatible"), but `security.blade.php:160` titles a card "SOC 2 Type II Compliance". These are `SHOW_PROMO_PAGES`-gated (not served in Zelta prod), so **README is the priority.**

**Desired state.** Public copy accurately positions FinAegis as an **open-source reference/education platform** and Zelta as the product; describes standards as **reference message implementations** (not operational rails / not scheme-certified); describes compliance as **readiness tooling / audit scaffolding, not held certifications**; states plainly that no third-party SOC 2 or PCI certification is held; and keeps the genuinely-good story visible (real GDPR engine, real message-layer engineering, clean tokenised-card PCI footprint / SAQ-A eligibility).

**Approach.**
1. `README.md`: rewrite lines 28 & 36 and the standards rows to honest language; add a prominent "Status & positioning" note near the top (reference-implementation vs certified/operational); elevate the `:427` caveat next to the claims.
2. `resources/views/security.blade.php`: "SOC 2 Type II Compliance" → "SOC 2 Type II **Readiness Tooling**"; PCI copy → "SAQ-A eligible via tokenised Stripe rail; no cardholder data stored."
3. `resources/views/compliance.blade.php`, `welcome.blade.php`: standards → "reference implementations"; RegTech → "report-format validators / adapters (no live regulator connectivity; not a licensed CASP/ARM)."
4. Run the `post-phase-review` skill before opening the PR (per project workflow) to catch copy/SEO/cross-page consistency.

**Tests.** None (copy). Optional: a doc-lint asserting the README no longer contains "SOC 2 Type II" adjacent to "certification" without the caveat.

## 4. Sequencing & PR plan

Four independent PRs (touch disjoint files → safe to parallelise; `post-phase-review` before each per project workflow):

| PR | Title | Files (primary) | Risk |
|----|-------|-----------------|------|
| 0A | `fix(ops): consume every dispatched queue + CI coverage guard` | **`etc/supervisor.conf` (primary/live)**, `config/horizon.php` (parity), `config/queue.php`, `tests/Feature/Ops/QueueCoverageTest.php` + **server-side `supervisorctl reread/update` step** | Med — restores live-broken push/webhooks/fraud; needs server apply |
| 0B | `fix(privacy): custody-neutral production default + 501 guard on custodial endpoints` | `.env.*.example`, custodial route group + middleware, `ops:verify-env`, tests | Low |
| 0C | `fix(compliance): SOC-2 demo-mode off by default + production hard-fail` | `config/compliance-certification.php`, `ComplianceCertificationController`, `.env.*.example`, `ops:verify-env`, tests | Low |
| 0D | `docs(honesty): reference-platform positioning + truthful compliance/standards copy` | `README.md`, `resources/views/{security,compliance,welcome}.blade.php` | Low (copy) |

0C and 0D are the same "compliance-honesty" theme and may be co-reviewed, but stay separate PRs (code vs copy).

## 5. Testing strategy

- Every code PR (0A/0B/0C) adds a failing-first test that encodes the fix and a matching `ops:verify-env` assertion where applicable.
- Full gate before each merge: `php-cs-fixer` → PHPStan L8 (`XDEBUG_MODE=off`) → `pest --parallel` incl. the new tests; MultiConnection job green.
- 0A's `QueueCoverageTest` becomes a permanent regression guard against future queue-name drift.

## 6. Rollback / risk

- 0A: config-only; rollback = revert. Risk is *under*-provisioning workers (mitigated by the coverage test) — no data mutation.
- 0B/0C: turn risky behaviour off; rollback = revert env value. No data mutation.
- 0D: copy only.
- None of the four touches money-movement code paths; all are default/config/copy changes.

## 7. Definition of done (Wave 0)

- Every dispatched queue has a consumer on the deployed topology; CI guard green.
- Production cannot run the custodial RAILGUN money path (501) and does not default to `ZK_PROVIDER=railgun`; `ops:verify-env` enforces it.
- Production cannot serve fabricated SOC-2 evidence; `ops:verify-env` enforces it.
- README + promo copy make no unqualified certification/operational-rail claim; FinAegis is positioned as a reference/OSS platform.
- `PRODUCTION_READINESS_CHECKLIST.md` updated to reflect the closed items.
