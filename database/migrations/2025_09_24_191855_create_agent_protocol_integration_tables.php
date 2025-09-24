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
        // Agent Compliance table
        if (! Schema::hasTable('agent_compliance')) {
            Schema::create('agent_compliance', function (Blueprint $table) {
                $table->id();
                $table->string('compliance_id')->unique();
                $table->string('agent_id');
                $table->string('status')->default('pending');
                $table->string('level')->default('basic');
                $table->integer('risk_score')->default(0);
                $table->string('linked_customer_id')->nullable();
                $table->timestamp('linked_at')->nullable();
                $table->json('link_metadata')->nullable();
                $table->json('transaction_limits')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('agent_id');
                $table->index('linked_customer_id');
                $table->index('status');
                $table->foreign('agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
            });
        }

        // Agent Groups table
        if (! Schema::hasTable('agent_groups')) {
            Schema::create('agent_groups', function (Blueprint $table) {
                $table->id();
                $table->string('group_id')->unique();
                $table->string('name');
                $table->json('configuration')->nullable();
                $table->timestamps();

                $table->index('group_id');
            });
        }

        // Agent Group Members table
        if (! Schema::hasTable('agent_group_members')) {
            Schema::create('agent_group_members', function (Blueprint $table) {
                $table->id();
                $table->string('group_id');
                $table->string('agent_id');
                $table->string('role')->default('member');
                $table->timestamp('joined_at');

                $table->index(['group_id', 'agent_id']);
                $table->foreign('group_id')->references('group_id')->on('agent_groups')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
            });
        }

        // Agent Collaborations table
        if (! Schema::hasTable('agent_collaborations')) {
            Schema::create('agent_collaborations', function (Blueprint $table) {
                $table->id();
                $table->string('collaboration_id')->unique();
                $table->string('task_id');
                $table->string('task_type');
                $table->json('task_data')->nullable();
                $table->string('status')->default('initiated');
                $table->string('escrow_id')->nullable();
                $table->timestamps();

                $table->index('collaboration_id');
                $table->index('task_id');
                $table->index('status');
            });
        }

        // Collaboration Participants table
        if (! Schema::hasTable('collaboration_participants')) {
            Schema::create('collaboration_participants', function (Blueprint $table) {
                $table->id();
                $table->string('collaboration_id');
                $table->string('agent_id');
                $table->string('role')->default('participant');
                $table->string('status')->default('pending');
                $table->timestamp('joined_at');

                $table->index(['collaboration_id', 'agent_id']);
                $table->foreign('collaboration_id')->references('collaboration_id')->on('agent_collaborations')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
            });
        }

        // Agent Consensus table
        if (! Schema::hasTable('agent_consensus')) {
            Schema::create('agent_consensus', function (Blueprint $table) {
                $table->id();
                $table->string('consensus_id')->unique();
                $table->string('proposal_id');
                $table->string('proposal_type');
                $table->json('proposal_data')->nullable();
                $table->json('rules')->nullable();
                $table->string('status')->default('voting');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index('consensus_id');
                $table->index('proposal_id');
                $table->index('status');
            });
        }

        // Consensus Votes table
        if (! Schema::hasTable('consensus_votes')) {
            Schema::create('consensus_votes', function (Blueprint $table) {
                $table->id();
                $table->string('consensus_id');
                $table->string('agent_id');
                $table->string('vote')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('voted_at')->nullable();
                $table->timestamps();

                $table->index(['consensus_id', 'agent_id']);
                $table->foreign('consensus_id')->references('consensus_id')->on('agent_consensus')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
            });
        }

        // Agent Sessions table
        if (! Schema::hasTable('agent_sessions')) {
            Schema::create('agent_sessions', function (Blueprint $table) {
                $table->uuid('session_id')->primary();
                $table->string('agent_id');
                $table->string('ai_agent_id')->nullable();
                $table->string('token');
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->index('agent_id');
                $table->index('token');
                $table->foreign('agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
            });
        }

        // Conversation Tools table
        if (! Schema::hasTable('conversation_tools')) {
            Schema::create('conversation_tools', function (Blueprint $table) {
                $table->id();
                $table->string('conversation_id');
                $table->string('agent_id');
                $table->json('tools')->nullable();
                $table->timestamps();

                $table->index('conversation_id');
                $table->index('agent_id');
                $table->foreign('agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
            });
        }

        // Group Wallets table
        if (! Schema::hasTable('group_wallets')) {
            Schema::create('group_wallets', function (Blueprint $table) {
                $table->id();
                $table->string('wallet_id')->unique();
                $table->string('group_id');
                $table->decimal('balance', 20, 2)->default(0);
                $table->string('currency')->default('USD');
                $table->timestamps();

                $table->index('group_id');
                $table->foreign('group_id')->references('group_id')->on('agent_groups')->onDelete('cascade');
            });
        }

        // Wallet Permissions table
        if (! Schema::hasTable('wallet_permissions')) {
            Schema::create('wallet_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('wallet_id');
                $table->string('agent_id');
                $table->json('permissions')->nullable();
                $table->timestamp('granted_at');

                $table->index(['wallet_id', 'agent_id']);
                $table->foreign('agent_id')->references('agent_id')->on('agent_identities')->onDelete('cascade');
            });
        }

        // Performance Metrics table (for benchmarking)
        if (! Schema::hasTable('performance_metrics')) {
            Schema::create('performance_metrics', function (Blueprint $table) {
                $table->id();
                $table->string('metric');
                $table->decimal('value', 20, 2);
                $table->string('unit');
                $table->string('test_run');
                $table->timestamps();

                $table->index('metric');
                $table->index('created_at');
            });
        }

        // Message Queue table (for A2A testing)
        if (! Schema::hasTable('message_queue')) {
            Schema::create('message_queue', function (Blueprint $table) {
                $table->id();
                $table->string('message_id')->unique();
                $table->string('recipient_id');
                $table->string('status')->default('pending');
                $table->string('priority')->default('normal');
                $table->integer('max_retries')->default(3);
                $table->integer('retry_attempts')->default(0);
                $table->integer('processing_order')->default(0);
                $table->timestamps();

                $table->index('recipient_id');
                $table->index('status');
                $table->index('priority');
            });
        }

        // Message Acknowledgments table
        if (! Schema::hasTable('message_acknowledgments')) {
            Schema::create('message_acknowledgments', function (Blueprint $table) {
                $table->id();
                $table->string('message_id');
                $table->string('status');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('message_id');
            });
        }

        // Agent Messages table (for testing)
        if (! Schema::hasTable('agent_messages')) {
            Schema::create('agent_messages', function (Blueprint $table) {
                $table->id();
                $table->string('message_id');
                $table->string('recipient_id');
                $table->string('sender_id')->nullable();
                $table->json('content')->nullable();
                $table->timestamps();

                $table->index('message_id');
                $table->index('recipient_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
        Schema::dropIfExists('message_acknowledgments');
        Schema::dropIfExists('message_queue');
        Schema::dropIfExists('performance_metrics');
        Schema::dropIfExists('wallet_permissions');
        Schema::dropIfExists('group_wallets');
        Schema::dropIfExists('conversation_tools');
        Schema::dropIfExists('agent_sessions');
        Schema::dropIfExists('consensus_votes');
        Schema::dropIfExists('agent_consensus');
        Schema::dropIfExists('collaboration_participants');
        Schema::dropIfExists('agent_collaborations');
        Schema::dropIfExists('agent_group_members');
        Schema::dropIfExists('agent_groups');
        Schema::dropIfExists('agent_compliance');
    }
};
