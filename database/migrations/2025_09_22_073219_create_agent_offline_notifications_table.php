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
        Schema::create('agent_offline_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('agent_did')->index();
            $table->string('type', 50);
            $table->json('data');
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->string('delivery_status', 20)->default('pending'); // pending, delivered, failed
            $table->text('last_error')->nullable();
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['agent_did', 'delivery_status']);
            $table->index(['delivery_status', 'next_retry_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_offline_notifications');
    }
};
