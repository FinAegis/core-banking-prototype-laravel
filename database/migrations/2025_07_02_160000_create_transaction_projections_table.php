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
        Schema::create('transaction_projections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('account_uuid');
            $table->string('type'); // deposit, withdrawal, transfer, exchange
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled
            $table->bigInteger('amount');
            $table->string('currency', 10);
            $table->string('description')->nullable();
            $table->string('reference')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->uuid('related_transaction_uuid')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('account_uuid');
            $table->index('status');
            $table->index('type');
            $table->index(['account_uuid', 'created_at']);
            $table->index(['status', 'created_at']);
            
            // Foreign key
            $table->foreign('account_uuid')->references('uuid')->on('accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_projections');
    }
};