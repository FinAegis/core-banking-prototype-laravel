<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('websocket_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent_id', 128)->nullable();
            $table->string('channel', 255);
            $table->string('protocol', 10)->comment('x402 or mpp');
            $table->uuid('payment_id')->nullable();
            $table->string('amount', 78)->nullable()->comment('Atomic units');
            $table->string('network', 64)->nullable()->comment('CAIP-2 identifier');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['channel', 'expires_at']);
            $table->index(['user_id', 'channel']);
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websocket_subscriptions');
    }
};
