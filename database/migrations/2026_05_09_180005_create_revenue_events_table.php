<?php

/**
 * revenue_events migration — projection target for both upstream paths
 * per ADR-0002 (chain-ingestor for on-chain fees, PSP outbox for off-chain).
 *
 * Slice 1 only writes from the off-chain outbox path (subscription_initial,
 * subscription_renewal, refund). Chain-ingestor writes tx_fee rows in a
 * later slice.
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
        Schema::create('revenue_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 64); // subscription_initial | subscription_renewal | refund | tx_fee | ramp_margin | ...
            $table->foreignId('user_id')->nullable();
            $table->char('user_id_hash', 64)->nullable(); // for cohort analytics post-erasure
            $table->string('source_type', 32); // stripe | apple_iap | google_play | stripe_bridge | chain | ondato
            $table->string('source_event_id', 255)->nullable();
            $table->string('aggregate_id', 255)->nullable(); // stripe_subscription_id, original_transaction_id, tx_hash, ...
            // Money triple per ADR-0004 (explicit because revenue_events is variable-currency).
            $table->bigInteger('amount'); // signed: negative for refunds
            $table->unsignedTinyInteger('amount_decimals');
            $table->string('amount_denomination', 16); // EUR / USDC / MATIC / ...
            $table->json('payload')->nullable();
            $table->timestamp('emitted_at');
            $table->timestamps();

            $table->unique(['source_type', 'source_event_id', 'event_type'], 'uniq_revenue_event_source');
            $table->index('emitted_at');
            $table->index(['user_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_events');
    }
};
