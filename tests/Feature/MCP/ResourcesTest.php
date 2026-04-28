<?php

declare(strict_types=1);

namespace Tests\Feature\MCP;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\MCP\Resources\AccountBalanceResource;
use App\Domain\MCP\Resources\AccountProfileResource;
use App\Domain\MCP\Resources\RecentTransactionsResource;
use App\Domain\MCP\Resources\ResourceRegistry;
use App\Domain\MCP\Resources\SingleTransactionResource;
use App\Domain\MCP\Server\JsonRpcRouter;
use App\Domain\MCP\Server\McpRequestContext;
use App\Models\User;
use stdClass;

beforeEach(function () {
    // Make sure the registry exists in the test container; the McpServiceProvider
    // is registered in production but unit-test boot may not always trigger it.
    app()->forgetInstance(ResourceRegistry::class);
    app()->singleton(ResourceRegistry::class, function ($app): ResourceRegistry {
        $r = new ResourceRegistry();
        $r->register($app->make(AccountProfileResource::class));
        $r->register($app->make(AccountBalanceResource::class));
        $r->register($app->make(RecentTransactionsResource::class));
        $r->register($app->make(SingleTransactionResource::class));

        return $r;
    });
});

it('lists only resources whose scope is granted', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok', 'cli', 1, ['accounts:read']);

    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'resources/list', 'params' => new stdClass(),
    ], $ctx);

    $uris = array_column($resp['result']['resources'], 'uri');

    expect($uris)->toContain('account://profile');
    expect($uris)->toContain('account://balance/{currency}');
    expect($uris)->not->toContain('transactions://recent');
    expect($uris)->not->toContain('transaction://{id}');
});

it('lists transaction resources when transactions:read is granted', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok', 'cli', 1, ['transactions:read']);

    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'resources/list', 'params' => new stdClass(),
    ], $ctx);

    $uris = array_column($resp['result']['resources'], 'uri');

    expect($uris)->toContain('transactions://recent');
    expect($uris)->toContain('transaction://{id}');
    expect($uris)->not->toContain('account://profile');
});

it('reads account://balance/{currency} as JSON', function () {
    $user = User::factory()->create();
    Account::factory()->create(['user_uuid' => $user->uuid]);

    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok', 'cli', $user->id, ['accounts:read']);

    $resp = $router->dispatch([
        'jsonrpc' => '2.0',
        'id'      => 2,
        'method'  => 'resources/read',
        'params'  => ['uri' => 'account://balance/USD'],
    ], $ctx);

    expect($resp['result']['contents'][0]['mimeType'])->toBe('application/json');
    $payload = json_decode($resp['result']['contents'][0]['text'], true);
    expect($payload)->toHaveKeys(['currency', 'balance_minor', 'as_of']);
    expect($payload['currency'])->toBe('USD');
});

it('returns -32602 when resources/read has no uri param', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok', 'cli', 1, ['accounts:read']);

    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 3, 'method' => 'resources/read', 'params' => [],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32602);
});

it('returns -32601 RESOURCE_NOT_FOUND for an unregistered URI', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok', 'cli', 1, ['accounts:read']);

    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 4, 'method' => 'resources/read', 'params' => ['uri' => 'unknown://x'],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32601);
    expect($resp['error']['data']['uri'] ?? null)->toBe('unknown://x');
});

it('returns -32000 INSUFFICIENT_SCOPE when resource is gated', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok', 'cli', 1, []); // no scopes

    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 5, 'method' => 'resources/read', 'params' => ['uri' => 'account://profile'],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32000);
    expect($resp['error']['data']['required'] ?? null)->toBe('accounts:read');
});

it('reads transaction://{id} for a transaction owned by the caller', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);

    /** @var TransactionProjection $tx */
    $tx = TransactionProjection::create([
        'uuid'         => '01999999-1111-7000-8000-aaaaaaaaaaaa',
        'account_uuid' => $account->uuid,
        'asset_code'   => 'USD',
        'amount'       => 5000,
        'type'         => 'credit',
        'status'       => 'completed',
        'description'  => 'test deposit',
        'hash'         => str_repeat('a', 64),
    ]);

    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok', 'cli', $user->id, ['transactions:read']);

    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 6, 'method' => 'resources/read',
        'params'  => ['uri' => "transaction://{$tx->uuid}"],
    ], $ctx);

    $payload = json_decode($resp['result']['contents'][0]['text'], true);
    expect($payload['id'])->toBe($tx->uuid);
    expect($payload['amount_minor'])->toBe(5000);
});

it('refuses to leak existence of a transaction owned by another user', function () {
    $owner = User::factory()->create();
    $caller = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $owner->uuid]);

    $tx = TransactionProjection::create([
        'uuid'         => '01999999-2222-7000-8000-bbbbbbbbbbbb',
        'account_uuid' => $account->uuid,
        'asset_code'   => 'USD',
        'amount'       => 1,
        'type'         => 'credit',
        'status'       => 'completed',
        'hash'         => str_repeat('b', 64),
    ]);

    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok', 'cli', $caller->id, ['transactions:read']);

    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 7, 'method' => 'resources/read',
        'params'  => ['uri' => "transaction://{$tx->uuid}"],
    ], $ctx);

    $payload = json_decode($resp['result']['contents'][0]['text'], true);
    expect($payload['error'] ?? null)->toBe('not found');
});
