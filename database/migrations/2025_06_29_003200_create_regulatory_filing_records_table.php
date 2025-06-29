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
        Schema::create('regulatory_filing_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('regulatory_report_id');
            $table->string('filing_id')->unique(); // FIL-2024-0001
            
            // Filing details
            $table->string('filing_type'); // initial, amendment, correction
            $table->string('filing_method'); // api, manual, email, portal
            $table->string('filing_status'); // pending, submitted, acknowledged, accepted, rejected
            $table->integer('filing_attempt')->default(1);
            
            // Submission details
            $table->timestamp('filed_at');
            $table->string('filed_by'); // User who filed
            $table->json('filing_credentials')->nullable(); // Encrypted credentials used
            $table->string('filing_reference')->nullable(); // External reference
            $table->json('filing_request')->nullable(); // Request sent
            $table->json('filing_response')->nullable(); // Response received
            $table->integer('response_code')->nullable();
            $table->text('response_message')->nullable();
            
            // Acknowledgment
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledgment_number')->nullable();
            $table->json('acknowledgment_details')->nullable();
            
            // Validation and errors
            $table->boolean('passed_validation')->default(false);
            $table->json('validation_errors')->nullable();
            $table->json('warnings')->nullable();
            
            // Retry information
            $table->boolean('requires_retry')->default(false);
            $table->timestamp('retry_after')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            
            // Audit and compliance
            $table->json('audit_log')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Foreign key
            $table->foreign('regulatory_report_id')->references('id')->on('regulatory_reports')->onDelete('cascade');
            
            // Indexes
            $table->index('filing_status');
            $table->index('filed_at');
            $table->index(['regulatory_report_id', 'filing_status'], 'idx_report_filing_status');
            $table->index(['requires_retry', 'retry_after'], 'idx_retry_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regulatory_filing_records');
    }
};