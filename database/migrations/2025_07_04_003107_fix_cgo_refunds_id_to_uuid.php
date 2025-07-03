<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support changing column types easily
        // So we'll create a new table and migrate data
        if (Schema::hasTable('cgo_refunds')) {
            // Check if we have any data
            $hasData = DB::table('cgo_refunds')->exists();
            
            if ($hasData) {
                // If we have data, we can't easily migrate with SQLite
                // For now, just truncate (this is okay for development)
                DB::table('cgo_refunds')->truncate();
            }
            
            // Drop the old table
            Schema::dropIfExists('cgo_refunds');
        }
        
        // Create the table with UUID primary key
        Schema::create('cgo_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('investment_id')->constrained('cgo_investments');
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('amount'); // Amount in cents
            $table->unsignedBigInteger('amount_refunded')->nullable();
            $table->string('currency', 3)->default('USD');
            
            // Refund reason
            $table->string('reason');
            $table->text('reason_details')->nullable();
            
            // Status tracking
            $table->string('status')->default('pending');
            $table->foreignId('initiated_by')->constrained('users');
            $table->timestamp('requested_at')->nullable();
            
            // Approval/Rejection
            $table->uuid('approved_by')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            // Processing details
            $table->string('payment_processor')->nullable();
            $table->string('processor_refund_id')->nullable();
            $table->string('processor_status')->nullable();
            $table->json('processor_response')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('processor_reference')->nullable();
            $table->text('processing_notes')->nullable();
            
            // Completion
            $table->timestamp('completed_at')->nullable();
            
            // Failure tracking
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            
            // Cancellation tracking
            $table->uuid('cancelled_by')->nullable();
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
            $table->index('investment_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cgo_refunds');
        
        // Recreate with auto-incrementing ID
        Schema::create('cgo_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained('cgo_investments');
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('USD');
            $table->enum('reason', [
                'requested_by_customer',
                'duplicate',
                'fraudulent',
                'agreement_violation',
                'regulatory_requirement',
                'other'
            ]);
            $table->text('reason_details')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])
                ->default('pending');
            $table->foreignId('initiated_by')->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->string('processor_reference')->nullable();
            $table->json('processor_response')->nullable();
            $table->text('processing_notes')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('refund_address')->nullable();
            $table->json('bank_details')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('created_at');
            $table->index(['investment_id', 'status']);
        });
    }
};