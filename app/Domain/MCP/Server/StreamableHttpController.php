<?php

declare(strict_types=1);

namespace App\Domain\MCP\Server;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP streamable-HTTP transport entry point (RFC-style spec 2025-11-25).
 *
 * - `POST /mcp` accepts a single JSON-RPC envelope, dispatches it through
 *   {@see JsonRpcRouter}, and returns the JSON-RPC response envelope.
 *   JSON-RPC notifications (no `id` member, e.g. `notifications/initialized`)
 *   get HTTP 202 with an empty body — never a response envelope.
 * - `GET /mcp` opens a long-lived SSE channel (server→client notifications)
 *   and emits periodic heartbeats so middleboxes don't drop the connection.
 *
 * The route is gated by the `mcp.oauth` middleware; by the time we reach this
 * controller, a verified Passport `Token` model is attached to the request as
 * `mcp.token`.
 */
final class StreamableHttpController
{
    public function __construct(
        private readonly JsonRpcRouter $router,
        private readonly SseStreamManager $sse,
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->handleGet($request);
        }

        return $this->handlePost($request);
    }

    private function handleGet(Request $request): Response
    {
        // The MCP spec lets servers be POST-only and return 405 on GET.
        // We default to POST-only (config('mcp.sse.enabled') = false) because a
        // long-lived SSE response inside PHP-FPM pins a worker for the entire
        // connection lifetime — under load that exhausts the pool. Enable only
        // when running under Octane/Swoole or a dedicated SSE FPM pool.
        if (! (bool) config('mcp.sse.enabled', false)) {
            return new JsonResponse([
                'error'             => 'method_not_allowed',
                'error_description' => 'Server-sent events are not enabled. Use POST /mcp for the JSON-RPC envelope flow.',
            ], 405, ['Allow' => 'POST']);
        }

        $accept = (string) $request->header('Accept', '');
        if (! str_contains($accept, 'text/event-stream')) {
            return new JsonResponse([
                'error' => 'Accept must include text/event-stream',
            ], 406);
        }

        return $this->sse->open((int) config('mcp.sse.heartbeat_seconds', 25));
    }

    private function handlePost(Request $request): Response
    {
        /** @var array<string, mixed> $envelope */
        $envelope = (array) $request->json()->all();

        // MCP streamable-HTTP: clients SHOULD send `MCP-Protocol-Version` on
        // every request after initialize. If present and unsupported, the spec
        // requires HTTP 400. Absent header => assume the current revision
        // (backwards compatible with older clients). The initialize request is
        // exempt — version negotiation happens in its params, not the header.
        $headerVersion = (string) $request->headers->get('MCP-Protocol-Version', '');
        $method = is_string($envelope['method'] ?? null) ? $envelope['method'] : '';
        if ($headerVersion !== '' && $method !== 'initialize') {
            $supported = JsonRpcRouter::supportedProtocolVersions();
            if (! in_array($headerVersion, $supported, true)) {
                return new JsonResponse([
                    'error'             => 'unsupported_protocol_version',
                    'error_description' => sprintf(
                        'MCP-Protocol-Version "%s" is not supported by this server.',
                        $headerVersion,
                    ),
                    'supported_versions' => $supported,
                ], 400);
            }
        }

        $token = $request->attributes->get('mcp.token');

        $tokenId = 'anon';
        $clientId = 'anon';
        $userId = null;
        /** @var array<int, string> $scopes */
        $scopes = [];

        if (is_object($token)) {
            $key = method_exists($token, 'getKey') ? $token->getKey() : null;
            if (is_string($key) || is_int($key)) {
                $tokenId = (string) $key;
            }

            if (isset($token->client_id) && (is_string($token->client_id) || is_int($token->client_id))) {
                $clientId = (string) $token->client_id;
            }

            if (isset($token->user_id) && (is_int($token->user_id) || is_string($token->user_id))) {
                $userId = (int) $token->user_id;
            }

            if (isset($token->scopes) && is_array($token->scopes)) {
                $scopes = array_values(array_map('strval', $token->scopes));
            }
        }

        $ctx = new McpRequestContext(
            tokenId: $tokenId,
            clientId: $clientId,
            userId: $userId,
            scopes: $scopes,
        );

        $response = $this->router->dispatch($envelope, $ctx);

        // [] is the router's "no response" sentinel for JSON-RPC notifications
        // (envelopes without an `id`). Per the MCP streamable-HTTP transport,
        // accepted notifications get HTTP 202 with an empty body.
        if ($response === []) {
            return new Response('', 202);
        }

        return new JsonResponse($response);
    }
}
