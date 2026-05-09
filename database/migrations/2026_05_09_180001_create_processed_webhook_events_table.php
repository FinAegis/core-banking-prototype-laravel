<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Plan B Slice 1 — global webhook idempotency dedup. Stripe / IAP / etc.
     * webhook handlers consult this table BEFORE Cashier dispatches; redelivery
     * of the same provider event_id is a no-op. See deltas Q7.3.
     */
    public function up(): void
    {
        Schema::create('processed_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 255);
            $table->string('event_type', 128)->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->unique(['provider', 'event_id'], 'uniq_provider_event_id');
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_webhook_events');
    }
};
