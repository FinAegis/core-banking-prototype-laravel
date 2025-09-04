<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('compliance_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_id')->index();
            $table->string('aggregate_type', 100)->index();
            $table->string('event_type', 100)->index();
            $table->integer('event_version')->default(1);
            $table->json('payload');
            $table->json('metadata')->nullable();
            $table->uuid('correlation_id')->nullable()->index();
            $table->uuid('causation_id')->nullable()->index();
            $table->string('user_id', 50)->nullable()->index();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            // Composite index for aggregate queries (with custom shorter names)
            $table->index(['aggregate_id', 'aggregate_type', 'event_version'], 'compliance_events_aggregate_idx');
            $table->index(['event_type', 'occurred_at'], 'compliance_events_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_events');
    }
};
