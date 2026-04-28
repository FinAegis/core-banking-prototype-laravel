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
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['amount' => 1, 'currency' => 'USD', 'idempotency_key' => 'idem-1']],
    ], $ctx);

    $second = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['amount' => 1, 'currency' => 'USD', 'idempotency_key' => 'idem-1']],
    ], $ctx);

    // Same result envelope (identical structuredContent).
    expect($second['result']['structuredContent'])->toBe($first['result']['structuredContent']);
});

it('returns -32002 IDEMPOTENCY_KEY_REUSED when the same key is sent with different args', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);

    $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 8, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['amount' => 1, 'currency' => 'USD', 'idempotency_key' => 'idem-2']],
    ], $ctx);

    $reuse = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => ['amount' => 9.99, 'currency' => 'USD', 'idempotency_key' => 'idem-2']],
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

it('canonicalises args order when computing args_hash so reordered keys hit the cache', function () {
    // Regression test for the original non-canonical hash bug: a client retrying
    // the same write call but stringifying its arguments object with keys in a
    // different order would have been rejected as IDEMPOTENCY_KEY_REUSED. After
    // canonicalisation (recursive ksort before encoding), the second call must
    // be served from the idempotency cache.
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);

    $first = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 100, 'method' => 'tools/call',
        'params'  => [
            'name'      => 'payment.transfer',
            'arguments' => ['amount' => 1, 'currency' => 'USD', 'idempotency_key' => 'reorder-key'],
        ],
    ], $ctx);

    $second = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 101, 'method' => 'tools/call',
        'params'  => [
            'name' => 'payment.transfer',
            // Same logical args, different key order. Must match the cached entry.
            'arguments' => ['idempotency_key' => 'reorder-key', 'currency' => 'USD', 'amount' => 1],
        ],
    ], $ctx);

    expect($second)->not->toHaveKey('error');
    expect($second['result']['structuredContent'])->toBe($first['result']['structuredContent']);
});

// -----------------------------------------------------------------------
// Spending-saga path
// -----------------------------------------------------------------------

it('reserves the daily spend on a successful payment-tool call and persists the increment', function () {
    // Catalog says payment.transfer.amount_decimals=2 → amount=120 → 12000 minor.
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 200, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => [
            'amount'          => 120,
            'currency'        => 'USD',
            'idempotency_key' => 'pay-success',
        ]],
    ], $ctx);

    expect($resp['result']['isError'])->toBeFalse();
    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_test')->value('daily_spend_minor'))->toBe(12000);
});

it('returns -32003 SPENDING_LIMIT_EXCEEDED when the reservation would exceed the daily cap', function () {
    DB::table('mcp_token_policies')->where('token_id', 'tok_test')->update(['daily_spend_minor' => 49000]);

    // amount=50 → 5000 minor. 49000 + 5000 = 54000 > 50000 limit → reject with 1000 remaining.
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 201, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => [
            'amount'          => 50,
            'currency'        => 'USD',
            'idempotency_key' => 'pay-overlimit',
        ]],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32003);
    expect($resp['error']['message'])->toBe('SPENDING_LIMIT_EXCEEDED');
    expect($resp['error']['data']['limit_remaining_minor'])->toBe(1000);
    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_test')->value('daily_spend_minor'))->toBe(49000);

    $this->assertDatabaseHas('mcp_tool_invocations', [
        'token_id'      => 'tok_test',
        'tool_name'     => 'payment.transfer',
        'result_status' => 'spending_limit',
    ]);
});

it('handles fractional float amounts via bcmath without IEEE-754 drift', function () {
    // amount=0.10 (USD 10¢) must round-trip to exactly 10 minor units.
    // The naive path `(int)(0.10 * 100) == 9` is the bug bcmath prevents.
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 204, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => [
            'amount'          => 0.10,
            'currency'        => 'USD',
            'idempotency_key' => 'pay-cents',
        ]],
    ], $ctx);

    expect($resp['result']['isError'])->toBeFalse();
    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_test')->value('daily_spend_minor'))->toBe(10);
});

it('releases the reservation when the payment tool reports an error', function () {
    // Stub a tool that always fails; replaces the success-stub from beforeEach.
    /** @var ToolRegistry $registry */
    $registry = app(ToolRegistry::class);
    $registry->unregister('payment.transfer');
    $registry->register(new class () implements MCPToolInterface {
        public function getName(): string
        {
            return 'payment.transfer';
        }

        public function getCategory(): string
        {
            return 'test';
        }

        public function getDescription(): string
        {
            return 'failing transfer';
        }

        /** @return array<string, mixed> */
        public function getInputSchema(): array
        {
            return ['type' => 'object'];
        }

        /** @return array<string, mixed> */
        public function getOutputSchema(): array
        {
            return ['type' => 'object'];
        }

        /** @param array<string, mixed> $parameters */
        public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
        {
            return ToolExecutionResult::failure('upstream rail unavailable');
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
    });

    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);
    // amount=75 → 7500 minor. Reservation succeeds, then tool fails → release.
    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 202, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => [
            'amount'          => 75,
            'currency'        => 'USD',
            'idempotency_key' => 'pay-fails',
        ]],
    ], $ctx);

    expect($resp['result']['isError'])->toBeTrue();
    // Reservation rolled back — daily spend stays at 0.
    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_test')->value('daily_spend_minor'))->toBe(0);
});

it('returns -32006 USER_CONTEXT_REQUIRED when a user-context tool is called without a user-bound token', function () {
    // client_credentials grants set user_id=null on the Passport token; the
    // McpRequestContext carries that null forward. payment.transfer is a
    // user-context tool (`requires_user: true` in catalog), so dispatch
    // should reject before ever invoking the tool.
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', null, ['payments:write']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 205, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => [
            'amount'          => 50,
            'currency'        => 'USD',
            'idempotency_key' => 'pay-no-user',
        ]],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32006);
    expect($resp['error']['data']['tool'])->toBe('payment.transfer');
    // Counter must NOT have been touched.
    expect((int) DB::table('mcp_token_policies')->where('token_id', 'tok_test')->value('daily_spend_minor'))->toBe(0);
});

it('rejects a payment-tool call missing amount with -32003 AMOUNT_INVALID', function () {
    $router = app(JsonRpcRouter::class);
    $ctx = new McpRequestContext('tok_test', 'cli', 1, ['payments:write']);
    $resp = $router->dispatch([
        'jsonrpc' => '2.0', 'id' => 203, 'method' => 'tools/call',
        'params'  => ['name' => 'payment.transfer', 'arguments' => [
            'currency'        => 'USD',
            'idempotency_key' => 'pay-no-amount',
        ]],
    ], $ctx);

    expect($resp['error']['code'])->toBe(-32003);
    expect($resp['error']['data']['error_code'])->toBe('AMOUNT_INVALID');
});
