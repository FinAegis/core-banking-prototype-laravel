<?php

/**
 * Plan B Slice 4 — add users.lifetime_spend_cents + users.kyc_completed_at columns.
 *
 * Required by DispatchKycRequiredCues aggregate-condition cron (Backend-Q8).
 *
 * lifetime_spend_cents:
 *   Maintained by ProjectRevenueOutbox worker after each revenue_events write.
 *   AMLD5 threshold check: >= 100_000 cents (€1,000).
 *   Using a dedicated column rather than SUM(revenue_events) enables efficient
 *   composite index scan at 100k+ MAU scale.
 *
 * kyc_completed_at:
 *   Set when users.kyc_level transitions to 'full'. Added alongside the
 *   existing kyc_level enum column (not replacing it).
 *   Enables NULL check in DispatchKycRequiredCues without joining KYC tables.
 *
 * Composite index idx_users_kyc_spend on (lifetime_spend_cents, kyc_completed_at)
 * supports the LEFT JOIN candidate query in DispatchKycRequiredCues.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.6a
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('lifetime_spend_cents')
                ->default(0)
                ->after('pro_marketing_opt_out');

            $table->timestamp('kyc_completed_at')
                ->nullable()
                ->after('lifetime_spend_cents');

            // Composite index for the DispatchKycRequiredCues LEFT JOIN query:
            //   WHERE lifetime_spend_cents >= 100000 AND kyc_completed_at IS NULL
            $table->index(
                ['lifetime_spend_cents', 'kyc_completed_at'],
                'idx_users_kyc_spend',
            );
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('idx_users_kyc_spend');
            $table->dropColumn(['lifetime_spend_cents', 'kyc_completed_at']);
        });
    }
};
