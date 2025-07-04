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
        Schema::create('lending_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->nullable();
            $table->unsignedBigInteger('aggregate_version')->nullable();
            $table->unsignedInteger('event_version')->default(1);
            $table->string('event_class');
            $table->jsonb('event_properties');
            $table->jsonb('meta_data');
            $table->timestamp('created_at', 6)->useCurrent();
            
            $table->index('event_class');
            $table->index('aggregate_uuid');
            $table->index('created_at');
            
            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });
        
        // Create snapshots table for lending aggregates
        Schema::create('lending_event_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid');
            $table->unsignedBigInteger('aggregate_version');
            $table->jsonb('state');
            $table->timestamp('created_at', 6)->useCurrent();
            
            $table->index('aggregate_uuid');
            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lending_event_snapshots');
        Schema::dropIfExists('lending_events');
    }
};