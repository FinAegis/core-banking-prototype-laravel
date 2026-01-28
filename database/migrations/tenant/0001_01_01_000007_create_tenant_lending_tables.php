<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for lending tables.
 *
 * This migration runs in tenant database context, creating tables for
 * loan applications, loans, collateral, and repayments.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid')->index();
            $table->string('application_number')->unique();
            $table->string('loan_type');
            $table->decimal('requested_amount', 20, 8);
            $table->string('currency', 10);
            $table->unsignedInteger('term_months');
            $table->string('purpose')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('credit_score', 5, 2)->nullable();
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->json('applicant_data')->nullable();
            $table->json('financial_data')->nullable();
            $table->json('documents')->nullable();
            $table->decimal('approved_amount', 20, 8)->nullable();
            $table->decimal('approved_rate', 8, 4)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_uuid', 'status']);
            $table->index(['loan_type', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid')->index();
            $table->foreignId('application_id')->nullable()->constrained('loan_applications')->nullOnDelete();
            $table->string('loan_number')->unique();
            $table->string('loan_type');
            $table->decimal('principal_amount', 20, 8);
            $table->decimal('outstanding_principal', 20, 8);
            $table->decimal('total_interest', 20, 8)->default(0);
            $table->decimal('outstanding_interest', 20, 8)->default(0);
            $table->decimal('total_fees', 20, 8)->default(0);
            $table->decimal('outstanding_fees', 20, 8)->default(0);
            $table->string('currency', 10);
            $table->decimal('interest_rate', 8, 4);
            $table->string('interest_type')->default('fixed');
            $table->unsignedInteger('term_months');
            $table->decimal('monthly_payment', 20, 8);
            $table->string('status')->default('active');
            $table->date('disbursement_date');
            $table->date('maturity_date');
            $table->date('next_payment_date')->nullable();
            $table->unsignedInteger('payments_made')->default(0);
            $table->unsignedInteger('payments_remaining');
            $table->unsignedInteger('days_past_due')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->string('closure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_uuid', 'status']);
            $table->index(['loan_type', 'status']);
            $table->index(['status', 'next_payment_date']);
            $table->index(['days_past_due', 'status']);
        });

        Schema::create('loan_collateral', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->string('collateral_type');
            $table->string('description');
            $table->decimal('value', 20, 8);
            $table->decimal('ltv_ratio', 8, 4)->nullable();
            $table->string('currency', 10);
            $table->string('status')->default('pledged');
            $table->json('valuation_data')->nullable();
            $table->timestamp('valued_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'status']);
            $table->index(['collateral_type', 'status']);
        });

        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->uuid('transaction_uuid')->nullable()->index();
            $table->unsignedInteger('payment_number');
            $table->decimal('amount', 20, 8);
            $table->decimal('principal_portion', 20, 8);
            $table->decimal('interest_portion', 20, 8);
            $table->decimal('fee_portion', 20, 8)->default(0);
            $table->decimal('penalty_portion', 20, 8)->default(0);
            $table->string('currency', 10);
            $table->string('status')->default('scheduled');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'status']);
            $table->index(['due_date', 'status']);
            $table->index(['payment_number']);
        });

        Schema::create('lending_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->unsignedInteger('event_version')->default(1);
            $table->string('event_class');
            $table->json('event_properties');
            $table->json('meta_data')->nullable();
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
            $table->index(['event_class', 'created_at']);
        });

        Schema::create('lending_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lending_snapshots');
        Schema::dropIfExists('lending_events');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('loan_collateral');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('loan_applications');
    }
};
