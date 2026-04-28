<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\MCP;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\MCP\Server\JsonRpcRouter;
use App\Domain\MCP\Server\McpRequestContext;
use stdClass;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Build a fake MCP request context for tests.
 *
 * @param array{token_id?: string, client_id?: string, user_id?: int|null, scopes?: array<int, string>} $overrides
 */
function fakeMcpContext(array $overrides = []): McpRequestContext
{
    return new McpRequestContext(
        tokenId: $overrides['token_id'] ?? 'tok_test',
        clientId: $overrides['client_id'] ?? 'client_test',
        userId: $overrides['user_id'] ?? 1,
        scopes: $overrides['scopes'] ?? ['accounts:read', 'payments:write', 'sms:send'],
    );
}

/**
 * Build a stub MCPToolInterface implementation with controllable name/description/schema.
 *
 * @param array<string, mixed> $inputSchema
 */
function fakeMcpTool(string $name, string $description, array $inputSchema): MCPToolInterface
{
    return new class ($name, $description, $inputSchema) implements MCPToolInterface {
        /**
         * @param array<string, mixed> $inputSchema
         */
        public function __construct(
            private readonly string $name,
            private readonly string $description,
            private readonly array $inputSchema,
        ) {
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function getCategory(): string
        {
            return 'test';
        }

        public function getDescription(): string
        {
            return $this->description;
        }

        /** @return array<string, mixed> */
        public function getInputSchema(): array
        {
            return $this->inputSchema;
        }

        /** @return array<string, mixed> */
        public function getOutputSchema(): array
        {
            return ['type' => 'object'];
        }

        /** @param array<string, mixed> $parameters */
        public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
        {
            return ToolExecutionResult::success([]);
        }

        /** @return array<int, string> */
        public function getCapabilities(): array
        {
            return [];
        }

        public function isCacheable(): bool
        {
            return false;
        }

        public function getCacheTtl(): int
        {
            return 0;
        }

        /** @param array<string, mixed> $parameters */
        public function validateInput(array $parameters): bool
        {
            return true;
        }

        public function authorize(?string $userId): bool
        {
            return true;
        }
    };
}

beforeEach(function () {
    // Reset the singleton ToolRegistry so each test starts with a clean slate
    // (the MCPToolServiceProvider skips bootstrapping during tests by default).
    app()->forgetInstance(ToolRegistry::class);
    app()->singleton(ToolRegistry::class, fn () => new ToolRegistry());

    /** @var ToolRegistry $registry */
    $registry = app(ToolRegistry::class);

    // Register stub tools matching the `internal` names declared in config/mcp.php
    // so the router's tools/list lookup can resolve them.
    $registry->register(fakeMcpTool(
        'account.balance',
        'Read account balance',
        ['type' => 'object', 'properties' => ['account_uuid' => ['type' => 'string']], 'required' => ['account_uuid']],
    ));
    $registry->register(fakeMcpTool(
        'payment.transfer',
        'Move money between accounts',
        ['type' => 'object', 'properties' => ['amount' => ['type' => 'integer']], 'required' => ['amount']],
    ));
    $registry->register(fakeMcpTool(
        'mpp.discovery',
        'Discover MPP-capable rails',
        ['type' => 'object', 'properties' => []],
    ));
});

it('responds to initialize with capabilities and protocol version', function () {
    /** @var JsonRpcRouter $router */
    $router = app(JsonRpcRouter::class);

    $envelope = [
        'jsonrpc' => '2.0',
        'id'      => 1,
        'method'  => 'initialize',
        'params'  => ['protocolVersion' => '2025-11-25', 'clientInfo' => ['name' => 'test', 'version' => '0.0.0']],
    ];

    $response = $router->dispatch($envelope, fakeMcpContext());

    expect($response['jsonrpc'])->toBe('2.0');
    expect($response['id'])->toBe(1);
    expect($response['result']['protocolVersion'])->toBe(config('mcp.protocol_version'));
    expect($response['result']['serverInfo']['name'])->toBe(config('mcp.server_info.name'));
    expect($response['result']['capabilities'])->toHaveKeys(['tools', 'resources', 'prompts', 'logging']);
});

it('returns -32600 INVALID_REQUEST for an envelope missing method or jsonrpc', function () {
    /** @var JsonRpcRouter $router */
    $router = app(JsonRpcRouter::class);

    $response = $router->dispatch(['method' => 'initialize'], fakeMcpContext());

    expect($response['error']['code'])->toBe(-32600);
    expect($response['error']['message'])->toBe('INVALID_REQUEST');
});

it('returns -32601 METHOD_NOT_FOUND with method name in data for unknown methods', function () {
    /** @var JsonRpcRouter $router */
    $router = app(JsonRpcRouter::class);

    $response = $router->dispatch(
        ['jsonrpc' => '2.0', 'id' => 7, 'method' => 'does/not/exist'],
        fakeMcpContext(),
    );

    expect($response['error']['code'])->toBe(-32601);
    expect($response['error']['message'])->toBe('METHOD_NOT_FOUND');
    expect($response['error']['data']['method'])->toBe('does/not/exist');
});

it('returns an empty object for ping', function () {
    /** @var JsonRpcRouter $router */
    $router = app(JsonRpcRouter::class);

    $response = $router->dispatch(
        ['jsonrpc' => '2.0', 'id' => 42, 'method' => 'ping'],
        fakeMcpContext(),
    );

    expect($response['jsonrpc'])->toBe('2.0');
    expect($response['id'])->toBe(42);
    expect($response['result'])->toBeInstanceOf(stdClass::class);
    expect((array) $response['result'])->toBe([]);
});

it('lists tools filtered by token scopes', function () {
    /** @var JsonRpcRouter $router */
    $router = app(JsonRpcRouter::class);

    $ctx = fakeMcpContext(['scopes' => ['accounts:read']]);
    $envelope = ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => new stdClass()];
    $response = $router->dispatch($envelope, $ctx);

    $names = array_column($response['result']['tools'], 'name');

    expect($names)->toContain('account.balance');     // accounts:read scope present
    expect($names)->not->toContain('payment.transfer'); // no payments:write scope
    expect($names)->toContain('mpp.discovery');         // public tool always listed
});

it('augments write tool input schemas with a required idempotency_key field', function () {
    /** @var JsonRpcRouter $router */
    $router = app(JsonRpcRouter::class);

    $ctx = fakeMcpContext(['scopes' => ['*']]);
    $envelope = ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list', 'params' => new stdClass()];
    $response = $router->dispatch($envelope, $ctx);

    $byName = [];
    foreach ($response['result']['tools'] as $tool) {
        $byName[$tool['name']] = $tool;
    }

    // Write tool: idempotency_key required.
    expect($byName['payment.transfer']['inputSchema']['properties'])->toHaveKey('idempotency_key');
    expect($byName['payment.transfer']['inputSchema']['properties']['idempotency_key']['format'])->toBe('uuid');
    expect($byName['payment.transfer']['inputSchema']['required'])->toContain('idempotency_key');

    // Read tool: no idempotency_key.
    expect($byName['account.balance']['inputSchema']['properties'] ?? [])->not->toHaveKey('idempotency_key');
});
