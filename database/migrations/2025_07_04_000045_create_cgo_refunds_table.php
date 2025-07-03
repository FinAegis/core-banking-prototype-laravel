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
        Schema::create('cgo_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained('cgo_investments');
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('amount'); // Amount in cents
            $table->string('currency', 3)->default('USD');
            
            // Refund reason
            $table->enum('reason', [
                'requested_by_customer',
                'duplicate',
                'fraudulent',
                'agreement_violation',
                'regulatory_requirement',
                'other'
            ]);
            $table->text('reason_details')->nullable();
            
            // Status tracking
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])
                ->default('pending');
            $table->foreignId('initiated_by')->constrained('users');
            
            // Processing details
            $table->timestamp('processed_at')->nullable();
            $table->string('processor_reference')->nullable(); // Stripe refund ID, bank reference, etc.
            $table->json('processor_response')->nullable();
            $table->text('processing_notes')->nullable();
            
            // Failure tracking
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            
            // Cancellation tracking
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            // Additional fields for manual refunds
            $table->string('refund_address')->nullable(); // For crypto refunds
            $table->json('bank_details')->nullable(); // For bank transfer refunds
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index('created_at');
            $table->index(['investment_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cgo_refunds');
    }
};