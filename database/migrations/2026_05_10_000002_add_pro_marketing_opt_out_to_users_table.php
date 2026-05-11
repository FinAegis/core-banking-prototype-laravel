<?php

/**
 * Plan B Slice 4 — add users.pro_marketing_opt_out column.
 *
 * PECR/ePrivacy compliance flag for pro_trial_reminder_d1 cue dispatch.
 * The EnqueueProTrialReminderD1 job checks this flag at fire time and skips
 * if true. The POST /api/v1/me/marketing-opt-out endpoint sets this flag.
 *
 * GDPR note: when a user is pseudonymised, this column should be reset to false.
 * See GdprController.php — add pro_marketing_opt_out to erasure walk if needed.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.8
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('pro_marketing_opt_out')
                ->default(false)
                ->after('sponsored_tx_limit');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('pro_marketing_opt_out');
        });
    }
};
