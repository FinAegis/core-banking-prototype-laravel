<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\WebSocket;

use App\Domain\X402\Services\WebSocketPaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * REST endpoints for managing paid WebSocket channel subscriptions.
 */
#[OA\Tag(
    name: 'WebSocket Subscriptions',
    description: 'Manage paid WebSocket channel subscriptions'
)]
class PaidChannelController extends Controller
{
    public function __construct(
        private readonly WebSocketPaymentService $paymentService,
    ) {
    }

    /**
     * List active subscriptions for the authenticated user.
     */
    #[OA\Get(
        path: '/api/v1/websocket/subscriptions',
        summary: 'List active paid channel subscriptions',
        tags: ['WebSocket Subscriptions']
    )]
    #[OA\Response(response: 200, description: 'Active subscriptions')]
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $subscriptions = $this->paymentService->getActiveSubscriptions($user->id);

        return response()->json([
            'success' => true,
            'data'    => $subscriptions->map(fn ($sub) => [
                'id'         => $sub->id,
                'channel'    => $sub->channel,
                'protocol'   => $sub->protocol,
                'amount'     => $sub->amount,
                'network'    => $sub->network,
                'expires_at' => $sub->expires_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Cancel an active subscription.
     */
    #[OA\Delete(
        path: '/api/v1/websocket/subscriptions/{id}',
        summary: 'Cancel a paid channel subscription',
        tags: ['WebSocket Subscriptions']
    )]
    #[OA\Response(response: 200, description: 'Subscription cancelled')]
    #[OA\Response(response: 404, description: 'Subscription not found')]
    public function destroy(string $id): JsonResponse
    {
        $cancelled = $this->paymentService->cancelSubscription($id);

        if (! $cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled.',
        ]);
    }
}
