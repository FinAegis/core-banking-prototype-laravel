<?php

/**
 * Plan B Slice 2 — iap_subscriptions read-model.
 *
 * Custom (non-Cashier) store of active Apple App Store / Google Play subscription
 * rows; the unified `SubscriptionProjection` joins this with Cashier's
 * `subscriptions` for the canonical Pro entitlement (Backend-Q1 γ).
 *
 * One-active-sub invariant via a STORED generated `active_user_id` column +
 * single-column unique index, mirroring Cashier's pattern. Cross-store conflict
 * is caught at /iap/verify time as ERR_SUB_002 (kind=two_stores_active).
 *
 * `user_id` is BIGINT `foreignId()` per project convention (the spec's
 * `CHAR(36)` is overridden by CLAUDE.md: "Users table: $table->id() (BIGINT),
 * use foreignId() not foreignUuid() for user_id FKs").
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.2
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md Q1 / Q6
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('iap_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id');
            $table->enum('store', ['apple', 'google']);
            $table->string('tier', 32);
            $table->string('status', 32);

            $table->string('original_transaction_id', 255)->nullable();
            $table->string('play_subscription_resource_id', 255)->nullable();
            $table->char('google_purchase_token_hash', 64)->nullable();
            $table->char('apple_app_account_token', 36)->nullable();
            $table->string('google_obfuscated_account_id', 255)->nullable();

            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();

            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('paused_until')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->string('last_notification_type', 64)->nullable();
            $table->char('last_event_id', 36)->nullable();

            $table->timestamps();

            $table->index('user_id', 'idx_iap_sub_user');
            $table->unique('original_transaction_id', 'uniq_iap_sub_apple');
            $table->unique('play_subscription_resource_id', 'uniq_iap_sub_play_resource');
            $table->index('google_purchase_token_hash', 'idx_iap_sub_google_hash');
            $table->index('current_period_ends_at', 'idx_iap_sub_period_end');
        });

        // One-active-sub invariant: STORED generated column carries user_id only
        // when the subscription is in an "alive" state. Combined with a single-
        // column UNIQUE index it enforces one active IAP row per user across
        // BOTH stores (cross-store conflict surfaces as ERR_SUB_002 at verify
        // time). MariaDB/MySQL 8 supports STORED virtual columns natively.
        // SQLite (used in tests) does NOT — we skip the generated column there
        // and rely on application-level enforcement via the conflict gate.
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('
                ALTER TABLE iap_subscriptions
                ADD COLUMN active_user_id BIGINT UNSIGNED
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN status IN ("active","trialing","past_due","grace_period","paused")
                            THEN user_id
                        END
                    ) STORED,
                ADD UNIQUE KEY uniq_iap_active_per_user (active_user_id)
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_subscriptions');
    }
};
