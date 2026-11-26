<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
| A user API key is counted once for each request it is accepted on, and a
| key that may only read cannot change anything through the v2 API. The v2
| group checks read permission only, so every route in it that changes
| something must ask for write or delete itself.
*/

beforeEach(function (): void {
    config(['cache.default' => 'array']);

    $this->owner = User::factory()->create();
    $this->account = Account::factory()->create(['user_uuid' => $this->owner->uuid, 'frozen' => false]);
});

/**
 * @param  list<string>  $permissions
 * @return array{0: ApiKey, 1: string} the key and its plain value
 */
function akuKey(User $user, array $permissions): array
{
    $result = ApiKey::createForUser($user, ['name' => 'usage key', 'permissions' => $permissions]);

    return [$result['api_key'], $result['plain_key']];
}

function akuStored(ApiKey $key): ApiKey
{
    $stored = $key->fresh();
    assert($stored instanceof ApiKey);

    return $stored;
}

it('makes every v2 route that changes something ask for more than read', function (): void {
    // POST only because the question has a body; it stores nothing.
    $readOnlyPosts = ['api/v2/compliance/check-transaction'];
    $missing = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $middleware = $route->gatherMiddleware();

        if (! in_array('auth.api_or_sanctum:read', $middleware, true)) {
            continue;
        }

        $changes = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']);
        if ($changes === [] || in_array($route->uri(), $readOnlyPosts, true)) {
            continue;
        }

        if (! in_array('auth.api_or_sanctum:write', $middleware, true) && ! in_array('auth.api_or_sanctum:delete', $middleware, true)) {
            $missing[] = implode('|', $changes) . ' /' . $route->uri();
        }
    }

    expect($missing)->toBe([]);
});

it('refuses a read-only key on the compliance routes that change something', function (string $uri): void {
    [, $plain] = akuKey($this->owner, ['read']);

    $this->withToken($plain)->postJson($uri, [])
        ->assertForbidden()
        ->assertJson(['message' => 'Insufficient permissions']);
})->with([
    '/api/v2/compliance/kyc/start',
    '/api/v2/compliance/kyc/ver-1/document',
    '/api/v2/compliance/kyc/ver-1/selfie',
    '/api/v2/compliance/aml/request-screening',
]);

it('counts a request once on a route that checks read', function (): void {
    [$key, $plain] = akuKey($this->owner, ['read']);

    $this->withToken($plain)->getJson('/api/v2/accounts')->assertOk();

    expect(akuStored($key)->request_count)->toBe(1);
});

it('counts a request once on a route that checks read and then write', function (): void {
    [$key, $plain] = akuKey($this->owner, ['read', 'write']);

    $this->withToken($plain)->postJson("/api/v2/accounts/{$this->account->uuid}/freeze", ['reason' => 'test'])
        ->assertSuccessful();

    expect(akuStored($key)->request_count)->toBe(1);
});

it('counts a read-only key once when the write check of the route refuses it', function (): void {
    [$key, $plain] = akuKey($this->owner, ['read']);

    $this->withToken($plain)->postJson("/api/v2/accounts/{$this->account->uuid}/freeze", ['reason' => 'test'])
        ->assertForbidden();

    expect(akuStored($key)->request_count)->toBe(1);
});

it('does not count a request the key is refused on outright', function (): void {
    [$key, $plain] = akuKey($this->owner, ['read']);
    $key->update(['allowed_ips' => ['203.0.113.9']]);

    $this->withToken($plain)->getJson('/api/v2/accounts')->assertForbidden();

    expect(akuStored($key)->request_count)->toBe(0)
        ->and(akuStored($key)->last_used_at)->toBeNull();
});

it('does not count looking a key up', function (): void {
    [$key, $plain] = akuKey($this->owner, ['read']);

    expect(ApiKey::verify($plain)?->id)->toBe($key->id)
        ->and(akuStored($key)->request_count)->toBe(0);
});
