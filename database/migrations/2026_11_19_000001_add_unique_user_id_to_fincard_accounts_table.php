<?php

/**
 * One FinCard funding account per user (GHSA-385w).
 *
 * FinCardAccountService provisions the account under a per-user lock, and
 * this index makes a second row for the same user impossible. The migration
 * refuses to run while a user already has more than one row: which of them
 * holds the user's funds has to be decided by hand (compare with FinCard's
 * balances, keep that row, close the others there and delete them here).
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $duplicates = DB::table('fincard_accounts')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Users with more than one fincard_accounts row (user ids: %s). Keep the row that holds the user\'s FinCard funds, remove the others, then run the migration again.',
                $duplicates->implode(', '),
            ));
        }

        Schema::table('fincard_accounts', function (Blueprint $table): void {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('fincard_accounts', function (Blueprint $table): void {
            $table->dropUnique(['user_id']);
        });
    }
};
