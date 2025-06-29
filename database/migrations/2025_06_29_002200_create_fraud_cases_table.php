<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('case_number')->unique(); // FC-YYYY-XXXXX
            $table->string('status')->default('open'); // open, investigating, resolved, closed
            $table->string('priority'); // low, medium, high, critical
            $table->string('type'); // account_takeover, identity_theft, transaction_fraud, etc.
            
            // Subject Information
            $table->foreignId('subject_user_id')->nullable()->constrained('users');
            $table->uuid('subject_account_id')->nullable();
            $table->json('related_entities'); // Related users, accounts, transactions
            
            // Case Details
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->integer('transaction_count')->default(0);
            $table->dateTime('fraud_start_date')->nullable();
            $table->dateTime('fraud_end_date')->nullable();
            $table->text('description');
            
            // Detection Information
            $table->string('detection_method'); // rule_based, ml_model, manual_report, external_report
            $table->json('detection_details'); // Which rules/models detected it
            $table->uuid('initial_fraud_score_id')->nullable();
            $table->timestamp('detected_at');
            
            // Investigation
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('investigation_started_at')->nullable();
            $table->timestamp('investigation_completed_at')->nullable();
            $table->json('investigation_notes')->nullable(); // Array of timestamped notes
            $table->json('evidence')->nullable(); // Documents, screenshots, logs
            
            // Actions Taken
            $table->json('actions_taken'); // Array of actions with timestamps
            $table->boolean('funds_recovered')->default(false);
            $table->decimal('amount_recovered', 15, 2)->default(0);
            $table->boolean('law_enforcement_notified')->default(false);
            $table->string('law_enforcement_reference')->nullable();
            
            // Resolution
            $table->string('resolution')->nullable(); // confirmed_fraud, false_positive, insufficient_evidence
            $table->text('resolution_summary')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            
            // Prevention Measures
            $table->json('prevention_measures')->nullable(); // New rules/controls implemented
            $table->boolean('rules_updated')->default(false);
            $table->json('updated_rules')->nullable();
            
            // Reporting
            $table->boolean('reported_to_regulator')->default(false);
            $table->json('regulatory_reports')->nullable();
            $table->boolean('customer_notified')->default(false);
            $table->timestamp('customer_notified_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('subject_account_id')->references('id')->on('accounts');
            $table->foreign('initial_fraud_score_id')->references('id')->on('fraud_scores');
            
            $table->index(['status', 'priority']);
            $table->index('case_number');
            $table->index('subject_user_id');
            $table->index('assigned_to');
            $table->index('detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_cases');
    }
};