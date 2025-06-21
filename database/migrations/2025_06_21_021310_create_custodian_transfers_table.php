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
        Schema::create('custodian_transfers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('from_account_uuid');
            $table->uuid('to_account_uuid');
            $table->foreignId('from_custodian_account_id')->constrained('custodian_accounts');
            $table->foreignId('to_custodian_account_id')->constrained('custodian_accounts');
            $table->unsignedBigInteger('amount');
            $table->string('asset_code', 10);
            $table->enum('transfer_type', ['internal', 'external', 'bridge']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled']);
            $table->string('reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('from_account_uuid');
            $table->index('to_account_uuid');
            $table->index('status');
            $table->index('transfer_type');
            $table->index(['asset_code', 'status']);
            $table->index('created_at');
            
            // Foreign keys
            $table->foreign('from_account_uuid')->references('uuid')->on('accounts');
            $table->foreign('to_account_uuid')->references('uuid')->on('accounts');
            $table->foreign('asset_code')->references('code')->on('assets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custodian_transfers');
    }
};