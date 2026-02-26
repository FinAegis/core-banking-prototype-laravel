<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Register a test route with the idempotency middleware
    Route::middleware(['api', 'idempotency'])->post('/test/idempotency', function () {
        return response()->json([
            'id'        => Str::uuid()->toString(),
            'message'   => 'created',
            'timestamp' => now()->toIso8601String(),
        ], 201);
    });

    Route::middleware(['api', 'idempotency'])->get('/test/idempotency-get', function () {
        return response()->json(['message' => 'ok']);
    });

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user, ['read', 'write']);
});

it('returns cached response with X-Idempotency-Replayed header on duplicate POST', function () {
    $idempotencyKey = Str::uuid()->toString();

    // First request
    $firstResponse = $this->postJson('/test/idempotency', ['amount' => 100], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $firstResponse->assertStatus(201);
    $firstResponse->assertHeader('X-Idempotency-Key', $idempotencyKey);
    $firstResponse->assertHeader('X-Idempotency-Replayed', 'false');
    $firstId = $firstResponse->json('id');

    // Duplicate request with same key and body
    $secondResponse = $this->postJson('/test/idempotency', ['amount' => 100], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $secondResponse->assertStatus(201);
    $secondResponse->assertHeader('X-Idempotency-Key', $idempotencyKey);
    $secondResponse->assertHeader('X-Idempotency-Replayed', 'true');
    $secondResponse->assertJsonPath('id', $firstId);
});

it('rejects different request body with same idempotency key', function () {
    $idempotencyKey = Str::uuid()->toString();

    // First request
    $this->postJson('/test/idempotency', ['amount' => 100], [
        'Idempotency-Key' => $idempotencyKey,
    ])->assertStatus(201);

    // Different body, same key
    $response = $this->postJson('/test/idempotency', ['amount' => 200], [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error', 'Idempotency key already used');
});

it('bypasses idempotency for GET requests', function () {
    $idempotencyKey = Str::uuid()->toString();

    $response = $this->getJson('/test/idempotency-get', [
        'Idempotency-Key' => $idempotencyKey,
    ]);

    $response->assertStatus(200);
    $response->assertHeaderMissing('X-Idempotency-Key');
});

it('processes request normally without Idempotency-Key header', function () {
    $first = $this->postJson('/test/idempotency', ['amount' => 100]);
    $first->assertStatus(201);
    $first->assertHeaderMissing('X-Idempotency-Key');

    // Second request without key creates a new resource
    $second = $this->postJson('/test/idempotency', ['amount' => 100]);
    $second->assertStatus(201);

    // IDs should be different since no idempotency key was used
    expect($second->json('id'))->not->toBe($first->json('id'));
});

it('allows re-submission after idempotency key expires', function () {
    $idempotencyKey = Str::uuid()->toString();

    // First request
    $firstResponse = $this->postJson('/test/idempotency', ['amount' => 100], [
        'Idempotency-Key' => $idempotencyKey,
    ]);
    $firstResponse->assertStatus(201);
    $firstId = $firstResponse->json('id');

    // Manually flush the cache to simulate expiration
    Cache::flush();

    // Same key should now create a new resource
    $secondResponse = $this->postJson('/test/idempotency', ['amount' => 100], [
        'Idempotency-Key' => $idempotencyKey,
    ]);
    $secondResponse->assertStatus(201);
    $secondResponse->assertHeader('X-Idempotency-Replayed', 'false');

    expect($secondResponse->json('id'))->not->toBe($firstId);
});

it('rejects invalid idempotency key format', function () {
    $response = $this->postJson('/test/idempotency', ['amount' => 100], [
        'Idempotency-Key' => 'short',
    ]);

    $response->assertStatus(400);
    $response->assertJsonPath('error', 'Invalid idempotency key format');
});
