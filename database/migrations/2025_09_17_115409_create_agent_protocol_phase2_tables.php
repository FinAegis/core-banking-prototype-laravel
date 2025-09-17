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
        // Agent Identities table
        Schema::create('agent_identities', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->unique();
            $table->string('did')->unique();
            $table->string('name');
            $table->string('type')->default('autonomous'); // autonomous, human, hybrid
            $table->string('status')->default('active'); // active, inactive, suspended
            $table->json('capabilities')->nullable();
            $table->decimal('reputation_score', 5, 2)->default(50.00);
            $table->string('wallet_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
            $table->index('reputation_score');
        });

        // Agent Wallets table
        Schema::create('agent_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_id')->unique();
            $table->string('agent_id');
            $table->string('currency', 3)->default('USD');
            $table->decimal('available_balance', 20, 2)->default(0);
            $table->decimal('held_balance', 20, 2)->default(0);
            $table->decimal('total_balance', 20, 2)->default(0);
            $table->decimal('daily_limit', 20, 2)->nullable();
            $table->decimal('transaction_limit', 20, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('currency');
            $table->index('is_active');
            $table->foreign('agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
        });

        // Agent Transactions table
        Schema::create('agent_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->string('from_agent_id');
            $table->string('to_agent_id');
            $table->decimal('amount', 20, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('fee_amount', 20, 2)->default(0);
            $table->string('fee_type')->nullable(); // domestic, international, crypto, escrow
            $table->string('status'); // initiated, validated, processing, completed, failed
            $table->string('type'); // direct, escrow, split
            $table->string('escrow_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('from_agent_id');
            $table->index('to_agent_id');
            $table->index('status');
            $table->index('type');
            $table->index('escrow_id');
            $table->foreign('from_agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
            $table->foreign('to_agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
        });

        // Escrows table
        Schema::create('escrows', function (Blueprint $table) {
            $table->id();
            $table->string('escrow_id')->unique();
            $table->string('transaction_id');
            $table->string('sender_agent_id');
            $table->string('receiver_agent_id');
            $table->decimal('amount', 20, 2);
            $table->string('currency', 3)->default('USD');
            $table->decimal('funded_amount', 20, 2)->default(0);
            $table->json('conditions')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status'); // created, funded, released, disputed, resolved, expired, cancelled
            $table->boolean('is_disputed')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->string('released_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('sender_agent_id');
            $table->index('receiver_agent_id');
            $table->index('status');
            $table->index('is_disputed');
            $table->index('expires_at');
            $table->foreign('transaction_id')->references('transaction_id')->on('agent_transactions')->onDelete('cascade');
            $table->foreign('sender_agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
            $table->foreign('receiver_agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
        });

        // Escrow Disputes table
        Schema::create('escrow_disputes', function (Blueprint $table) {
            $table->id();
            $table->string('dispute_id')->unique();
            $table->string('escrow_id');
            $table->string('disputed_by');
            $table->text('reason');
            $table->json('evidence')->nullable();
            $table->string('status'); // open, investigating, resolved, escalated
            $table->string('resolution_method'); // automated, arbitration, voting
            $table->string('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_type')->nullable(); // release_to_receiver, return_to_sender, split, arbitrated
            $table->json('resolution_allocation')->nullable();
            $table->json('resolution_details')->nullable();
            $table->timestamps();

            $table->index('escrow_id');
            $table->index('disputed_by');
            $table->index('status');
            $table->index('resolution_method');
            $table->foreign('escrow_id')->references('escrow_id')->on('escrows')->onDelete('cascade');
            $table->foreign('disputed_by')->references('agent_id')->on('agent_identities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_disputes');
        Schema::dropIfExists('escrows');
        Schema::dropIfExists('agent_transactions');
        Schema::dropIfExists('agent_wallets');
        Schema::dropIfExists('agent_identities');
    }
};
