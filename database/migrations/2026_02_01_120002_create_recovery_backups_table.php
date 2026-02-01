<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('recovery_backups', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->uuid('user_uuid')->index();
            $table->text('encrypted_backup');
            $table->string('encryption_method', 50);
            $table->string('key_version', 50);
            $table->string('backup_hash', 64);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_uuid', 'is_verified']);
            $table->index(['user_uuid', 'key_version']);

            // Foreign key
            $table->foreign('user_uuid')
                ->references('uuid')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_backups');
    }
};
