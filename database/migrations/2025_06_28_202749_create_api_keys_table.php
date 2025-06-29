<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->onDelete('cascade');
            $table->string('name');
            $table->string('key_prefix', 8); // First 8 chars of key for identification
            $table->string('key_hash'); // Hashed API key
            $table->text('description')->nullable();
            $table->json('permissions')->nullable(); // Granular permissions
            $table->json('rate_limits')->nullable(); // Custom rate limits
            $table->json('allowed_ips')->nullable(); // IP whitelist
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_used_at')->nullable();
            $table->string('last_used_ip')->nullable();
            $table->unsignedBigInteger('request_count')->default(0);
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_uuid', 'is_active']);
            $table->index('key_prefix');
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('api_keys');
    }
};