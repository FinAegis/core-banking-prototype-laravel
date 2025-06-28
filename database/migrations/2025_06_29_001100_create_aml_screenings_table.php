<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aml_screenings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('entity_id')->index(); // Can be user_id or financial_institution_id
            $table->string('entity_type'); // user, financial_institution
            $table->string('screening_number')->unique(); // AML-YYYY-XXXXX
            $table->string('type'); // sanctions, pep, adverse_media, comprehensive
            $table->string('status')->default('pending'); // pending, in_progress, completed, failed
            $table->string('provider')->nullable(); // dow_jones, refinitiv, manual, etc.
            $table->string('provider_reference')->nullable();
            
            // Screening Parameters
            $table->json('search_parameters'); // Names, DOB, countries, etc.
            $table->json('screening_config')->nullable(); // Provider-specific config
            $table->boolean('fuzzy_matching')->default(true);
            $table->integer('match_threshold')->default(85); // 0-100
            
            // Results Summary
            $table->integer('total_matches')->default(0);
            $table->integer('confirmed_matches')->default(0);
            $table->integer('false_positives')->default(0);
            $table->string('overall_risk')->nullable(); // low, medium, high, critical
            
            // Screening Results
            $table->json('sanctions_results')->nullable();
            $table->json('pep_results')->nullable();
            $table->json('adverse_media_results')->nullable();
            $table->json('other_results')->nullable();
            
            // Match Details
            $table->json('confirmed_matches_detail')->nullable();
            $table->json('potential_matches_detail')->nullable();
            $table->json('dismissed_matches_detail')->nullable();
            
            // Lists Checked
            $table->json('lists_checked')->nullable(); // OFAC, EU, UN, etc.
            $table->timestamp('lists_updated_at')->nullable();
            
            // Review Information
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_decision')->nullable(); // clear, escalate, block
            $table->text('review_notes')->nullable();
            
            // Screening Metadata
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('processing_time', 8, 2)->nullable(); // seconds
            $table->json('api_response')->nullable(); // Raw provider response
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['entity_id', 'entity_type']);
            $table->index(['status', 'type']);
            $table->index('overall_risk');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aml_screenings');
    }
};