<?php

declare(strict_types=1);

use App\Http\Middleware\IdempotencyKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Counter — shared across the test's route closures so we can detect
    // whether the handler ran (vs. a cached replay).
    $GLOBALS['plan_b_idem_counter'] = 0;

    Route::middleware(['api', 'auth:sanctum', IdempotencyKey::class])
        ->post('/__test__/idem/echo', function () {
            $GLOBALS['plan_b_idem_counter']++;

            return response()->json([
                'success'  => true,
                'count'    => $GLOBALS['plan_b_idem_counter'],
                'received' => request()->all(),
            ]);
        });

    Route::middleware(['api', 'auth:sanctum', IdempotencyKey::class])
        ->post('/__test__/idem/boom', function () {
            $GLOBALS['plan_b_idem_counter']++;

            return response()->json(['success' => false], 500);
        });

    Route::middleware(['api', 'auth:sanctum', IdempotencyKey::class])
        ->get('/__test__/idem/get', function () {
            return response()->json(['ok' => true]);
        });
});

function actingUser(): User
{
    /** @var User $user */
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    return $user;
}

it('replays the cached response on same key + same body within 24h', function (): void {
    actingUser();
    $key = str_repeat('a', 24);

    $first = $this->postJson('/__test__/idem/echo', ['v' => 1], ['Idempotency-Key' => $key]);
    $second = $this->postJson('/__test__/idem/echo', ['v' => 1], ['Idempotency-Key' => $key]);

    $first->assertOk();
    $second->assertOk();

    expect($GLOBALS['plan_b_idem_counter'])->toBe(1)
        ->and($first->json('count'))->toBe(1)
        ->and($second->json('count'))->toBe(1)
        ->and($second->headers->get('X-Idempotency-Replayed'))->toBe('true');
});

it('returns 409 ERR_IDEMPOTENCY_409 on same key + different body', function (): void {
    actingUser();
    $key = str_repeat('b', 24);

    $first = $this->postJson('/__test__/idem/echo', ['v' => 1], ['Idempotency-Key' => $key]);
    $second = $this->postJson('/__test__/idem/echo', ['v' => 2], ['Idempotency-Key' => $key]);

    $first->assertOk();
    $second->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'ERR_IDEMPOTENCY_409');
});

it('returns 422 ERR_VALIDATION_001 when Idempotency-Key header is missing', function (): void {
    actingUser();

    $this->postJson('/__test__/idem/echo', ['v' => 1])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'ERR_VALIDATION_001');

    expect($GLOBALS['plan_b_idem_counter'])->toBe(0);
});

it('returns 422 ERR_VALIDATION_003 on a malformed Idempotency-Key', function (): void {
    actingUser();

    // Too short: 8 chars (min is 16).
    $this->postJson('/__test__/idem/echo', ['v' => 1], ['Idempotency-Key' => 'short123'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_VALIDATION_003');

    // Disallowed character (`@`).
    $bad = 'has@an@illegal@char-12345';
    $this->postJson('/__test__/idem/echo', ['v' => 1], ['Idempotency-Key' => $bad])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_VALIDATION_003');
});

it('bypasses GET requests entirely', function (): void {
    actingUser();

    // No Idempotency-Key header on GET should NOT produce 422.
    $this->getJson('/__test__/idem/get')
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('replaces stale (expired) row on next request with same key', function (): void {
    actingUser();
    $key = str_repeat('c', 24);

    $first = $this->postJson('/__test__/idem/echo', ['v' => 1], ['Idempotency-Key' => $key]);
    $first->assertOk();
    expect($GLOBALS['plan_b_idem_counter'])->toBe(1);

    // Manually expire the stored row.
    DB::table('idempotency_keys')
        ->where('idempotency_key', $key)
        ->update(['expires_at' => Carbon::now()->subHour()]);

    $second = $this->postJson('/__test__/idem/echo', ['v' => 1], ['Idempotency-Key' => $key]);

    $second->assertOk();
    // Handler should have run again because the row was stale.
    expect($GLOBALS['plan_b_idem_counter'])->toBe(2)
        ->and($second->json('count'))->toBe(2);
});

it('does not cache 5xx responses (next attempt processes fresh)', function (): void {
    actingUser();
    $key = str_repeat('d', 24);

    $first = $this->postJson('/__test__/idem/boom', ['v' => 1], ['Idempotency-Key' => $key]);
    $first->assertStatus(500);

    $second = $this->postJson('/__test__/idem/boom', ['v' => 1], ['Idempotency-Key' => $key]);
    $second->assertStatus(500);

    // Both calls should have invoked the handler.
    expect($GLOBALS['plan_b_idem_counter'])->toBe(2);

    // No row persisted for this key.
    $row = DB::table('idempotency_keys')->where('idempotency_key', $key)->first();
    expect($row)->toBeNull();
});

it('persists the cached response with a 24h expiry', function (): void {
    actingUser();
    $key = str_repeat('e', 24);

    $this->postJson('/__test__/idem/echo', ['v' => 1], ['Idempotency-Key' => $key])
        ->assertOk();

    $row = DB::table('idempotency_keys')->where('idempotency_key', $key)->first();
    expect($row)->not->toBeNull();
    /** @var stdClass $row */
    $expiresAt = Carbon::parse($row->expires_at);

    expect($expiresAt->isAfter(Carbon::now()->addHours(23)))->toBeTrue()
        ->and($expiresAt->isBefore(Carbon::now()->addHours(25)))->toBeTrue();
});

it('isolates idempotency keys per user', function (): void {
    $key = str_repeat('f', 24);

    // User 1 makes a request.
    actingUser();
    $first = $this->postJson('/__test__/idem/echo', ['v' => 1], ['Idempotency-Key' => $key]);
    $first->assertOk();
    expect($GLOBALS['plan_b_idem_counter'])->toBe(1);

    // User 2 with the same key hits a fresh row, not user 1's cached one.
    actingUser();
    $second = $this->postJson('/__test__/idem/echo', ['v' => 999], ['Idempotency-Key' => $key]);
    $second->assertOk();
    expect($GLOBALS['plan_b_idem_counter'])->toBe(2)
        ->and($second->json('count'))->toBe(2);
});
