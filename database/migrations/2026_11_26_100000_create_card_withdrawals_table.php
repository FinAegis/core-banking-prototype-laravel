<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| One row per "Send to wallet": US dollars leave the card, become USDC and go
| to the user's own wallet address, which is copied onto the row at the start
| and never changes. Each money step is recorded here before its call to the
| card provider, with its own idempotency key, because none of those calls is
| known to be safe to repeat. Amounts: dollars leaving the card have 2
| decimals, USDC has 6, cents are US dollars.
|
| card_withdrawal_events is the append-only audit trail of every step and
| user action. It never holds the emailed code, the verification token or a
| provider payload.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('card_withdrawals')) {
            Schema::create('card_withdrawals', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('card_id')->constrained('cards')->cascadeOnDelete();
                $table->string('provider', 32);
                $table->string('network', 32);
                $table->string('asset', 16);
                $table->string('destination_address', 128);
                $table->string('destination_address_uuid', 36);
                $table->decimal('amount', 20, 2);
                $table->boolean('withdraw_all')->default(false);
                $table->boolean('close_card')->default(false);
                $table->json('estimate')->nullable();
                $table->string('status', 24);

                $table->bigInteger('card_balance_before_cents')->nullable();
                $table->bigInteger('unloaded_cents')->nullable();
                $table->bigInteger('unload_fee_cents')->nullable();
                $table->bigInteger('conversion_fee_cents')->nullable();
                $table->decimal('usdc_balance_before', 20, 6)->nullable();
                $table->decimal('usdc_amount', 20, 6)->nullable();
                $table->decimal('withdrawal_fee', 20, 6)->nullable();
                $table->decimal('withdrawal_amount', 20, 6)->nullable();
                $table->decimal('provider_actual_amount', 20, 6)->nullable();
                $table->decimal('received_amount', 20, 6)->nullable();
                $table->string('tx_hash', 100)->nullable();
                $table->boolean('card_closed')->default(false);

                $table->string('provider_unload_id')->nullable();
                $table->string('provider_trade_id')->nullable();
                $table->string('provider_withdrawal_id')->nullable();
                $table->string('provider_transfer_id')->nullable();
                $table->string('provider_status', 64)->nullable();
                $table->text('provider_verification_token')->nullable();

                $table->uuid('unload_key')->nullable();
                $table->uuid('exchange_key')->nullable();
                $table->uuid('withdrawal_key')->nullable();
                $table->uuid('confirm_key')->nullable();
                $table->unsignedTinyInteger('withdrawal_attempts')->default(0);

                $table->timestamp('step_started_at')->nullable();
                $table->timestamp('unload_requested_at')->nullable();
                $table->timestamp('unloaded_at')->nullable();
                $table->timestamp('exchange_requested_at')->nullable();
                $table->timestamp('exchanged_at')->nullable();
                $table->timestamp('withdrawal_requested_at')->nullable();
                $table->timestamp('code_sent_at')->nullable();
                $table->timestamp('code_expires_at')->nullable();
                $table->string('code_sent_to')->nullable();
                $table->unsignedInteger('code_attempts')->default(0);
                $table->integer('code_attempts_left')->nullable();
                $table->unsignedInteger('code_resends')->default(0);
                $table->timestamp('last_resend_at')->nullable();
                $table->timestamp('confirm_requested_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('resumed_at')->nullable();
                $table->timestamp('card_close_attempted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('last_error')->nullable();
                $table->timestamps();

                // One on-chain transfer completes at most one withdrawal.
                $table->unique(['network', 'tx_hash']);
                $table->index(['user_id', 'status']);
                $table->index(['card_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('card_withdrawal_events')) {
            Schema::create('card_withdrawal_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignUuid('withdrawal_id')->constrained('card_withdrawals')->cascadeOnDelete();
                $table->timestamp('at');
                $table->string('actor');
                $table->string('action', 32);
                $table->unsignedSmallInteger('partner_http_status')->nullable();
                $table->string('from_status', 24)->nullable();
                $table->string('to_status', 24)->nullable();
                $table->string('note')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();

                $table->index(['withdrawal_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('card_withdrawal_events');
        Schema::dropIfExists('card_withdrawals');
    }
};
