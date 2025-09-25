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
        // Create agent protocol events table
        Schema::create('agent_protocol_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version')->default(1);
            $table->unsignedInteger('event_version')->default(1);
            $table->string('event_class');
            $table->json('event_properties');
            $table->json('meta_data')->nullable();
            $table->timestamp('created_at', 6);

            $table->index(['aggregate_uuid', 'aggregate_version']);
            $table->index('event_class');
            $table->index('created_at');
        });

        // Create agent protocol snapshots table
        Schema::create('agent_protocol_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamp('created_at', 6);

            $table->index(['aggregate_uuid', 'aggregate_version']);
        });

        // Create agent reputations table if it doesn't exist
        if (! Schema::hasTable('agent_reputations')) {
            Schema::create('agent_reputations', function (Blueprint $table) {
                $table->id();
                $table->string('reputation_id')->unique();
                $table->string('agent_id')->index();
                $table->decimal('score', 5, 2)->default(50.00);
                $table->string('trust_level')->default('medium');
                $table->unsignedInteger('total_transactions')->default(0);
                $table->unsignedInteger('successful_transactions')->default(0);
                $table->unsignedInteger('failed_transactions')->default(0);
                $table->unsignedInteger('disputed_transactions')->default(0);
                $table->decimal('success_rate', 5, 2)->default(0.00);
                $table->timestamp('last_decay_at')->nullable();
                $table->timestamps();

                $table->index('score');
                $table->index('trust_level');
            });
        }

        // Create agent compliance monitoring table
        if (! Schema::hasTable('agent_compliance_monitoring')) {
            Schema::create('agent_compliance_monitoring', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('compliance_id');
                $table->string('transaction_id');
                $table->integer('risk_score');
                $table->json('flags');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('compliance_id');
                $table->index('transaction_id');
                $table->index('risk_score');
            });
        }

        // Create agent sessions table for authentication
        if (! Schema::hasTable('agent_sessions')) {
            Schema::create('agent_sessions', function (Blueprint $table) {
                $table->uuid('session_id')->primary();
                $table->string('agent_id')->index();
                $table->string('ai_agent_id')->nullable()->index();
                $table->string('token', 64);
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->index('token');
                $table->index('expires_at');
            });
        }

        // Create agent capabilities table
        if (! Schema::hasTable('agent_capabilities')) {
            Schema::create('agent_capabilities', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('agent_id')->index();
                $table->string('ai_agent_id')->nullable();
                $table->string('capability');
                $table->json('settings');
                $table->timestamp('enabled_at');
                $table->timestamps();

                $table->index(['agent_id', 'capability']);
            });
        }

        // Create conversation tools table
        if (! Schema::hasTable('conversation_tools')) {
            Schema::create('conversation_tools', function (Blueprint $table) {
                $table->string('conversation_id')->primary();
                $table->string('agent_id')->index();
                $table->json('tools');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_tools');
        Schema::dropIfExists('agent_capabilities');
        Schema::dropIfExists('agent_sessions');
        Schema::dropIfExists('agent_compliance_monitoring');
        Schema::dropIfExists('agent_reputations');
        Schema::dropIfExists('agent_protocol_snapshots');
        Schema::dropIfExists('agent_protocol_events');
    }
};
