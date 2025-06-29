<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suspicious_activity_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sar_number')->unique(); // SAR-YYYY-XXXXX
            $table->string('status')->default('draft'); // draft, pending_review, submitted, closed
            $table->string('priority'); // low, medium, high, critical
            
            // Subject Information
            $table->foreignId('subject_user_id')->nullable()->constrained('users');
            $table->string('subject_type'); // customer, transaction, pattern
            $table->json('subject_details'); // Name, ID, account info
            
            // Activity Details
            $table->dateTime('activity_start_date');
            $table->dateTime('activity_end_date');
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->string('primary_currency', 3)->nullable();
            $table->integer('transaction_count')->default(0);
            $table->json('involved_accounts'); // List of account IDs
            $table->json('involved_parties'); // Other parties/entities
            
            // Suspicious Activity Information
            $table->json('activity_types'); // structuring, layering, etc.
            $table->text('activity_description');
            $table->json('red_flags'); // Identified red flags
            $table->json('triggering_rules'); // Rule IDs that triggered
            $table->json('related_transactions'); // Transaction IDs
            
            // Investigation Details
            $table->foreignId('investigator_id')->nullable()->constrained('users');
            $table->timestamp('investigation_started_at')->nullable();
            $table->timestamp('investigation_completed_at')->nullable();
            $table->text('investigation_findings')->nullable();
            $table->json('supporting_documents')->nullable();
            
            // Decision and Actions
            $table->string('decision')->nullable(); // file_sar, no_action, continue_monitoring
            $table->text('decision_rationale')->nullable();
            $table->foreignId('decision_maker_id')->nullable()->constrained('users');
            $table->timestamp('decision_date')->nullable();
            $table->json('actions_taken')->nullable();
            
            // Regulatory Filing
            $table->boolean('filed_with_regulator')->default(false);
            $table->string('filing_reference')->nullable();
            $table->timestamp('filing_date')->nullable();
            $table->string('filing_jurisdiction')->nullable();
            $table->json('filing_details')->nullable();
            
            // Follow-up
            $table->boolean('requires_follow_up')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->json('related_sars')->nullable(); // Links to other SARs
            
            // Quality Assurance
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comments')->nullable();
            $table->boolean('qa_approved')->default(false);
            
            // Metadata
            $table->boolean('is_confidential')->default(true);
            $table->json('access_log')->nullable(); // Who accessed and when
            $table->timestamp('retention_until')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'priority']);
            $table->index('subject_user_id');
            $table->index('filing_date');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspicious_activity_reports');
    }
};