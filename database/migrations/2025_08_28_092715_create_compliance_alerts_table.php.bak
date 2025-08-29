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
        Schema::create('compliance_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('alert_id')->unique()->index();
            $table->string('type')->index();
            $table->string('severity')->index();
            $table->string('status')->default('open')->index();
            $table->string('title');
            $table->text('description');
            $table->string('source')->default('system');

            // Entity references
            $table->string('entity_type')->nullable();
            $table->uuid('entity_id')->nullable();
            $table->uuid('transaction_id')->nullable();
            $table->uuid('account_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('rule_id')->nullable();
            $table->uuid('case_id')->nullable();

            // Alert data
            $table->json('pattern_data')->nullable();
            $table->json('evidence')->nullable();
            $table->decimal('risk_score', 5, 2)->default(0)->index();
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();

            // Timestamps
            $table->timestamp('detected_at')->index();
            $table->timestamp('expires_at')->nullable()->index();

            // Assignment
            $table->uuid('assigned_to')->nullable()->index();
            $table->timestamp('assigned_at')->nullable();
            $table->uuid('assigned_by')->nullable();

            // Resolution
            $table->timestamp('resolved_at')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->decimal('resolution_time_hours', 8, 2)->nullable();
            $table->text('false_positive_notes')->nullable();

            // Escalation
            $table->timestamp('escalated_at')->nullable();
            $table->text('escalation_reason')->nullable();

            // Investigation
            $table->json('investigation_notes')->nullable();
            $table->json('linked_alerts')->nullable();
            $table->json('history')->nullable();

            // Status tracking
            $table->timestamp('status_changed_at')->nullable();
            $table->uuid('status_changed_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['type', 'severity', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['account_id', 'status']);
            $table->index(['detected_at', 'status']);
            $table->index(['risk_score', 'status']);

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('status_changed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rule_id')->references('id')->on('transaction_monitoring_rules')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_alerts');
    }
};
