<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ramp_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('provider'); // mock, moonpay, transak
            $table->string('type'); // on, off
            $table->string('fiat_currency', 3);
            $table->decimal('fiat_amount', 16, 2)->nullable();
            $table->string('crypto_currency', 10);
            $table->decimal('crypto_amount', 24, 8)->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('provider_session_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('provider_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ramp_sessions');
    }
};
