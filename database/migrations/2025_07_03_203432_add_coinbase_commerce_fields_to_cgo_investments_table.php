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
        Schema::table('cgo_investments', function (Blueprint $table) {
            // Get existing columns to avoid duplicates
            $existingColumns = Schema::getColumnListing('cgo_investments');
            
            // Coinbase Commerce fields
            if (!in_array('coinbase_charge_id', $existingColumns)) {
                $table->string('coinbase_charge_id')->nullable()->after('stripe_payment_intent_id');
                $table->index('coinbase_charge_id');
            }
            
            if (!in_array('coinbase_charge_code', $existingColumns)) {
                $table->string('coinbase_charge_code')->nullable()->after('coinbase_charge_id');
            }
            
            if (!in_array('crypto_payment_url', $existingColumns)) {
                $table->string('crypto_payment_url')->nullable()->after('coinbase_charge_code');
            }
            
            if (!in_array('crypto_transaction_hash', $existingColumns)) {
                $table->string('crypto_transaction_hash')->nullable()->after('crypto_address');
            }
            
            if (!in_array('crypto_amount_paid', $existingColumns)) {
                $table->decimal('crypto_amount_paid', 20, 8)->nullable()->after('crypto_transaction_hash');
            }
            
            if (!in_array('crypto_currency_paid', $existingColumns)) {
                $table->string('crypto_currency_paid', 10)->nullable()->after('crypto_amount_paid');
            }
            
            if (!in_array('amount_paid', $existingColumns)) {
                $table->decimal('amount_paid', 10, 2)->nullable()->after('amount');
            }
            
            if (!in_array('payment_pending_at', $existingColumns)) {
                $table->timestamp('payment_pending_at')->nullable();
            }
            
            if (!in_array('failed_at', $existingColumns)) {
                $table->timestamp('failed_at')->nullable();
            }
            
            if (!in_array('failure_reason', $existingColumns)) {
                $table->text('failure_reason')->nullable();
            }
            
            if (!in_array('notes', $existingColumns)) {
                $table->text('notes')->nullable();
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
                'coinbase_charge_id',
                'coinbase_charge_code',
                'crypto_payment_url',
                'crypto_transaction_hash',
                'crypto_amount_paid',
                'crypto_currency_paid',
                'amount_paid',
                'payment_pending_at',
                'failed_at',
                'failure_reason',
                'notes',
            ]);
        });
    }
};