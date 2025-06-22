<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            // Add user_id column if it doesn't exist
            if (!Schema::hasColumn('webhooks', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
                $table->index('user_id');
            }
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            // Rename webhook_uuid to webhook_id if needed
            if (Schema::hasColumn('webhook_deliveries', 'webhook_uuid') && !Schema::hasColumn('webhook_deliveries', 'webhook_id')) {
                $table->renameColumn('webhook_uuid', 'webhook_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            if (Schema::hasColumn('webhooks', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('webhook_deliveries', 'webhook_id') && !Schema::hasColumn('webhook_deliveries', 'webhook_uuid')) {
                $table->renameColumn('webhook_id', 'webhook_uuid');
            }
        });
    }
};