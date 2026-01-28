<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for account balances and ledger entries.
 *
 * This migration runs in tenant database context, creating the account_balances
 * and ledger tables for detailed balance tracking and accounting.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_balances', function (Blueprint $table) {
            $table->id();
            $table->uuid('account_uuid')->index();
            $table->string('currency', 10);
            $table->decimal('balance', 20, 8)->default(0);
            $table->decimal('available_balance', 20, 8)->default(0);
            $table->decimal('pending_balance', 20, 8)->default(0);
            $table->decimal('reserved_balance', 20, 8)->default(0);
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamps();

            $table->unique(['account_uuid', 'currency']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('account_uuid')->index();
            $table->string('entry_type');
            $table->decimal('debit', 20, 8)->default(0);
            $table->decimal('credit', 20, 8)->default(0);
            $table->decimal('balance', 20, 8);
            $table->string('currency', 10);
            $table->string('reference')->nullable()->index();
            $table->string('description')->nullable();
            $table->uuid('related_transaction_uuid')->nullable()->index();
            $table->uuid('related_transfer_uuid')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_uuid', 'created_at']);
            $table->index(['entry_type', 'created_at']);
        });

        Schema::create('ledger_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_snapshots');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('account_balances');
    }
};
