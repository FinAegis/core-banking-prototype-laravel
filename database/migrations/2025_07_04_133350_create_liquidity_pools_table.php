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
        Schema::create('liquidity_pools', function (Blueprint $table) {
            $table->id();
            $table->string('pool_id')->unique();
            $table->string('base_asset');
            $table->string('quote_asset');
            $table->decimal('base_balance', 36, 18);
            $table->decimal('quote_balance', 36, 18);
            $table->decimal('total_shares', 36, 18);
            $table->string('status')->default('active');
            $table->decimal('total_liquidity', 36, 18)->default(0);
            $table->decimal('volume_24h', 36, 18)->default(0);
            $table->decimal('fee_percentage', 10, 4)->default(0.3);
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['base_asset', 'quote_asset']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liquidity_pools');
    }
};
