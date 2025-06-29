<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // FR-001, FR-002, etc.
            $table->string('name');
            $table->text('description');
            $table->string('category'); // velocity, pattern, amount, geography, device, behavior
            $table->string('severity'); // low, medium, high, critical
            $table->boolean('is_active')->default(true);
            $table->boolean('is_blocking')->default(false); // If true, blocks transaction
            
            // Rule Configuration
            $table->json('conditions'); // Rule logic and parameters
            $table->json('thresholds')->nullable(); // Numeric thresholds
            $table->string('time_window')->nullable(); // 1h, 24h, 7d, 30d
            $table->integer('min_occurrences')->default(1); // Minimum times to trigger
            
            // Scoring
            $table->integer('base_score')->default(50); // Base fraud score (0-100)
            $table->decimal('weight', 5, 2)->default(1.00); // Weight multiplier
            
            // Actions
            $table->json('actions'); // block, flag, review, notify, challenge
            $table->json('notification_channels')->nullable(); // email, sms, webhook
            
            // Performance Metrics
            $table->integer('triggers_count')->default(0);
            $table->integer('true_positives')->default(0);
            $table->integer('false_positives')->default(0);
            $table->decimal('precision_rate', 5, 2)->nullable(); // TP / (TP + FP)
            $table->timestamp('last_triggered_at')->nullable();
            
            // Machine Learning
            $table->boolean('ml_enabled')->default(false);
            $table->string('ml_model_id')->nullable();
            $table->json('ml_features')->nullable();
            $table->decimal('ml_confidence_threshold', 5, 2)->default(80.00);
            
            $table->timestamps();
            
            $table->index(['is_active', 'category']);
            $table->index('severity');
            $table->index('last_triggered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_rules');
    }
};