<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_risk_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained();
            $table->string('profile_number')->unique(); // CRP-YYYY-XXXXX
            
            // Overall Risk Assessment
            $table->string('risk_rating'); // low, medium, high, prohibited
            $table->decimal('risk_score', 5, 2); // 0-100
            $table->timestamp('last_assessment_at');
            $table->timestamp('next_review_at');
            
            // Risk Factors
            $table->json('geographic_risk'); // Countries, jurisdictions
            $table->json('product_risk'); // Products/services used
            $table->json('channel_risk'); // Onboarding/transaction channels
            $table->json('customer_risk'); // PEP, sanctions, occupation
            $table->json('behavioral_risk'); // Transaction patterns
            
            // Customer Due Diligence
            $table->string('cdd_level'); // simplified, standard, enhanced
            $table->json('cdd_measures'); // Applied measures
            $table->timestamp('cdd_completed_at')->nullable();
            $table->timestamp('cdd_expires_at')->nullable();
            
            // PEP (Politically Exposed Person) Status
            $table->boolean('is_pep')->default(false);
            $table->string('pep_type')->nullable(); // domestic, foreign, international_org
            $table->string('pep_position')->nullable();
            $table->json('pep_details')->nullable();
            $table->timestamp('pep_verified_at')->nullable();
            
            // Sanctions Status
            $table->boolean('is_sanctioned')->default(false);
            $table->json('sanctions_details')->nullable();
            $table->timestamp('sanctions_verified_at')->nullable();
            
            // Adverse Media
            $table->boolean('has_adverse_media')->default(false);
            $table->json('adverse_media_details')->nullable();
            $table->timestamp('adverse_media_checked_at')->nullable();
            
            // Transaction Limits
            $table->decimal('daily_transaction_limit', 15, 2);
            $table->decimal('monthly_transaction_limit', 15, 2);
            $table->decimal('single_transaction_limit', 15, 2);
            $table->json('restricted_countries')->nullable();
            $table->json('restricted_currencies')->nullable();
            
            // Monitoring Settings
            $table->boolean('enhanced_monitoring')->default(false);
            $table->json('monitoring_rules')->nullable();
            $table->integer('monitoring_frequency')->default(30); // days
            
            // Historical Data
            $table->json('risk_history')->nullable(); // Historical ratings
            $table->json('screening_history')->nullable(); // Past screening results
            $table->integer('suspicious_activities_count')->default(0);
            $table->timestamp('last_suspicious_activity_at')->nullable();
            
            // Source of Wealth/Funds
            $table->string('source_of_wealth')->nullable();
            $table->string('source_of_funds')->nullable();
            $table->boolean('sow_verified')->default(false);
            $table->boolean('sof_verified')->default(false);
            
            // Business Information (for business accounts)
            $table->string('business_type')->nullable();
            $table->string('industry_code')->nullable();
            $table->json('beneficial_owners')->nullable();
            $table->boolean('complex_structure')->default(false);
            
            // Review and Approval
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->json('override_reasons')->nullable(); // If risk was overridden
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('risk_rating');
            $table->index('cdd_level');
            $table->index(['is_pep', 'is_sanctioned']);
            $table->index('next_review_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_risk_profiles');
    }
};