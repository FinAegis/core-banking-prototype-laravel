<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for stablecoin tables.
 *
 * This migration runs in tenant database context, creating tables for
 * stablecoin management, collateral positions, and operations.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stablecoins', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('symbol', 10);

            // Pegging configuration
            $table->string('peg_asset_code');
            $table->decimal('peg_ratio', 20, 8)->default(1.0);
            $table->decimal('target_price', 20, 8);

            // Stability mechanism configuration
            $table->enum('stability_mechanism', ['collateralized', 'algorithmic', 'hybrid'])->default('collateralized');
            $table->decimal('collateral_ratio', 8, 4)->default(1.5);
            $table->decimal('min_collateral_ratio', 8, 4)->default(1.2);
            $table->decimal('liquidation_penalty', 8, 4)->default(0.05);

            // Issuance limits and status
            $table->bigInteger('total_supply')->default(0);
            $table->bigInteger('max_supply')->nullable();
            $table->bigInteger('total_collateral_value')->default(0);

            // Operational settings
            $table->decimal('mint_fee', 8, 6)->default(0.001);
            $table->decimal('burn_fee', 8, 6)->default(0.001);
            $table->integer('precision')->default(8);

            // Status and metadata
            $table->boolean('is_active')->default(true);
            $table->boolean('minting_enabled')->default(true);
            $table->boolean('burning_enabled')->default(true);
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('peg_asset_code');
            $table->index(['is_active', 'minting_enabled']);
            $table->index('stability_mechanism');
        });

        Schema::create('stablecoin_collateral_positions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Position ownership
            $table->uuid('account_uuid')->index();

            // Stablecoin reference
            $table->string('stablecoin_code');

            // Collateral details
            $table->string('collateral_asset_code');
            $table->bigInteger('collateral_amount');
            $table->bigInteger('debt_amount');

            // Position metrics
            $table->decimal('collateral_ratio', 8, 4);
            $table->decimal('liquidation_price', 20, 8)->nullable();
            $table->bigInteger('interest_accrued')->default(0);

            // Position status
            $table->enum('status', ['active', 'liquidated', 'closed'])->default('active');
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('liquidated_at')->nullable();

            // Risk management
            $table->boolean('auto_liquidation_enabled')->default(true);
            $table->decimal('stop_loss_ratio', 8, 4)->nullable();

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->string('liquidated_by')->nullable();
            $table->timestamps();

            $table->index(['account_uuid', 'stablecoin_code'], 'scp_account_stablecoin_idx');
            $table->index(['stablecoin_code', 'status'], 'scp_stablecoin_status_idx');
            $table->index(['collateral_asset_code', 'status'], 'scp_collateral_status_idx');
            $table->index('collateral_ratio', 'scp_collateral_ratio_idx');
            $table->index('liquidation_price', 'scp_liquidation_price_idx');

            $table->unique(['account_uuid', 'stablecoin_code'], 'scp_account_stablecoin_unique');
        });

        Schema::create('stablecoin_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->string('stablecoin', 10);
            $table->bigInteger('amount');
            $table->string('collateral_asset', 10)->nullable();
            $table->bigInteger('collateral_amount')->nullable();
            $table->bigInteger('collateral_return')->nullable();
            $table->uuid('source_account')->nullable()->index();
            $table->uuid('recipient_account')->nullable()->index();
            $table->uuid('operator_uuid')->index();
            $table->string('position_uuid')->nullable();
            $table->string('reason');
            $table->string('status')->default('pending')->index();
            $table->json('metadata')->nullable();

            // Audit fields
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('stablecoin');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stablecoin_operations');
        Schema::dropIfExists('stablecoin_collateral_positions');
        Schema::dropIfExists('stablecoins');
    }
};
