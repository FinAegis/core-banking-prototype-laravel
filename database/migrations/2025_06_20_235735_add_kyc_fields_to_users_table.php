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
        Schema::table('users', function (Blueprint $table) {
            // KYC Status fields
            $table->enum('kyc_status', ['not_started', 'pending', 'in_review', 'approved', 'rejected', 'expired'])
                ->default('not_started')
                ->after('email_verified_at');
            $table->timestamp('kyc_submitted_at')->nullable()->after('kyc_status');
            $table->timestamp('kyc_approved_at')->nullable()->after('kyc_submitted_at');
            $table->timestamp('kyc_expires_at')->nullable()->after('kyc_approved_at');
            
            // KYC Level (for tiered verification)
            $table->enum('kyc_level', ['basic', 'enhanced', 'full'])->default('basic')->after('kyc_expires_at');
            
            // Compliance fields
            $table->boolean('pep_status')->default(false)->comment('Politically Exposed Person')->after('kyc_level');
            $table->string('risk_rating')->nullable()->comment('low, medium, high')->after('pep_status');
            $table->json('kyc_data')->nullable()->comment('Encrypted KYC data')->after('risk_rating');
            
            // GDPR and consent fields
            $table->timestamp('privacy_policy_accepted_at')->nullable()->after('kyc_data');
            $table->timestamp('terms_accepted_at')->nullable()->after('privacy_policy_accepted_at');
            $table->timestamp('marketing_consent_at')->nullable()->after('terms_accepted_at');
            $table->boolean('data_retention_consent')->default(false)->after('marketing_consent_at');
            
            // Indexes for performance
            $table->index('kyc_status');
            $table->index('kyc_level');
            $table->index('risk_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['kyc_status']);
            $table->dropIndex(['kyc_level']);
            $table->dropIndex(['risk_rating']);
            
            $table->dropColumn([
                'kyc_status',
                'kyc_submitted_at',
                'kyc_approved_at',
                'kyc_expires_at',
                'kyc_level',
                'pep_status',
                'risk_rating',
                'kyc_data',
                'privacy_policy_accepted_at',
                'terms_accepted_at',
                'marketing_consent_at',
                'data_retention_consent',
            ]);
        });
    }
};
