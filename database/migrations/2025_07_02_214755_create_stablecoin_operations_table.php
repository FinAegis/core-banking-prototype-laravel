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
        Schema::create('stablecoin_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type'); // mint, burn
            $table->string('stablecoin', 10); // USDX, EURX, GBPX
            $table->bigInteger('amount'); // in cents
            $table->string('collateral_asset', 10)->nullable();
            $table->bigInteger('collateral_amount')->nullable(); // in cents
            $table->bigInteger('collateral_return')->nullable(); // in cents
            $table->uuid('source_account')->nullable();
            $table->uuid('recipient_account')->nullable();
            $table->uuid('operator_uuid');
            $table->string('position_uuid')->nullable();
            $table->string('reason');
            $table->string('status')->default('pending'); // pending, completed, failed, cancelled
            $table->json('metadata')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('type');
            $table->index('stablecoin');
            $table->index('operator_uuid');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stablecoin_operations');
    }
};
