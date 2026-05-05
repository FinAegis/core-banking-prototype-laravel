<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound wallet-to-wallet transfer records.
 *
 * Distinct from `payment_intents` (which is the merchant-payment flow with
 * a non-nullable merchant_id). Wallet sends have no merchant.
 *
 * Status flow:
 *   pending    → created in DB, signing in progress
 *   submitted  → broadcast to RPC/bundler, tx hash known, awaiting confirmation
 *   confirmed  → on-chain confirmation observed (via webhook or polling)
 *   failed     → simulation/RPC/bundler rejected; error_code/message populated
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('wallet_send_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('public_id', 64)->unique()->comment('Public-facing intent ID (pi_send_...)');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('network', 20);
            $table->string('asset', 10);
            // Decimal major units as a string. Always normalize via bcmath; never cast to float.
            $table->decimal('amount', 30, 8);
            $table->string('sender_address', 128);
            $table->string('recipient_address', 128);
            $table->string('status', 20)->default('pending');
            $table->string('tx_hash', 128)->nullable()->index();
            $table->string('user_op_hash', 128)->nullable()->index();
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->string('quote_id', 64)->nullable();
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['network', 'tx_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_send_records');
    }
};
