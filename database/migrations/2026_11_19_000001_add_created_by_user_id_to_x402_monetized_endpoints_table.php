<?php

/**
 * x402 monetized endpoint prices belong to platform administrators
 * (GHSA-385w).
 *
 * Before the advisory fix any signed-in user could write a price row, and
 * prices apply platform-wide. Rows now record the administrator who created
 * them (created_by_user_id). Every existing row has no creator, so it cannot
 * be told apart from a row a regular user wrote: the upgrade deactivates
 * them. A route without an active price is not monetized and keeps its
 * normal authentication. Administrators review the deactivated rows (GET
 * /api/v1/x402/endpoints) and re-activate the right ones; an
 * administrator's update records them as the row's creator.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('x402_monetized_endpoints', 'created_by_user_id')) {
            Schema::table('x402_monetized_endpoints', function (Blueprint $table): void {
                $table->foreignId('created_by_user_id')->nullable()->after('team_id')->constrained('users')->nullOnDelete();
            });
        }

        $deactivated = DB::table('x402_monetized_endpoints')
            ->whereNull('created_by_user_id')
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);

        if ($deactivated > 0) {
            Log::warning('x402: deactivated monetized endpoint prices without a creator; administrators must review and re-activate them', [
                'count' => $deactivated,
            ]);
        }
    }

    public function down(): void
    {
        // Deactivated prices stay off: re-activating them is a review decision.
        Schema::table('x402_monetized_endpoints', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
