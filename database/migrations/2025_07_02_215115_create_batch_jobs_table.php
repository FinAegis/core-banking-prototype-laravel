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
        Schema::create('batch_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('user_uuid');
            $table->string('name');
            $table->string('type'); // transfer, payment, reconciliation, etc.
            $table->string('status')->default('pending'); // pending, processing, completed, failed, cancelled
            $table->integer('total_items')->default(0);
            $table->integer('processed_items')->default(0);
            $table->integer('failed_items')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('user_uuid');
            $table->index('type');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_jobs');
    }
};
