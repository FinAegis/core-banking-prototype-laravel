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
        Schema::create('compliance_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('case_id')->unique()->index();
            $table->string('title');
            $table->text('description');
            $table->string('type')->index();
            $table->string('priority')->index();
            $table->string('status')->default('open')->index();

            // Metrics
            $table->integer('alert_count')->default(0);
            $table->decimal('total_risk_score', 8, 2)->default(0);

            // Investigation data
            $table->json('entities')->nullable();
            $table->json('evidence')->nullable();
            $table->text('investigation_summary')->nullable();
            $table->json('findings')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('actions_taken')->nullable();
            $table->json('regulatory_filings')->nullable();

            // Assignment
            $table->uuid('assigned_to')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();
            $table->uuid('assigned_by')->nullable();
            $table->uuid('created_by')->nullable();

            // Review
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Closure
            $table->uuid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closure_reason')->nullable();
            $table->text('closure_notes')->nullable();

            // Activity tracking
            $table->integer('reopened_count')->default(0);
            $table->timestamp('last_activity_at')->nullable()->index();

            // SLA management
            $table->timestamp('due_date')->nullable()->index();
            $table->string('sla_status')->nullable()->index();
            $table->integer('escalation_level')->default(0);

            // Metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->json('history')->nullable();
            $table->json('documents')->nullable();
            $table->json('communications')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['type', 'priority', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['due_date', 'status']);
            $table->index(['created_at', 'status']);

            // Foreign keys
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
        });

        // Add foreign key to compliance_alerts table
        Schema::table('compliance_alerts', function (Blueprint $table) {
            $table->foreign('case_id')->references('id')->on('compliance_cases')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key from compliance_alerts first
        Schema::table('compliance_alerts', function (Blueprint $table) {
            $table->dropForeign(['case_id']);
        });

        Schema::dropIfExists('compliance_cases');
    }
};
