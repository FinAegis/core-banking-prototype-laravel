<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('api_key_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->onDelete('cascade');
            $table->string('method', 10);
            $table->string('path');
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->integer('response_code')->nullable();
            $table->integer('response_time')->nullable(); // in milliseconds
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('created_at');
            
            $table->index(['api_key_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('api_key_logs');
    }
};