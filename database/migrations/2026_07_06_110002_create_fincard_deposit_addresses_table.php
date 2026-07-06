<?php

/**
 * FinCard crypto deposit addresses — one per user per coin.
 *
 * A user funds their FinCard account by sending stablecoin (e.g. USDT_TRC20)
 * from their own Zelta wallet to a FinCard-provided deposit address; FinCard
 * converts to USD and credits the account. Addresses are stable per (user,
 * coin), so we persist them. Amounts are integer minor units.
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md §6.1
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('fincard_deposit_addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fincard_account_id');
            $table->string('coin_key');            // e.g. USDT_TRC20, USDT_BEP20
            $table->string('chain')->nullable();   // e.g. TRON, BSC
            $table->string('address')->index();
            $table->bigInteger('min_deposit_cents')->nullable();
            $table->unsignedInteger('confirmations')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'coin_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fincard_deposit_addresses');
    }
};
