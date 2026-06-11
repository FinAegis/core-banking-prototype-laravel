<?php

/**
 * Operator reconciliation fields for HyperSwitch deposit intents.
 *
 * `completion_failed` intents (payment succeeded at HyperSwitch but the
 * tenant-side credit/completion threw) are an explicit operator worklist.
 * The admin panel's "Mark reconciled" action records WHO settled the case,
 * WHEN, and HOW (free-text note) and moves the row to `reconciled` so it
 * drops out of the default `completion_failed` filter.
 *
 * Also widens `status` from 16 to 32 chars: the original column was sized
 * before `completion_failed` (17 chars) existed — under strict-mode MySQL
 * that value would be rejected as too long, so the reconciliation state
 * machine could never reach its operator-facing terminal status.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('hyperswitch_deposit_intents', function (Blueprint $table): void {
            $table->string('status', 32)->default('pending')->change();
            $table->text('reconciliation_note')->nullable()->after('status');
            $table->string('reconciled_by')->nullable()->after('reconciliation_note');
            $table->timestamp('reconciled_at')->nullable()->after('reconciled_by');
        });

        Schema::table('hyperswitch_deposit_intents', function (Blueprint $table): void {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('hyperswitch_deposit_intents', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['reconciliation_note', 'reconciled_by', 'reconciled_at']);
            $table->string('status', 16)->default('pending')->change();
        });
    }
};
