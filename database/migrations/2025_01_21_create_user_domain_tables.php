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
        // User profiles table
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('email');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active');
            $table->boolean('is_verified')->default(false);
            $table->json('preferences')->nullable();
            $table->json('notification_preferences')->nullable();
            $table->json('privacy_settings')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('is_verified');
            $table->index('country');
            $table->index('last_activity_at');
        });

        // User activities table
        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('activity');
            $table->json('context')->nullable();
            $table->timestamp('tracked_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('activity');
            $table->index('tracked_at');
            $table->index(['user_id', 'tracked_at']);
        });

        // User events table for event sourcing
        Schema::create('user_events', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_uuid');
            $table->unsignedInteger('aggregate_version');
            $table->integer('event_version')->default(1);
            $table->string('event_class');
            $table->json('event_properties');
            $table->json('meta_data');
            $table->timestamp('created_at');

            $table->unique(['aggregate_uuid', 'aggregate_version']);
            $table->index('aggregate_uuid');
            $table->index('event_class');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activities');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('user_events');
    }
};
