<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Webhooks",
 *     description="Webhook management for real-time event notifications"
 * )
 */
class WebhookController extends Controller
{
    /**
     * @OA\Get(
     *     path="/webhooks",
     *     operationId="listWebhooks",
     *     tags={"Webhooks"},
     *     summary="List webhooks",
     *     description="Get a list of all configured webhooks for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of webhooks",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="url", type="string", format="url"),
     *                     @OA\Property(property="events", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="is_active", type="boolean"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="last_triggered_at", type="string", format="date-time", nullable=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // For now, return all webhooks until user association is implemented
        $webhooks = Webhook::orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $webhooks->map(function ($webhook) {
                return [
                    'id' => $webhook->uuid,
                    'url' => $webhook->url,
                    'events' => $webhook->events,
                    'is_active' => $webhook->is_active,
                    'description' => $webhook->description,
                    'created_at' => $webhook->created_at->toIso8601String(),
                    'last_triggered_at' => $webhook->last_triggered_at?->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/webhooks",
     *     operationId="createWebhook",
     *     tags={"Webhooks"},
     *     summary="Create webhook",
     *     description="Create a new webhook endpoint",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"url", "events"},
     *             @OA\Property(property="url", type="string", format="url", example="https://example.com/webhook"),
     *             @OA\Property(property="events", type="array", @OA\Items(type="string"), example={"account.created", "transaction.completed"}),
     *             @OA\Property(property="description", type="string", example="Production webhook for transaction notifications"),
     *             @OA\Property(property="is_active", type="boolean", default=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Webhook created",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="events", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="secret", type="string", example="whsec_[redacted]"),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url|https',
            'events' => 'required|array|min:1',
            'events.*' => ['required', 'string', Rule::in($this->getAvailableEvents())],
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $secret = 'whsec_' . Str::random(32);

        $webhook = Webhook::create([
            'uuid' => Str::uuid(),
            'name' => $validated['description'] ?? 'Webhook',
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => $secret,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => [
                'id' => $webhook->uuid,
                'url' => $webhook->url,
                'events' => $webhook->events,
                'secret' => $secret, // Only shown once at creation
                'is_active' => $webhook->is_active,
                'created_at' => $webhook->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/webhooks/{id}",
     *     operationId="getWebhook",
     *     tags={"Webhooks"},
     *     summary="Get webhook details",
     *     description="Get details of a specific webhook",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Webhook ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook details",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="events", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="is_active", type="boolean"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="statistics", type="object",
     *                     @OA\Property(property="total_deliveries", type="integer"),
     *                     @OA\Property(property="successful_deliveries", type="integer"),
     *                     @OA\Property(property="failed_deliveries", type="integer"),
     *                     @OA\Property(property="last_triggered_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        $statistics = [
            'total_deliveries' => $webhook->deliveries()->count(),
            'successful_deliveries' => $webhook->deliveries()->where('status', WebhookDelivery::STATUS_SUCCESS)->count(),
            'failed_deliveries' => $webhook->deliveries()->where('status', WebhookDelivery::STATUS_FAILED)->count(),
            'last_triggered_at' => $webhook->last_triggered_at?->toIso8601String(),
        ];

        return response()->json([
            'data' => [
                'id' => $webhook->uuid,
                'url' => $webhook->url,
                'events' => $webhook->events,
                'is_active' => $webhook->is_active,
                'description' => $webhook->description,
                'created_at' => $webhook->created_at->toIso8601String(),
                'statistics' => $statistics,
            ],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/webhooks/{id}",
     *     operationId="updateWebhook",
     *     tags={"Webhooks"},
     *     summary="Update webhook",
     *     description="Update webhook configuration",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Webhook ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="url", type="string", format="url"),
     *             @OA\Property(property="events", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook updated"
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        $validated = $request->validate([
            'url' => 'sometimes|url|https',
            'events' => 'sometimes|array|min:1',
            'events.*' => ['required', 'string', Rule::in($this->getAvailableEvents())],
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $webhook->update($validated);

        return response()->json([
            'data' => [
                'id' => $webhook->uuid,
                'url' => $webhook->url,
                'events' => $webhook->events,
                'is_active' => $webhook->is_active,
                'updated_at' => $webhook->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/webhooks/{id}",
     *     operationId="deleteWebhook",
     *     tags={"Webhooks"},
     *     summary="Delete webhook",
     *     description="Delete a webhook endpoint",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Webhook ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Webhook deleted"
     *     )
     * )
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        $webhook->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Get(
     *     path="/webhooks/{id}/deliveries",
     *     operationId="listWebhookDeliveries",
     *     tags={"Webhooks"},
     *     summary="List webhook deliveries",
     *     description="Get delivery history for a webhook",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Webhook ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by status",
     *         @OA\Schema(type="string", enum={"pending", "success", "failed"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook deliveries"
     *     )
     * )
     */
    public function deliveries(Request $request, string $id): JsonResponse
    {
        $webhook = Webhook::findOrFail($id);

        $query = $webhook->deliveries()
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $deliveries = $query->paginate(20);

        return response()->json([
            'data' => $deliveries->items(),
            'meta' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/webhooks/events",
     *     operationId="listWebhookEvents",
     *     tags={"Webhooks"},
     *     summary="List available webhook events",
     *     description="Get a list of all available webhook events that can be subscribed to",
     *     @OA\Response(
     *         response=200,
     *         description="Available webhook events",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="account", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="transaction", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="transfer", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="basket", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="governance", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function events(): JsonResponse
    {
        return response()->json([
            'data' => [
                'account' => [
                    'account.created',
                    'account.updated',
                    'account.frozen',
                    'account.unfrozen',
                    'account.closed',
                ],
                'transaction' => [
                    'transaction.created',
                    'transaction.completed',
                    'transaction.failed',
                    'transaction.reversed',
                ],
                'transfer' => [
                    'transfer.initiated',
                    'transfer.completed',
                    'transfer.failed',
                ],
                'basket' => [
                    'basket.created',
                    'basket.rebalanced',
                    'basket.decomposed',
                ],
                'governance' => [
                    'poll.created',
                    'poll.activated',
                    'poll.completed',
                    'vote.cast',
                ],
                'exchange_rate' => [
                    'rate.updated',
                    'rate.stale',
                ],
            ],
        ]);
    }

    private function getAvailableEvents(): array
    {
        return [
            'account.created',
            'account.updated',
            'account.frozen',
            'account.unfrozen',
            'account.closed',
            'transaction.created',
            'transaction.completed',
            'transaction.failed',
            'transaction.reversed',
            'transfer.initiated',
            'transfer.completed',
            'transfer.failed',
            'basket.created',
            'basket.rebalanced',
            'basket.decomposed',
            'poll.created',
            'poll.activated',
            'poll.completed',
            'vote.cast',
            'rate.updated',
            'rate.stale',
        ];
    }
}