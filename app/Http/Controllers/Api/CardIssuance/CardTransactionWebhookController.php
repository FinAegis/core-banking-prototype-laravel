<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Services\CardTransactionSyncService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Webhook controller for card transaction sync from Rain/Marqeta.
 */
#[OA\Tag(
    name: 'Card Webhooks',
    description: 'Card issuer webhook endpoints (internal)'
)]
class CardTransactionWebhookController extends Controller
{
    public function __construct(
        private readonly CardTransactionSyncService $syncService,
    ) {
    }

    /**
     * Handle card transaction webhook.
     */
    #[OA\Post(
        path: '/api/webhooks/card-issuer/transaction',
        summary: 'Card transaction sync webhook',
        tags: ['Card Webhooks'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'event_type', type: 'string', example: 'transaction.created'),
            new OA\Property(property: 'data', type: 'object'),
        ]))
    )]
    #[OA\Response(response: 200, description: 'Webhook processed')]
    public function handleTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'nullable|string',
            'type'       => 'nullable|string',
            'data'       => 'nullable|array',
        ]);

        try {
            $result = $this->syncService->processWebhook($validated);

            return response()->json([
                'success'        => $result['synced'],
                'transaction_id' => $result['transaction_id'],
            ]);
        } catch (Throwable $e) {
            Log::error('Card transaction webhook failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Webhook processing failed',
            ], 500);
        }
    }
}
