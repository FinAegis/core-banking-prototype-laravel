<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Wave 0B — the custodial RAILGUN money-movement/key endpoints (server-side
// seed derivation from app.key) are hard-disabled (501) in production,
// regardless of ZK_PROVIDER; privacy is non-custodial / on-device. Outside
// production they stay reachable so tests/local can exercise the inert demo
// path. Mirrors the both-sides pattern used for account-flag bypass tests.
it('returns 501 for custodial privacy endpoints in production', function (string $method, string $uri) {
    $this->app->detectEnvironment(fn () => 'production');
    Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

    $this->json($method, $uri, [])
        ->assertStatus(501)
        ->assertJson(['error' => 'CUSTODIAL_PRIVACY_DISABLED']);
})->with([
    ['post', '/api/v1/privacy/shield'],
    ['post', '/api/v1/privacy/unshield'],
    ['post', '/api/v1/privacy/transfer'],
    ['get', '/api/v1/privacy/viewing-key'],
    ['post', '/api/v1/privacy/proof-of-innocence'],
    ['post', '/api/v1/privacy/delegated-proof'],
]);

it('does not block custodial privacy endpoints outside production', function () {
    Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

    // testing env (default): the guard must NOT fire — any non-501 confirms the env gate.
    $status = $this->json('post', '/api/v1/privacy/shield', [])->status();

    $this->assertNotSame(501, $status);
});

it('keeps the non-custodial engine-config endpoint reachable in production', function () {
    $this->app->detectEnvironment(fn () => 'production');
    Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

    // The non-custodial on-device bootstrap must NOT be swept up by the guard.
    $status = $this->getJson('/api/v1/privacy/engine-config')->status();

    $this->assertNotSame(501, $status);
});
