<?php

/**
 * FinCard funding accounts — one per Zelta user.
 *
 * FinCard is a prefunded card program: each user gets a FinCard "account" (a USD
 * ledger) that cards spend from. We mirror its balance here (`balance_cents`,
 * integer minor units) from wallet-deposit + account webhooks and periodic sync;
 * `fincard_account_id` is FinCard's identifier for the account. Runs on the
 * default connection (the model reads/writes via the tenant connection at
 * runtime, matching cardholders/cards).
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md §4, §6
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('fincard_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fincard_account_id')->unique();
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('balance_cents')->default(0);
            $table->string('status')->default('active'); // active, frozen, closed
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fincard_accounts');
    }
};
