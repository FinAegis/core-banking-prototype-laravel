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
            // KYC verification fields
            if (!Schema::hasColumn('cgo_investments', 'kyc_verified_at')) {
                $table->timestamp('kyc_verified_at')->nullable()->after('payment_completed_at');
            }
            if (!Schema::hasColumn('cgo_investments', 'kyc_level')) {
                $table->string('kyc_level')->nullable()->after('kyc_verified_at');
            }
            if (!Schema::hasColumn('cgo_investments', 'risk_assessment')) {
                $table->decimal('risk_assessment', 5, 2)->nullable()->after('kyc_level');
            }
            if (!Schema::hasColumn('cgo_investments', 'aml_checked_at')) {
                $table->timestamp('aml_checked_at')->nullable()->after('risk_assessment');
            }
            if (!Schema::hasColumn('cgo_investments', 'aml_flags')) {
                $table->json('aml_flags')->nullable()->after('aml_checked_at');
            }
            
            // Add indexes for performance
            try {
                $table->index('kyc_verified_at');
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cgo_investments', function (Blueprint $table) {
            try {
                $table->dropIndex(['kyc_verified_at']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
            $table->dropColumn([
                'kyc_verified_at',
                'kyc_level',
                'risk_assessment',
                'aml_checked_at',
                'aml_flags',
            ]);
        });
    }
};