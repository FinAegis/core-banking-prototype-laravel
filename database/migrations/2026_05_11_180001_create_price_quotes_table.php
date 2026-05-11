<?php

/**
 * Plan B Slice 3 — price_quotes table.
 *
 * Stores tamper-evident, time-limited, tier-aware price quotes issued by
 * POST /api/v1/pricing/quote. Each row corresponds to one quote issued to one
 * user. The entity_key column provides backend-computed dedup (Q2.1). The
 * signature column provides HMAC-SHA256 tamper-evidence (QuoteSigner). The
 * user_op_hash column is the join key for the ADR-0002 chain-event ingestor.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md §5.5
 * @see docs/adr/0003-pricing-bounded-context.md
 * @see docs/adr/0004-money-on-the-wire.md
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('price_quotes', function (Blueprint $table): void {
            // id: RFC 4122 v4 UUID — MariaDB rejects non-v4 UUIDs.
            // Generated with Str::uuid() in the service layer.
            $table->uuid('id')->primary();

            // user_id: FK → users.id (BIGINT unsigned on the users table).
            // Not a FK constraint to avoid cross-connection referential issues;
            // validated at the application layer.
            $table->unsignedBigInteger('user_id');

            // user_tier: snapshot of the tier at quote-issuance time.
            $table->string('user_tier', 32);

            // kind: the quote type — determines fee shape + expiry TTL.
            $table->enum('kind', [
                'send',
                'swap',
                'ramp_buy',
                'ramp_sell',
                'subscription_initial',
                'card_waitlist_deposit',
            ]);

            // request_payload: full request body at quote time (JSON).
            $table->json('request_payload');

            // response_payload: full serialised response (JSON) — used for
            // replay on GET /pricing/quote/{quoteId} without re-calling upstreams.
            $table->json('response_payload');

            // entity_key: SHA256(user_id || kind || canonical_amount || recipient
            // || from_json || to_json) — backend-computed Q2.1 dedup key.
            $table->char('entity_key', 64);

            // user_op_hash: 0x + 32-byte keccak256 hex of the canonical unsigned
            // userOp. NULL for fiat-only kinds (ramp_*, subscription_initial,
            // card_waitlist_deposit). UNIQUE: two live quotes cannot share the
            // same userOp hash (prevents replay on-chain).
            $table->char('user_op_hash', 66)->nullable()->unique();

            // user_op_payload: full unsigned userOp JSON for send/swap. NULL for
            // fiat-only kinds. Not persisted on the wire — only stored here so
            // the chain ingestor can reconstruct context if needed.
            $table->json('user_op_payload')->nullable();

            // signature: HMAC-SHA256(canonical_form, PRICING_QUOTE_PEPPER) — 32
            // bytes as 64 hex chars. Provides tamper-evidence against direct DB
            // writes that bypass the application layer (defense-in-depth).
            $table->char('signature', 64);

            // superseded_by: self-referencing pointer when this quote is
            // superseded by a refresh. No FK constraint (avoids circular FK on
            // CHAR(36) self-reference). Validated at the application layer.
            $table->uuid('superseded_by')->nullable()->comment('Self-FK: points to the newer quote on refresh. No FK constraint — validated in app layer.');

            // terms_changed: true when a refresh produced a materially different
            // fee (>€0.10 or >5% delta, or different route/currency). Mobile
            // uses this to decide whether to re-prompt the user (Q3.2).
            $table->boolean('terms_changed')->default(false);

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();

            // consumed_by: tx_hash | subscription_id | deposit_id — redacted to
            // the reference type only when served over GET (reduces PII exposure).
            $table->string('consumed_by', 255)->nullable();

            $table->timestamps();

            // Indexes per spec §5.5 DDL.
            $table->index(['user_id', 'expires_at'], 'idx_price_quotes_user_expires');
            $table->index('consumed_at', 'idx_price_quotes_consumed');
            $table->index(['entity_key', 'expires_at', 'consumed_at'], 'idx_price_quotes_entity_live');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_quotes');
    }
};
