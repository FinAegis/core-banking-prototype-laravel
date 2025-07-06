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
        Schema::create('loan_collaterals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('loan_id')->index();
            $table->string('type', 50);
            $table->text('description');
            $table->decimal('estimated_value', 20, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 50)->default('pending_verification');
            $table->uuid('verification_document_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('liquidated_at')->nullable();
            $table->decimal('liquidation_value', 20, 2)->nullable();
            $table->timestamp('last_valuation_date')->nullable();
            $table->json('valuation_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'status']);
            $table->index('type');
            $table->index('status');
            $table->index('last_valuation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_collaterals');
    }
};