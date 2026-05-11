# Plan B v1.3.0 — Slice 4: Cue Queue Infrastructure (Backend-Q8)

**Date:** 2026-05-10
**Author:** Backend Architect
**Status:** Spec — implementation not started
**Slice predecessor:** Slice 1 (Cashier Stripe subscription path) — merged to `main` as commit `957ea3d8` via PR #1037
**Estimated implementation effort:** 5–7 engineer-days (+ 1 operational cutover day for `revenue_outbox_events` migration)
**Mobile target:** Zelta v1.3.0

---

> **Revision 1 — 2026-05-11** — Doc-grounded grilling pass + controller decision baked in.
>
> Controller decision: slice 4 owns **BOTH sources** of `grace_period_started` (Stripe `customer.subscription.updated status='past_due'` + Apple `DID_FAIL_TO_RENEW`). Previously unresolved mutual deferral between slices 2 and 4 — now resolved.
>
> Fixes applied: F-20 (`grace_period_started` unified ownership in §5.4, §6, Appendix A), F-21 (event classes `SubscriptionTrialStarted` + `OnboardingCompleted` added to scope, §5.5/§12), F-22 (`users.lifetime_spend_cents` + `kyc_completed_at` migration added as §5.6a + §12/§13), F-23 (`EntitlementsService` resolved → use `SubscriptionProjection` + `TrialFingerprintService`, §5.5), F-24 (`dismissedAction` camelCase, §5.3), F-25 (`trial_will_end` distinct event handler added, §5.7), F-26 (deltas Q5.1 is source for cue endpoint contracts, §3), F-27 (`app/Domain/Shared/EventSourcing/` path corrected, §6), F-28 (`ProjectRevenueOutbox` docblock interpretation clarified, §2), F-29 (`Metric::increment` → `Log::info` pragmatic note, §5.10), F-30 (`cues.user_id BIGINT` Q5.1 DDL error noted, §5.1). **Slice 4 now implements 9 of 11 active cue kinds (was 8).**

---

## 1. Working directory and authorisation

