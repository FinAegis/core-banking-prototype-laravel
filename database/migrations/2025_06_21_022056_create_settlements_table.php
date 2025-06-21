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
        Schema::create('settlements', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->enum('type', ['realtime', 'batch', 'net']);
            $table->string('from_custodian', 50);
            $table->string('to_custodian', 50);
            $table->string('asset_code', 10);
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('net_amount');
            $table->unsignedInteger('transfer_count')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed']);
            $table->string('external_reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index('type');
            $table->index(['from_custodian', 'to_custodian']);
            $table->index(['asset_code', 'status']);
            $table->index('created_at');
            
            // Foreign keys
            $table->foreign('asset_code')->references('code')->on('assets');
        });
        
        // Add settlement_id to custodian_transfers
        Schema::table('custodian_transfers', function (Blueprint $table) {
            $table->string('settlement_id')->nullable()->after('reference');
            $table->index('settlement_id');
            $table->foreign('settlement_id')->references('id')->on('settlements');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custodian_transfers', function (Blueprint $table) {
            $table->dropForeign(['settlement_id']);
            $table->dropColumn('settlement_id');
        });
        
        Schema::dropIfExists('settlements');
    }
};