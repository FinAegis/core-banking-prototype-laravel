<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->index();
            $table->string('verification_number')->unique(); // KYC-YYYY-XXXXX
            $table->string('type'); // identity, address, income, enhanced_due_diligence
            $table->string('status')->default('pending'); // pending, in_progress, completed, failed, expired
            $table->string('provider')->nullable(); // jumio, onfido, manual, etc.
            $table->string('provider_reference')->nullable();
            
            // Verification Details
            $table->json('verification_data')->nullable(); // Provider-specific data
            $table->json('extracted_data')->nullable(); // Extracted identity info
            $table->json('checks_performed')->nullable(); // List of checks done
            $table->decimal('confidence_score', 5, 2)->nullable(); // 0-100
            
            // Document Information
            $table->string('document_type')->nullable(); // passport, driving_license, national_id
            $table->string('document_number')->nullable();
            $table->string('document_country')->nullable();
            $table->date('document_expiry')->nullable();
            
            // Personal Information (encrypted)
            $table->text('first_name')->nullable();
            $table->text('last_name')->nullable();
            $table->text('middle_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('nationality')->nullable();
            
            // Address Information (encrypted)
            $table->text('address_line1')->nullable();
            $table->text('address_line2')->nullable();
            $table->text('city')->nullable();
            $table->text('state')->nullable();
            $table->text('postal_code')->nullable();
            $table->string('country')->nullable();
            
            // Risk Assessment
            $table->string('risk_level')->nullable(); // low, medium, high
            $table->json('risk_factors')->nullable();
            $table->boolean('pep_check')->default(false);
            $table->boolean('sanctions_check')->default(false);
            $table->boolean('adverse_media_check')->default(false);
            
            // Verification Results
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('verification_report')->nullable();
            
            // Review Information
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'type']);
            $table->index('provider_reference');
            $table->index('document_number');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_verifications');
    }
};