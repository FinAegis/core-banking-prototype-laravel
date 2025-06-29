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
        Schema::create('transaction_monitoring_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_id')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('category'); // velocity, pattern, threshold, behavior
            $table->json('conditions'); // Rule conditions in JSON format
            $table->json('thresholds')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->json('actions'); // What to do when triggered
            $table->integer('lookback_minutes')->nullable();
            $table->decimal('min_confidence', 5, 2)->default(0.7);
            $table->json('exemptions')->nullable(); // Exempt conditions
            $table->timestamps();
            
            $table->index('rule_id');
            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_monitoring_rules');
    }
};