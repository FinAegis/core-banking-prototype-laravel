<?php

/**
 * revenue_outbox_events migration — off-chain projection upstream per ADR-0002.
 *
 * Webhook handlers (Stripe / Apple IAP / Google Play / Stripe Bridge / Ondato)
 * write a row here in the same transaction as `processed_webhook_events` dedup;
 * a separate worker (`ProjectRevenueOutbox`) projects pending rows into
 * `revenue_events` and marks them delivered. Idempotent on
 * (source_type, source_event_id) via the unique index.
 *
 * @see docs/adr/0002-revenue-projection-dual-upstream.md
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('revenue_outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->string('source_event_id', 255);
            $table->string('source_type', 32); // stripe | apple_iap | google_play | ondato | stripe_bridge
            $table->string('event_kind', 64);
            $table->json('payload');
            $table->string('status', 16)->default('pending'); // pending | delivered | failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_event_id'], 'uniq_outbox_source');
            $table->index(['status', 'created_at'], 'idx_outbox_pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_outbox_events');
    }
};
