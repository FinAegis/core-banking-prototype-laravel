<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sponsored-send cost tracking.
 *
 * Gas-sponsored sends (Pimlico paymaster on EVM, fee-payer account on
 * Solana) spend platform money on every transaction, but until now no
 * per-send cost was ever recorded — monthly spend and cost-per-user were
 * invisible. These columns capture the actual fee at confirmation time.
 *
 * `sponsored_fee_raw` stores the native unit as a decimal string —
 * lamports for Solana, wei for EVM. 78 chars covers a full 256-bit value.
 * Always handle via bcmath; never cast to float/int.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('wallet_send_records', function (Blueprint $table): void {
            $table->string('sponsored_fee_raw', 78)->nullable()->after('quote_id')
                ->comment('Sponsored network fee in native units (lamports/wei), decimal string');
            $table->string('sponsored_fee_asset', 16)->nullable()->after('sponsored_fee_raw')
                ->comment('Asset the sponsored fee was paid in (SOL, MATIC, ETH-BASE, ETH-ARB, ETH)');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_send_records', function (Blueprint $table): void {
            $table->dropColumn(['sponsored_fee_raw', 'sponsored_fee_asset']);
        });
    }
};
