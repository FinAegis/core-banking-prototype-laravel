<?php

/**
 * Plan B Backend-Q5 — trial-abuse fingerprint check (12-month retry).
 *
 * fingerprint_hash = HMAC-SHA256(stripe_pm.card.fingerprint, TRIAL_FINGERPRINT_PEPPER).
 *
 * Eligibility: a fingerprint is eligible for a NEW trial iff
 *   no row exists OR last_used_at + 12 months < now().
 *
 * Pepper rotation policy (event-triggered only): raw fingerprint is
 * recoverable via the user's Stripe Subscription, so rotation = re-hash all
 * rows in one transaction.
 *
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md (Backend-Q5)
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('trial_card_fingerprints', function (Blueprint $table): void {
            $table->char('fingerprint_hash', 64)->primary();
            $table->foreignId('first_user_id')->nullable();
            $table->foreignId('last_user_id')->nullable();
            $table->timestamp('first_used_at');
            $table->timestamp('last_used_at');
            $table->unsignedInteger('trial_user_count')->default(1);
            $table->string('stripe_payment_method_id', 255)->nullable();
            $table->timestamps();

            $table->index('last_used_at', 'idx_fingerprint_last_used');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_card_fingerprints');
    }
};
