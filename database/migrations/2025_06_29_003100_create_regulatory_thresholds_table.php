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
        Schema::create('regulatory_thresholds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('threshold_code')->unique(); // THR-CTR-US-001
            $table->string('name');
            $table->text('description')->nullable();
            
            // Categorization
            $table->string('category'); // transaction, customer, account, aggregate
            $table->string('report_type'); // CTR, SAR, OFAC, etc.
            $table->string('jurisdiction'); // US, EU, UK, etc.
            $table->string('regulation_reference')->nullable();
            
            // Threshold configuration
            $table->json('conditions'); // Complex threshold conditions
            $table->decimal('amount_threshold', 20, 2)->nullable();
            $table->string('currency')->default('USD');
            $table->integer('count_threshold')->nullable(); // For count-based thresholds
            $table->string('time_period')->nullable(); // daily, weekly, monthly
            $table->integer('time_period_days')->nullable(); // Number of days for custom periods
            
            // Aggregation rules
            $table->boolean('requires_aggregation')->default(false);
            $table->json('aggregation_rules')->nullable(); // How to aggregate transactions
            $table->string('aggregation_key')->nullable(); // customer, account, etc.
            
            // Actions
            $table->json('actions'); // report, flag, notify, block
            $table->boolean('auto_report')->default(false);
            $table->boolean('requires_review')->default(true);
            $table->integer('review_priority')->default(1);
            
            // Status and validity
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->string('status')->default('active'); // active, suspended, expired
            
            // Performance tracking
            $table->integer('trigger_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->decimal('false_positive_rate', 5, 2)->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('category');
            $table->index('report_type');
            $table->index('jurisdiction');
            $table->index(['is_active', 'effective_from', 'effective_to'], 'idx_active_effective');
            $table->index(['report_type', 'jurisdiction', 'is_active'], 'idx_type_jurisdiction_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regulatory_thresholds');
    }
};