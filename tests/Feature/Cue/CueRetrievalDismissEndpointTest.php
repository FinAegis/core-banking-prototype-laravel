<?php

/**
 * CueRetrievalDismissEndpointTest — Feature tests for:
 *   GET  /api/v1/me/pending-cues
 *   POST /api/v1/me/cues/{cueId}/dismissed
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.3, §9
 */

declare(strict_types=1);

use App\Domain\Subscription\Models\Cue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);
uses(Tests\TestCase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
});

// ─── GET /api/v1/me/pending-cues ─────────────────────────────────────────────

it('returns empty array when user has no cues', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->getJson('/api/v1/me/pending-cues');

    $response->assertOk()->assertJson([]);
});

it('returns pending cues for authenticated user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $cue = Cue::query()->create([
        'user_id'         => $user->id,
        'kind'            => 'payment_failed',
        'priority'        => 'critical',
        'due_at'          => now()->subMinute(),
        'expires_at'      => now()->addDay(),
        'payload'         => ['invoiceId' => 'in_test'],
        'idempotency_key' => hash('sha256', "{$user->id}:payment_failed:2026-01-01T00:00:00Z"),
    ]);

    $response = $this->getJson('/api/v1/me/pending-cues');

    $response->assertOk();
    $data = $response->json();
    expect($data)->toHaveCount(1);
    expect($data[0]['kind'])->toBe('payment_failed');
    expect($data[0]['priority'])->toBe('critical');
    expect($data[0])->toHaveKey('dueAt');
    expect($data[0])->toHaveKey('expiresAt');
    expect($data[0])->toHaveKey('payload');
});

it('does not return dismissed cues', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    Cue::query()->create([
        'user_id'          => $user->id,
        'kind'             => 'payment_failed',
        'priority'         => 'critical',
        'due_at'           => now()->subMinute(),
        'expires_at'       => now()->addDay(),
        'payload'          => [],
        'dismissed_at'     => now(),
        'dismissed_action' => 'dismissed',
        'idempotency_key'  => hash('sha256', "{$user->id}:payment_failed:window1"),
    ]);

    $response = $this->getJson('/api/v1/me/pending-cues');
    $response->assertOk()->assertJson([]);
});

it('does not return expired cues', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    Cue::query()->create([
        'user_id'         => $user->id,
        'kind'            => 'refund_processed',
        'priority'        => 'high',
        'due_at'          => now()->subDay(),
        'expires_at'      => now()->subHour(),
        'payload'         => [],
        'idempotency_key' => hash('sha256', "{$user->id}:refund_processed:window1"),
    ]);

    $response = $this->getJson('/api/v1/me/pending-cues');
    $response->assertOk()->assertJson([]);
});

it('sorts cues by priority then due_at ascending', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // Insert in reverse priority order.
    Cue::query()->create([
        'user_id'         => $user->id,
        'kind'            => 'subscription_canceled_external',
        'priority'        => 'normal',
        'due_at'          => now()->subMinutes(2),
        'expires_at'      => now()->addDay(),
        'payload'         => [],
        'idempotency_key' => hash('sha256', "{$user->id}:subscription_canceled_external:w1"),
    ]);

    Cue::query()->create([
        'user_id'         => $user->id,
        'kind'            => 'payment_failed',
        'priority'        => 'critical',
        'due_at'          => now()->subMinutes(3),
        'expires_at'      => now()->addDay(),
        'payload'         => [],
        'idempotency_key' => hash('sha256', "{$user->id}:payment_failed:w1"),
    ]);

    Cue::query()->create([
        'user_id'         => $user->id,
        'kind'            => 'refund_processed',
        'priority'        => 'high',
        'due_at'          => now()->subMinute(),
        'expires_at'      => now()->addDay(),
        'payload'         => [],
        'idempotency_key' => hash('sha256', "{$user->id}:refund_processed:w1"),
    ]);

    $response = $this->getJson('/api/v1/me/pending-cues');
    $response->assertOk();
    $data = $response->json();

    expect($data)->toHaveCount(3);
    expect($data[0]['priority'])->toBe('critical');
    expect($data[1]['priority'])->toBe('high');
    expect($data[2]['priority'])->toBe('normal');
});

