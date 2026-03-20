<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('card_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->unique();
            $table->string('merchant_name');
            $table->string('merchant_category')->default('');
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending, settled, declined, reversed
            $table->timestamp('transacted_at')->nullable();
            $table->timestamps();

            $table->index(['card_id', 'status']);
            $table->index(['user_id', 'transacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_transactions');
    }
};
