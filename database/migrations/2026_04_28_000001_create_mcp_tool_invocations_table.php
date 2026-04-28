<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('mcp_tool_invocations', function (Blueprint $table) {
            $table->id();
            $table->string('token_id', 100)->index();
            $table->string('client_id', 100)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('tool_name', 100);
            $table->string('args_hash', 64);
            $table->string('idempotency_key', 64)->nullable();
            $table->enum('result_status', ['success', 'error', 'rate_limited', 'spending_limit', 'idempotency_replay']);
            $table->string('error_code', 64)->nullable();
            $table->unsignedBigInteger('settlement_amount_minor')->nullable();
            $table->string('settlement_currency', 8)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['token_id', 'created_at']);
            $table->index(['idempotency_key', 'token_id', 'tool_name']);
            $table->index(['tool_name', 'result_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tool_invocations');
    }
};
