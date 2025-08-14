<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('liquidity_pools', function (Blueprint $table) {
            $table->id();
            $table->string('pool_id')->unique();
            $table->string('account_id')->nullable()->index();
            $table->string('base_currency');
            $table->string('quote_currency');
            $table->decimal('base_reserve', 36, 18)->default(0);
            $table->decimal('quote_reserve', 36, 18)->default(0);
            $table->decimal('total_shares', 36, 18)->default(0);
            $table->decimal('fee_rate', 10, 6)->default(0.003);
            $table->boolean('is_active')->default(true);
            $table->decimal('volume_24h', 36, 18)->default(0);
            $table->decimal('fees_collected_24h', 36, 18)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['base_currency', 'quote_currency']);
            $table->index('is_active');
        });

        Schema::create('liquidity_providers', function (Blueprint $table) {
            $table->id();
            $table->string('pool_id')->index();
            $table->string('provider_id')->index();
            $table->decimal('shares', 36, 18)->default(0);
            $table->decimal('base_contributed', 36, 18)->default(0);
            $table->decimal('quote_contributed', 36, 18)->default(0);
            $table->json('pending_rewards')->nullable();
            $table->timestamps();

            $table->unique(['pool_id', 'provider_id']);
        });

        // Add foreign key constraint separately to avoid timeout issues
        // Use raw SQL with increased lock wait timeout for MySQL
        if (config('database.default') === 'mysql') {
            DB::statement('SET SESSION innodb_lock_wait_timeout = 120');
            Schema::table('liquidity_providers', function (Blueprint $table) {
                $table->foreign('pool_id')
                    ->references('pool_id')
                    ->on('liquidity_pools')
                    ->onDelete('cascade');
            });
            DB::statement('SET SESSION innodb_lock_wait_timeout = 50'); // Reset to default
        } else {
            // For SQLite and other databases, add the foreign key normally
            Schema::table('liquidity_providers', function (Blueprint $table) {
                $table->foreign('pool_id')
                    ->references('pool_id')
                    ->on('liquidity_pools')
                    ->onDelete('cascade');
            });
        }

        Schema::create('pool_swaps', function (Blueprint $table) {
            $table->id();
            $table->string('pool_id')->index();
            $table->string('account_id')->index();
            $table->string('input_currency');
            $table->decimal('input_amount', 36, 18);
            $table->string('output_currency');
            $table->decimal('output_amount', 36, 18);
            $table->decimal('fee_amount', 36, 18);
            $table->decimal('execution_price', 36, 18);
            $table->decimal('price_impact', 10, 6);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Add foreign key constraint separately to avoid timeout issues
        if (config('database.default') === 'mysql') {
            DB::statement('SET SESSION innodb_lock_wait_timeout = 120');
            Schema::table('pool_swaps', function (Blueprint $table) {
                $table->foreign('pool_id')
                    ->references('pool_id')
                    ->on('liquidity_pools')
                    ->onDelete('cascade');
            });
            DB::statement('SET SESSION innodb_lock_wait_timeout = 50'); // Reset to default
        } else {
            // For SQLite and other databases, add the foreign key normally
            Schema::table('pool_swaps', function (Blueprint $table) {
                $table->foreign('pool_id')
                    ->references('pool_id')
                    ->on('liquidity_pools')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pool_swaps');
        Schema::dropIfExists('liquidity_providers');
        Schema::dropIfExists('liquidity_pools');
    }
};