You are in a git worktree branched off `main` (which already includes slice 1 from PR #1037, and by the time this spec is implemented, also slice 2 IAP from PR #1038 and slice 3 Pricing from PR #1039).

- Create branch: `feat/plan-b-slice-4-cue-queue`
- Worktree base: `origin/main` (post-slice-1, ideally post-slice-3)
- Do NOT modify any files outside:
  - `app/Domain/Subscription/` (cue dispatch jobs, `CueRepository`, cue model)
  - `app/Console/Commands/` (aggregate-condition cron commands)
  - `app/Filament/Admin/Resources/` (Filament admin for `cues` + dispatch health widget)
  - `app/Filament/Admin/Widgets/` (dispatch-health widget)
  - `database/migrations/` (new `cues` table)
  - `routes/api.php` (new `GET /api/v1/me/pending-cues` + `POST /api/v1/me/cues/{id}/dismissed`)
  - `routes/console.php` (cron appends only — never modify existing schedule lines)
  - `config/subscription.php` (new `cues` section + `pro_marketing_opt_out` handling)
  - `config/error_codes.php` (if any new `ERR_CUE_*` codes are needed — none expected)
  - `app/Providers/AppServiceProvider.php` (if listener binding is needed)
  - `tests/Feature/Cue/` and `tests/Unit/Cue/`
- Do NOT modify `bootstrap/app.php` or `composer.json` (no new vendor dependencies required).
- Commit + push + open PR against `main` titled `feat(subscription): slice 4 — cue queue infrastructure (Backend-Q8)`.
- Do NOT merge; the human reviewer will.
- After all commits are clean (php-cs-fixer, PHPStan L8, Pest pass), report your status using the format in §13.

---

## 2. Situation summary

### What slice 1 delivered (cue-relevant)

Slice 1 (PR #1037) delivered the **Stripe-only subscription lifecycle** plus:

- `POST /webhooks/stripe/subscriptions` — Stripe webhook handler. Its `Cashier event → cue event bridge` (described in the deltas' Backend-Q1 §5 table) emits side-effect signals. In slice 1, the cue-insertion side of that bridge is a **stub** — event types `trial_will_end`, `invoice.payment_failed`, and `customer.subscription.deleted` are received correctly, but the `cues` table does not yet exist and no cue rows are inserted.
- `ProjectRevenueOutbox` job — the off-chain revenue projection worker (ADR-0002). The `ProjectRevenueOutbox` docblock notes slice 4 wires the full delayed-job infrastructure per Backend-Q8. This refers to the cue dispatch layer being added — the outbox worker itself remains unchanged in this slice (slice 4 simply adds its missing schedule entry). The cue queue infrastructure (slice 4) is the `cues` table + dispatch jobs + API endpoints; the outbox stays as-is per ADR-0002.

### What Backend-Q8 decided

Backend-Q8 closed the design decision on **how** to dispatch cues. The review identified that the deltas' implied windowed-cohort cron for all cues (option α) loses entire cohorts on a missed run — at 1–2k onboarding events per day, a single failed cron misfire loses 1–2k unreachable cues.

**Decision (γ): hybrid — aligning each cue type with its natural dispatch pattern.**

| Cue type | Natural pattern | Rationale |
|---|---|---|
| **Time-from-event** | Laravel delayed job dispatched at the source event | Deterministic offset; failure recovery via `failed_jobs`; no cohort loss on cron miss |
| **Aggregate-condition** | Windowed cron with `LEFT JOIN` candidate query | Evolving condition (e.g. AMLD5 lifetime spend threshold); no natural source-event hook |
| **Webhook-driven** | Direct insert from the webhook handler | External event arrival is the trigger; no need for a separate dispatch step |

This decision is **closed**. The architecture is committed in the review-deltas doc. Slice 4 implements (γ) exactly.

### What slice 4 adds

1. **`cues` table** (Q5.1) — the durable per-user-per-kind cue storage.
2. **`GET /api/v1/me/pending-cues`** — mobile's polling endpoint, with server-side precondition reaping (Q5.2).
3. **`POST /api/v1/me/cues/{cue_id}/dismissed`** — idempotent dismiss action (Q5.1).
4. **`CueRepository`** — the idempotent-write service used by all dispatch paths (Q5.4 dedup on `idempotency_key`).
5. **Four delayed-job classes** (`EnqueueProTrialReminderD1`, `EnqueueTrialEnding2d`, `EnqueueTrialEnding1d`, `EnqueueTrialEnding1h`) dispatched from source-event listeners (Q8 time-from-event pattern).
6. **Two new event classes** — `App\Domain\Subscription\Events\SubscriptionTrialStarted` (fires on Stripe trial subscription created) and `App\Domain\Onboarding\Events\OnboardingCompleted` (fires on user onboarding completion). Both are new; they do not exist in slice 1.
7. **One aggregate-condition cron command** (`DispatchKycRequiredCues`) for the `kyc_required` cue kind (Q8 aggregate-condition pattern).
8. **Webhook-driven cue inserts** — filling in the Stripe webhook stubs left by slice 1 for `invoice.payment_failed`, `customer.subscription.deleted`/`cancelled_external`, `charge.refunded`; adding the `customer.subscription.trial_will_end` handler (new — slice 1 does not handle this event); and adding **`grace_period_started`** cue from Stripe `customer.subscription.updated status='past_due'` (Stripe source) and Apple `DID_FAIL_TO_RENEW` (Apple source). **Slice 4 owns BOTH sources of `grace_period_started`** (controller decision 2026-05-11).
9. **Filament admin** — `CueResource` and `CueDispatchHealthWidget` for operator visibility.
10. **`users.pro_marketing_opt_out` column** — the `pro_trial_reminder_d1` opt-out per Q11.2.
11. **`users.lifetime_spend_cents` + `users.kyc_completed_at` columns** — required by `DispatchKycRequiredCues`; new migration (§5.6a). Write-path hooks in `ProjectRevenueOutbox` (spend) and KYC level-transition code (kyc_completed_at).
12. **Outbox worker schedule entry** — the missing `routes/console.php` line from slice 1. Outbox architecture unchanged; slice 4 simply ensures the worker is scheduled properly (see §15).

### Where slice 4 plugs in

The `cues` table is global (no tenant connection — cues are per-user not per-workspace). The `CueRepository::createIdempotent()` method is the single write path; all dispatch strategies call it. The `GET /api/v1/me/pending-cues` endpoint reads from `cues` and applies server-side precondition reaping per Q5.2.

---

## 3. Read these BEFORE writing code (in order)

These files are the canonical source of truth. Read them in this order.

1. **`docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md`** — the authoritative architecture for slice 4. Key sections (search for these headings):
   - `### Q5 — Cue queue (modal/reminder system)` — full `cues` table DDL, Q5.1–Q5.5, all 11 cue kinds
   - `## Cue dispatch architecture (Backend grilling, Q8)` — the (γ) hybrid decision; time-from-event job pattern; aggregate-condition cron pattern; cancel-on-state-change self-cancel; database queue driver locked for v1.3.0; observability metrics table; idempotency under retry
   - `### Q8 — Lock screen + cue + reconciler interaction` (mobile-side only; no backend changes in this section)
   - `### Q11.2 — pro_trial_reminder_d1 delayed job` — the Q11.2 supersession from windowed cron to delayed job
   - `### Q13.6 — Cue export_ready` — when and how `export_ready` cue is created (not in slice 4 scope; see §6)
   - `### Cashier event → cue event bridge` (in the Backend-Q1 section) — the table of which Cashier-handled Stripe events trigger which cues

2. **`docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md`** — original subscription surface area. §1.4 onboarding flow (source event for `pro_trial_reminder_d1`); §1.5 multi-store conflict (source of `family_sharing_unsupported` cue — but that cue's dispatch belongs to slice 2 IAP, not slice 4; see §6). **Cue endpoint contracts** (`GET /pending-cues`, `POST /cues/{id}/dismissed`) are defined in the deltas at Q5.1 — not commercial §1.2 (which covers subscription endpoints).

3. **`docs/adr/0002-revenue-projection-dual-upstream.md`** — ADR-0002's "saga rule" governs why the `ProjectRevenueOutbox` worker (slice 1) is NOT wrapped in a new slice-4 cue layer. The outbox is a separate concern (off-chain revenue projection); the cue queue is for user-facing modal triggers. They share the global DB connection but are architecturally orthogonal.

4. **Slice 1 code (immediate predecessor):**
   - `app/Domain/Subscription/Jobs/ProjectRevenueOutbox.php` — read the docblock noting the slice 4 relationship. Understand the existing per-row lock pattern; slice 4's `ProjectRevenueOutbox` cron scheduling (§15) follows the same `withoutOverlapping()` convention.
   - `app/Domain/Subscription/Webhooks/SubscriptionWebhookController.php` — the stub where webhook-driven cue inserts must be filled in. The dedup + outbox write pattern (in the `handle()` method) is the structural template; cue inserts go in the same per-event `DB::transaction()` block.
   - `database/migrations/2026_05_09_180004_create_revenue_outbox_events_table.php` — understand the existing outbox schema so slice 4 does NOT change it (cue queue is a separate table, not a generalisation of the outbox table; see §8 OD-1 discussion).

5. **`app/Filament/Admin/Resources/TrialCardFingerprintResource.php`** — the most recent Filament resource convention. Mirror: `RespectsModuleVisibility` trait, `navigationGroup`, `canCreate(): bool`, `canEdit()` guard, bulk-actions empty `[]`, custom `Action` with `requiresConfirmation()`.

6. **`routes/console.php`** — existing scheduled commands. Slice 4 **appends** two entries: `DispatchKycRequiredCues` (hourly) and `outbox:project-revenue` (every-minute or every-5-minutes — see §5.7). Never modify existing schedule lines.

7. **`CLAUDE.md`** — project conventions. Especially: multi-connection deadlock rule (cues are global — no `UsesTenantConnection` in this domain; safe to use standard `DB::transaction()`); constructor injection in hot paths (never `app()` in the cue worker or the pending-cues endpoint); `assert()` as auth guard (don't — use guard return patterns).

---

## 4. Existing repo state (foundations slice 4 builds on)

### Tables already in `main` after slice 1

| Table | Purpose | Slice 4 relationship |
|---|---|---|
| `processed_webhook_events` | Stripe event.id dedup | Read-only; slice 4 adds cue inserts in the same transaction that writes this dedup row |
| `revenue_outbox_events` | Off-chain revenue outbox (ADR-0002) | Unchanged; `ProjectRevenueOutbox` continues to process it |
| `revenue_events` | Unified revenue read model | Unchanged |
| `trial_card_fingerprints` | Trial-abuse prevention gate | Unchanged |
| `subscription_consent_log` | EU CRD audit trail | Unchanged |
| Cashier `subscriptions` / `subscription_items` | Stripe-only subscription state | Source of `SubscriptionTrialStartedListener` events |

### Tables NOT yet in `main` — slice 4 must create

| Table | Purpose |
|---|---|
| `cues` | Per-user cue rows (Q5.1 DDL — see §5.1) |

### Columns NOT yet in `main` — slice 4 must add

| Column | Table | Purpose |
|---|---|---|
| `pro_marketing_opt_out` | `users` | Q11.2 opt-out flag (§5.8) |
| `lifetime_spend_cents` | `users` | AMLD5 threshold check for `DispatchKycRequiredCues` (§5.6a) |
| `kyc_completed_at` | `users` | Replaces `kyc_level` enum check for cron candidate query (§5.6a) |

### Services / classes already in `main`

| Class | Location | Slice 4 relationship |
|---|---|---|
| `SubscriptionWebhookController` | `app/Domain/Subscription/Webhooks/` | Slice 4 fills in the cue-insert stubs + adds `trial_will_end` handler |
| `ProjectRevenueOutbox` job | `app/Domain/Subscription/Jobs/` | Slice 4 adds the cron schedule entry + `lifetime_spend_cents` increment call |
| `SubscriptionProjection` | `app/Domain/Subscription/` | Used by delayed jobs for tier checks (replaces `EntitlementsService`) |
| `TrialFingerprintService` | `app/Domain/Subscription/` | Used by `EnqueueProTrialReminderD1` for trial-eligibility checks |
| `RevenueOutboxEvent` model | `app/Domain/Subscription/Models/` | Unchanged |
| `ProcessedWebhookEvent` model | `app/Domain/Subscription/Models/` | Unchanged |
| `TrialCardFingerprintResource` | `app/Filament/Admin/Resources/` | Convention template for Filament admin style |
| `PushNotificationService` | `app/Domain/Mobile/` | Referenced for DLQ alerting OD-3 (see §8) |

### Queue driver

The production queue driver is `database`. This is **locked for v1.3.0** per Backend-Q8. Laravel's database driver is durable across worker restarts and Redis crashes. At 100k MAU × 4 cue job kinds ≈ 400k pending rows steady-state; well within MySQL InnoDB capacity. `SELECT ... FOR UPDATE SKIP LOCKED` handles contention. The documented migration trigger: when sustained queue throughput exceeds 100 jobs/sec for >1h, OR when the `jobs` table exceeds 1M rows steady-state — neither is reachable at v1.3.0 traffic.

---

## 5. Slice 4 scope — BUILD this

### 5.1 The `cues` table

Create `database/migrations/2026_05_10_000001_create_cues_table.php`:

| Column | Type | Notes |
|---|---|---|
| `id` | `CHAR(36) PRIMARY KEY` | UUID v4 |
| `user_id` | `BIGINT UNSIGNED NOT NULL` | FK to `users.id`; `foreignId()` not `foreignUuid()` (users.id is BIGINT per CLAUDE.md). **Note:** Q5.1 source DDL specifies `user_id CHAR(36)` — that is a source-doc error. `users.id` is `BIGINT UNSIGNED` per project convention; corrected here. A future deltas amendment should fix Q5.1's DDL. |
| `kind` | `VARCHAR(64) NOT NULL` | One of the 11 kinds in Q5.5 (see §5.4) |
| `priority` | `ENUM('critical','high','normal') NOT NULL` | Rendering order per Q5.3 |
| `due_at` | `TIMESTAMP NOT NULL` | When the cue becomes available; UTC |
| `expires_at` | `TIMESTAMP NOT NULL` | Absolute render window end; UTC |
| `payload` | `JSON NOT NULL` | Kind-specific data for mobile rendering |
| `dismissed_at` | `TIMESTAMP NULL` | NULL = not yet dismissed |
| `dismissed_action` | `ENUM('cancelled','kept','dismissed') NULL` | Analytics |
| `idempotency_key` | `CHAR(64) NOT NULL` | `sha256({user_id}:{kind}:{occurrence_window_start_iso8601})` |
| `created_at` | `TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP` | |

Indexes:
- `INDEX idx_cues_user_pending (user_id, dismissed_at, due_at, expires_at)` — covers the pending-cues endpoint query
- `UNIQUE KEY uniq_cues_idempotency (user_id, idempotency_key)` — backend-side dedup (Q5.4)

Additional indexes for aggregate-condition cron:
- `users(lifetime_spend_cents, kyc_completed_at)` — composite; needs to be added in the users table migration (see §5.6a below)
- `cues(user_id, kind, dismissed_at)` — already covered by `idx_cues_user_pending`

No `updated_at` column — cues are append-only except for `dismissed_at`/`dismissed_action` updates. Use `timestamps()` without `updated_at` (or add both and accept the minor overhead).

**Multi-connection note:** `cues` is a global table. No `UsesTenantConnection` involvement. Standard `DB::transaction()` is safe everywhere in this slice.

### 5.2 The `CueRepository` service

Location: `app/Domain/Subscription/Services/CueRepository.php`

This is the single write path for all three dispatch strategies. Constructor-injected everywhere — never accessed via `app()`.

Key method:

```
createIdempotent(User $user, string $kind, array $payload, string $occurrenceWindowStartIso8601): Cue
```

- Derives `idempotency_key = sha256("{$user->id}:{$kind}:{$occurrenceWindowStartIso8601}")`.
- Derives `priority`, `expires_at` offset, and `due_at` from a `CueKindRegistry` (a simple `config/subscription.php` sub-section or in-class constant map) keyed by `kind`.
- Executes `INSERT ... ON DUPLICATE KEY UPDATE id = id` (MySQL no-op on conflict) — the returned model is always the existing or just-created row.
- Returns the `Cue` model.

The `CueKindRegistry` (embedded in `CueRepository` or a small `readonly class CueKindConfig`) carries:

| kind | priority | render_window_seconds | precondition_class |
|---|---|---|---|
| `trial_ending_2d` | high | 172800 | `TrialEndingPrecondition::class` |
| `trial_ending_1d` | high | 86400 | `TrialEndingPrecondition::class` |
| `trial_ending_1h` | high | 3600 | `TrialEndingPrecondition::class` |
| `payment_failed` | critical | 604800 | `PaymentFailedPrecondition::class` |
| `subscription_canceled_external` | normal | 604800 | `null` (always returned) |
| `refund_processed` | high | 604800 | `null` (always returned) |
| `grace_period_started` | high | `null` (Apple retry window, ~16d) | `GracePeriodPrecondition::class` |
| `kyc_required` | critical | 2592000 | `KycRequiredPrecondition::class` |
| `family_sharing_unsupported` | normal | `null` (precondition-reaped only) | `FamilySharingPrecondition::class` |
| `export_ready` | normal | 604800 | `ExportReadyPrecondition::class` |
| `pro_trial_reminder_d1` | normal | 604800 | `ProTrialReminderPrecondition::class` |

Precondition classes implement a one-method interface (`isMet(User $user, Cue $cue): bool`) called during the pending-cues reaping step (§5.3).

### 5.3 API endpoints

#### `GET /api/v1/me/pending-cues`

**Auth:** Sanctum bearer, ability `read`.
**Route:** `auth:sanctum` + `scope:read` middleware group.

Processing:

1. Fetch all `cues` rows for the authenticated user where `dismissed_at IS NULL AND due_at <= now() AND expires_at >= now()`.
2. For each row, instantiate the kind's precondition class and call `isMet($user, $cue)`. Suppress rows where `isMet()` returns `false`.
3. Sort by: `priority` (critical < high < normal), then `due_at ASC` within priority.
4. Return JSON array; each item:

```json
{
  "id": "uuid",
  "kind": "trial_ending_1h",
  "priority": "high",
  "dueAt": "2026-05-15T07:00:00Z",
  "expiresAt": "2026-05-15T08:00:00Z",
  "payload": { "trialEndsAt": "2026-05-15T08:00:00Z" }
}
```

Wire format is camelCase (matches existing mobile wire contract convention from slice 1 and ADR-0004).

**Performance note:** `isMet()` must be cheap. `TrialEndingPrecondition::isMet()` checks `$user->subscribed('default')` (Cashier in-memory check after eager loading). `KycRequiredPrecondition::isMet()` checks `$user->kyc_completed_at !== null`. No external HTTP calls. No N+1 queries — eager-load the user's subscription state once before the loop.

#### `POST /api/v1/me/cues/{cue_id}/dismissed`

**Auth:** Sanctum bearer, ability `write`.
**Idempotency-Key header:** required (reuses the existing `idempotency.required` middleware).

Processing:

1. Find `Cue` by `cue_id` where `user_id = auth()->id()`. Return 404 if not found or belongs to another user.
2. If `dismissed_at` is already set: return 200 with the existing cue row (idempotent).
3. Validate `dismissedAction` body field (`cancelled` | `kept` | `dismissed`). Default to `dismissed` if absent. (Wire convention is camelCase per ADR-0004 and §7; read via `$request->input('dismissedAction', 'dismissed')`.)
4. Set `dismissed_at = now()`, `dismissed_action = $request->input('dismissedAction', 'dismissed')`. Save.
5. Return 200.

### 5.4 The eleven cue kinds (v1.3.0 complete list)

The Q5.5 table from the deltas defines all 11 kinds with their priorities, render windows, precondition reaping logic, triggers, and dispatch patterns. Slice 4 implements **9 of the 11**:

| Kind | Dispatch pattern | Implemented in |
|---|---|---|
| `trial_ending_2d` | Delayed job (`EnqueueTrialEnding2d`) | Slice 4 |
| `trial_ending_1d` | Delayed job (`EnqueueTrialEnding1d`) | Slice 4 |
| `trial_ending_1h` | Delayed job (`EnqueueTrialEnding1h`) | Slice 4 |
| `payment_failed` | Direct webhook insert | Slice 4 (fills Stripe stub; IAP path fills separately in slice 2) |
| `subscription_canceled_external` | Direct webhook insert | Slice 4 (fills Stripe stub; IAP path in slice 2) |
| `refund_processed` | Direct webhook insert | Slice 4 (fills Stripe stub; IAP path in slice 2) |
| `grace_period_started` | Direct webhook insert — **TWO sources, both owned by slice 4** | Slice 4. Source 1: Stripe `customer.subscription.updated status='past_due'` webhook (via `SubscriptionWebhookController`). Source 2: Apple `DID_FAIL_TO_RENEW` notification (via slice 4's Apple webhook handler stub — to be wired similarly to the Stripe stub). Both insert a `grace_period_started` cue via `CueRepository::createIdempotent()`. (Previously this was deferred to slice 2 — mutual deferral caught and resolved per controller decision 2026-05-11.) |
| `kyc_required` | Aggregate-condition hourly cron | Slice 4 |
| `family_sharing_unsupported` | Direct webhook insert during IAP verify | **Slice 2** |
| `export_ready` | Direct event insert on export job completion | **Deferred** — depends on export feature; not in v1.3.0 slice 4 scope |
| `pro_trial_reminder_d1` | Delayed job (`EnqueueProTrialReminderD1`) | Slice 4 |

Slice 4 implements 9 of the 11 active kinds (increased from 8 — `grace_period_started` transferred from slice 2). The 2 remaining kinds deferred to their owning slices are noted explicitly.

### 5.5 Time-from-event delayed jobs

Four job classes under `app/Domain/Subscription/Jobs/Cue/`:

**`EnqueueProTrialReminderD1`** — dispatched by `OnboardingCompletedListener` with a `->delay(now()->addDay())`.

Job handle logic:

```
1. User::find($userId) → if null (user erased): log cue.job.skipped reason=user_erased, return
2. $user->pro_marketing_opt_out → if true: log cue.job.skipped reason=opted_out, return
3. SubscriptionProjection::for($user)['tier'] → if not 'free': log cue.job.skipped reason=tier_changed, return
4. TrialFingerprintService::isEligibleForCard($user) → if false: log cue.job.skipped reason=trial_used, return
5. $cueRepository->createIdempotent($user, 'pro_trial_reminder_d1', payload, occurrence_window_start)
6. Log cue.job.success kind=pro_trial_reminder_d1
```

**Note on `EntitlementsService`** (F-23): There is no pre-existing `EntitlementsService` class. The spec formerly treated it as pre-existing. **Resolution:** Use slice 1's `SubscriptionProjection::for($user)` (returns `tier` and `status`) for tier checks, and `TrialFingerprintService::isEligibleForCard($user)` for trial-eligibility checks (both exist from slice 1). No new `EntitlementsService` class is needed or in scope for slice 4.

The occurrence window for `pro_trial_reminder_d1` is lifetime per Q5.4 (a user gets at most one), so `occurrence_window_start_iso8601 = '1970-01-01T00:00:00Z'` (epoch as the stable window identifier).

**`EnqueueTrialEnding2d`**, **`EnqueueTrialEnding1d`**, **`EnqueueTrialEnding1h`** — dispatched by `SubscriptionTrialStartedListener`:

```php
EnqueueTrialEnding2d::dispatch($userId, $trialEndsAt)->delay($trialEndsAt->subDays(2));
EnqueueTrialEnding1d::dispatch($userId, $trialEndsAt)->delay($trialEndsAt->subDays(1));
EnqueueTrialEnding1h::dispatch($userId, $trialEndsAt)->delay($trialEndsAt->subHour());
```

Each job handle follows the same shape:

```
1. User::find($userId) → null → cue.job.skipped reason=user_erased, return
2. Cashier subscription active+paid → cue.job.skipped reason=tier_changed, return
3. $cueRepository->createIdempotent($user, 'trial_ending_{interval}', payload, $trialStartedAt->toIso8601String())
4. cue.job.success
```

The occurrence window for `trial_ending_*` is the trial period itself, keyed by `$trialStartedAt`.

**Self-cancel pattern:** If the user converts mid-trial, all three queued jobs fire and self-cancel via steps 2. At ~30% trial-cancel rate, that's ~0.9 wasted job-runs per trialing user. Marginal at v1.3.0 traffic. No proactive `Bus::findBatch()` cancellation — simpler, correct under retry.

**`tries` and `backoff`:** Inherit from `ProjectRevenueOutbox` convention: `public int $tries = 5; public int $backoff = 30;` (5 attempts, 30s backoff). Permanent failures land in `failed_jobs` for ops review.

**Event class scope (F-21):** The two source events driving these listeners do NOT exist yet and must be created as part of slice 4:

- `App\Domain\Subscription\Events\SubscriptionTrialStarted` — fired by the `SubscriptionWebhookController` (or a dedicated listener on `WebhookReceived`) when a Stripe `customer.subscription.created` event arrives and the subscription has `trial_ends_at IS NOT NULL`. This is the trigger for `SubscriptionTrialStartedListener`.
- `App\Domain\Onboarding\Events\OnboardingCompleted` (or `App\Domain\Subscription\Events\OnboardingCompleted` if an Onboarding domain does not exist — check `app/Domain/` for an existing `Onboarding` or `User` domain that signals onboarding completion). Wire from wherever the platform currently dispatches the user `welcome` completion signal (the user registration + profile-completion flow). This is the trigger for `OnboardingCompletedListener`.

Both event classes must be added to slice 4 deliverables and are reflected in the commit topology (§12 commit 4).

### 5.6 Aggregate-condition cron: `DispatchKycRequiredCues`

Location: `app/Console/Commands/DispatchKycRequiredCues.php`

Runs **hourly**. The command uses a `LEFT JOIN`-based candidate query — NOT `NOT IN (SELECT ...)` (the subquery form degrades at scale):

```sql
SELECT u.id AS user_id
  FROM users u
  LEFT JOIN cues c
    ON c.user_id = u.id
   AND c.kind = 'kyc_required'
   AND c.dismissed_at IS NULL
 WHERE u.lifetime_spend_cents >= 100000
   AND u.kyc_completed_at IS NULL
   AND c.id IS NULL
```

Iterate via `chunkById(1000)` for memory isolation. For each candidate user, call `$cueRepository->createIdempotent(...)` with `occurrence_window_start_iso8601 = '1970-01-01T00:00:00Z'` (lifetime window — one active `kyc_required` cue per user at a time).

Required indexes (verify/add if absent):
- `users(lifetime_spend_cents, kyc_completed_at)` — composite candidate filter
- `cues(user_id, kind, dismissed_at)` — covered by existing `idx_cues_user_pending`

Emit `cue.cron.aggregate.kyc_required.candidates` (gauge) and `cue.cron.aggregate.kyc_required.processed` (counter) per Q8 observability table.

**Cron cadence:** Hourly at `:15` past the hour (offset from the busy `:00` slot). The AMLD5 €1,000 threshold is not second-sensitive.

### 5.6a New `users` columns: `lifetime_spend_cents` + `kyc_completed_at` (F-22)

The `DispatchKycRequiredCues` query (§5.6) filters on `users.lifetime_spend_cents` and `users.kyc_completed_at`. Neither column exists in the current schema:

- `users.lifetime_spend_cents` does not exist (no such column in slice 1).
- `users.kyc_completed_at` does not exist — the current column is `users.kyc_level` (enum).

**Decision (column-add approach):** Add both columns via a new migration `2026_05_10_000003_add_lifetime_spend_and_kyc_completed_at_to_users_table.php`:

| Column | Type | Notes |
|---|---|---|
| `lifetime_spend_cents` | `BIGINT UNSIGNED NOT NULL DEFAULT 0` | Updated by the revenue write path. See write-path note below. |
| `kyc_completed_at` | `TIMESTAMP NULL DEFAULT NULL` | Set when `kyc_level` transitions to a completed value (check `app/Domain/Compliance/` or `app/Domain/KYC/` for the existing transition point). |

**`lifetime_spend_cents` write path:** The column is maintained by the `ProjectRevenueOutbox` worker (slice 1's off-chain projection job) — add an `User::where('id', $userId)->increment('lifetime_spend_cents', $amountCents)` call after a `revenue_events` row is written (in the same transaction, or immediately after). Using a column rather than computing on-the-fly (`SUM(revenue_events.amount_cents)`) is the preferred approach because it enables an efficient composite index on `(lifetime_spend_cents, kyc_completed_at)` for the hourly cron query. A computed `SUM` on `revenue_events` at cron time would require a full index scan at 100k+ MAU scale.

**`kyc_completed_at` write path:** When `users.kyc_level` transitions to a fully-verified state (the exact enum value — likely `'verified'` or `'full'` — confirm in the existing KYC flow), set `kyc_completed_at = now()`. Add this to the same code path that currently updates `kyc_level`.

Add composite index: `INDEX idx_users_kyc_spend (lifetime_spend_cents, kyc_completed_at)` (see §5.6 required indexes).

This migration and its associated write-path changes are slice 4 deliverables. Update §12 commit topology and §13 status format to include this migration.

### 5.7 Webhook-driven cue inserts (filling slice 1 stubs)

In `SubscriptionWebhookController`, the following Cashier-handled Stripe events get cue-insert code added inside the existing per-event `DB::transaction()` block (same transaction as the dedup row write):

| Stripe event | Cue kind | Notes |
|---|---|---|
| `invoice.payment_failed` | `payment_failed` | Occurrence window = billing cycle start (`subscription_period_start` from Stripe payload) |
| `invoice.payment_succeeded` (after failure) | Suppress active `payment_failed` cue | Set `dismissed_at = now()` on any un-dismissed `payment_failed` cue for this user |
| `customer.subscription.deleted` | `subscription_canceled_external` | Only if `cancellation_details.reason` is `'cancellation_requested'` (user-initiated) |
| `customer.subscription.trial_will_end` | Triggers the three `trial_ending_*` delayed jobs | **Note:** This is `customer.subscription.trial_will_end` — a distinct event from `customer.subscription.updated`. Stripe fires this ~3 days before trial end. Slice 1's `SubscriptionWebhookController::dispatchEvent()` does NOT currently handle `trial_will_end` (it handles `updated` and `deleted`). **Slice 4 must add a handler for `trial_will_end`** alongside the existing event cases. On receipt, fan out by dispatching `EnqueueTrialEnding{2d,1d,1h}` from the subscription's current `trial_end` timestamp. |
| `customer.subscription.updated` (status=`'past_due'`) | `grace_period_started` | The Stripe source for the `grace_period_started` cue. Occurrence window = subscription period start. (Second source: Apple `DID_FAIL_TO_RENEW` — see §5.4.) |
| `charge.refunded` | `refund_processed` | Occurrence window = refund `created` timestamp |

**Important:** The `trial_will_end` Stripe event fires once at ~3 days before trial end. Rather than trusting Cashier's timing entirely, the `SubscriptionTrialStartedListener` (dispatched when a Cashier trial subscription is created) also dispatches the three delayed jobs from the trial start time. Both paths are present; `cues.uniq_cues_idempotency` prevents double-creation if both fire.

### 5.8 `users.pro_marketing_opt_out` column

Per Q11.2: add a boolean column `pro_marketing_opt_out TINYINT(1) DEFAULT 0` to the `users` table. Create migration `2026_05_10_000002_add_pro_marketing_opt_out_to_users_table.php`.

Endpoint for mobile to set the flag:

```
POST /api/v1/me/marketing-opt-out
Body: { "optOut": true | false }
Response: 200 { "proMarketingOptOut": true }
```

**Auth:** Sanctum bearer, ability `write`.
**Regulatory note:** PECR/ePrivacy compliance. The `pro_trial_reminder_d1` delayed job checks `$user->pro_marketing_opt_out` at fire time and skips if true.

This column is also referenced by GDPR erasure: when a user is pseudonymised, `pro_marketing_opt_out` is reset to `0` (no preference survives anonymisation). Add to the GDPR erasure walk in `app/Http/Controllers/Api/GdprController.php` if the erasure walk is already present; otherwise, document as a §15 coordination item.

### 5.9 Filament admin

#### `CueResource`

Location: `app/Filament/Admin/Resources/CueResource.php`

Follows `TrialCardFingerprintResource` conventions exactly:
- `use RespectsModuleVisibility;`
- `protected static ?string $navigationGroup = 'Commerce';` (matches slice 1 nav group)
- `protected static ?string $navigationLabel = 'Cue Queue';`
- `canCreate(): false`
- `canEdit(): false`

Table columns:
- `user_id` — sortable
- `kind` — filterable (select from the 11 kind values)
- `priority` — badge (critical=danger, high=warning, normal=gray)
- `status` — derived: `pending` (not dismissed, not expired), `dismissed`, `expired`, `suppressed` (dismissed_at null but expired_at past)
- `due_at` — date-time, sortable
- `expires_at` — date-time
- `dismissed_at` — nullable, date-time
- `idempotency_key` — truncated, copyable

Filters: status (pending / dismissed / expired), kind, date range on `created_at`.

Custom actions:
- **"Mark dismissed"** — `Action::make('markDismissed')` with `requiresConfirmation()`. Sets `dismissed_at = now()`, `dismissed_action = 'dismissed'`. For operator use when a cue is known-stale.
- **"Force delete"** — admin-only (`canDeleteAny()` guarded by `auth()->user()->is_admin`), with double-confirmation modal. Hard-deletes the row. Use only when a cue was created erroneously.

No bulk actions (consistent with slice 1 convention).

#### `CueDispatchHealthWidget`

Location: `app/Filament/Admin/Widgets/CueDispatchHealthWidget.php`

- Implements `WidgetRespectsModuleVisibility` trait (per CLAUDE.md admin panel rules).
- `protected static string $adminModule = 'Commerce';`
- Renders a status card: green if all dispatch paths processed in last 24h with <1% failure rate; yellow if any job kind has 1–5% failure rate; red if any job kind >5% failure rate or cron missed.
- Reads from: `failed_jobs` table (count failed jobs by `payload->kind` in last 24h), plus the `cue.cron.aggregate.kyc_required.duration` metric from cache.
- A "View failed jobs" link navigates to the existing Laravel `failed_jobs` admin if present; otherwise links to the `CueResource` index filtered by `status=expired`.

### 5.10 Observability metrics

All metrics emitted via `Log::info()` structured log channels (consistent with existing `revenue_outbox.project_failed` log usage in slice 1). No Prometheus dependency introduced in this slice.

**Note on Q8 metric references (F-29):** The Backend-Q8 source doc references `Metric::increment()` emitted to Sentry and a "Sentry + Filament dashboard." The codebase has no Sentry SDK and no `Metric` facade. `Log::info()` to structured channels — matching slice 1's `revenue_outbox.project_failed` pattern — is the implementable equivalent. Adding a proper metrics emission layer (Sentry, Prometheus, or similar) is out of slice 4 scope; a future infrastructure slice could swap `Log::info()` calls for real metric emission without changing the call sites.

Per Backend-Q8 observability table:

| Metric | Source |
|---|---|
| `cue.job.dispatched.{kind}` | `OnboardingCompletedListener` / `SubscriptionTrialStartedListener` at dispatch call |
| `cue.job.success.{kind}` | Job handle(), after `createIdempotent()` call |
| `cue.job.skipped.{kind}.{reason}` | Job handle(), early returns (see §5.5) |
| `cue.job.failed.{kind}` | Job `failed(Throwable $e)` method — implement this hook |
| `cue.job.duration.{kind}` | `$startAt = microtime(true)` at job start; emit `microtime(true) - $startAt` on success |
| `cue.cron.aggregate.{kind}.candidates` | `DispatchKycRequiredCues` before loop |
| `cue.cron.aggregate.{kind}.processed` | `DispatchKycRequiredCues` after loop |
| `cue.cron.aggregate.{kind}.duration` | `DispatchKycRequiredCues` start/end |
| `cue.dispatch_health` | Computed by `CueDispatchHealthWidget` from `failed_jobs` |

`cue.job.skipped` is the load-bearing signal: a spike in `tier_changed` skips means the trial-conversion funnel is working (good); a spike in `user_erased` skips means a potential account-deletion bug (bad).

### 5.11 Idempotency under retry

`cues.uniq_cues_idempotency` covers the duplicate-job edge case. If a delayed job fires twice (queue retry after partial failure), the second `createIdempotent()` call hits the UNIQUE key constraint and returns the existing row as a no-op. No double-cues. No special guard needed in the job beyond the repository's `INSERT ... ON DUPLICATE KEY` pattern.

---

## 6. Out of scope — slice 4 does NOT

- **`export_ready` cue** — depends on the export feature (not yet in v1.3.0 scope). Add when the export job ships.
- **`family_sharing_unsupported` cue** — triggered during `POST /api/v1/subscription/iap/verify`. Belongs to slice 2.
- **`grace_period_started` (Apple-only path that goes beyond slice 4's scope)** — slice 4 owns BOTH sources of `grace_period_started` (Stripe `past_due` webhook AND Apple `DID_FAIL_TO_RENEW`). The Apple-side stub requires coordination with slice 2's IAP webhook infrastructure, but the `grace_period_started` cue insertion for both sources is owned by slice 4. Slice 2 does NOT own this cue kind. (Previous spec had mutual deferral — resolved per controller decision 2026-05-11.)
- **IAP `payment_failed` cue** — IAP-side billing failure cues are slice 2's responsibility; slice 4 covers only Stripe-side `payment_failed`.
- **Replace `revenue_outbox_events` table** — the outbox pattern (ADR-0002) is architecturally separate from the cue queue. The outbox is for off-chain revenue projection; the `cues` table is for user-facing modal triggers. Do NOT attempt to generalise the outbox into the cue system or vice versa. See §8 OD-1 for why this is an explicit non-decision.
- **Refactor Spatie event sourcing** — event sourcing is the write-model; cue queue is for outbound side-effects triggered by those writes. They are orthogonal layers.
- **Redis Streams migration** — `app/Domain/Shared/EventSourcing/` (Redis Streams publisher/consumer with DLQ + backpressure) is NOT the right foundation for the cue dispatch system. Backend-Q8 explicitly chose the Laravel `database` queue driver for v1.3.0 because durability > latency at this scale. The EventSourcing/EventStreaming domain is for intra-service event fan-out (existing use cases); the cue queue needs durable per-row persistence per kind per user. Do not route delayed jobs through Redis Streams.
- **New observability infrastructure** — Prometheus/Grafana metrics are a separate stream. Slice 4 uses structured log channels (`Log::info()`), consistent with existing patterns.
- **GDPR erasure walk** — slice 4 adds `pro_marketing_opt_out` to the users table; a full GDPR walk extension is a coordination item (§15), not a code deliverable in this slice.
- **Legacy-user `pro_trial_reminder_d1` backfill** — users who onboarded before v1.3.0 rollout don't have a delayed job dispatched at their onboarding event. Per Backend-Q8: acceptable; the cue is for net-new acquisition. If the founder wants a retroactive backfill, ship a one-shot backfill cron in v1.3.1. Do NOT add it here.
- **Card waitlist cue** (slice 5) — slice 5 ships its own cue types (`card_waitlist_*`); it uses `CueRepository::createIdempotent()` from the foundation slice 4 provides, but slice 4 does not define card waitlist cues.

---

## 7. Mobile contract

**Slice 4 adds two new API endpoints** — both mobile-facing:

### `GET /api/v1/me/pending-cues`

Response (200):
```json
[
  {
    "id": "uuid",
    "kind": "trial_ending_1h",
    "priority": "high",
    "dueAt": "2026-05-15T07:00:00Z",
    "expiresAt": "2026-05-15T08:00:00Z",
    "payload": { "trialEndsAt": "2026-05-15T08:00:00Z" }
  }
]
```

Empty array `[]` when no pending cues. No 404. Mobile polls on app foreground (not on a timer); every foreground event triggers this endpoint. The cue is a "render this UI now" item — not a notification. Mobile's `CueOrchestrator` renders the highest-priority pending cue as a modal.

**Precondition reaping** is server-side: if a cue's precondition is no longer met, it is excluded from the response. Mobile does not need to check preconditions.

**Server side:** No caching of the response — precondition state can change between foreground events, and the table is small per user.

### `POST /api/v1/me/cues/{cue_id}/dismissed`

Request (optional body):
```json
{ "dismissedAction": "kept" }
```

`dismissedAction` values: `cancelled` (user cancelled the modal), `kept` (user chose to keep the subscription), `dismissed` (generic dismiss). Defaults to `dismissed` if absent.

Response (200):
```json
{ "id": "uuid", "dismissedAt": "2026-05-15T07:05:00Z" }
```

Idempotent — replaying the same request returns 200 with the already-set `dismissedAt`.

### `POST /api/v1/me/marketing-opt-out`

Request:
```json
{ "optOut": true }
```

Response (200):
```json
{ "proMarketingOptOut": true }
```

Mobile shows a "Don't show me Pro reminders again" link on the onboarding paywall. This endpoint sets `users.pro_marketing_opt_out`.

### Wire conventions

- All field names are **camelCase** on the wire (matches slice 1 convention and ADR-0004).
- All timestamps are **UTC ISO 8601** (`Z` suffix).
- `priority` values are lowercase strings: `"critical"`, `"high"`, `"normal"`.
- `kind` values are lowercase snake_case: `"trial_ending_1h"`, `"kyc_required"`, etc.

### Mobile contract owner

The endpoint shapes above are agreed per Q5.1 in the deltas. No mobile-side changes are needed to consume them — the mobile client polls `GET /pending-cues` on foreground, renders the first result, and posts to `POST /cues/{id}/dismissed` on user action. All precondition reaping happens server-side.

---

## 8. Open design decisions

### OD-1 — Cue queue vs outbox: unified `cue_events` table or separate concerns? (RESOLVED: separate)

The prompt's original framing explored whether slice 4 should generalise `revenue_outbox_events` into a unified `cue_events` table. **After reading Backend-Q8, this is not the right question.** The cue queue (Q5 + Q8) and the revenue outbox (ADR-0002 / Q3) serve different purposes:

- **Cue queue (`cues` table):** User-facing modal triggers. One row per user per kind per occurrence window. Read by the mobile client on every foreground. Managed by `CueRepository` with idempotent semantics.
- **Revenue outbox (`revenue_outbox_events`):** Off-chain revenue projection worker rows. Not user-facing. Read by `ProjectRevenueOutbox` worker. Semantics: write on PSP webhook receipt, project to `revenue_events`, mark delivered.

These are **architecturally orthogonal**. Merging them into a unified `cue_events` table would:
1. Contaminate the user-facing pending-cues endpoint with revenue projection rows
2. Require mobile-visible semantics (kind, priority, dismiss action) on rows that have none
3. Undermine ADR-0002's explicit separation of the dual-upstream projection paths

**Decision: keep them separate.** The `cues` table is new in slice 4. `revenue_outbox_events` is unchanged. The implementation agent must NOT attempt to merge these concerns.

This was never an open question in Backend-Q8 — it is noted here to pre-empt an incorrect implementation choice.

### OD-2 — Immediate vs phased migration of slice 1's outbox

**Situation:** `revenue_outbox_events` exists in production after slice 1. Its `ProjectRevenueOutbox` worker is the only consumer. No migration into a different table is needed (OD-1 is resolved above).

**Residual question:** Slice 4 must schedule the `ProjectRevenueOutbox` job in `routes/console.php`. The slice 1 implementation shipped without a schedule entry (the spec said "scheduled job" but the cron line wasn't in scope for slice 1). Slice 4 adds it.

**Recommendation:** `Schedule::job(new ProjectRevenueOutbox())->everyFiveMinutes()->withoutOverlapping()`. At v1.3.0 traffic, 5-minute sweep cadence is sufficient. Offset the run by `:00` + 5min intervals so it doesn't collide with the pending-cues poll traffic peak (app foreground).

**Not an open decision — just a coordination item.** The implementation agent should add this schedule entry.

### OD-3 — DLQ alerting channel: Filament only, or push to operators?

**Situation:** When `failed_jobs` accumulates cue-job failures (>5% failure rate per Backend-Q8 alerting threshold), operators need to know.

**Options:**
- (a) **Filament dashboard only** — `CueDispatchHealthWidget` turns red; no active alert.
- (b) **Daily email digest** — a `cue:dlq-digest` cron command sends a summary email to `MAIL_ADMIN_ADDRESS` if failure rate exceeded in the past 24h.
- (c) **Slack webhook** — routes through existing `PushNotificationService` or a new `SlackNotifier`.

**Recommendation:** Option (b) — daily digest email. Rationale: Filament-only (a) requires an operator to be actively looking at the dashboard; Slack (c) introduces a new notification channel and additional configuration. An email digest is the minimum viable alert for v1.3.0. Slack can be added in v1.4.

**Controller input required:** Confirm whether `MAIL_ADMIN_ADDRESS` is already set in production env, and whether a daily digest email is acceptable vs a Slack hook. If neither is set up, fall back to (a) Filament-only for v1.3.0.

### OD-4 — Worker scaling: serial or N-process per aggregate?

**Situation:** The `database` queue driver's `SELECT ... FOR UPDATE SKIP LOCKED` handles concurrency naturally. Multiple worker processes can run in parallel without deadlock.

**For cue dispatch jobs specifically:** Per ADR-0002's saga rule, jobs that write to both the tenant connection and the global connection need per-aggregate ordering. **Cue insertion jobs do not touch the tenant connection** — they write only to the global `cues` table. There is no multi-connection saga concern here.

**Recommendation:** Run the standard Laravel queue worker with `--queue=default --timeout=60 --max-jobs=500`. No special per-aggregate serialisation needed. Multiple workers can run in parallel. The `uniq_cues_idempotency` constraint handles any race between parallel workers trying to insert the same cue.

**Not an open decision — just a sizing note for the deployment runbook.**

### OD-5 — `pro_marketing_opt_out` setter: separate endpoint or `PATCH /me` body extension?

**Options:**
- (a) **Dedicated endpoint** `POST /api/v1/me/marketing-opt-out` as specified in §7.
- (b) **Extend `PATCH /api/v1/me`** with `{ "proMarketingOptOut": true }` — if a user profile update endpoint exists.

**Recommendation:** (a) separate endpoint. Rationale: there may not be a generic `PATCH /me` in v1.3.0; the opt-out has specific regulatory semantics (PECR) that merit an isolated endpoint with its own idempotency + audit trail. If a `PATCH /me` exists in the codebase, consider routing through it as an alternative — but the dedicated endpoint is safe regardless.

**Controller input requested:** Confirm preferred approach. Separate endpoint adds one route and one controller method.

---

## 9. Acceptance checklist

### Database

- [ ] Migration `create_cues_table` creates the table with all columns, `idx_cues_user_pending` index, and `uniq_cues_idempotency` unique key
- [ ] Migration `add_pro_marketing_opt_out_to_users` adds the boolean column with `default(false)`
- [ ] Migration `add_lifetime_spend_cents_and_kyc_completed_at_to_users` adds both columns with correct types and composite index (§5.6a)
- [ ] `php artisan migrate` runs without error on a clean database (migrations are idempotent)
- [ ] `cues` table is global (no `UsesTenantConnection` on the model); confirmed by checking the model has no such trait
- [ ] `SubscriptionTrialStarted` event class exists at `App\Domain\Subscription\Events\SubscriptionTrialStarted`
- [ ] `OnboardingCompleted` event class exists (verify domain namespace)
- [ ] `SubscriptionWebhookController` has a handler for `customer.subscription.trial_will_end` (new in slice 4)
- [ ] `grace_period_started` cue is inserted by both the Stripe `past_due` webhook path and the Apple `DID_FAIL_TO_RENEW` path

### API endpoints

- [ ] `GET /api/v1/me/pending-cues` returns `[]` for a user with no cues
- [ ] `GET /api/v1/me/pending-cues` returns cues ordered by priority then `due_at ASC`
- [ ] `GET /api/v1/me/pending-cues` suppresses a `trial_ending_1h` cue for a user who has since converted to paid
- [ ] `GET /api/v1/me/pending-cues` suppresses a `kyc_required` cue for a user who has completed KYC
- [ ] `GET /api/v1/me/pending-cues` does NOT return cues with `dismissed_at IS NOT NULL`
- [ ] `GET /api/v1/me/pending-cues` does NOT return cues with `expires_at < now()`
- [ ] `POST /api/v1/me/cues/{cue_id}/dismissed` sets `dismissed_at` and returns 200
- [ ] `POST /api/v1/me/cues/{cue_id}/dismissed` is idempotent on a second call
- [ ] `POST /api/v1/me/cues/{cue_id}/dismissed` returns 404 for a cue belonging to a different user
- [ ] `POST /api/v1/me/marketing-opt-out` sets `users.pro_marketing_opt_out` and returns 200
- [ ] Both endpoints return 401 without a valid Sanctum token
- [ ] Missing `Idempotency-Key` on `POST /dismissed` returns `ERR_VALIDATION_001`

### Delayed jobs

- [ ] `EnqueueProTrialReminderD1` dispatches with 24h delay on `OnboardingCompleted` event
- [ ] `EnqueueProTrialReminderD1` emits `cue.job.skipped reason=user_erased` if user was deleted before fire
- [ ] `EnqueueProTrialReminderD1` emits `cue.job.skipped reason=opted_out` if `pro_marketing_opt_out = true`
- [ ] `EnqueueProTrialReminderD1` emits `cue.job.skipped reason=tier_changed` if user is on paid tier
- [ ] `EnqueueProTrialReminderD1` emits `cue.job.skipped reason=trial_used` if trial already used
- [ ] `EnqueueProTrialReminderD1` inserts a `pro_trial_reminder_d1` cue on eligible user
- [ ] Queue retry scenario: `createIdempotent()` on second job fire hits `uniq_cues_idempotency` — no duplicate cue created
- [ ] Same test for `EnqueueTrialEnding{2d,1d,1h}`
- [ ] Three `trial_ending_*` jobs dispatch at correct delays relative to trial start
- [ ] `SubscriptionTrialStartedListener` dispatches all three jobs with correct delay arithmetic

### Aggregate-condition cron

- [ ] `DispatchKycRequiredCues` runs via `chunkById(1000)` — no unbounded in-memory collection
- [ ] `DispatchKycRequiredCues` emits `cue.cron.aggregate.kyc_required.candidates` gauge before chunk loop
- [ ] `DispatchKycRequiredCues` emits `cue.cron.aggregate.kyc_required.processed` counter after chunk loop
- [ ] `DispatchKycRequiredCues` does NOT create a second `kyc_required` cue for a user who already has one pending
- [ ] `DispatchKycRequiredCues` does NOT skip users who had a `kyc_required` cue dismissed (dismissed = precondition no longer met → reaping suppresses at GET time; a NEW cue may be appropriate if spend crosses the threshold again. Decision: treat dismissed as "user acknowledged"; do NOT recreate unless crossed again. The `LEFT JOIN` already covers this: dismissed cue has `dismissed_at IS NOT NULL`, so the join finds it and DOES create a new row. This is intentional — user may cross threshold again after dismissing the first cue.)
- [ ] Cron schedule entry appended to `routes/console.php` (`->hourly()->at(':15')->withoutOverlapping()`)

### Webhook-driven inserts

- [ ] `invoice.payment_failed` Stripe event inserts a `payment_failed` cue
- [ ] `invoice.payment_succeeded` (after failure) sets `dismissed_at` on any pending `payment_failed` cue
- [ ] `customer.subscription.deleted` inserts `subscription_canceled_external` cue (user-initiated cancel only)
- [ ] `charge.refunded` inserts `refund_processed` cue
- [ ] `customer.subscription.trial_will_end` Stripe event triggers all three `trial_ending_*` delayed jobs (new handler in slice 4 — slice 1 does not handle this event)
- [ ] `customer.subscription.updated status='past_due'` inserts `grace_period_started` cue (Stripe source)
- [ ] Apple `DID_FAIL_TO_RENEW` notification inserts `grace_period_started` cue (Apple source)
- [ ] Cue insert is in the same `DB::transaction()` as the `processed_webhook_events` dedup write
- [ ] Replaying the same Stripe event (dedup hit) does NOT re-insert the cue

### Filament admin

- [ ] `CueResource` lists rows with kind, priority, and status columns
- [ ] `CueResource` filter by kind and status works
- [ ] `CueResource` "Mark dismissed" action sets `dismissed_at` with confirmation
- [ ] `CueResource` uses `RespectsModuleVisibility` trait
- [ ] `CueDispatchHealthWidget` renders green/yellow/red status card
- [ ] `CueDispatchHealthWidget` uses `WidgetRespectsModuleVisibility` trait

### Quality gates (per CLAUDE.md)

- [ ] `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php` — zero diff after fix
- [ ] `XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G` — Level 8, zero errors
- [ ] `./vendor/bin/pest --parallel --stop-on-failure` — all green

---

## 10. Conventions and pitfalls

| Concern | Convention |
|---|---|
| Constructor injection | Never `app()` inside job `handle()`, cron command, or the pending-cues controller. Inject `CueRepository`, `SubscriptionProjection`, `TrialFingerprintService`, etc. via constructor. The worker and controller are latency-sensitive paths. (`EntitlementsService` does not exist — use `SubscriptionProjection` and `TrialFingerprintService` from slice 1 instead; see §5.5.) |
| Multi-connection | `cues` is a global table. No `UsesTenantConnection` anywhere in this slice. Standard `DB::transaction()` is safe. |
| `users.id` FK | `cues.user_id` is `BIGINT UNSIGNED`. Use `$table->foreignId('user_id')`, NOT `foreignUuid()`. Per CLAUDE.md: `users.$table->id()` is BIGINT. |
| `assert()` as auth guard | Never use `assert()` for auth checks — compiled out with `zend.assertions=-1`. Guard: `if ($cue->user_id !== $request->user()->id) { return response()->json(['code' => 'ERR_NOT_FOUND'], 404); }` |
| Float money | No money arithmetic in this slice — cues carry no financial amounts. But if `payload` embeds a `trialEndsAt` date, use ISO 8601 string not unix timestamp. |
| `DB::transaction()` scope | Wrap cue insert + dedup row write together. Do NOT wrap the entire webhook handler in one transaction (it may call external services; long-held locks are dangerous). |
| `SKIP LOCKED` | Laravel's `database` queue driver uses `SELECT ... FOR UPDATE SKIP LOCKED` automatically. Do not add manual locking to the cue-insert path. |
| Parallel cue.job.skipped | The `cue.job.skipped` metric must always include BOTH `kind` AND `reason` tags in the structured log context. `Log::info('cue.job.skipped', ['kind' => $kind, 'reason' => $reason])`. |
| Cue soft-delete | Do NOT add `SoftDeletes` to the `Cue` model. Dismissed cues are surfaced via `dismissed_at` timestamp. Hard delete in the Filament admin is a deliberate admin-only escape hatch, not the normal dismiss flow. |
| `occurrence_window_start_iso8601` for lifetime cues | Use `'1970-01-01T00:00:00Z'` as the stable window key for cues that have a lifetime occurrence window (`kyc_required`, `pro_trial_reminder_d1`, `family_sharing_unsupported`). |

---

## 11. Code quality workflow

Run in this order before committing:

```bash
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/pest --parallel --stop-on-failure
```

Common PHPStan issues to anticipate:

| Issue | Fix |
|---|---|
| `$user->pro_marketing_opt_out` — PHPStan doesn't know the column type | Add `@property bool $pro_marketing_opt_out` to the `User` model docblock, or use a cast in `User::casts()` |
| `EnqueueProTrialReminderD1::dispatch()->delay()` — `delay()` returns `PendingDispatch`, not `void` | Assign to `$_` or call as statement; no return annotation needed |
| `CueRepository::createIdempotent()` — return type | Declare `: Cue` return type; if using `firstOrCreate()`, PHPStan may infer `Model` — add `@return Cue` docblock |
| `json_encode` in payload construction | Cast: `(string) json_encode($payload)` |
| `env()` in config | `(string) env('QUEUE_CONNECTION', 'database')` |

---

## 12. Commit topology

Follow the same topology as slice 2/3 specs. Suggested sequence (each commit is green on php-cs-fixer + PHPStan + Pest):

1. **`feat(subscription): create cues table migration + Cue model + CueRepository`** — the foundation; all dispatch strategies depend on this
2. **`feat(subscription): pending-cues endpoint (GET + POST dismissed)`** — API surface; test with empty cues first
3. **`feat(subscription): marketing-opt-out endpoint + users.pro_marketing_opt_out migration`** — the opt-out column and endpoint
4. **`feat(subscription): add SubscriptionTrialStarted + OnboardingCompleted events + users.lifetime_spend_cents + kyc_completed_at migration`** — new event classes (`App\Domain\Subscription\Events\SubscriptionTrialStarted`, `App\Domain\Onboarding\Events\OnboardingCompleted`); new `users` columns migration (§5.6a); write-path hooks in `ProjectRevenueOutbox` and the KYC level-transition code
5. **`feat(subscription): time-from-event delayed job classes (4 jobs + 2 listeners)`** — `EnqueueProTrialReminderD1`, `EnqueueTrialEnding{2d,1d,1h}`, `OnboardingCompletedListener`, `SubscriptionTrialStartedListener` (depends on commit 4 event classes)
6. **`feat(subscription): aggregate-condition cron — DispatchKycRequiredCues`** — `kyc_required` cron command + schedule entry (depends on commit 4 users columns)
7. **`feat(subscription): webhook-driven cue inserts — fill Stripe stubs (+ trial_will_end handler + grace_period_started)`** — `SubscriptionWebhookController` cue inserts for `payment_failed`, `refund_processed`, `subscription_canceled_external`, `grace_period_started` (Stripe `past_due` path); add `trial_will_end` handler (missing from slice 1); Apple `grace_period_started` path via Apple webhook handler stub (for `DID_FAIL_TO_RENEW`)
8. **`feat(subscription): Filament admin — CueResource + CueDispatchHealthWidget`** — admin visibility
9. **`feat(subscription): ProjectRevenueOutbox cron schedule entry`** — the missing `routes/console.php` line from slice 1 (see §15)

---

## 13. Status reporting format

When all commits are clean and the PR is open, report:

```
STATUS: DONE | DONE_WITH_CONCERNS | BLOCKED
BRANCH: feat/plan-b-slice-4-cue-queue
PR_URL: https://github.com/FinAegis/core-banking-prototype-laravel/pull/XXXX
MIGRATIONS: 3 new (create_cues_table, add_pro_marketing_opt_out_to_users, add_lifetime_spend_cents_and_kyc_completed_at_to_users)
ROUTES: 3 new (GET /api/v1/me/pending-cues, POST /api/v1/me/cues/{id}/dismissed, POST /api/v1/me/marketing-opt-out)
JOBS: 4 new (EnqueueProTrialReminderD1, EnqueueTrialEnding{2d,1d,1h})
COMMANDS: 1 new (DispatchKycRequiredCues)
EVENTS_CREATED: 2 new (SubscriptionTrialStarted, OnboardingCompleted)
CUE_KINDS_IMPLEMENTED: 9 of 11 (grace_period_started Stripe+Apple sources unified under slice 4)
CONCERNS: [list any] | none
OPEN_ITEMS: [list any unresolved ODs] | none
```

---

## 14. Estimated effort

**Total: 5–7 engineer-days.** Breakdown:

| Task | Estimate |
|---|---|
| `cues` table migration, `Cue` model, `CueRepository` (idempotent write, kind registry, precondition interface) | 0.5d |
| `GET /pending-cues` endpoint with server-side precondition reaping (11 precondition classes, N+1 guard, priority sort) | 1.0d |
| `POST /dismissed` endpoint + `pro_marketing_opt_out` endpoint + migration | 0.5d |
| Four delayed job classes + two source-event listeners + unit tests | 1.0d |
| `DispatchKycRequiredCues` aggregate-condition cron + LEFT JOIN query + cron schedule entry | 0.5d |
| Webhook-driven cue inserts in `SubscriptionWebhookController` (Stripe: `payment_failed`, `subscription_canceled_external`, `refund_processed`, suppress-on-resolve) | 0.75d |
| Filament admin: `CueResource` + `CueDispatchHealthWidget` | 0.75d |
| Integration tests (`Feature/Cue/PendingCuesTest`, `Feature/Cue/DismissedCueTest`, `Feature/Cue/DelayedJobTest`, `Feature/Cue/KycRequiredCronTest`) | 0.75d |
| Code quality passes (php-cs-fixer, PHPStan L8, Pest full suite) | 0.25d |
| **Total** | **6.0d** (median; range 5–7d) |

**Additionally: 1 operational day for the `ProjectRevenueOutbox` schedule entry cutover.** The outbox worker was deployed without a cron entry in slice 1. Adding it in slice 4 means rows that accumulated between slice 1 deploy and slice 4 deploy will be processed in the first sweep. The sweep is idempotent; no data risk. Operator should monitor the first sweep run and confirm all pre-existing pending rows move to `delivered` status. This is a coordination item, not a code change.

Compared to slices 2 and 3: slice 4 is similar in surface area (both were estimated 5–7d). Slice 4 has fewer external dependencies (no Apple/Google API integration, no HMAC signing) but more dispatch-path complexity (three dispatch strategies, 11 cue kinds, precondition registry).

---

## 15. Coordination items

### Before implementation starts — controller must confirm

1. **OD-3 decision** — daily digest email vs Filament-only for DLQ alerting. Confirm `MAIL_ADMIN_ADDRESS` is set in production, or confirm Filament-only is acceptable for v1.3.0.
2. **OD-5 decision** — dedicated `POST /me/marketing-opt-out` endpoint vs extending `PATCH /me`. Confirm preferred approach.
3. **Slice sequencing** — slice 4 fills the `SubscriptionWebhookController` Stripe stubs. If slice 2 (IAP) is implemented in parallel, coordinate: both slices should NOT modify `SubscriptionWebhookController` simultaneously (merge conflict risk). Preferred order: slice 4 merges first, then slice 2 adds IAP webhook controllers as separate files.

### After implementation, before going live — operator must action

1. **`php artisan migrate`** — 2 new migrations: `create_cues_table`, `add_pro_marketing_opt_out_to_users_table`.
2. **Queue worker startup** — ensure at least one `php artisan queue:work --queue=default` process is running in production. Delayed jobs for `trial_ending_*` and `pro_trial_reminder_d1` require a running worker. This should already be running for `ProjectRevenueOutbox`; confirm worker count is adequate for the additional 4 job kinds.
3. **`ProjectRevenueOutbox` cron monitoring** — the first run of `outbox:project-revenue` (slice 4 adds this schedule entry) will process any `pending` rows that accumulated since slice 1 deployed. Monitor the sweep run; confirm all rows move to `delivered` within the first 5-minute window.
4. **`DispatchKycRequiredCues` first run monitoring** — on first hourly run, log the `cue.cron.aggregate.kyc_required.candidates` count. If unexpectedly high (>10k), investigate — may indicate an index is missing or `lifetime_spend_cents` data is incorrect.
5. **GDPR erasure walk extension** — `users.pro_marketing_opt_out` should be reset to `0` during pseudonymisation. Check `GdprController.php` to confirm the erasure walk covers this column; if not, add it in this slice or document as a v1.3.1 follow-up.
6. **Legacy-user `pro_trial_reminder_d1` decision** — confirm with founder whether a one-shot backfill for pre-v1.3.0 users is desired. If yes, schedule as v1.3.1 work, not this slice.

### No new env vars required

Slice 4 uses:
- `QUEUE_CONNECTION=database` — already set
- `DB_*` connection credentials — already set
- `MAIL_ADMIN_ADDRESS` — needed only if OD-3 resolves to email digest (see above)
- No Redis config changes (EventStreaming domain is not used for cue dispatch)

### Cron additions to `routes/console.php` (append-only)

```php
// Hourly: dispatch kyc_required cues for users approaching AMLD5 threshold (Plan B Backend-Q8).
Schedule::command('cue:dispatch-kyc-required')
    ->hourlyAt(15)
    ->description('Dispatch kyc_required cues for users approaching €1,000 lifetime spend (Backend-Q8 aggregate-condition cron)')
    ->appendOutputTo(storage_path('logs/cue-dispatch-kyc-required.log'))
    ->withoutOverlapping();

// Every 5 minutes: project pending revenue_outbox_events rows into revenue_events (Plan B ADR-0002).
// Schedule entry missing from slice 1; added here.
Schedule::job(new App\Domain\Subscription\Jobs\ProjectRevenueOutbox())
    ->everyFiveMinutes()
    ->description('Project pending revenue outbox rows into revenue_events (ADR-0002 off-chain projection)')
    ->withoutOverlapping();
```

### Next step after spec merges

Once this spec is reviewed and OD-3 and OD-5 are answered by the controller, the implementation agent uses `docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md` as the direct input prompt to implement slice 4 on a `feat/plan-b-slice-4-cue-queue` branch.

Coordinate with the slice 2 implementation agent: slice 4 should merge before slice 2 to avoid `SubscriptionWebhookController` modification conflicts.

---

## Appendix A — Backend-Q8 architecture summary

For quick reference, the decided architecture (γ) per `docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md § Cue dispatch architecture (Backend grilling, Q8)`:

| Pattern | Cue kinds | Why |
|---|---|---|
| **Laravel delayed job** dispatched at source event | `trial_ending_2d`, `trial_ending_1d`, `trial_ending_1h`, `pro_trial_reminder_d1` | Deterministic offset from a known event; structural failure recovery via `failed_jobs`; no cohort loss on cron miss |
| **Windowed cron with `LEFT JOIN` candidate query** | `kyc_required` (and any future aggregate-condition cue) | Evolving condition without a natural source-event hook; query is idempotent; hourly cadence acceptable for AMLD5 threshold |
| **Direct insert from webhook handler** | `payment_failed`, `subscription_canceled_external`, `refund_processed`, `grace_period_started` (**slice 4** — both Stripe `past_due` + Apple `DID_FAIL_TO_RENEW` sources), `family_sharing_unsupported` (slice 2), `export_ready` (deferred) | External event arrival is the trigger; no intermediate dispatch step needed |

**What was rejected:**
- **(α) Windowed-cohort cron for everything** — a single cron miss permanently loses the cohort (1–2k cues/day at v1.3.0 traffic); unrecoverable.
- **(β) Persistent worklist for everything** — this is exactly what Laravel's `jobs` table already gives for time-from-event cues; reinventing it is custom infra without benefit.

**Database queue driver locked for v1.3.0.** Migration trigger: sustained >100 jobs/sec for >1h, or `jobs` table >1M rows steady-state. Neither reachable at launch traffic.

---

## Appendix B — `cues` table full DDL (for reference)

```sql
CREATE TABLE cues (
  id                CHAR(36)                              NOT NULL PRIMARY KEY,
  user_id           BIGINT UNSIGNED                       NOT NULL,
  kind              VARCHAR(64)                           NOT NULL,
  priority          ENUM('critical', 'high', 'normal')   NOT NULL,
  due_at            TIMESTAMP                             NOT NULL,
  expires_at        TIMESTAMP                             NOT NULL,
  payload           JSON                                  NOT NULL,
  dismissed_at      TIMESTAMP                             NULL,
  dismissed_action  ENUM('cancelled', 'kept', 'dismissed') NULL,
  idempotency_key   CHAR(64)                              NOT NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cues_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  INDEX idx_cues_user_pending (user_id, dismissed_at, due_at, expires_at),
  UNIQUE KEY uniq_cues_idempotency (user_id, idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
