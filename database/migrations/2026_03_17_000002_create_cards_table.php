<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('cardholder_id')->constrained()->cascadeOnDelete(); // cardholders uses uuid PK
            $table->string('issuer_card_token')->unique();
            $table->string('issuer'); // rain, marqeta, demo
            $table->string('last4', 4);
            $table->string('network'); // visa, mastercard
            $table->string('status')->default('pending'); // pending, active, frozen, cancelled, expired
            $table->string('currency', 3)->default('USD');
            $table->string('label')->nullable();
            $table->string('funding_source')->nullable();
            $table->unsignedInteger('spend_limit_cents')->nullable();
            $table->string('spend_limit_interval')->nullable(); // daily, weekly, monthly
            $table->text('metadata')->nullable(); // encrypted
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['cardholder_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
