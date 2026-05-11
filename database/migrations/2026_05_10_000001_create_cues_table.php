<?php

/**
 * Plan B Slice 4 — create cues table (Backend-Q8 cue queue infrastructure).
 *
 * Global table (no tenant connection). Stores per-user cue rows for the
 * mobile CueOrchestrator. One row per user-kind-occurrence-window combination;
 * idempotent write via uniq_cues_idempotency (user_id, idempotency_key).
 *
 * Distinct from revenue_outbox_events (off-chain projection, ADR-0002).
 * These are user-facing modal triggers; the outbox is a revenue projection
 * worker concern.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.1
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('cues', function (Blueprint $table): void {
            // UUID v4 primary key.
            $table->uuid('id')->primary();

            // FK to users.id (BIGINT UNSIGNED per CLAUDE.md — users.$table->id() is BIGINT).
            // Note: Q5.1 source DDL listed CHAR(36); corrected to BIGINT per project convention.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // One of the 11 cue kinds defined in Backend-Q8.
            $table->string('kind', 64);

            $table->enum('priority', ['critical', 'high', 'normal']);

            // When the cue becomes available (UTC).
            $table->timestamp('due_at');

            // Absolute render window end (UTC).
            $table->timestamp('expires_at');

            // Kind-specific data for mobile rendering.
            $table->json('payload');

            // Dismiss state — NULL until user dismisses.
            $table->timestamp('dismissed_at')->nullable();
            $table->enum('dismissed_action', ['cancelled', 'kept', 'dismissed'])->nullable();

            // sha256({user_id}:{kind}:{occurrence_window_start_iso8601}) — backend dedup.
            $table->char('idempotency_key', 64);

            $table->timestamp('created_at')->useCurrent();

            // Pending-cues endpoint query: WHERE user_id = ? AND dismissed_at IS NULL
            //   AND due_at <= now() AND expires_at >= now()
            $table->index(
                ['user_id', 'dismissed_at', 'due_at', 'expires_at'],
                'idx_cues_user_pending',
            );

            // Backend-side dedup — prevents duplicate cue creation from parallel workers.
            $table->unique(['user_id', 'idempotency_key'], 'uniq_cues_idempotency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cues');
    }
};
