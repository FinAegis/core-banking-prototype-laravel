<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exchange_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->nullable();
            $table->unsignedBigInteger('aggregate_version')->nullable();
            $table->integer('event_version')->default(1);
            $table->string('event_class');
            $table->jsonb('event_properties');
            $table->jsonb('meta_data');
            $table->timestamp('created_at');
            
            // Indexes for performance
            $table->index('event_class');
            $table->index('aggregate_uuid');
            $table->index('created_at');
            
            // Unique constraint to prevent duplicate events
            $table->unique(['aggregate_uuid', 'aggregate_version'], 'exchange_events_aggregate_uuid_version');
        });
        
        Schema::create('exchange_event_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid');
            $table->unsignedInteger('aggregate_version');
            $table->jsonb('state');
            $table->timestamp('created_at');
            
            $table->index('aggregate_uuid');
            $table->index(['aggregate_uuid', 'aggregate_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_event_snapshots');
        Schema::dropIfExists('exchange_events');
    }
};
