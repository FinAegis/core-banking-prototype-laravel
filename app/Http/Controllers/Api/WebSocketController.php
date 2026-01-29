<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Controller for WebSocket configuration and status endpoints.
 *
 * Provides clients with the necessary configuration to connect to
 * the WebSocket server and subscribe to tenant-specific channels.
 */
#[OA\Tag(
    name: 'WebSocket',
    description: 'WebSocket configuration and channel management'
)]
class WebSocketController extends Controller
{
    /**
     * Get WebSocket connection configuration.
     *
     * Returns the necessary configuration for clients to connect
     * to the Pusher-compatible WebSocket server (Soketi).
     */
    #[OA\Get(
        path: '/api/websocket/config',
        operationId: 'getWebSocketConfig',
        summary: 'Get WebSocket connection configuration',
        tags: ['WebSocket'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'WebSocket configuration',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'enabled', type: 'boolean', example: true),
                        new OA\Property(property: 'key', type: 'string', example: 'finaegis-key'),
                        new OA\Property(property: 'cluster', type: 'string', example: 'mt1'),
                        new OA\Property(property: 'ws_host', type: 'string', example: '127.0.0.1'),
                        new OA\Property(property: 'ws_port', type: 'integer', example: 6001),
                        new OA\Property(property: 'wss_port', type: 'integer', example: 6001),
                        new OA\Property(property: 'force_tls', type: 'boolean', example: false),
                        new OA\Property(property: 'encrypted', type: 'boolean', example: true),
                        new OA\Property(property: 'auth_endpoint', type: 'string', example: '/broadcasting/auth'),
                    ]
                )
            ),
        ]
    )]
    public function config(): JsonResponse
    {
        $enabled = config('websocket.enabled', true);

        return response()->json([
            'enabled'       => $enabled,
            'key'           => config('broadcasting.connections.pusher.key'),
            'cluster'       => config('broadcasting.connections.pusher.options.cluster', 'mt1'),
            'ws_host'       => config('broadcasting.connections.pusher.options.host', '127.0.0.1'),
            'ws_port'       => (int) config('broadcasting.connections.pusher.options.port', 6001),
            'wss_port'      => (int) config('broadcasting.connections.pusher.options.port', 6001),
            'force_tls'     => (bool) config('broadcasting.connections.pusher.options.useTLS', false),
            'encrypted'     => (bool) config('broadcasting.connections.pusher.options.encrypted', true),
            'auth_endpoint' => '/broadcasting/auth',
        ]);
    }

    /**
     * Get available channels for the authenticated user.
     *
     * Returns the list of channels the user can subscribe to
     * based on their tenant membership and role.
     */
    #[OA\Get(
        path: '/api/websocket/channels',
        operationId: 'getWebSocketChannels',
        summary: 'Get available WebSocket channels for user',
        security: [['sanctum' => []]],
        tags: ['WebSocket'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Available channels',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'channels',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'name', type: 'string', example: 'private-tenant.1.exchange'),
                                    new OA\Property(property: 'type', type: 'string', example: 'private'),
                                    new OA\Property(property: 'description', type: 'string', example: 'Exchange/trading updates'),
                                    new OA\Property(
                                        property: 'events',
                                        type: 'array',
                                        items: new OA\Items(type: 'string'),
                                        example: ['orderbook.updated', 'trade.executed']
                                    ),
                                ],
                                type: 'object'
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function channels(Request $request): JsonResponse
    {
        $user = $request->user();
        $channels = [];

        if ($user === null) {
            return response()->json(['channels' => []]);
        }

        // Get user's current team (tenant)
        $team = $user->currentTeam;

        if ($team === null) {
            return response()->json(['channels' => []]);
        }

        $tenantId = $team->id;
        $isAdmin = $team->user_id === $user->id || $user->hasTeamRole($team, 'admin');

        // Base tenant channel
        $channels[] = [
            'name'        => "private-tenant.{$tenantId}",
            'type'        => 'private',
            'description' => 'General tenant notifications',
            'events'      => ['notification'],
        ];

        // Exchange channel (all users)
        $channels[] = [
            'name'        => "private-tenant.{$tenantId}.exchange",
            'type'        => 'private',
            'description' => 'Exchange/trading updates',
            'events'      => [
                'orderbook.updated',
                'trade.executed',
                'order.placed',
                'order.cancelled',
                'order.filled',
            ],
        ];

        // Accounts channel (all users)
        $channels[] = [
            'name'        => "private-tenant.{$tenantId}.accounts",
            'type'        => 'private',
            'description' => 'Account and portfolio updates',
            'events'      => [
                'balance.updated',
                'portfolio.updated',
                'nav.calculated',
            ],
        ];

        // Transactions channel (all users)
        $channels[] = [
            'name'        => "private-tenant.{$tenantId}.transactions",
            'type'        => 'private',
            'description' => 'Transaction notifications',
            'events'      => [
                'transaction.credited',
                'transaction.debited',
                'transaction.pending',
            ],
        ];

        // Multi-sig wallet channel (all users)
        $channels[] = [
            'name'        => "private-tenant.{$tenantId}.wallet.multi-sig",
            'type'        => 'private',
            'description' => 'Multi-signature wallet updates',
            'events'      => [
                'approval.created',
                'signature.submitted',
                'approval.completed',
            ],
        ];

        // Compliance channel (admin only)
        if ($isAdmin) {
            $channels[] = [
                'name'        => "private-tenant.{$tenantId}.compliance",
                'type'        => 'private',
                'description' => 'Compliance alerts (admin only)',
                'events'      => [
                    'alert.created',
                    'review.required',
                    'threshold.exceeded',
                ],
            ];
        }

        return response()->json(['channels' => $channels]);
    }

    /**
     * Get WebSocket server status.
     *
     * Returns the current status of the WebSocket server.
     * Useful for health checks and monitoring.
     */
    #[OA\Get(
        path: '/api/websocket/status',
        operationId: 'getWebSocketStatus',
        summary: 'Get WebSocket server status',
        tags: ['WebSocket'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'WebSocket server status',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'enabled', type: 'boolean', example: true),
                        new OA\Property(property: 'connected', type: 'boolean', example: true),
                        new OA\Property(property: 'server', type: 'string', example: 'soketi'),
                        new OA\Property(
                            property: 'rate_limits',
                            type: 'object',
                            example: ['order_book' => 10, 'trades' => 50, 'portfolio' => 1]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function status(): JsonResponse
    {
        $enabled = (bool) config('websocket.enabled', true);
        $connected = false;

        // Try to check if Soketi is reachable
        if ($enabled) {
            $host = config('websocket.soketi.host', '127.0.0.1');
            $port = config('websocket.soketi.port', 6001);

            $connection = @fsockopen($host, (int) $port, $errno, $errstr, 1);
            if ($connection !== false) {
                $connected = true;
                fclose($connection);
            }
        }

        return response()->json([
            'enabled'     => $enabled,
            'connected'   => $connected,
            'server'      => 'soketi',
            'rate_limits' => [
                'order_book'   => config('websocket.rate_limiting.order_book.max_per_second', 10),
                'trades'       => config('websocket.rate_limiting.trades.max_per_second', 50),
                'portfolio'    => config('websocket.rate_limiting.portfolio.max_per_second', 1),
                'balance'      => config('websocket.rate_limiting.balance.max_per_second', 5),
                'transactions' => config('websocket.rate_limiting.transactions.max_per_second', 20),
            ],
        ]);
    }

    /**
     * Get channel information by type.
     *
     * Returns details about a specific channel type including
     * rate limits and event descriptions.
     */
    #[OA\Get(
        path: '/api/websocket/channels/{type}',
        operationId: 'getChannelInfo',
        summary: 'Get channel information by type',
        tags: ['WebSocket'],
        parameters: [
            new OA\Parameter(
                name: 'type',
                in: 'path',
                required: true,
                description: 'Channel type',
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['exchange', 'accounts', 'transactions', 'compliance', 'wallet']
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Channel information',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'exchange'),
                        new OA\Property(property: 'suffix', type: 'string', example: 'exchange'),
                        new OA\Property(
                            property: 'events',
                            type: 'array',
                            items: new OA\Items(type: 'string')
                        ),
                        new OA\Property(
                            property: 'rate_limit',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'max_per_second', type: 'integer'),
                                new OA\Property(property: 'batch_window_ms', type: 'integer'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Channel type not found'),
        ]
    )]
    public function channelInfo(string $type): JsonResponse
    {
        $channels = config('websocket.channels', []);

        if (! isset($channels[$type])) {
            return response()->json([
                'error'   => 'Channel type not found',
                'message' => "Unknown channel type: {$type}",
            ], 404);
        }

        $channelConfig = $channels[$type];
        $rateLimitKey = match ($type) {
            'exchange'     => 'order_book',
            'accounts'     => 'portfolio',
            'transactions' => 'transactions',
            'compliance'   => 'compliance',
            'wallet'       => 'transactions',
            default        => 'transactions',
        };

        $rateLimit = config("websocket.rate_limiting.{$rateLimitKey}", [
            'max_per_second'  => 10,
            'batch_window_ms' => 100,
        ]);

        return response()->json([
            'type'       => $type,
            'suffix'     => $channelConfig['suffix'],
            'events'     => $channelConfig['events'],
            'rate_limit' => $rateLimit,
        ]);
    }
}
