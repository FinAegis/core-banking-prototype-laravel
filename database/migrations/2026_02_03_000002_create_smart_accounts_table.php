<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the smart_accounts table for ERC-4337 account abstraction.
 *
 * Smart accounts are counterfactual addresses computed from owner EOA + factory.
 * The deployed flag tracks whether the account exists on-chain.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('smart_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('owner_address', 42)->comment('EOA owner address with 0x prefix');
            $table->string('account_address', 42)->comment('Smart account address with 0x prefix');
            $table->string('network', 20);
            $table->boolean('deployed')->default(false);
            $table->string('deploy_tx_hash', 66)->nullable()->comment('Deployment transaction hash');
            $table->unsignedBigInteger('nonce')->default(0)->comment('Next expected nonce');
            $table->unsignedInteger('pending_ops')->default(0)->comment('Operations awaiting confirmation');
            $table->timestamps();

            // Unique constraint: one smart account per owner per network
            $table->unique(['owner_address', 'network']);

            // Indexes for lookups
            $table->index('account_address');
            $table->index(['network', 'deployed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smart_accounts');
    }
};
