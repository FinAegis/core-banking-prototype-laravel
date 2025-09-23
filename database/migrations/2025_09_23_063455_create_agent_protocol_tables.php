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
        // Agents table
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->unique();
            $table->string('did')->unique();
            $table->string('name');
            $table->string('type')->default('standard');
            $table->string('status')->default('pending');
            $table->string('network_id')->nullable()->index();
            $table->string('organization')->nullable()->index();
            $table->json('endpoints')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('metadata')->nullable();
            $table->decimal('relay_score', 5, 2)->default(0.00);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'type']);
            $table->index(['organization', 'status']);
        });

        // Agent capabilities table
        Schema::create('agent_capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->index();
            $table->string('capability_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('status')->default('draft');
            $table->json('endpoints')->nullable();
            $table->json('parameters')->nullable();
            $table->json('required_permissions')->nullable();
            $table->json('supported_protocols')->nullable();
            $table->string('category')->nullable()->index();
            $table->integer('priority')->default(50);
            $table->boolean('is_public')->default(true);
            $table->json('rate_limits')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['category', 'status']);
            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
        });

        // Agent connections table
        Schema::create('agent_connections', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->index();
            $table->string('connected_agent_id')->index();
            $table->string('connection_type')->default('direct');
            $table->string('status')->default('pending');
            $table->integer('latency_ms')->default(0);
            $table->decimal('bandwidth_mbps', 10, 2)->default(0.00);
            $table->decimal('reliability_score', 5, 2)->default(0.00);
            $table->timestamp('last_contact_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'connected_agent_id']);
            $table->index(['status', 'connection_type']);
            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
        });

        // Agent messages table
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('from_agent_id')->index();
            $table->string('to_agent_id')->index();
            $table->string('message_type')->default('direct');
            $table->integer('priority')->default(50);
            $table->string('status')->default('pending')->index();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->string('correlation_id')->nullable()->index();
            $table->string('reply_to')->nullable();
            $table->boolean('requires_acknowledgment')->default(false);
            $table->integer('acknowledgment_timeout')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['from_agent_id', 'status']);
            $table->index(['to_agent_id', 'status']);
            $table->index('requires_acknowledgment');
            $table->foreign('from_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->foreign('to_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
        });

        // Agent activities table (for audit trail)
        Schema::create('agent_activities', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->index();
            $table->string('activity_type')->index();
            $table->json('data')->nullable();
            $table->timestamp('created_at');

            $table->index(['agent_id', 'activity_type']);
            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
        });

        // A2A message events table (for event sourcing)
        Schema::create('a2a_message_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version')->default(1);
            $table->integer('event_version')->default(1);
            $table->string('event_class');
            $table->json('event_properties');
            $table->json('meta_data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['aggregate_uuid', 'aggregate_version'], 'a2a_msg_events_uuid_ver_unique');
            $table->index('event_class');
            $table->index('created_at');
        });

        // A2A message snapshots table
        Schema::create('a2a_message_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['aggregate_uuid', 'aggregate_version'], 'a2a_msg_snap_uuid_ver_unique');
        });

        // Agent capability events table (for event sourcing)
        Schema::create('agent_capability_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version')->default(1);
            $table->integer('event_version')->default(1);
            $table->string('event_class');
            $table->json('event_properties');
            $table->json('meta_data')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['aggregate_uuid', 'aggregate_version'], 'agent_cap_events_uuid_ver_unique');
            $table->index('event_class');
            $table->index('created_at');
        });

        // Agent capability snapshots table
        Schema::create('agent_capability_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['aggregate_uuid', 'aggregate_version'], 'agent_cap_snap_uuid_ver_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_capability_snapshots');
        Schema::dropIfExists('agent_capability_events');
        Schema::dropIfExists('a2a_message_snapshots');
        Schema::dropIfExists('a2a_message_events');
        Schema::dropIfExists('agent_activities');
        Schema::dropIfExists('agent_messages');
        Schema::dropIfExists('agent_connections');
        Schema::dropIfExists('agent_capabilities');
        Schema::dropIfExists('agents');
    }
};
