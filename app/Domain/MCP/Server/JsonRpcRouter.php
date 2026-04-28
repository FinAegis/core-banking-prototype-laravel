<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

use App\Domain\AI\Exceptions\ToolNotFoundException;
use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\MCP\Audit\ToolInvocationLogger;
use App\Domain\MCP\Exceptions\IdempotencyKeyInFlightException;
use App\Domain\MCP\Exceptions\IdempotencyKeyReusedException;
use App\Domain\MCP\Exceptions\SpendingLimitExceededException;
use App\Domain\MCP\Policy\IdempotencyCache;
use App\Domain\MCP\Resources\ResourceRegistry;
use App\Domain\MCP\Sagas\SpendingEnforcedToolCallSaga;
use stdClass;

/**
 * JSON-RPC 2.0 dispatcher for the public MCP server.
 *
 * Decodes a JSON-RPC envelope, routes by method name, and returns a JSON-RPC
 * response envelope. The router runs *inside* the OAuth-guarded `POST /mcp`
 * endpoint — it assumes the McpRequestContext has already been populated from
 * a verified bearer token.
 *
 * Currently implemented methods: `initialize`, `tools/list`, `tools/call`, `ping`.
 * Unknown methods return -32601 METHOD_NOT_FOUND.
 *
 * Spending limits: payment tools (catalog `is_payment: true`) are wrapped in
 *   SpendingEnforcedToolCallSaga, which reserves the requested amount before
 *   execution and releases the reservation if the tool reports failure. Tools
 *   without explicit amounts (sms.send, ramp.start) are NOT yet covered — see
 *   the catalog and Phase 3 follow-up for variable-cost rails.
 */
