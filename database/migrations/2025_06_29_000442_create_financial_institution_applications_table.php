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
        Schema::create('financial_institution_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('application_number')->unique(); // FIA-YYYY-XXXXX
            
            // Institution Details
            $table->string('institution_name');
            $table->string('legal_name');
            $table->string('registration_number');
            $table->string('tax_id');
            $table->string('country', 2);
            $table->string('institution_type'); // bank, credit_union, investment_firm, etc.
            $table->decimal('assets_under_management', 20, 2)->nullable();
            $table->integer('years_in_operation');
            $table->string('primary_regulator')->nullable();
            $table->string('regulatory_license_number')->nullable();
            
            // Contact Information
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->string('contact_position');
            $table->string('contact_department')->nullable();
            
            // Address Information
            $table->string('headquarters_address');
            $table->string('headquarters_city');
            $table->string('headquarters_state')->nullable();
            $table->string('headquarters_postal_code');
            $table->string('headquarters_country', 2);
            
            // Business Information
            $table->text('business_description');
            $table->json('target_markets'); // Array of country codes
            $table->json('product_offerings'); // Array of products/services
            $table->integer('expected_monthly_transactions')->nullable();
            $table->decimal('expected_monthly_volume', 20, 2)->nullable();
            $table->json('required_currencies'); // Array of currency codes
            
            // Technical Requirements
            $table->json('integration_requirements'); // API, file transfer, etc.
            $table->boolean('requires_api_access')->default(true);
            $table->boolean('requires_webhooks')->default(true);
            $table->boolean('requires_reporting')->default(true);
            $table->json('security_certifications')->nullable(); // ISO27001, SOC2, etc.
            
            // Compliance Information
            $table->boolean('has_aml_program')->default(false);
            $table->boolean('has_kyc_procedures')->default(false);
            $table->boolean('has_data_protection_policy')->default(false);
            $table->boolean('is_pci_compliant')->default(false);
            $table->boolean('is_gdpr_compliant')->default(false);
            $table->json('compliance_certifications')->nullable();
            
            // Application Status
            $table->string('status')->default('pending'); // pending, under_review, approved, rejected, on_hold
            $table->string('review_stage')->nullable(); // initial, compliance, technical, legal, final
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Risk Assessment
            $table->string('risk_rating')->nullable(); // low, medium, high
            $table->json('risk_factors')->nullable();
            $table->decimal('risk_score', 5, 2)->nullable();
            
            // Documents
            $table->json('required_documents')->nullable();
            $table->json('submitted_documents')->nullable();
            $table->boolean('documents_verified')->default(false);
            
            // Agreement Details
            $table->date('agreement_start_date')->nullable();
            $table->date('agreement_end_date')->nullable();
            $table->json('fee_structure')->nullable();
            $table->json('service_level_agreement')->nullable();
            
            // Integration Details (populated after approval)
            $table->uuid('partner_id')->nullable();
            $table->string('api_client_id')->nullable();
            $table->boolean('sandbox_access_granted')->default(false);
            $table->boolean('production_access_granted')->default(false);
            $table->timestamp('onboarding_completed_at')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->string('source')->nullable(); // website, referral, direct, etc.
            $table->string('referral_code')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('status');
            $table->index('institution_type');
            $table->index('country');
            $table->index(['status', 'review_stage']);
            $table->index('risk_rating');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_institution_applications');
    }
};