it('returns 401 without auth token', function () {
    $response = $this->getJson('/api/v1/me/pending-cues');
    $response->assertUnauthorized();
});

// ─── POST /api/v1/me/cues/{cueId}/dismissed ──────────────────────────────────

it('dismisses a cue and returns 200 with dismissedAt', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $cue = Cue::query()->create([
        'user_id'         => $user->id,
        'kind'            => 'payment_failed',
        'priority'        => 'critical',
        'due_at'          => now()->subMinute(),
        'expires_at'      => now()->addDay(),
        'payload'         => [],
        'idempotency_key' => hash('sha256', "{$user->id}:payment_failed:w1"),
    ]);

    $response = $this->postJson(
        "/api/v1/me/cues/{$cue->id}/dismissed",
        ['dismissedAction' => 'kept'],
        ['Idempotency-Key' => 'test-dismiss-' . $cue->id],
    );

    $response->assertOk();
    $data = $response->json();
    expect($data)->toHaveKey('dismissedAt');
    expect($data['id'])->toBe($cue->id);

    $cue->refresh();
    expect($cue->dismissed_at)->not()->toBeNull();
    expect($cue->dismissed_action)->toBe('kept');
});

it('dismiss is idempotent on second call', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $cue = Cue::query()->create([
        'user_id'          => $user->id,
        'kind'             => 'payment_failed',
        'priority'         => 'critical',
        'due_at'           => now()->subMinute(),
        'expires_at'       => now()->addDay(),
        'payload'          => [],
        'dismissed_at'     => now(),
        'dismissed_action' => 'dismissed',
        'idempotency_key'  => hash('sha256', "{$user->id}:payment_failed:w1"),
    ]);

    $response = $this->postJson(
        "/api/v1/me/cues/{$cue->id}/dismissed",
        [],
        ['Idempotency-Key' => 'test-dismiss-idem-' . $cue->id],
    );

    $response->assertOk();
    $data = $response->json();
    expect($data['id'])->toBe($cue->id);
    expect($data)->toHaveKey('dismissedAt');
});

it('returns 404 for cue belonging to different user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    Sanctum::actingAs($user2, ['read', 'write', 'delete']);

    $cue = Cue::query()->create([
        'user_id'         => $user1->id,
        'kind'            => 'payment_failed',
        'priority'        => 'critical',
        'due_at'          => now()->subMinute(),
        'expires_at'      => now()->addDay(),
        'payload'         => [],
        'idempotency_key' => hash('sha256', "{$user1->id}:payment_failed:w1"),
    ]);

    $response = $this->postJson(
        "/api/v1/me/cues/{$cue->id}/dismissed",
        [],
        ['Idempotency-Key' => 'test-cross-user-' . $cue->id],
    );

    $response->assertNotFound();
});

it('returns 422 when Idempotency-Key header is missing on dismiss', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $cue = Cue::query()->create([
        'user_id'         => $user->id,
        'kind'            => 'payment_failed',
        'priority'        => 'critical',
        'due_at'          => now()->subMinute(),
        'expires_at'      => now()->addDay(),
        'payload'         => [],
        'idempotency_key' => hash('sha256', "{$user->id}:payment_failed:w1"),
    ]);

    $response = $this->postJson("/api/v1/me/cues/{$cue->id}/dismissed");

    $response->assertStatus(422);
    $data = $response->json();
    expect($data['error']['code'])->toBe('ERR_VALIDATION_001');
});

it('returns 401 on dismiss without auth', function () {
    $response = $this->postJson('/api/v1/me/cues/fake-id/dismissed');
    $response->assertUnauthorized();
});
