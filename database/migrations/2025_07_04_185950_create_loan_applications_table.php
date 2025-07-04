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
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('borrower_id');
            $table->decimal('requested_amount', 19, 2);
            $table->integer('term_months');
            $table->string('purpose');
            $table->string('status');
            $table->jsonb('borrower_info');
            
            // Credit check fields
            $table->integer('credit_score')->nullable();
            $table->string('credit_bureau')->nullable();
            $table->jsonb('credit_report')->nullable();
            $table->timestamp('credit_checked_at')->nullable();
            
            // Risk assessment fields
            $table->string('risk_rating')->nullable();
            $table->decimal('default_probability', 5, 4)->nullable();
            $table->jsonb('risk_factors')->nullable();
            $table->timestamp('risk_assessed_at')->nullable();
            
            // Approval/rejection fields
            $table->decimal('approved_amount', 19, 2)->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->jsonb('terms')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->jsonb('rejection_reasons')->nullable();
            $table->string('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            $table->timestamp('submitted_at');
            $table->timestamps();
            
            $table->index('borrower_id');
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
