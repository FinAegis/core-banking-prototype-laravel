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
        // Products table
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description');
            $table->string('category');
            $table->string('type'); // subscription, one_time, service, etc.
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
            $table->json('features')->nullable();
            $table->json('prices')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('popularity_score')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('type');
            $table->index('status');
            $table->index('popularity_score');
        });

        // User products table (products assigned to users)
        Schema::create('user_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->uuid('product_id');
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->timestamp('subscribed_at');
            $table->timestamp('expires_at')->nullable();
            $table->json('configuration')->nullable();
            $table->json('usage_data')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->unique(['user_id', 'product_id']);
            $table->index(['status', 'expires_at']);
        });

        // Product events table for event sourcing
        Schema::create('product_events', function (Blueprint $table) {
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
        Schema::dropIfExists('user_products');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_events');
    }
};
