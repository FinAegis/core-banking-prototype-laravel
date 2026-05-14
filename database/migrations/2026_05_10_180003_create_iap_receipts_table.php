<?php

/**
 * Plan B Slice 2 — iap_receipts table.
 *
 * Holds the raw JWS/purchaseToken plus normalised money fields per ADR-0004.
 * Pseudonymisation (Backend-Q7 α) nulls personal data here (user_id, raw
 * original_transaction_id, receipt_blob) but populates an HMAC-SHA256 hash so
 * later store webhooks (REFUND / RENEWAL) can match the scrubbed row.
 *
 * One row per receipt event: initial purchase + each renewal. A subscription
 * can have many receipts; the relationship is FK iap_subscription_id → uuid.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.2
 * @see docs/adr/0004-money-on-the-wire.md
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md Q7 (erasure α)
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('iap_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->uuid('iap_subscription_id');
            $table->enum('store', ['apple', 'google']);

            // For Apple, this is the StoreKit 2 originalTransactionId. Nulled
            // on pseudonymisation; the post-erasure lookup falls back to the
            // _hash column. UNIQUE on non-null values only (MySQL skips NULL).
            $table->string('original_transaction_id', 255)->nullable();
            $table->char('original_transaction_id_hash', 64)->nullable();

            $table->char('apple_app_account_token', 36)->nullable();
            $table->string('google_obfuscated_account_id', 255)->nullable();

            $table->string('product_id', 255);
            $table->string('transaction_id', 255)->nullable();

            // Raw JWS string (Apple) or raw purchaseToken (Google). Stored for
            // audit; nulled at erasure.
            $table->text('receipt_blob')->nullable();

            $table->string('tier', 32);

            // ADR-0004 money triple: integer smallest-unit + decimals + currency.
            $table->bigInteger('amount_smallest_unit');
            $table->unsignedTinyInteger('amount_decimals');
            $table->string('amount_currency', 8)->default('EUR');

            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();

            $table->enum('environment', ['sandbox', 'production'])->default('production');

            $table->timestamp('scrubbed_at')->nullable();
            // SMALLINT UNSIGNED is enough — incremented per RENEWAL after
            // erasure; cap 65535 covers >5000 yrs of monthly renewals.
            $table->unsignedSmallInteger('scrubbed_renewal_count')->default(0);

            $table->timestamps();

            $table->unique('original_transaction_id', 'uniq_iap_receipts_original_tx');
            $table->index('user_id', 'idx_iap_receipts_user');
            $table->index('original_transaction_id_hash', 'idx_iap_receipts_scrubbed_hash');
            $table->index('iap_subscription_id', 'idx_iap_receipts_sub');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_receipts');
    }
};
