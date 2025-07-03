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
        // Only add columns that don't exist
        $columns = Schema::getColumnListing('cgo_investments');
        
        Schema::table('cgo_investments', function (Blueprint $table) use ($columns) {
            if (!in_array('stripe_session_id', $columns)) {
                $table->string('stripe_session_id')->nullable();
            }
            if (!in_array('stripe_payment_intent_id', $columns)) {
                $table->string('stripe_payment_intent_id')->nullable();
            }
            if (!in_array('payment_status', $columns)) {
                $table->string('payment_status')->default('pending');
            }
            if (!in_array('payment_completed_at', $columns)) {
                $table->timestamp('payment_completed_at')->nullable();
            }
            if (!in_array('payment_failed_at', $columns)) {
                $table->timestamp('payment_failed_at')->nullable();
            }
            if (!in_array('payment_failure_reason', $columns)) {
                $table->text('payment_failure_reason')->nullable();
            }
            if (!in_array('coinbase_charge_id', $columns)) {
                $table->string('coinbase_charge_id')->nullable();
            }
            if (!in_array('bank_transfer_reference', $columns)) {
                $table->string('bank_transfer_reference')->nullable();
            }
            if (!in_array('cancelled_at', $columns)) {
                $table->timestamp('cancelled_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cgo_investments', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_session_id',
                'stripe_payment_intent_id',
                'payment_status',
                'payment_completed_at',
                'payment_failed_at',
                'payment_failure_reason',
                'coinbase_charge_id',
                'bank_transfer_reference',
                'cancelled_at',
            ]);
        });
    }
};