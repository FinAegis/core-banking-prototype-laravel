<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Table for storing liquidity forecasts
        Schema::create('liquidity_forecasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('treasury_id')->index();
            $table->integer('forecast_days');
            $table->json('base_forecast');
            $table->json('scenarios')->nullable();
            $table->json('risk_metrics');
            $table->json('alerts')->nullable();
            $table->decimal('confidence_level', 3, 2);
            $table->json('recommendations')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('generated_at');
            $table->string('generated_by');
            $table->timestamps();

            $table->index(['treasury_id', 'generated_at']);
            $table->index('status');
        });

        // Table for liquidity alerts
        Schema::create('liquidity_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('treasury_id')->index();
            $table->uuid('forecast_id')->nullable();
            $table->string('level'); // critical, warning, info
            $table->string('type');
            $table->text('message');
            $table->decimal('value', 20, 4)->nullable();
            $table->decimal('threshold', 20, 4)->nullable();
            $table->boolean('action_required')->default(false);
            $table->string('status')->default('active'); // active, acknowledged, resolved
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['treasury_id', 'status']);
            $table->index(['level', 'status']);
            $table->foreign('forecast_id')->references('id')->on('liquidity_forecasts')->onDelete('cascade');
        });

        // Table for scheduled payments (for committed outflows)
        Schema::create('scheduled_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('treasury_id')->index();
            $table->string('payment_type');
            $table->decimal('amount', 20, 4);
            $table->date('due_date')->index();
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled
            $table->string('recipient');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['treasury_id', 'due_date', 'status']);
            $table->index(['status', 'due_date']);
        });

        // Table for expected receivables (for expected inflows)
        Schema::create('expected_receivables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('treasury_id')->index();
            $table->string('receivable_type');
            $table->decimal('amount', 20, 4);
            $table->date('expected_date')->index();
            $table->string('status')->default('pending'); // pending, received, overdue, cancelled
            $table->string('source');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['treasury_id', 'expected_date', 'status']);
            $table->index(['status', 'expected_date']);
        });

        // Table for liquidity buffer configuration
        Schema::create('liquidity_buffers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('treasury_id')->unique();
            $table->decimal('target_buffer_amount', 20, 4);
            $table->decimal('minimum_buffer_amount', 20, 4);
            $table->integer('target_coverage_days');
            $table->integer('minimum_coverage_days');
            $table->decimal('lcr_target', 5, 2)->default(1.20);
            $table->decimal('lcr_minimum', 5, 2)->default(1.00);
            $table->decimal('nsfr_target', 5, 2)->default(1.10);
            $table->decimal('nsfr_minimum', 5, 2)->default(1.00);
            $table->json('stress_scenarios')->nullable();
            $table->boolean('auto_rebalance')->default(false);
            $table->json('rebalance_rules')->nullable();
            $table->timestamps();
        });

        // Table for liquidity mitigation actions
        Schema::create('liquidity_mitigation_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('treasury_id')->index();
            $table->uuid('alert_id')->nullable();
            $table->string('action_type');
            $table->text('description');
            $table->json('parameters')->nullable();
            $table->string('status')->default('pending'); // pending, in_progress, completed, failed
            $table->decimal('expected_impact', 20, 4)->nullable();
            $table->decimal('actual_impact', 20, 4)->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('initiated_by');
            $table->text('outcome_notes')->nullable();
            $table->timestamps();

            $table->index(['treasury_id', 'status']);
            $table->index('status');
            $table->foreign('alert_id')->references('id')->on('liquidity_alerts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liquidity_mitigation_actions');
        Schema::dropIfExists('liquidity_buffers');
        Schema::dropIfExists('expected_receivables');
        Schema::dropIfExists('scheduled_payments');
        Schema::dropIfExists('liquidity_alerts');
        Schema::dropIfExists('liquidity_forecasts');
    }
};
