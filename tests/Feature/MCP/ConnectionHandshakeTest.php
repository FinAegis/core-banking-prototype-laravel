<?php

declare(strict_types=1);

namespace Tests\Feature\MCP;

use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\MCP\Server\JsonRpcRouter;
use App\Domain\MCP\Server\McpRequestContext;

// End-to-end conformance: a realistic JSON-RPC envelope passes through the
// full dispatch stack (initialize -> tools/list -> resources/list) with scope
// filtering applied. We construct the McpRequestContext directly rather than
// going through HTTP + Passport because the Passport client_credentials dance
// is covered by DcrRegistrationTest + ConsentRoutingTest. This test asserts
// what the server returns once middleware has produced a context.
beforeEach(function () {
    // Reset and re-register tools so the routing layer's tool registry is
    // populated even though Tests\TestCase normally suppresses it.
    app()->forgetInstance(ToolRegistry::class);
    app()->singleton(ToolRegistry::class, fn () => new ToolRegistry());

    (new \App\Providers\MCPToolServiceProvider(app()))->boot();
});

it('completes initialize -> tools/list -> resources/list with scope filtering', function () {
    config(['ai.register_mcp_tools_in_tests' => true]);
    app()->forgetInstance(ToolRegistry::class);
    app()->singleton(ToolRegistry::class, fn () => new ToolRegistry());

    $router = app(JsonRpcRouter::class);

    // Re-trigger tool registration after singleton reset.
    (new \App\Providers\MCPToolServiceProvider(app()))->boot();

    $ctx = new McpRequestContext('tok_handshake', 'cli', 1, ['accounts:read']);

    // Step 1: initialize.
    $init = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [],
    ], $ctx);
    expect($init['result']['protocolVersion'])->toBe('2025-11-25');
    expect($init['result']['capabilities']['tools']['listChanged'])->toBeTrue();
    expect($init['result']['capabilities']['resources']['listChanged'])->toBeTrue();

    // Step 2: tools/list — only accounts:read tools surface.
    $list = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => [],
    ], $ctx);
    $names = array_column($list['result']['tools'], 'name');
    expect($names)->toContain('account.balance');
    expect($names)->not->toContain('payment.transfer');
    expect($names)->not->toContain('exchange.trade');
    expect($names)->not->toContain('ramp.start');

    // Step 3: resources/list — same scope filter on resources.
    $resources = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 3, 'method' => 'resources/list', 'params' => [],
    ], $ctx);
    $uris = array_column($resources['result']['resources'], 'uri');
    expect($uris)->toContain('account://profile');
    expect($uris)->toContain('account://balance/{currency}');
    expect($uris)->not->toContain('transactions://recent');
});

it('upgrades visible tool/resource set as scopes are added to the bearer token', function () {
    config(['ai.register_mcp_tools_in_tests' => true]);
    app()->forgetInstance(ToolRegistry::class);
    app()->singleton(ToolRegistry::class, fn () => new ToolRegistry());

    $router = app(JsonRpcRouter::class);

    (new \App\Providers\MCPToolServiceProvider(app()))->boot();

    // Wide-scope token — should see write tools too.
    $ctx = new McpRequestContext('tok_admin', 'cli', 1, [
        'accounts:read', 'payments:write', 'transactions:read', 'ramp:write',
    ]);

    $list = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => [],
    ], $ctx);
    $names = array_column($list['result']['tools'], 'name');

    expect($names)->toContain('account.balance');
    expect($names)->toContain('payment.transfer');
    expect($names)->toContain('ramp.start');

    $resources = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'resources/list', 'params' => [],
    ], $ctx);
    $uris = array_column($resources['result']['resources'], 'uri');
    expect($uris)->toContain('transactions://recent');
});

it('returns -32601 METHOD_NOT_FOUND for an unknown JSON-RPC method', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok', 'cli', 1, []);

    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 99, 'method' => 'made_up.method',
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32601);
});

it('returns -32600 INVALID_REQUEST for missing jsonrpc field', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok', 'cli', 1, []);

    $resp = $router->dispatch([
        'id' => 1, 'method' => 'initialize',
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32600);
});
