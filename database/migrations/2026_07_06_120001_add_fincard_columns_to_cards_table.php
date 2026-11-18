<?php

/**
 * FinCard card lifecycle — prefunded stored-value columns.
 *
 * FinCard cards carry their own spendable balance (funds moved from the user's
 * FinCard account on open / top-up), tracked here as an integer-minor-unit
 * cache (`balance_cents`; FinCard holds the authoritative figure).
 * `fincard_account_id` links the card to the account it draws from;
 * `merchant_order_no` is the client idempotency key used on open/reconcile.
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md §8
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table): void {
            $table->bigInteger('balance_cents')->nullable()->after('currency');
            $table->string('fincard_account_id')->nullable()->after('balance_cents');
            $table->string('merchant_order_no')->nullable()->after('fincard_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table): void {
            $table->dropColumn(['balance_cents', 'fincard_account_id', 'merchant_order_no']);
        });
    }
};
