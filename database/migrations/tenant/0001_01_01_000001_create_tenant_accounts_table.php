<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for accounts table.
 *
 * This migration runs in tenant database context, creating the accounts table
 * which stores all tenant-specific financial account data.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid')->index();
            $table->string('name');
            $table->string('type')->default('standard');
            $table->string('currency', 10)->default('USD');
            $table->decimal('balance', 20, 8)->default(0);
            $table->decimal('available_balance', 20, 8)->default(0);
            $table->decimal('reserved_balance', 20, 8)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_frozen')->default(false);
            $table->string('frozen_reason')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_uuid', 'type']);
            $table->index(['currency', 'is_active']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