final class JsonRpcRouter
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly McpToolAdapter $adapter,
        private readonly IdempotencyCache $idempotency,
        private readonly ToolInvocationLogger $logger,
        private readonly SpendingEnforcedToolCallSaga $spendingSaga,
        private readonly ResourceRegistry $resources,
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
        /** @var array<string, mixed> $params */
        $params = is_array($envelope['params'] ?? null) ? $envelope['params'] : [];

        return match ($method) {
            'initialize'     => $this->handleInitialize($id),
            'tools/list'     => $this->handleToolsList($id, $ctx),
            'tools/call'     => $this->handleToolsCall($id, $params, $ctx),
            'resources/list' => $this->handleResourcesList($id, $ctx),
            'resources/read' => $this->handleResourcesRead($id, $params, $ctx),
            'ping'           => ['jsonrpc' => '2.0', 'id' => $id, 'result' => new stdClass()],
            default          => $this->error($id, -32601, 'METHOD_NOT_FOUND', ['method' => $method]),
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
            if (! is_array($entry) || ! ($entry['enabled'] ?? false)) {
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
            } catch (ToolNotFoundException) {
                // Tool declared in catalog but not registered yet — skip silently.
                // We narrowed from `Throwable` so OOM / DB / etc. still propagate.
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
     * Dispatch a `tools/call` invocation: resolve the public tool name to its
     * internal MCPToolInterface, enforce scope + write-tool idempotency, execute
     * via the adapter (wrapped in IdempotencyCache for write tools), and append
     * an audit row to mcp_tool_invocations.
     *
     * @param  array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleToolsCall(mixed $id, array $params, McpRequestContext $ctx): array
    {
        $name = (string) ($params['name'] ?? '');
        /** @var array<string, mixed> $arguments */
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        /** @var array<string, mixed> $catalog */
        $catalog = (array) config('mcp.tools');
        if (! isset($catalog[$name]) || ! is_array($catalog[$name])) {
            return $this->error($id, -32601, 'TOOL_NOT_FOUND', ['name' => $name]);
        }

        /** @var array<string, mixed> $entry */
        $entry = $catalog[$name];

        if (! ($entry['enabled'] ?? false)) {
            return $this->error($id, -32004, 'TOOL_DISABLED', ['name' => $name]);
        }

        /** @var string|null $requiredScope */
        $requiredScope = $entry['scope'] ?? null;
        if (! $ctx->hasScope($requiredScope)) {
            return $this->error($id, -32000, 'INSUFFICIENT_SCOPE', [
                'required' => $requiredScope,
                'granted'  => $ctx->scopes,
            ]);
        }

        // User-context tools (anything that accesses a user's own accounts /
        // payments / transactions) require a token bound to a user. A
        // client_credentials grant has user_id=null on the token; without
        // this guard, those tools would silently fall through to "Auth::user()
        // returned null" inside the tool and return an opaque error. Surface
        // the constraint at the dispatch layer instead.
        $requiresUser = (bool) ($entry['requires_user'] ?? false);
        if ($requiresUser && $ctx->userId === null) {
            return $this->error($id, -32006, 'USER_CONTEXT_REQUIRED', [
                'tool'   => $name,
                'detail' => 'this tool requires a user-bound bearer token; client_credentials grants are not allowed',
            ]);
        }

        $isWrite = (bool) ($entry['is_write'] ?? false);
        $idemKeyRaw = $arguments['idempotency_key'] ?? null;
        $idemKey = is_string($idemKeyRaw) && $idemKeyRaw !== '' ? $idemKeyRaw : null;

        if ($isWrite && $idemKey === null) {
            return $this->error($id, -32602, 'IDEMPOTENCY_KEY_REQUIRED', ['tool' => $name]);
        }

        // Cap idempotency_key length so a malicious client can't blow up Redis
        // key sizes or audit-log columns. 128 chars is generous (UUIDs are 36).
        if ($idemKey !== null && strlen($idemKey) > 128) {
            return $this->error($id, -32602, 'IDEMPOTENCY_KEY_TOO_LONG', [
                'tool'       => $name,
                'max_length' => 128,
                'received'   => strlen($idemKey),
            ]);
        }

        $internalName = (string) ($entry['internal'] ?? '');
        try {
            $tool = $this->toolRegistry->get($internalName);
        } catch (ToolNotFoundException) {
            return $this->error($id, -32603, 'INTERNAL_TOOL_MISSING', ['internal' => $internalName]);
        }

        $argsHash = self::canonicalArgsHash($arguments);
        $started = hrtime(true);
        $isPayment = (bool) ($entry['is_payment'] ?? false);

        try {
            $baseExec = fn (): array => $this->adapter->execute($tool, $arguments, 'mcp_' . $ctx->tokenId);

            // Saga wraps the bare execute, so an idempotent replay served from
            // the cache below skips the reserve/release dance entirely (the
            // first call already settled the spend).
            $execWithSpending = $isPayment
                ? fn (): array => $this->spendingSaga->run($ctx->tokenId, $arguments, $entry, $baseExec)
                : $baseExec;

            if ($isWrite) {
                /** @var array<string, mixed> $result */
                $result = $this->idempotency->remember($ctx->tokenId, $name, (string) $idemKey, $argsHash, $execWithSpending);
            } else {
                $result = $execWithSpending();
            }
        } catch (IdempotencyKeyReusedException) {
            $this->audit($ctx, $name, $argsHash, $idemKey, 'error', $started, errorCode: 'IDEMPOTENCY_KEY_REUSED');

            return $this->error($id, -32002, 'IDEMPOTENCY_KEY_REUSED', ['idempotency_key' => $idemKey]);
        } catch (IdempotencyKeyInFlightException) {
            $this->audit($ctx, $name, $argsHash, $idemKey, 'error', $started, errorCode: 'IDEMPOTENCY_KEY_IN_FLIGHT');

            return $this->error($id, -32005, 'IDEMPOTENCY_KEY_IN_FLIGHT', [
                'idempotency_key'     => $idemKey,
                'retry_after_seconds' => 1,
            ]);
        } catch (SpendingLimitExceededException $e) {
            $this->audit($ctx, $name, $argsHash, $idemKey, 'spending_limit', $started, errorCode: (string) ($e->data['error_code'] ?? 'LIMIT_EXCEEDED'));

            return $this->error($id, -32003, 'SPENDING_LIMIT_EXCEEDED', $e->data);
        }

        $status = ($result['isError'] ?? false) ? 'error' : 'success';
        $this->audit($ctx, $name, $argsHash, $idemKey, $status, $started);

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ];
    }

    /**
     * Canonical sha256 of the arguments map: keys sorted recursively before encoding,
     * unicode unescaped. Matches the same hash whether the client sends
     * {"a":1,"b":2} or {"b":2,"a":1} — required for idempotency to work for
     * arbitrary clients that may stringify their JSON differently on retry.
     *
     * Note: `idempotency_key` is intentionally hashed *with* the rest of the
     * arguments. A retry must send the identical key (by definition), so this
     * doesn't change behavior; including it just keeps the hash an opaque
     * function of the entire envelope.
     *
     * @param array<string, mixed> $arguments
     */
    private static function canonicalArgsHash(array $arguments): string
    {
        $canonical = self::canonicalize($arguments);

        return hash('sha256', (string) json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Recursively sort associative-array keys; preserve list ordering. Lists
     * are arrays where keys are 0..n-1; everything else is treated as an
     * associative map.
     */
    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = self::canonicalize($v);
        }

        if (! $isList) {
            ksort($out);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function handleResourcesList(mixed $id, McpRequestContext $ctx): array
    {
        $items = [];
        foreach ($this->resources->all() as $res) {
            if (! $ctx->hasScope($res->scope())) {
                continue;
            }

            $items[] = [
                'uri'         => $res->uriTemplate(),
                'name'        => $res->name(),
                'description' => $res->description(),
                'mimeType'    => $res->mimeType(),
            ];
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['resources' => $items]];
    }

    /**
     * @param  array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleResourcesRead(mixed $id, array $params, McpRequestContext $ctx): array
    {
        $uri = (string) ($params['uri'] ?? '');
        if ($uri === '') {
            return $this->error($id, -32602, 'URI_REQUIRED');
        }

        $hit = $this->resources->resolve($uri);
        if ($hit === null) {
            return $this->error($id, -32601, 'RESOURCE_NOT_FOUND', ['uri' => $uri]);
        }

        [$resource, $uriParams] = $hit;

        if (! $ctx->hasScope($resource->scope())) {
            return $this->error($id, -32000, 'INSUFFICIENT_SCOPE', [
                'required' => $resource->scope(),
                'granted'  => $ctx->scopes,
            ]);
        }

        $body = $resource->read($uriParams, $ctx->userId);

        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => [
                'contents' => [[
                    'uri'      => $uri,
                    'mimeType' => $resource->mimeType(),
                    'text'     => $body,
                ]],
            ],
        ];
    }

    private function audit(
        McpRequestContext $ctx,
        string $toolName,
        string $argsHash,
        ?string $idemKey,
        string $status,
        int|float $startedHrtime,
        ?string $errorCode = null,
    ): void {
        $this->logger->log([
            'token_id'        => $ctx->tokenId,
            'client_id'       => $ctx->clientId,
            'user_id'         => $ctx->userId,
            'tool_name'       => $toolName,
            'args_hash'       => $argsHash,
            'idempotency_key' => $idemKey,
            'result_status'   => $status,
            'error_code'      => $errorCode,
            'duration_ms'     => (int) ((hrtime(true) - $startedHrtime) / 1_000_000),
        ]);
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
