<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| One row per "Add to card" (source app) and per deposit that arrived without
| one (source external_deposit). Amounts are USDC decimals (6 places), cents
| are USD. Each step of the money pipeline is recorded here before its Lepo
| call, because Lepo's conversion and card-load calls take no idempotency key.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('card_topups')) {
            return;
        }

        Schema::create('card_topups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('card_id')->constrained('cards')->cascadeOnDelete();
            $table->foreignUuid('funding_account_id')->nullable()->constrained('card_funding_accounts')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('source', 20)->default('app'); // app, external_deposit
            $table->string('asset', 16);
            $table->string('network', 32);
            $table->decimal('amount', 20, 6);
            $table->decimal('received_amount', 20, 6)->nullable();
            $table->string('deposit_address', 128);
            $table->string('tx_hash', 100)->nullable();
            $table->string('status', 24);
            $table->bigInteger('credited_cents')->nullable();
            $table->bigInteger('fee_cents')->nullable();
            $table->bigInteger('issuance_fee_cents')->nullable();
            $table->string('provider_deposit_id')->nullable();
            $table->string('provider_conversion_id')->nullable();
            $table->string('provider_load_id')->nullable();
            $table->timestamp('step_started_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // A bundled (ERC-4337) transaction can carry several users' sends,
            // so a hash is unique per user, not globally.
            $table->unique(['user_id', 'network', 'tx_hash']);
            $table->index(['user_id', 'status']);
            // One deposit funds one top-up, enforced by the database.
            $table->unique(['provider', 'provider_deposit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_topups');
    }
};
