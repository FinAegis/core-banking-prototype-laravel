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
        Schema::create('regulatory_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('report_id')->unique(); // REG-2024-0001
            $table->string('report_type'); // CTR, SAR, OFAC, BSA, etc.
            $table->string('jurisdiction'); // US, EU, UK, etc.
            $table->date('reporting_period_start');
            $table->date('reporting_period_end');
            $table->string('status'); // draft, pending_review, submitted, accepted, rejected
            $table->integer('priority')->default(1); // 1-5, 5 being highest
            
            // Report details
            $table->string('file_path')->nullable();
            $table->string('file_format'); // json, xml, csv, pdf
            $table->integer('file_size')->nullable(); // in bytes
            $table->string('file_hash')->nullable(); // SHA-256 hash for integrity
            
            // Submission details
            $table->timestamp('generated_at');
            $table->timestamp('submitted_at')->nullable();
            $table->string('submitted_by')->nullable();
            $table->string('submission_reference')->nullable(); // External reference number
            $table->json('submission_response')->nullable(); // Response from regulatory authority
            
            // Review process
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();
            $table->boolean('requires_correction')->default(false);
            $table->json('corrections_required')->nullable();
            
            // Compliance details
            $table->string('regulation_reference')->nullable(); // e.g., "31 CFR 1022.320"
            $table->boolean('is_mandatory')->default(true);
            $table->timestamp('due_date')->nullable();
            $table->boolean('is_overdue')->default(false);
            $table->integer('days_overdue')->default(0);
            
            // Report metadata
            $table->json('report_data')->nullable(); // Summary data
            $table->integer('record_count')->default(0);
            $table->decimal('total_amount', 20, 2)->nullable();
            $table->json('entities_included')->nullable(); // List of entities in report
            $table->json('risk_indicators')->nullable();
            
            // Audit trail
            $table->json('audit_trail')->nullable();
            $table->json('tags')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('report_type');
            $table->index('jurisdiction');
            $table->index('status');
            $table->index('reporting_period_start');
            $table->index('reporting_period_end');
            $table->index('due_date');
            $table->index(['is_overdue', 'due_date']);
            $table->index(['report_type', 'status', 'jurisdiction']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regulatory_reports');
    }
};