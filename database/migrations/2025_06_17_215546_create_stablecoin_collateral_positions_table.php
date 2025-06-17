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
        Schema::create('stablecoin_collateral_positions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Position ownership
            $table->uuid('account_uuid'); // Account that owns this position
            $table->foreign('account_uuid')->references('uuid')->on('accounts');
            
            // Stablecoin reference
            $table->string('stablecoin_code');
            $table->foreign('stablecoin_code')->references('code')->on('stablecoins');
            
            // Collateral details
            $table->string('collateral_asset_code'); // Asset used as collateral (USD, EUR, BTC, etc.)
            $table->bigInteger('collateral_amount'); // Amount of collateral locked
            $table->bigInteger('debt_amount'); // Amount of stablecoin borrowed/minted
            
            // Position metrics
            $table->decimal('collateral_ratio', 8, 4); // Current collateralization ratio
            $table->decimal('liquidation_price', 20, 8)->nullable(); // Price at which position gets liquidated
            $table->bigInteger('interest_accrued')->default(0); // Accumulated interest
            
            // Position status
            $table->enum('status', ['active', 'liquidated', 'closed'])->default('active');
            $table->timestamp('last_interaction_at')->nullable(); // Last mint/burn/collateral action
            $table->timestamp('liquidated_at')->nullable();
            
            // Risk management
            $table->boolean('auto_liquidation_enabled')->default(true);
            $table->decimal('stop_loss_ratio', 8, 4)->nullable(); // Auto-close position if ratio drops below
            
            $table->timestamps();
            
            // Indexes with custom names to avoid length issues
            $table->index(['account_uuid', 'stablecoin_code'], 'scp_account_stablecoin_idx');
            $table->index(['stablecoin_code', 'status'], 'scp_stablecoin_status_idx');
            $table->index(['collateral_asset_code', 'status'], 'scp_collateral_status_idx');
            $table->index('collateral_ratio', 'scp_collateral_ratio_idx');
            $table->index('liquidation_price', 'scp_liquidation_price_idx');
            
            // Unique constraint: one position per account per stablecoin
            $table->unique(['account_uuid', 'stablecoin_code'], 'scp_account_stablecoin_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stablecoin_collateral_positions');
    }
};
