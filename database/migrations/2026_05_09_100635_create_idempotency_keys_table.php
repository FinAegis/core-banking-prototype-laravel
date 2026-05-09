<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan B v1.3.0 — request-idempotency cache.
 *
 * Backs `App\Http\Middleware\IdempotencyKey`. Differs from the legacy
 * cache-based `App\Http\Middleware\IdempotencyMiddleware` by being
 * persistent (survives a Redis flush) and race-safe via SELECT FOR UPDATE.
 *
 * The composite primary key (user_id, idempotency_key) is the lookup key.
 * `request_hash` ties a key to a specific request body — replays with a
 * different body collide and return 409 ERR_IDEMPOTENCY_409.
 *
 * 24h TTL; daily purge runs via `idempotency:purge` (see routes/console.php).
 *
 * @see docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md §0.2 (Idempotency)
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            // VARCHAR(64) supports both anonymous string ("anonymous") and
            // BIGINT user IDs cast to string, plus future UUID users.
            $table->string('user_id', 64);
            $table->string('idempotency_key', 255);
            $table->char('request_hash', 64)->comment('sha256 hex of request body');
            $table->unsignedInteger('response_status');
            $table->longText('response_body')->comment('serialized JSON of response payload');
            $table->json('response_headers')->comment('subset of response headers we replay');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'idempotency_key']);
            $table->index('expires_at', 'idx_idempotency_expires');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
