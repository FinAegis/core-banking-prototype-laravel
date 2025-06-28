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
        Schema::create('cgo_investments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('round_id')->nullable()->constrained('cgo_pricing_rounds')->onDelete('set null');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10);
            $table->decimal('share_price', 10, 4);
            $table->decimal('shares_purchased', 15, 4);
            $table->decimal('ownership_percentage', 8, 6); // e.g., 0.001234 = 0.1234%
            $table->enum('tier', ['bronze', 'silver', 'gold']);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'refunded'])->default('pending');
            $table->string('payment_method'); // crypto, bank_transfer, card
            $table->string('crypto_address')->nullable();
            $table->string('crypto_tx_hash')->nullable();
            $table->string('certificate_number')->nullable();
            $table->timestamp('certificate_issued_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');
            $table->index('tier');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cgo_investments');
    }
};
