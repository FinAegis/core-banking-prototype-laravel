<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for Agent Protocol (AP2) tables.
 *
 * This migration runs in tenant database context, creating tables for
 * AI agent identities, wallets, transactions, escrows, and disputes.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_identities', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->unique();
            $table->string('did')->unique();
            $table->string('name');
            $table->string('type')->default('autonomous'); // autonomous, human, hybrid
            $table->string('status')->default('active')->index(); // active, inactive, suspended
            $table->json('capabilities')->nullable();
            $table->decimal('reputation_score', 5, 2)->default(50.00);
            $table->string('wallet_id')->nullable();
            $table->json('metadata')->nullable();

            // KYC linkage
            $table->string('linked_user_uuid')->nullable()->index();
            $table->string('kyc_level')->nullable();
            $table->timestamp('kyc_verified_at')->nullable();

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('reputation_score');
        });

        Schema::create('agent_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id')->unique();
            $table->string('agent_id')->index();
            $table->string('currency', 3)->default('USD');
            $table->decimal('available_balance', 20, 2)->default(0);
            $table->decimal('held_balance', 20, 2)->default(0);
            $table->decimal('total_balance', 20, 2)->default(0);
            $table->decimal('daily_limit', 20, 2)->nullable();
            $table->decimal('transaction_limit', 20, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            // Daily tracking
            $table->decimal('daily_volume', 20, 2)->default(0);
            $table->date('daily_reset_date')->nullable();

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->timestamps();

            $table->index('currency');
            $table->index('is_active');
            $table->foreign('agent_id')->references('agent_id')->on('agent_identities')->cascadeOnDelete();
        });

        Schema::create('agent_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->string('from_agent_id')->index();
            $table->string('to_agent_id')->index();
            $table->decimal('amount', 20, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('fee_amount', 20, 2)->default(0);
            $table->string('fee_type')->nullable(); // domestic, international, crypto, escrow
            $table->string('status')->index(); // initiated, validated, processing, completed, failed
            $table->string('type'); // direct, escrow, split
            $table->string('escrow_id')->nullable()->index();
            $table->json('metadata')->nullable();

            // Verification tracking
            $table->boolean('fraud_checked')->default(false);
            $table->decimal('fraud_score', 5, 2)->nullable();
            $table->string('fraud_status')->nullable();

            // Audit fields
            $table->string('initiated_by')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->foreign('from_agent_id')->references('agent_id')->on('agent_identities')->cascadeOnDelete();
            $table->foreign('to_agent_id')->references('agent_id')->on('agent_identities')->cascadeOnDelete();
        });

        Schema::create('escrows', function (Blueprint $table) {
            $table->id();
            $table->string('escrow_id')->unique();
            $table->string('transaction_id')->index();
            $table->string('sender_agent_id')->index();
            $table->string('receiver_agent_id')->index();
            $table->decimal('amount', 20, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('funded_amount', 20, 2)->default(0);
            $table->json('conditions')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('status')->index(); // created, funded, released, disputed, resolved, expired, cancelled
            $table->boolean('is_disputed')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->string('released_by')->nullable();
            $table->json('metadata')->nullable();

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->timestamps();

            $table->index('is_disputed');
            $table->foreign('transaction_id')->references('transaction_id')->on('agent_transactions')->cascadeOnDelete();
            $table->foreign('sender_agent_id')->references('agent_id')->on('agent_identities')->cascadeOnDelete();
            $table->foreign('receiver_agent_id')->references('agent_id')->on('agent_identities')->cascadeOnDelete();
        });

        Schema::create('escrow_disputes', function (Blueprint $table) {
            $table->id();
            $table->string('dispute_id')->unique();
            $table->string('escrow_id')->index();
            $table->string('disputed_by')->index();
            $table->text('reason');
            $table->json('evidence')->nullable();
            $table->string('status')->index(); // open, investigating, resolved, escalated
            $table->string('resolution_method'); // automated, arbitration, voting
            $table->string('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_type')->nullable(); // release_to_receiver, return_to_sender, split, arbitrated
            $table->json('resolution_allocation')->nullable();
            $table->json('resolution_details')->nullable();

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->timestamps();

            $table->index('resolution_method');
            $table->foreign('escrow_id')->references('escrow_id')->on('escrows')->cascadeOnDelete();
            $table->foreign('disputed_by')->references('agent_id')->on('agent_identities')->cascadeOnDelete();
        });

        // Agent Protocol event sourcing
        Schema::create('agent_protocol_events', function (Blueprint $table) {
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

        Schema::create('agent_protocol_snapshots', function (Blueprint $table) {
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
        Schema::dropIfExists('agent_protocol_snapshots');
        Schema::dropIfExists('agent_protocol_events');
        Schema::dropIfExists('escrow_disputes');
        Schema::dropIfExists('escrows');
        Schema::dropIfExists('agent_transactions');
        Schema::dropIfExists('agent_wallets');
        Schema::dropIfExists('agent_identities');
    }
};
