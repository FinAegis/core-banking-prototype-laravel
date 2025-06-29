<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_monitoring_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rule_code')->unique(); // TMR-001, TMR-002, etc.
            $table->string('name');
            $table->text('description');
            $table->string('category'); // velocity, pattern, threshold, geography, behavior
            $table->string('risk_level'); // low, medium, high
            $table->boolean('is_active')->default(true);
            
            // Rule Configuration
            $table->json('conditions'); // Rule conditions/logic
            $table->json('parameters'); // Configurable parameters
            $table->string('time_window')->nullable(); // 1h, 24h, 7d, 30d
            $table->decimal('threshold_amount', 15, 2)->nullable();
            $table->integer('threshold_count')->nullable();
            
            // Actions
            $table->json('actions'); // alert, block, review, report
            $table->boolean('auto_escalate')->default(false);
            $table->string('escalation_level')->nullable(); // compliance_team, management
            
            // Applicability
            $table->json('applies_to_customer_types')->nullable(); // individual, business
            $table->json('applies_to_risk_levels')->nullable(); // low, medium, high
            $table->json('applies_to_countries')->nullable();
            $table->json('applies_to_currencies')->nullable();
            $table->json('applies_to_transaction_types')->nullable();
            
            // Performance Metrics
            $table->integer('triggers_count')->default(0);
            $table->integer('true_positives')->default(0);
            $table->integer('false_positives')->default(0);
            $table->decimal('accuracy_rate', 5, 2)->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            
            // Review and Tuning
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('last_modified_by')->nullable()->constrained('users');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->json('tuning_history')->nullable();
            
            $table->timestamps();
            
            $table->index(['is_active', 'category']);
            $table->index('risk_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_monitoring_rules');
    }
};