<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bridge_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_chain');
            $table->string('dest_chain');
            $table->string('token');
            $table->decimal('amount', 36, 18);
            $table->string('provider');
            $table->string('status')->default('initiated');
            $table->string('sender_address');
            $table->string('recipient_address');
            $table->string('source_tx_hash')->nullable();
            $table->string('dest_tx_hash')->nullable();
            $table->decimal('fee_amount', 36, 18)->nullable();
            $table->string('fee_currency')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('provider');
            $table->index('source_chain');
            $table->index('dest_chain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bridge_transactions');
    }
};
