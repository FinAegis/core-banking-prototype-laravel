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
        Schema::create('blockchain_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('withdrawal_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->string('wallet_id');
            $table->string('chain');
            $table->string('to_address');
            $table->string('amount_fiat');
            $table->string('amount_crypto');
            $table->string('asset')->default('native');
            $table->string('token_address')->nullable();
            $table->string('transaction_hash')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['wallet_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index('transaction_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blockchain_withdrawals');
    }
};