<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stablecoin_reserves', function (Blueprint $table) {
            $table->id();
            $table->uuid('reserve_id')->unique();
            $table->uuid('pool_id')->index(); // Links to ReservePool aggregate
            $table->string('stablecoin_code')->index(); // e.g., 'FUSD', 'FEUR'
            $table->string('asset_code'); // Reserve asset code (ETH, BTC, USDC, etc.)
            $table->decimal('amount', 36, 18)->default(0); // Current reserve amount
            $table->decimal('value_usd', 20, 8)->default(0); // Value in USD at last update
            $table->decimal('allocation_percentage', 8, 4)->default(0); // Percentage of total reserves

            // Custodian information
            $table->string('custodian_id')->nullable();
            $table->string('custodian_name')->nullable();
            $table->enum('custodian_type', ['hot_wallet', 'cold_wallet', 'institutional', 'smart_contract'])->default('hot_wallet');
            $table->string('wallet_address')->nullable();

            // Audit and verification
            $table->timestamp('last_verified_at')->nullable();
            $table->string('verification_source')->nullable(); // e.g., 'chainlink', 'internal', 'audit_firm'
            $table->string('verification_tx_hash')->nullable();
            $table->json('verification_metadata')->nullable();

            // Status
            $table->enum('status', ['active', 'frozen', 'pending_withdrawal', 'liquidating'])->default('active');
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['stablecoin_code', 'asset_code']);
            $table->index(['pool_id', 'status']);
            $table->index('custodian_id');
        });

        // Reserve audit log for tracking all reserve movements
        Schema::create('stablecoin_reserve_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('audit_id')->unique();
            $table->uuid('reserve_id')->index();
            $table->uuid('pool_id')->index();
            $table->string('stablecoin_code')->index();
            $table->string('asset_code');

            // Movement details
            $table->enum('action', ['deposit', 'withdrawal', 'rebalance', 'price_update', 'verification', 'freeze', 'unfreeze']);
            $table->decimal('amount_change', 36, 18)->default(0); // Positive for deposits, negative for withdrawals
            $table->decimal('amount_before', 36, 18)->default(0);
            $table->decimal('amount_after', 36, 18)->default(0);
            $table->decimal('value_usd_before', 20, 8)->nullable();
            $table->decimal('value_usd_after', 20, 8)->nullable();

            // Transaction details
            $table->string('transaction_hash')->nullable();
            $table->string('custodian_id')->nullable();
            $table->string('executed_by')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('executed_at');
            $table->timestamps();

            // Indexes
            $table->index(['reserve_id', 'executed_at'], 'reserve_audit_reserve_executed_idx');
            $table->index(['stablecoin_code', 'action', 'executed_at'], 'reserve_audit_code_action_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stablecoin_reserve_audit_logs');
        Schema::dropIfExists('stablecoin_reserves');
    }
};
