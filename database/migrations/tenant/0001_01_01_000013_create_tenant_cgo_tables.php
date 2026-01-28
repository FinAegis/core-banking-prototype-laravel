<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for CGO (Continuous Growth Offering) tables.
 *
 * This migration runs in tenant database context, creating tables for
 * investment rounds, investments, refunds, and notifications.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cgo_pricing_rounds', function (Blueprint $table) {
            $table->id();
            $table->integer('round_number')->unique();
            $table->string('name')->nullable();
            $table->decimal('share_price', 10, 4);
            $table->decimal('max_shares_available', 15, 4);
            $table->decimal('shares_sold', 15, 4)->default(0);
            $table->decimal('total_raised', 15, 2)->default(0);
            $table->decimal('valuation', 20, 2)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_active')->default(false);

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('cgo_investments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid')->index();
            $table->foreignId('round_id')->nullable()->constrained('cgo_pricing_rounds')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10);
            $table->string('email')->nullable();
            $table->decimal('share_price', 10, 4);
            $table->decimal('shares_purchased', 15, 4);
            $table->decimal('ownership_percentage', 8, 6);
            $table->enum('tier', ['bronze', 'silver', 'gold']);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'refunded'])->default('pending');

            // Payment information
            $table->string('payment_method');
            $table->string('crypto_address')->nullable();
            $table->string('crypto_tx_hash')->nullable();

            // Stripe integration
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->enum('stripe_payment_status', ['pending', 'succeeded', 'failed', 'cancelled'])->nullable();

            // Coinbase Commerce integration
            $table->string('coinbase_charge_id')->nullable()->index();
            $table->string('coinbase_code')->nullable();
            $table->enum('coinbase_payment_status', ['pending', 'confirmed', 'unresolved', 'expired', 'cancelled'])->nullable();
            $table->json('coinbase_metadata')->nullable();

            // Certificate
            $table->string('certificate_number')->nullable();
            $table->timestamp('certificate_issued_at')->nullable();

            // KYC fields
            $table->boolean('kyc_required')->default(false);
            $table->enum('kyc_status', [
                'not_required',
                'pending',
                'documents_requested',
                'in_review',
                'approved',
                'rejected',
                'expired',
            ])->default('not_required');
            $table->timestamp('kyc_started_at')->nullable();
            $table->timestamp('kyc_approved_at')->nullable();
            $table->string('kyc_provider_id')->nullable();
            $table->json('kyc_documents')->nullable();
            $table->text('kyc_rejection_reason')->nullable();
            $table->timestamp('kyc_expiry_date')->nullable();

            // Agreement fields
            $table->boolean('agreement_signed')->default(false);
            $table->timestamp('agreement_signed_at')->nullable();
            $table->string('agreement_ip_address')->nullable();
            $table->text('agreement_user_agent')->nullable();
            $table->string('agreement_document_hash')->nullable();

            $table->json('metadata')->nullable();

            // Audit fields
            $table->string('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('tier');
            $table->index('kyc_status');
            $table->index('created_at');
        });

        Schema::create('cgo_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('investment_id')->constrained('cgo_investments');
            $table->string('user_uuid')->index();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('USD');

            $table->enum('reason', [
                'requested_by_customer',
                'duplicate',
                'fraudulent',
                'agreement_violation',
                'regulatory_requirement',
                'other',
            ]);
            $table->text('reason_details')->nullable();

            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])
                ->default('pending');
            $table->string('initiated_by')->index();

            // Processing details
            $table->timestamp('processed_at')->nullable();
            $table->string('processor_reference')->nullable();
            $table->json('processor_response')->nullable();
            $table->text('processing_notes')->nullable();

            // Failure tracking
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            // Cancellation tracking
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Refund destination
            $table->string('refund_address')->nullable();
            $table->json('bank_details')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['investment_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('cgo_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid')->index();
            $table->foreignId('investment_id')->nullable()->constrained('cgo_investments')->nullOnDelete();
            $table->string('type', 50); // round_started, investment_confirmed, kyc_required, etc.
            $table->string('channel', 20)->default('email'); // email, sms, push
            $table->string('status', 20)->default('pending')->index();
            $table->text('subject')->nullable();
            $table->text('content');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('created_at');
        });

        // CGO event sourcing
        Schema::create('cgo_events', function (Blueprint $table) {
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

        Schema::create('cgo_snapshots', function (Blueprint $table) {
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
        Schema::dropIfExists('cgo_snapshots');
        Schema::dropIfExists('cgo_events');
        Schema::dropIfExists('cgo_notifications');
        Schema::dropIfExists('cgo_refunds');
        Schema::dropIfExists('cgo_investments');
        Schema::dropIfExists('cgo_pricing_rounds');
    }
};
