<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('a2a_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('sender_did', 255)->index();
            $table->string('receiver_did', 255)->index();
            $table->string('state', 20)->default('submitted')->index();
            $table->string('skill_id', 128)->nullable();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('artifacts')->nullable();
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Compound indexes for common query patterns
            $table->index(['sender_did', 'state']);
            $table->index(['receiver_did', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('a2a_tasks');
    }
};
