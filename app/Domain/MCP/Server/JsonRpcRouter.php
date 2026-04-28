<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

use App\Domain\AI\MCP\ToolRegistry;
use stdClass;
use Throwable;

/**
 * JSON-RPC 2.0 dispatcher for the public MCP server.
 *
 * Decodes a JSON-RPC envelope, routes by method name, and returns a JSON-RPC
 * response envelope. The router runs *inside* the OAuth-guarded `POST /mcp`
 * endpoint — it assumes the McpRequestContext has already been populated from
 * a verified bearer token.
 *
 * Currently implemented methods: `initialize`, `tools/list`, `ping`.
 * Unknown methods return -32601 METHOD_NOT_FOUND.
 */
final class JsonRpcRouter
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
    ) {
    }

    /**
     * @param  array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    public function dispatch(array $envelope, McpRequestContext $ctx): array
    {
        $id = $envelope['id'] ?? null;

        if (($envelope['jsonrpc'] ?? null) !== '2.0' || ! isset($envelope['method'])) {
            return $this->error($id, -32600, 'INVALID_REQUEST');
        }

        $method = (string) $envelope['method'];

        return match ($method) {
            'initialize' => $this->handleInitialize($id),
            'tools/list' => $this->handleToolsList($id, $ctx),
            'ping'       => ['jsonrpc' => '2.0', 'id' => $id, 'result' => new stdClass()],
            default      => $this->error($id, -32601, 'METHOD_NOT_FOUND', ['method' => $method]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function handleInitialize(mixed $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'protocolVersion' => (string) config('mcp.protocol_version'),
                'serverInfo'      => (array) config('mcp.server_info'),
                'capabilities'    => [
                    'tools'     => ['listChanged' => true],
                    'resources' => ['listChanged' => true, 'subscribe' => false],
                    'prompts'   => null,
                    'logging'   => null,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function handleToolsList(mixed $id, McpRequestContext $ctx): array
    {
        $tools = [];
        $catalog = (array) config('mcp.tools');

        foreach ($catalog as $publicName => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (! ($entry['enabled'] ?? false)) {
                continue;
            }

            /** @var string|null $scope */
            $scope = $entry['scope'] ?? null;
            if (! $ctx->hasScope($scope)) {
                continue;
            }

            $internalName = (string) ($entry['internal'] ?? '');
            if ($internalName === '') {
                continue;
            }

            try {
                $internal = $this->toolRegistry->get($internalName);
            } catch (Throwable) {
                // Tool declared in catalog but not registered yet — skip silently.
                continue;
            }

            $tools[] = [
                'name'        => (string) $publicName,
                'description' => $internal->getDescription(),
                'inputSchema' => $this->withIdempotencyField(
                    $internal->getInputSchema(),
                    (bool) ($entry['is_write'] ?? false),
                ),
            ];
        }

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => ['tools' => $tools],
        ];
    }

    /**
     * Augment a write tool's JSON Schema with a required `idempotency_key` field.
     *
     * @param  array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function withIdempotencyField(array $schema, bool $isWrite): array
    {
        if (! $isWrite) {
            return $schema;
        }

        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $properties['idempotency_key'] = [
            'type'        => 'string',
            'format'      => 'uuid',
            'description' => 'Required for write tools. Server caches result for 24h; same key + same args returns cached result; same key + different args returns -32002.',
        ];
        $schema['properties'] = $properties;

        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $schema['required'] = array_values(array_unique(array_merge($required, ['idempotency_key'])));

        return $schema;
    }

    /**
     * @param  array<string, mixed>|null $data
     * @return array<string, mixed>
     */
    private function error(mixed $id, int $code, string $message, ?array $data = null): array
    {
        $err = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $err['data'] = $data;
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => $err];
    }
}
