<?php

declare(strict_types=1);

use App\Domain\KeyManagement\Enums\ShardStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('key_shards', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->uuid('user_uuid')->index();
            $table->string('shard_type', 20);
            $table->unsignedTinyInteger('shard_index');
            $table->text('encrypted_data');
            $table->string('encrypted_for', 50);
            $table->string('key_version', 50);
            $table->string('status', 20)->default(ShardStatus::ACTIVE->value);
            $table->string('public_key_hash', 64)->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_uuid', 'shard_type', 'status']);
            $table->index(['user_uuid', 'key_version']);
            $table->unique(['user_uuid', 'shard_type', 'key_version', 'shard_index'], 'unique_shard');

            // Foreign key
            $table->foreign('user_uuid')
                ->references('uuid')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_shards');
    }
};
