<?php

declare(strict_types=1);

namespace Tests\Feature\MCP;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\MCP\Server\JsonRpcRouter;
use App\Domain\MCP\Server\McpRequestContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// TestCase is already bound to tests/Feature via tests/Pest.php.

/**
 * Stub implementation of MCPToolInterface used by the tools/call dispatch tests.
 *
 * @param array<string, mixed> $schema
 */
function toolsCallStub(string $name, string $description, array $schema): MCPToolInterface
{
    return new class ($name, $description, $schema) implements MCPToolInterface {
        /**
         * @param array<string, mixed> $schema
         */
        public function __construct(
            private string $name,
            private string $description,
            private array $schema,
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
            return $this->schema;
        }

        /** @return array<string, mixed> */
        public function getOutputSchema(): array
        {
            return ['type' => 'object'];
        }

        /** @param array<string, mixed> $parameters */
        public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
        {
            return ToolExecutionResult::success(['ok' => true, 'echoed' => $parameters]);
        }

        /** @return array<int|string, mixed> */
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
    DB::table('mcp_token_policies')->insert([
        'token_id'              => 'tok_test',
        'daily_limit_minor'     => 50000,
        'daily_limit_currency'  => 'USD',
        'daily_spend_minor'     => 0,
        'daily_window_start_at' => now(),
        'created_at'            => now(),
        'updated_at'            => now(),
    ]);

    // Pin idempotency cache to array store so tests don't require Redis.
    config(['mcp.idempotency.cache_store' => 'array']);
    Cache::store('array')->flush();

    // Reset tool registry singleton; provider skips bootstrap in test env.
    app()->forgetInstance(ToolRegistry::class);
    app()->singleton(ToolRegistry::class, fn () => new ToolRegistry());
    /** @var ToolRegistry $registry */
    $registry = app(ToolRegistry::class);

    // Stub tools matching catalog dotted internal names.
    $registry->register(toolsCallStub('account.balance', 'Read account balance', [
        'type' => 'object', 'properties' => ['account_id' => ['type' => 'string']],
    ]));
    $registry->register(toolsCallStub('payment.transfer', 'Move money', [
        'type' => 'object', 'properties' => ['amount' => ['type' => 'integer']],
    ]));
});

it('returns -32601 for an unknown public tool name', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['*']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params'  => ['name' => 'nonexistent.tool', 'arguments' => []],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32601);
    expect($resp['error']['data']['name'] ?? null)->toBe('nonexistent.tool');
});

it('returns -32004 when the tool is disabled by config', function () {
    // The catalog key `payment.transfer` literally contains a dot, so the
    // dotted config() setter would create a parallel nested path instead of
    // updating the literal key. Rewrite the catalog map directly.
    /** @var array<string, mixed> $catalog */
    $catalog = (array) config('mcp.tools');
    $catalog['payment.transfer']['enabled'] = false;
    config(['mcp.tools' => $catalog]);

    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['idempotency_key' => 'k', 'amount' => 1]],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32004);
});

it('returns -32000 INSUFFICIENT_SCOPE when token lacks the required scope', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['accounts:read']); // no payments:write
    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['idempotency_key' => 'k1', 'amount' => 1]],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32000);
    expect($resp['error']['data']['required'] ?? null)->toBe('payments:write');
});

it('returns -32602 IDEMPOTENCY_KEY_REQUIRED on a write tool with no key', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['amount' => 100]], // no idempotency_key
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32602);
    expect($resp['error']['data']['tool'] ?? null)->toBe('payment.transfer');
});

it('writes an audit row on a successful read-tool invocation', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['accounts:read']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call',
        'params'  => ['name' => 'account.balance', 'arguments' => ['account_id' => 'acc-x']],
    ], $ctx);

    expect($resp['result'])->toHaveKey('content');
    expect($resp['result']['isError'])->toBeFalse();

    $this->assertDatabaseHas('mcp_tool_invocations', [
        'token_id'      => 'tok_test',
        'tool_name'     => 'account.balance',
        'result_status' => 'success',
    ]);
});

it('caches the result on a write tool retry with the same idempotency_key + args', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);

    $first = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['amount' => 100, 'idempotency_key' => 'idem-1']],
    ], $ctx);

    $second = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['amount' => 100, 'idempotency_key' => 'idem-1']],
    ], $ctx);

    // Same result envelope (identical structuredContent).
    expect($second['result']['structuredContent'])->toBe($first['result']['structuredContent']);
});

it('returns -32002 IDEMPOTENCY_KEY_REUSED when the same key is sent with different args', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);

    $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 8, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['amount' => 100, 'idempotency_key' => 'idem-2']],
    ], $ctx);

    $reuse = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['amount' => 999, 'idempotency_key' => 'idem-2']],
    ], $ctx);

    expect($reuse['error']['code'])->toBe(-32002);

    $this->assertDatabaseHas('mcp_tool_invocations', [
        'token_id'        => 'tok_test',
        'tool_name'       => 'payment.transfer',
        'idempotency_key' => 'idem-2',
        'result_status'   => 'error',
        'error_code'      => 'IDEMPOTENCY_KEY_REUSED',
    ]);
});
