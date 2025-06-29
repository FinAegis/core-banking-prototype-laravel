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
        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_uuid');
            $table->string('from_bank_code', 50);
            $table->string('from_account_id');
            $table->string('to_bank_code', 50);
            $table->string('to_account_id');
            $table->decimal('amount', 20, 2);
            $table->string('currency', 3);
            $table->string('type', 50); // SEPA, SWIFT, INTERNAL, etc.
            $table->string('status', 20)->default('pending');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->json('fees')->nullable();
            $table->json('exchange_rate')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->index(['user_uuid', 'status']);
            $table->index(['from_bank_code', 'from_account_id']);
            $table->index(['to_bank_code', 'to_account_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transfers');
    }
};