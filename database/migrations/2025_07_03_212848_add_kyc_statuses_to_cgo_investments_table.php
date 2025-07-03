<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support modifying enum columns directly
        // So we need to check the database type
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE cgo_investments MODIFY COLUMN status ENUM('pending', 'confirmed', 'cancelled', 'refunded', 'kyc_required', 'aml_review', 'payment_failed', 'expired') DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            // First, remove the default constraint
            DB::statement("ALTER TABLE cgo_investments ALTER COLUMN status DROP DEFAULT");
            
            // Add new values to the enum type
            DB::statement("ALTER TYPE cgo_investments_status_enum ADD VALUE IF NOT EXISTS 'kyc_required'");
            DB::statement("ALTER TYPE cgo_investments_status_enum ADD VALUE IF NOT EXISTS 'aml_review'");
            DB::statement("ALTER TYPE cgo_investments_status_enum ADD VALUE IF NOT EXISTS 'payment_failed'");
            DB::statement("ALTER TYPE cgo_investments_status_enum ADD VALUE IF NOT EXISTS 'expired'");
            
            // Re-add the default
            DB::statement("ALTER TABLE cgo_investments ALTER COLUMN status SET DEFAULT 'pending'");
        } else {
            // For SQLite and other databases, we need to recreate the table
            // This is acceptable for testing but should be avoided in production
            
            // Create temporary table with new schema
            Schema::create('cgo_investments_temp', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('round_id')->nullable()->constrained('cgo_pricing_rounds')->onDelete('set null');
                $table->decimal('amount', 15, 2);
                $table->string('currency', 10);
                $table->decimal('share_price', 10, 4);
                $table->decimal('shares_purchased', 15, 4);
                $table->decimal('ownership_percentage', 8, 6);
                $table->enum('tier', ['bronze', 'silver', 'gold']);
                $table->enum('status', ['pending', 'confirmed', 'cancelled', 'refunded', 'kyc_required', 'aml_review', 'payment_failed', 'expired'])->default('pending');
                $table->string('payment_method');
                $table->string('stripe_session_id')->nullable();
                $table->string('stripe_payment_intent_id')->nullable();
                $table->string('payment_status')->nullable();
                $table->timestamp('payment_completed_at')->nullable();
                $table->timestamp('payment_failed_at')->nullable();
                $table->string('payment_failure_reason')->nullable();
                $table->string('coinbase_charge_id')->nullable();
                $table->string('coinbase_charge_code')->nullable();
                $table->string('crypto_payment_url')->nullable();
                $table->string('bank_transfer_reference')->nullable();
                $table->string('crypto_address')->nullable();
                $table->string('crypto_tx_hash')->nullable();
                $table->string('crypto_transaction_hash')->nullable();
                $table->decimal('crypto_amount_paid', 18, 8)->nullable();
                $table->string('crypto_currency_paid', 10)->nullable();
                $table->decimal('amount_paid', 15, 2)->nullable();
                $table->timestamp('payment_pending_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->text('notes')->nullable();
                $table->string('certificate_number')->nullable();
                $table->timestamp('certificate_issued_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('metadata')->nullable();
                $table->string('email')->nullable();
                $table->timestamp('kyc_verified_at')->nullable();
                $table->string('kyc_level')->nullable();
                $table->decimal('risk_assessment', 5, 2)->nullable();
                $table->timestamp('aml_checked_at')->nullable();
                $table->json('aml_flags')->nullable();
                $table->timestamps();
            });
            
            // Copy data from old table
            DB::statement('INSERT INTO cgo_investments_temp SELECT * FROM cgo_investments');
            
            // Drop old table and rename new one
            Schema::drop('cgo_investments');
            Schema::rename('cgo_investments_temp', 'cgo_investments');
            
            // Recreate indexes
            Schema::table('cgo_investments', function (Blueprint $table) {
                $table->index('user_id');
                $table->index('status');
                $table->index('tier');
                $table->index('created_at');
                $table->index('kyc_verified_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE cgo_investments MODIFY COLUMN status ENUM('pending', 'confirmed', 'cancelled', 'refunded') DEFAULT 'pending'");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL doesn't support removing values from enums easily
            // Would need to recreate the type, which is complex
            // For simplicity, we'll leave the values in place
        } else {
            // For SQLite, we'd need to recreate the table again
            // This is complex and error-prone, so we'll skip it
        }
    }
};