<?php

/**
 * Plan B deltas Q14.2 — withdrawal consent audit log for Stripe Web flow.
 *
 * One row per successful Stripe-Web subscription creation (CRD/EU dispute
 * trail). Per-row text snapshots: if the consent line copy changes, increment
 * consent_version — a dispute lookup retrieves the exact text shown at consent
 * time, not whatever's current.
 *
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md (Q14.2)
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('subscription_consent_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->text('consent_text');
            $table->unsignedInteger('consent_version');
            $table->timestamp('shown_at');
            $table->timestamp('accepted_at');
            $table->char('ip_hash', 64);
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('user_id', 'idx_consent_user');
            $table->index('subscription_id', 'idx_consent_subscription');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_consent_log');
    }
};
