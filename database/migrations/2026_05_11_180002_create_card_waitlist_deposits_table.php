<?php

/**
 * Plan B Slice 5 — card_waitlist_deposits table.
 *
 * Tracks the deposit-payment lifecycle on top of the existing free-tier
 * card_waitlist membership. A user can be on the waitlist (card_waitlist row)
 * without a deposit; once they pay the €5 refundable deposit a row is created
 * here and transitions through pending_payment → paid → (refunded | expired
 * | card_shipped). Multiple deposit attempts are supported (separate rows);
 * only one row per user is allowed in an "active" state (pending_payment |
 * paid | cancellation_requested) — enforced at the application layer in
 * WaitlistDepositService::startDeposit.
 *
 * Multi-connection note: this table lives on the default global connection
 * (no UsesTenantConnection on the companion model) — DB::transaction() is
 * safe alongside processed_webhook_events / revenue_outbox_events writes.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-5-card-waitlist-deposit-design.md §5.5
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('card_waitlist_deposits', function (Blueprint $table): void {
            // RFC 4122 v4 UUID — non-enumerable in deep links / API responses.
            $table->uuid('id')->primary();

            // FK → users.id (BIGINT unsigned). Not a constraint to avoid
            // cross-connection issues; validated at the application layer.
            $table->unsignedBigInteger('user_id');

            // FK → price_quotes.id (CHAR(36)). UNIQUE backstop: a given quote
            // can only fund one deposit row (mirrors price_quotes.consumed_at).
            $table->uuid('quote_id');

            // Lifecycle status. card_shipped is reserved for the future
            // card-issuance slice; slice 5 only writes the other six states.
            $table->enum('status', [
                'pending_payment',
                'paid',
                'cancellation_requested',
                'refunded',
                'refund_pending_manual',
                'expired',
                'card_shipped',
            ])->default('pending_payment');

            // Money-on-the-wire (ADR-0004) triple, stored as explicit columns
            // even though v1.3.0 pins to €5.00 EUR. Preserves flexibility +
            // ADR guidance ("variable-currency tables use explicit triple").
            $table->unsignedInteger('deposit_amount_cents')->default(500);
            $table->unsignedTinyInteger('deposit_decimals')->default(2);
            $table->char('deposit_currency', 3)->default('EUR');

            // Stripe references. Whitelisted from webhook payload via
            // array_intersect_key() — never the raw event blob.
            $table->string('stripe_checkout_session_id', 255)->nullable();
            $table->string('stripe_payment_intent_id', 255)->nullable();
            $table->string('stripe_refund_id', 255)->nullable();

            // Frozen at paid_at + 18 months. Per Q9.2 NEVER recalculated.
            $table->timestamp('refund_eligible_after')->nullable();

            // Lifecycle timestamps.
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('shipped_at')->nullable();

            // Refund classification (audit + reporting).
            $table->enum('refunded_reason', [
                'user_cancelled',
                'card_shipped',
                'eighteen_month_auto',
                'account_closure',
            ])->nullable();

            // FK → subscription_consent_log.id (BIGINT auto-increment). Optional
            // in v1.3.0; required v1.3.1+. Not a constraint to mirror slice 1.
            $table->unsignedBigInteger('withdrawal_consent_log_id')->nullable();

            $table->timestamps();

            // Per spec §5.5 DDL.
            $table->index(['user_id', 'status'], 'idx_cwd_user_status');
            $table->index('stripe_checkout_session_id', 'idx_cwd_checkout_session');
            $table->index('stripe_payment_intent_id', 'idx_cwd_payment_intent');
            $table->index('paid_at', 'idx_cwd_paid_at');
            $table->index('refund_eligible_after', 'idx_cwd_refund_eligible');
            $table->unique('quote_id', 'uniq_cwd_quote');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_waitlist_deposits');
    }
};
