<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for compliance tables.
 *
 * This migration runs in tenant database context, creating tables for
 * compliance monitoring, KYC, AML, and risk management.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('compliance_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->string('entity_type');
            $table->string('entity_id')->index();
            $table->string('user_uuid')->nullable()->index();
            $table->text('description');
            $table->json('details')->nullable();
            $table->string('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_type')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('resolved_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['severity', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('compliance_cases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('case_number')->unique();
            $table->string('type');
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');
            $table->string('user_uuid')->nullable()->index();
            $table->text('summary');
            $table->json('details')->nullable();
            $table->string('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('resolution')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->string('closed_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['due_date', 'status']);
        });

        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid')->index();
            $table->string('verification_level')->default('basic');
            $table->string('status')->default('pending');
            $table->json('verified_data')->nullable();
            $table->json('verification_results')->nullable();
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('verified_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_uuid', 'verification_level']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid')->index();
            $table->foreignId('verification_id')->nullable()->constrained('kyc_verifications')->nullOnDelete();
            $table->string('document_type');
            $table->string('document_number')->nullable();
            $table->string('issuing_country', 3)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('file_path');
            $table->string('file_hash')->nullable();
            $table->string('status')->default('pending');
            $table->json('verification_results')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_uuid', 'document_type']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('compliance_events', function (Blueprint $table) {
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

        Schema::create('compliance_snapshots', function (Blueprint $table) {
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
        Schema::dropIfExists('compliance_snapshots');
        Schema::dropIfExists('compliance_events');
        Schema::dropIfExists('kyc_documents');
        Schema::dropIfExists('kyc_verifications');
        Schema::dropIfExists('compliance_cases');
        Schema::dropIfExists('compliance_alerts');
    }
};
