<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Mobile\Models\MobilePushNotification;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    /**
     * Type category → raw notification_type prefix mapping for filtering.
     *
     * @var array<string, array<string>>
     */
    private const TYPE_FILTER_MAP = [
        'transaction' => ['transaction.', 'balance.'],
        'security'    => ['security.'],
        'system'      => ['system.', 'kyc.', 'general'],
        'promo'       => ['promo.', 'marketing.', 'price.'],
    ];

    public function __construct(
        private readonly PushNotificationService $pushService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/notifications',
        operationId: 'v1ListNotifications',
        tags: ['Notifications'],
        summary: 'List paginated notifications',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'offset', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 0)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['transaction', 'security', 'system', 'promo'])),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Paginated notification list',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'type', type: 'string', enum: ['transaction', 'security', 'system', 'promo']),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'body', type: 'string'),
                        new OA\Property(property: 'read', type: 'boolean'),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                    ]
                )),
                new OA\Property(property: 'meta', type: 'object', properties: [
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'offset', type: 'integer'),
                    new OA\Property(property: 'limit', type: 'integer'),
                    new OA\Property(property: 'unread_count', type: 'integer'),
                ]),
            ]
        )
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $offset = max(0, (int) $request->input('offset', 0));
        $limit = min(max(1, (int) $request->input('limit', 20)), 100);
        $typeFilter = $request->input('type');

        $query = MobilePushNotification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($typeFilter && isset(self::TYPE_FILTER_MAP[$typeFilter])) {
            $prefixes = self::TYPE_FILTER_MAP[$typeFilter];
            $query->where(function ($q) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    if (str_ends_with($prefix, '.')) {
                        $q->orWhere('notification_type', 'like', $prefix . '%');
                    } else {
                        $q->orWhere('notification_type', $prefix);
                    }
                }
            });
        }

        $total = $query->count();
        $notifications = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'total'        => $total,
                'offset'       => $offset,
                'limit'        => $limit,
                'unread_count' => $this->pushService->getUnreadCount($user),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/v1/notifications/{id}',
        operationId: 'v1ShowNotification',
        tags: ['Notifications'],
        summary: 'Get a single notification',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Notification detail')]
    #[OA\Response(response: 404, description: 'Not found')]
    public function show(Request $request, string $id): JsonResponse
    {
        $notification = MobilePushNotification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $notification) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Notification not found',
                ],
            ], 404);
        }

        return response()->json([
            'data' => new NotificationResource($notification),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/notifications/{id}/read',
        operationId: 'v1MarkNotificationRead',
        tags: ['Notifications'],
        summary: 'Mark a notification as read',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Marked as read')]
    #[OA\Response(response: 404, description: 'Not found')]
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = MobilePushNotification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $notification) {
            return response()->json([
                'error' => [
                    'code'    => 'NOT_FOUND',
                    'message' => 'Notification not found',
                ],
            ], 404);
        }

        $this->pushService->markAsRead($notification);
        $notification->refresh();

        return response()->json([
            'data'    => new NotificationResource($notification),
            'message' => 'Notification marked as read',
        ]);
    }

    #[OA\Post(
        path: '/api/v1/notifications/read-all',
        operationId: 'v1MarkAllNotificationsRead',
        tags: ['Notifications'],
        summary: 'Mark all notifications as read',
        security: [['sanctum' => []]]
    )]
    #[OA\Response(response: 200, description: 'All marked as read')]
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->pushService->markAllAsRead($user);

        return response()->json([
            'data' => [
                'count' => $count,
            ],
            'message' => "{$count} notifications marked as read",
        ]);
    }

    #[OA\Get(
        path: '/api/v1/notifications/unread-count',
        operationId: 'v1NotificationUnreadCount',
        tags: ['Notifications'],
        summary: 'Get unread notification count',
        security: [['sanctum' => []]]
    )]
    #[OA\Response(response: 200, description: 'Unread count')]
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'unread_count' => $this->pushService->getUnreadCount($request->user()),
            ],
        ]);
    }
}
