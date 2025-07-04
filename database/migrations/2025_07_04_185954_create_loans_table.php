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
        Schema::create('loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('application_id');
            $table->uuid('borrower_id');
            $table->decimal('principal', 19, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->integer('term_months');
            $table->jsonb('repayment_schedule');
            $table->jsonb('terms');
            $table->string('status');
            
            // Funding fields
            $table->jsonb('investor_ids')->nullable();
            $table->decimal('funded_amount', 19, 2)->nullable();
            $table->timestamp('funded_at')->nullable();
            
            // Disbursement fields
            $table->decimal('disbursed_amount', 19, 2)->nullable();
            $table->timestamp('disbursed_at')->nullable();
            
            // Payment tracking
            $table->decimal('total_principal_paid', 19, 2)->default(0);
            $table->decimal('total_interest_paid', 19, 2)->default(0);
            $table->timestamp('last_payment_date')->nullable();
            $table->integer('missed_payments')->default(0);
            
            // Settlement/completion fields
            $table->decimal('settlement_amount', 19, 2)->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('settled_by')->nullable();
            $table->timestamp('defaulted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            
            // $table->foreign('application_id')->references('id')->on('loan_applications');
            $table->index('application_id');
            $table->index('borrower_id');
            $table->index('status');
            $table->index(['status', 'disbursed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
