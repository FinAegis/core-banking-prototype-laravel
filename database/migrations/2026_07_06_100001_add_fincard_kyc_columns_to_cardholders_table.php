<?php

/**
 * FinCard KYC — cardholder approval tracking.
 *
 * FinCard's cardholder KYC is a two-stage review (admin → channel) reported via
 * webhooks. `kyc_status` (existing: pending/in_review/verified/rejected) records
 * the outcome; `kyc_stage` records which review stage is in flight, and
 * `kyc_rejection_reason` captures the reason on a reject. Richer KYC attributes
 * (occupation, financial fields, ID metadata) live in the existing encrypted
 * `verification_data` blob — no new plaintext PII columns.
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md §4, §7
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('cardholders', function (Blueprint $table): void {
            $table->string('kyc_stage')->nullable()->after('kyc_status'); // admin, channel
            $table->text('kyc_rejection_reason')->nullable()->after('kyc_stage');
        });
    }

    public function down(): void
    {
        Schema::table('cardholders', function (Blueprint $table): void {
            $table->dropColumn(['kyc_stage', 'kyc_rejection_reason']);
        });
    }
};
