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
        Schema::create('regulatory_endpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('regulator');
            $table->string('jurisdiction', 10);
            $table->string('endpoint_type')->default('filing');
            $table->string('base_url');
            $table->string('api_version')->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->text('api_secret_encrypted')->nullable();
            $table->json('headers')->nullable();
            $table->json('auth_config')->nullable();
            $table->boolean('is_sandbox')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->timestamp('last_health_check')->nullable();
            $table->string('health_status')->default('unknown');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['jurisdiction', 'regulator']);
            $table->index(['is_active', 'is_sandbox']);
            $table->index('health_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regulatory_endpoints');
    }
};
