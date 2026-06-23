<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add the sync_status / sync_error columns that BalanceSynchronizationService
     * reads and writes. They were referenced in code (success/failed status plus
     * the failure-rate stats query) but never created by a migration, so the
     * scheduled `custodian:sync-balances` command threw
     * SQLSTATE[42S22] "Unknown column 'sync_status'" on MySQL every run.
     */
    public function up(): void
    {
        Schema::table('custodian_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('custodian_accounts', 'sync_status')) {
                $table->string('sync_status')->nullable()->after('last_synced_at');
                $table->index('sync_status');
            }

            if (! Schema::hasColumn('custodian_accounts', 'sync_error')) {
                $table->text('sync_error')->nullable()->after('sync_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custodian_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('custodian_accounts', 'sync_status')) {
                $table->dropIndex(['sync_status']);
                $table->dropColumn('sync_status');
            }

            if (Schema::hasColumn('custodian_accounts', 'sync_error')) {
                $table->dropColumn('sync_error');
            }
        });
    }
};
