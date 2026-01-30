<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add biometric security tracking for per-device rate limiting.
 *
 * This migration adds:
 * 1. A new table for tracking biometric authentication failures
 * 2. Columns to the mobile_devices table for biometric blocking
 *
 * Security rationale: Per-device rate limiting prevents brute force attacks
 * on biometric authentication while not affecting other users.
 */
return new class () extends Migration {
    public function up(): void
    {
        // Create biometric failures tracking table
        Schema::create('biometric_failures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mobile_device_id');
            $table->string('ip_address', 45)->nullable();
            $table->string('failure_reason', 100);
            $table->timestamps();

            $table->foreign('mobile_device_id')
                ->references('id')
                ->on('mobile_devices')
                ->onDelete('cascade');

            // Indexes for efficient querying
            $table->index(['mobile_device_id', 'created_at'], 'biometric_failures_device_time');
            $table->index('created_at', 'biometric_failures_cleanup');
        });

        // Add biometric security columns to mobile_devices
        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->unsignedSmallInteger('biometric_failure_count')->default(0)->after('biometric_enabled_at');
            $table->timestamp('biometric_blocked_until')->nullable()->after('biometric_failure_count');

            // Index for checking blocked devices
            $table->index('biometric_blocked_until', 'mobile_devices_biometric_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->dropIndex('mobile_devices_biometric_blocked');
            $table->dropColumn(['biometric_failure_count', 'biometric_blocked_until']);
        });

        Schema::dropIfExists('biometric_failures');
    }
};
