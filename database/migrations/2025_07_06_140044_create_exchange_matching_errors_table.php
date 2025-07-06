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
        Schema::create('exchange_matching_errors', function (Blueprint $table) {
            $table->id();
            $table->uuid('buy_order_id')->nullable()->index();
            $table->uuid('sell_order_id')->nullable()->index();
            $table->uuid('trade_id')->nullable();
            $table->decimal('executed_amount', 36, 18)->nullable();
            $table->decimal('executed_price', 36, 18)->nullable();
            $table->text('error_message');
            $table->json('match_data')->nullable();
            $table->timestamps();
            
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_matching_errors');
    }
};