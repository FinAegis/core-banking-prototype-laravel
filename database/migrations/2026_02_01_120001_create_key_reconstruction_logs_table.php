<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('key_reconstruction_logs', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->uuid('user_uuid')->index();
            $table->string('key_version', 50);
            $table->json('shards_used');
            $table->string('purpose', 50);
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->string('device_id', 100)->nullable();
            $table->boolean('success')->default(false);
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_uuid', 'created_at']);
            $table->index(['user_uuid', 'success']);

            // Foreign key
            $table->foreign('user_uuid')
                ->references('uuid')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_reconstruction_logs');
    }
};
