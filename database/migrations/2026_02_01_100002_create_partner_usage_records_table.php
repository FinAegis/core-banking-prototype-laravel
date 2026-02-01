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
        Schema::create('partner_usage_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('partner_id');

            // Time period
            $table->date('usage_date');
            $table->string('period_type')->default('daily'); // daily, hourly

            // API Usage
            $table->unsignedBigInteger('api_calls')->default(0);
            $table->unsignedBigInteger('api_calls_success')->default(0);
            $table->unsignedBigInteger('api_calls_failed')->default(0);

            // Endpoint breakdown
            $table->json('endpoint_breakdown')->nullable();

            // Transactions
            $table->unsignedInteger('transactions_count')->default(0);
            $table->decimal('transactions_volume', 20, 2)->default(0);

            // Webhooks
            $table->unsignedInteger('webhooks_sent')->default(0);
            $table->unsignedInteger('webhooks_failed')->default(0);

            // Widget usage
            $table->unsignedInteger('widget_loads')->default(0);
            $table->unsignedInteger('widget_conversions')->default(0);

            // SDK downloads
            $table->unsignedInteger('sdk_downloads')->default(0);

            // Performance metrics
            $table->decimal('avg_response_time_ms', 10, 2)->nullable();
            $table->decimal('p99_response_time_ms', 10, 2)->nullable();

            // Errors
            $table->json('error_breakdown')->nullable();

            // Billing impact
            $table->boolean('is_billable')->default(true);
            $table->decimal('overage_amount_usd', 10, 2)->default(0);

            $table->timestamps();

            $table->foreign('partner_id')
                ->references('id')
                ->on('financial_institution_partners')
                ->onDelete('cascade');

            $table->unique(['partner_id', 'usage_date', 'period_type']);
            $table->index(['usage_date', 'period_type']);
            $table->index('is_billable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_usage_records');
    }
};
