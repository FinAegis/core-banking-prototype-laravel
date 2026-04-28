<?php

declare(strict_types=1);

namespace App\Domain\MCP\Audit;

use Illuminate\Support\Facades\DB;

final class ToolInvocationLogger
{
    /**
     * Append a row to mcp_tool_invocations and return the generated id.
     *
     * Required keys in $payload: token_id, client_id, tool_name, args_hash, result_status.
     * Optional: user_id, idempotency_key, error_code, settlement_amount_minor,
     * settlement_currency, ip, user_agent, request_id, duration_ms. Optional metadata
     * (ip / user_agent / request_id) falls back to the current request if available.
     *
     * @param  array<string, mixed>  $payload
     */
    public function log(array $payload): int
    {
        $request = function_exists('request') ? request() : null;

        $row = [
            'token_id'                => (string) ($payload['token_id'] ?? ''),
            'client_id'               => (string) ($payload['client_id'] ?? ''),
            'user_id'                 => $payload['user_id'] ?? null,
            'tool_name'               => (string) ($payload['tool_name'] ?? ''),
            'args_hash'               => (string) ($payload['args_hash'] ?? ''),
            'idempotency_key'         => $payload['idempotency_key'] ?? null,
            'result_status'           => (string) ($payload['result_status'] ?? 'error'),
            'error_code'              => $payload['error_code'] ?? null,
            'settlement_amount_minor' => $payload['settlement_amount_minor'] ?? null,
            'settlement_currency'     => $payload['settlement_currency'] ?? null,
            'ip'                      => $payload['ip'] ?? $request?->ip(),
            'user_agent'              => $payload['user_agent'] ?? $request?->userAgent(),
            'request_id'              => $payload['request_id'] ?? $request?->header('X-Request-ID'),
            'duration_ms'             => $payload['duration_ms'] ?? null,
            'created_at'              => now(),
        ];

        return (int) DB::table('mcp_tool_invocations')->insertGetId($row);
    }
}
