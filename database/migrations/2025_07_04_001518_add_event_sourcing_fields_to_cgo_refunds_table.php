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
        Schema::table('cgo_refunds', function (Blueprint $table) {
            // The original migration uses auto-incrementing ID, but model uses HasUuids
            // This is handled by the model itself, no need to change here
            
            if (!Schema::hasColumn('cgo_refunds', 'approved_by')) {
                $table->uuid('approved_by')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'approval_notes')) {
                $table->text('approval_notes')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'rejected_by')) {
                $table->uuid('rejected_by')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'payment_processor')) {
                $table->string('payment_processor')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'processor_refund_id')) {
                $table->string('processor_refund_id')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'processor_status')) {
                $table->string('processor_status')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'amount_refunded')) {
                $table->unsignedBigInteger('amount_refunded')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'cancelled_by')) {
                $table->uuid('cancelled_by')->nullable();
            }
            
            if (!Schema::hasColumn('cgo_refunds', 'requested_at')) {
                $table->timestamp('requested_at')->nullable();
            }
            
            // Indexes might already exist from the original migration
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cgo_refunds', function (Blueprint $table) {
            $table->dropColumn([
                'approved_by',
                'approval_notes',
                'approved_at',
                'rejected_by',
                'rejection_reason',
                'rejected_at',
                'payment_processor',
                'processor_refund_id',
                'processor_status',
                'amount_refunded',
                'completed_at',
                'cancelled_by',
                'requested_at',
            ]);
        });
    }
};
