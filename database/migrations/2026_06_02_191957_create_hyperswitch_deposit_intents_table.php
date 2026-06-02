<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lookup table mapping a HyperSwitch payment back to the deposit it funds.
// Lives on the central/default connection (like `processed_webhook_events`),
// NOT the tenant connection: the webhook handler reads it inside its
// idempotency transaction before doing the tenant-connection balance credit.
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('hyperswitch_deposit_intents', function (Blueprint $table) {
            $table->id();
            $table->string('hyperswitch_payment_id')->unique();
            $table->uuid('deposit_uuid')->index();
            $table->uuid('account_uuid');
            $table->uuid('user_uuid')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('status', 16)->default('pending'); // pending | completed | failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hyperswitch_deposit_intents');
    }
};
