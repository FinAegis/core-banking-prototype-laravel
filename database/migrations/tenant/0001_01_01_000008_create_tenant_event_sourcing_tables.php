<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for event sourcing infrastructure.
 *
 * This migration creates the core event sourcing tables for tenant databases,
 * including the generic stored_events and snapshots tables, plus domain-specific
 * event stores for Account, Treasury, and Exchange domains.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Generic stored events table for domains without custom event stores
        Schema::create('stored_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->nullable()->index();
            $table->unsignedInteger('aggregate_version')->nullable();
            $table->unsignedInteger('event_version')->default(1);
            $table->string('event_class');
            $table->json('event_properties');
            $table->json('meta_data')->nullable();
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
            $table->index(['event_class', 'created_at']);
        });

        // Generic snapshots table
        Schema::create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });

        // Account domain event sourcing
        Schema::create('account_events', function (Blueprint $table) {
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

        Schema::create('account_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });

        // Treasury domain event sourcing
        Schema::create('treasury_events', function (Blueprint $table) {
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

        Schema::create('treasury_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });

        // Exchange domain event sourcing
        Schema::create('exchange_events', function (Blueprint $table) {
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

        Schema::create('exchange_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });

        // Stablecoin domain event sourcing
        Schema::create('stablecoin_events', function (Blueprint $table) {
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

        Schema::create('stablecoin_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });

        // Wallet domain event sourcing
        Schema::create('wallet_events', function (Blueprint $table) {
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

        Schema::create('wallet_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });

        // Payment domain event sourcing
        Schema::create('payment_events', function (Blueprint $table) {
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

        Schema::create('payment_snapshots', function (Blueprint $table) {
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
        Schema::dropIfExists('payment_snapshots');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('wallet_snapshots');
        Schema::dropIfExists('wallet_events');
        Schema::dropIfExists('stablecoin_snapshots');
        Schema::dropIfExists('stablecoin_events');
        Schema::dropIfExists('exchange_snapshots');
        Schema::dropIfExists('exchange_events');
        Schema::dropIfExists('treasury_snapshots');
        Schema::dropIfExists('treasury_events');
        Schema::dropIfExists('account_snapshots');
        Schema::dropIfExists('account_events');
        Schema::dropIfExists('snapshots');
        Schema::dropIfExists('stored_events');
    }
};
