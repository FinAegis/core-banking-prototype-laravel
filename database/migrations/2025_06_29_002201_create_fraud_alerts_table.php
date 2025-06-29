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
        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('alert_type'); // velocity, pattern, threshold, etc
            $table->enum('status', ['active', 'acknowledged', 'dismissed', 'escalated']);
            $table->enum('priority', ['low', 'medium', 'high', 'critical']);
            $table->string('rule_id')->nullable();
            $table->string('rule_name')->nullable();
            $table->json('trigger_conditions');
            $table->json('matched_data');
            $table->decimal('confidence_score', 5, 2);
            $table->uuid('entity_type'); // account, user, transaction
            $table->uuid('entity_id');
            $table->text('description');
            $table->timestamp('triggered_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->uuid('acknowledged_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('alert_type');
            $table->index('status');
            $table->index('priority');
            $table->index(['entity_type', 'entity_id']);
            $table->index('triggered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraud_alerts');
    }
};