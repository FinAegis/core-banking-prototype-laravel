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
        Schema::create('mobile_notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('mobile_device_id')->nullable();
            $table->string('notification_type', 50);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->timestamps();

            // Unique constraint: one preference per user/device/type combination
            $table->unique(
                ['user_id', 'mobile_device_id', 'notification_type'],
                'uniq_user_device_type'
            );

            // Index for querying user preferences
            $table->index(['user_id', 'notification_type']);

            // Foreign key to mobile_devices (nullable for global preferences)
            $table->foreign('mobile_device_id')
                ->references('id')
                ->on('mobile_devices')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_notification_preferences');
    }
};
