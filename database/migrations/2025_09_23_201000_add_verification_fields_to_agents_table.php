<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Security and suspension fields
            $table->boolean('is_suspended')->default(false)->after('status');
            $table->string('suspension_reason')->nullable()->after('is_suspended');
            $table->timestamp('suspended_at')->nullable()->after('suspension_reason');

            // KYC/Compliance fields
            $table->boolean('kyc_verified')->default(false)->after('suspended_at');
            $table->string('kyc_status')->default('pending')->after('kyc_verified'); // pending, verified, rejected
            $table->timestamp('kyc_verified_at')->nullable()->after('kyc_status');

            // Reputation and limits
            $table->integer('reputation_score')->default(50)->after('kyc_verified_at');
            $table->integer('total_transactions')->default(0)->after('reputation_score');
            $table->integer('successful_transactions')->default(0)->after('total_transactions');
            $table->decimal('single_transaction_limit', 20, 2)->nullable()->after('successful_transactions');
            $table->decimal('daily_limit', 20, 2)->nullable()->after('single_transaction_limit');
            $table->decimal('monthly_limit', 20, 2)->nullable()->after('daily_limit');

            // Additional security fields
            $table->string('risk_level')->default('low')->after('monthly_limit'); // low, medium, high, critical
            $table->timestamp('last_security_audit')->nullable()->after('risk_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'is_suspended',
                'suspension_reason',
                'suspended_at',
                'kyc_verified',
                'kyc_status',
                'kyc_verified_at',
                'reputation_score',
                'total_transactions',
                'successful_transactions',
                'single_transaction_limit',
                'daily_limit',
                'monthly_limit',
                'risk_level',
                'last_security_audit',
            ]);
        });
    }
};
