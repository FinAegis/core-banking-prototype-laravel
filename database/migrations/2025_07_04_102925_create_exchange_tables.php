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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id')->unique();
            $table->uuid('account_id');
            $table->enum('type', ['buy', 'sell']);
            $table->enum('order_type', ['market', 'limit', 'stop', 'stop_limit']);
            $table->string('base_currency', 10);
            $table->string('quote_currency', 10);
            $table->decimal('amount', 36, 18);
            $table->decimal('filled_amount', 36, 18)->default(0);
            $table->decimal('price', 36, 18)->nullable();
            $table->decimal('stop_price', 36, 18)->nullable();
            $table->decimal('average_price', 36, 18)->nullable();
            $table->string('status', 20);
            $table->jsonb('trades')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('filled_at')->nullable();
            
            // Indexes
            $table->index('account_id');
            $table->index('status');
            $table->index(['base_currency', 'quote_currency']);
            $table->index('created_at');
        });
        
        Schema::create('order_books', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_book_id')->unique();
            $table->string('base_currency', 10);
            $table->string('quote_currency', 10);
            $table->jsonb('buy_orders')->nullable();
            $table->jsonb('sell_orders')->nullable();
            $table->decimal('best_bid', 36, 18)->nullable();
            $table->decimal('best_ask', 36, 18)->nullable();
            $table->decimal('last_price', 36, 18)->nullable();
            $table->decimal('volume_24h', 36, 18)->default(0);
            $table->decimal('high_24h', 36, 18)->nullable();
            $table->decimal('low_24h', 36, 18)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->unique(['base_currency', 'quote_currency']);
            $table->index('updated_at');
        });
        
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->uuid('trade_id')->unique();
            $table->uuid('buy_order_id');
            $table->uuid('sell_order_id');
            $table->uuid('buyer_account_id');
            $table->uuid('seller_account_id');
            $table->string('base_currency', 10);
            $table->string('quote_currency', 10);
            $table->decimal('price', 36, 18);
            $table->decimal('amount', 36, 18);
            $table->decimal('value', 36, 18);
            $table->decimal('maker_fee', 36, 18);
            $table->decimal('taker_fee', 36, 18);
            $table->enum('maker_side', ['buy', 'sell']);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('buyer_account_id');
            $table->index('seller_account_id');
            $table->index(['base_currency', 'quote_currency']);
            $table->index('created_at');
        });
        
        Schema::create('exchange_fees', function (Blueprint $table) {
            $table->id();
            $table->string('fee_type', 50);
            $table->decimal('maker_fee_percent', 5, 4)->default(0.1); // 0.1%
            $table->decimal('taker_fee_percent', 5, 4)->default(0.2); // 0.2%
            $table->jsonb('volume_discounts')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->unique(['fee_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_fees');
        Schema::dropIfExists('trades');
        Schema::dropIfExists('order_books');
        Schema::dropIfExists('orders');
    }
};
