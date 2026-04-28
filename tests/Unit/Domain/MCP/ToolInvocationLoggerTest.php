<?php

declare(strict_types=1);

use App\Domain\MCP\Audit\ToolInvocationLogger;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

it('writes a successful invocation row', function () {
    $logger = new ToolInvocationLogger();

    $id = $logger->log([
        'token_id'      => 'tok_test_1',
        'client_id'     => 'client_test',
        'user_id'       => 1,
        'tool_name'     => 'account.balance',
        'args_hash'     => str_repeat('a', 64),
        'result_status' => 'success',
        'duration_ms'   => 42,
    ]);

    expect($id)->toBeInt();
    expect($id)->toBeGreaterThan(0);
    $this->assertDatabaseHas('mcp_tool_invocations', [
        'token_id'      => 'tok_test_1',
        'tool_name'     => 'account.balance',
        'result_status' => 'success',
    ]);
});

it('records settlement amount on monetary tools', function () {
    (new ToolInvocationLogger())->log([
        'token_id'                => 'tok_pay',
        'client_id'               => 'c',
        'user_id'                 => 1,
        'tool_name'               => 'payment.transfer',
        'args_hash'               => str_repeat('b', 64),
        'result_status'           => 'success',
        'settlement_amount_minor' => 12500,
        'settlement_currency'     => 'USD',
    ]);

    $this->assertDatabaseHas('mcp_tool_invocations', [
        'tool_name'               => 'payment.transfer',
        'settlement_amount_minor' => 12500,
        'settlement_currency'     => 'USD',
    ]);
});

it('persists error_code and idempotency_key when present', function () {
    (new ToolInvocationLogger())->log([
        'token_id'        => 'tok_err',
        'client_id'       => 'c',
        'tool_name'       => 'sms.send',
        'args_hash'       => str_repeat('c', 64),
        'idempotency_key' => 'idem-key-123',
        'result_status'   => 'error',
        'error_code'      => 'PROVIDER_TIMEOUT',
    ]);

    $this->assertDatabaseHas('mcp_tool_invocations', [
        'token_id'        => 'tok_err',
        'idempotency_key' => 'idem-key-123',
        'result_status'   => 'error',
        'error_code'      => 'PROVIDER_TIMEOUT',
    ]);
});

it('captures request metadata (ip, user_agent, request_id) from the current request when not provided', function () {
    /** @var Illuminate\Http\Request $request */
    $request = request();
    $request->server->set('REMOTE_ADDR', '203.0.113.7');
    $request->headers->set('User-Agent', 'TestAgent/1.0');
    $request->headers->set('X-Request-ID', 'req-abc-123');

    (new ToolInvocationLogger())->log([
        'token_id'      => 'tok_meta',
        'client_id'     => 'c',
        'tool_name'     => 'account.profile',
        'args_hash'     => str_repeat('d', 64),
        'result_status' => 'success',
    ]);

    $row = (array) DB::table('mcp_tool_invocations')->where('token_id', 'tok_meta')->first();
    expect($row['ip'])->toBe('203.0.113.7');
    expect($row['user_agent'])->toBe('TestAgent/1.0');
    expect($row['request_id'])->toBe('req-abc-123');
});

it('throws InvalidArgumentException on an unknown result_status', function () {
    expect(fn () => (new ToolInvocationLogger())->log([
        'token_id'      => 'tok_x',
        'client_id'     => 'c',
        'tool_name'     => 'account.balance',
        'args_hash'     => str_repeat('e', 64),
        'result_status' => 'NOT_AN_ENUM_VALUE',
    ]))->toThrow(InvalidArgumentException::class);
});
