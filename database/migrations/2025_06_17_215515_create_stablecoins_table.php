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
        Schema::create('stablecoins', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'FUSD', 'FEUR'
            $table->string('name'); // e.g., 'FinAegis USD', 'FinAegis EUR'
            $table->string('symbol', 10); // e.g., 'FUSD', 'FEUR'
            
            // Pegging configuration
            $table->string('peg_asset_code'); // Asset code this stablecoin is pegged to (USD, EUR, etc.)
            $table->decimal('peg_ratio', 20, 8)->default(1.0); // How many units of peg asset = 1 stablecoin
            $table->decimal('target_price', 20, 8); // Target price in peg asset
            
            // Stability mechanism configuration
            $table->enum('stability_mechanism', ['collateralized', 'algorithmic', 'hybrid'])->default('collateralized');
            $table->decimal('collateral_ratio', 8, 4)->default(1.5); // 150% collateralization
            $table->decimal('min_collateral_ratio', 8, 4)->default(1.2); // Minimum before liquidation
            $table->decimal('liquidation_penalty', 8, 4)->default(0.05); // 5% penalty
            
            // Issuance limits and status
            $table->bigInteger('total_supply')->default(0); // Current circulating supply
            $table->bigInteger('max_supply')->nullable(); // Maximum allowed supply
            $table->bigInteger('total_collateral_value')->default(0); // Total collateral backing
            
            // Operational settings
            $table->decimal('mint_fee', 8, 6)->default(0.001); // 0.1% minting fee
            $table->decimal('burn_fee', 8, 6)->default(0.001); // 0.1% burning fee
            $table->integer('precision')->default(8); // Decimal precision
            
            // Status and metadata
            $table->boolean('is_active')->default(true);
            $table->boolean('minting_enabled')->default(true);
            $table->boolean('burning_enabled')->default(true);
            $table->json('metadata')->nullable(); // Additional configuration
            
            $table->timestamps();
            
            // Indexes
            $table->index('peg_asset_code');
            $table->index(['is_active', 'minting_enabled']);
            $table->index('stability_mechanism');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stablecoins');
    }
};
