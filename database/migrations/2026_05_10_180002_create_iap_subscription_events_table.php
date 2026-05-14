<?php

/**
 * Plan B Slice 2 — iap_subscription_events event store.
 *
 * Spatie-style event store for the IAP custom path (Backend-Q1 γ). Stores
 * out-of-order Apple ASN V2 / Google RTDN notifications and the synthesised
 * /iap/verify creation events so the subscription state can be replayed
 * deterministically. Unique on (aggregate_uuid, aggregate_version) to make
 * concurrent appenders fail loudly rather than corrupt the stream.
 *
 * Slice 2 writes to this table; full event-sourcing replay logic is deferred
 * (the canonical state lives in `iap_subscriptions` and the projection reads
 * from there). The event store is the durable audit trail.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.2
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('iap_subscription_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('aggregate_uuid');
            $table->unsignedInteger('aggregate_version');
            $table->string('event_class', 255);
            $table->json('event_payload');
            $table->json('metadata');
            $table->timestamp('created_at', 6)->useCurrent();

            $table->index(['aggregate_uuid', 'aggregate_version'], 'idx_ise_aggregate');
            $table->unique(['aggregate_uuid', 'aggregate_version'], 'uniq_ise_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_subscription_events');
    }
};
