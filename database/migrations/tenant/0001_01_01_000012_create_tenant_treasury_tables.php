<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for treasury and portfolio tables.
 *
 * This migration runs in tenant database context, creating tables for
 * portfolio management, asset allocations, and treasury operations.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Portfolio event sourcing (separate from treasury domain)
        Schema::create('portfolio_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->unsignedInteger('event_version')->default(1);
            $table->string('event_class');
            $table->json('event_properties');
            $table->json('meta_data')->nullable();
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
            $table->index(['event_class', 'created_at']);
        });

        Schema::create('portfolio_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });

        // Asset allocations table (for treasury management)
        Schema::create('asset_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('portfolio_uuid')->nullable()->index();
            $table->uuid('account_uuid')->index();
            $table->string('asset_code', 20);
            $table->string('asset_type', 50); // cash, bond, equity, crypto, etc.
            $table->decimal('target_weight', 8, 4)->nullable(); // Target allocation %
            $table->decimal('current_weight', 8, 4)->nullable(); // Current allocation %
            $table->decimal('amount', 36, 18);
            $table->decimal('value_usd', 20, 2)->nullable();
            $table->string('status')->default('active')->index();

            // Risk metrics
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->string('risk_category')->nullable(); // low, medium, high
            $table->decimal('expected_return', 8, 4)->nullable();
            $table->decimal('volatility', 8, 4)->nullable();

            // Rebalancing
            $table->boolean('auto_rebalance')->default(false);
            $table->decimal('rebalance_threshold', 8, 4)->nullable(); // Trigger threshold %
            $table->timestamp('last_rebalanced_at')->nullable();

            $table->json('metadata')->nullable();

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_code', 'status']);
            $table->index(['asset_type', 'status']);
        });

        // Cash allocations for treasury liquidity management
        Schema::create('cash_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('account_uuid')->index();
            $table->string('currency', 10);
            $table->decimal('amount', 20, 2);
            $table->string('allocation_type', 50); // operational, reserve, investment
            $table->string('destination')->nullable(); // bank, money_market, yield_protocol
            $table->decimal('yield_rate', 8, 4)->nullable();
            $table->string('status')->default('active')->index();

            // Maturity tracking for fixed-term allocations
            $table->date('maturity_date')->nullable();
            $table->boolean('auto_rollover')->default(false);

            $table->json('metadata')->nullable();

            // Audit fields
            $table->string('allocated_by')->nullable()->index();
            $table->timestamp('allocated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['currency', 'allocation_type']);
            $table->index('maturity_date');
        });

        // Yield optimization records
        Schema::create('yield_optimizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('account_uuid')->index();
            $table->string('strategy_type', 50); // fixed, variable, laddered
            $table->decimal('principal_amount', 20, 2);
            $table->string('currency', 10);
            $table->decimal('current_yield', 8, 4);
            $table->decimal('projected_yield', 8, 4)->nullable();
            $table->string('protocol')->nullable(); // aave, compound, etc.
            $table->string('status')->default('active')->index();

            // Performance tracking
            $table->decimal('total_earned', 20, 2)->default(0);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            $table->json('metadata')->nullable();

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['strategy_type', 'status']);
            $table->index(['protocol', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('yield_optimizations');
        Schema::dropIfExists('cash_allocations');
        Schema::dropIfExists('asset_allocations');
        Schema::dropIfExists('portfolio_snapshots');
        Schema::dropIfExists('portfolio_events');
    }
};
