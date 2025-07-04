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
        // Liquidity pools table
        Schema::create('liquidity_pools', function (Blueprint $table) {
            $table->id();
            $table->uuid('pool_id')->unique();
            $table->uuid('account_id'); // Pool's system account
            $table->string('base_currency', 10);
            $table->string('quote_currency', 10);
            $table->decimal('base_reserve', 36, 18)->default(0);
            $table->decimal('quote_reserve', 36, 18)->default(0);
            $table->decimal('total_shares', 36, 18)->default(0);
            $table->decimal('fee_rate', 5, 4)->default(0.003); // 0.3%
            $table->boolean('is_active')->default(true);
            $table->decimal('volume_24h', 36, 18)->default(0);
            $table->decimal('fees_collected_24h', 36, 18)->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->unique(['base_currency', 'quote_currency']);
            $table->index('is_active');
            $table->index('account_id');
        });

        // Liquidity providers table
        Schema::create('liquidity_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('pool_id');
            $table->uuid('provider_id'); // Account ID
            $table->decimal('shares', 36, 18)->default(0);
            $table->decimal('initial_base_amount', 36, 18)->default(0);
            $table->decimal('initial_quote_amount', 36, 18)->default(0);
            $table->jsonb('pending_rewards')->nullable();
            $table->decimal('total_rewards_claimed', 36, 18)->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->unique(['pool_id', 'provider_id']);
            $table->index('provider_id');
            $table->foreign('pool_id')->references('pool_id')->on('liquidity_pools')->onDelete('cascade');
        });

        // Pool swaps table
        Schema::create('pool_swaps', function (Blueprint $table) {
            $table->id();
            $table->uuid('swap_id')->unique();
            $table->uuid('pool_id');
            $table->uuid('account_id')->nullable();
            $table->string('input_currency', 10);
            $table->decimal('input_amount', 36, 18);
            $table->string('output_currency', 10);
            $table->decimal('output_amount', 36, 18);
            $table->decimal('fee_amount', 36, 18);
            $table->decimal('price_impact', 10, 6)->default(0);
            $table->decimal('execution_price', 36, 18);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('pool_id');
            $table->index('account_id');
            $table->index(['input_currency', 'output_currency']);
            $table->index('created_at');
            $table->foreign('pool_id')->references('pool_id')->on('liquidity_pools')->onDelete('cascade');
        });

        // Balance locks table (for liquidity locking)
        Schema::create('balance_locks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('currency_code', 10);
            $table->decimal('amount', 36, 18);
            $table->string('reason', 50);
            $table->uuid('reference_id')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['account_id', 'currency_code']);
            $table->index('reference_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_locks');
        Schema::dropIfExists('pool_swaps');
        Schema::dropIfExists('liquidity_providers');
        Schema::dropIfExists('liquidity_pools');
    }
};