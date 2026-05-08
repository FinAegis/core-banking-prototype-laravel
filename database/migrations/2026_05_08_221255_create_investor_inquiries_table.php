<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public investor lead-capture submissions from /invest.
 *
 * Append-only — these rows are never updated, only inserted (form submit) and
 * deleted (24-month retention sweep). Hence no `updated_at` column.
 *
 * IP is stored as sha256(ip + app.key) for abuse triage without keeping the
 * raw address. user_agent is kept verbatim (capped at 500 chars) for spam
 * triage; PII risk is acceptable for an opt-in marketing form.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('investor_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('linkedin_url', 500);
            $table->string('investing_as', 32);              // angel | vc | family_office | other
            $table->string('path_of_interest', 32);          // licensed | non_custodial | both
            $table->string('check_size_range', 32);          // under_25k | 25k_100k | 100k_500k | 500k_plus
            $table->text('questions')->nullable();           // 500-char cap enforced in form request
            $table->char('ip_hash', 64);                     // sha256(ip + app.key) — never store raw IP
            $table->string('user_agent', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'idx_inquiries_created');
            $table->index('path_of_interest', 'idx_inquiries_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_inquiries');
    }
};
