<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Services\JitFundingService;
use App\Domain\CardIssuance\ValueObjects\AuthorizationRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook controller for Just-in-Time (JIT) card funding.
 *
 * This endpoint receives real-time authorization requests from the card issuer
 * when a user taps their card at a merchant. The response must be returned
 * within 2000ms to approve/deny the transaction.
 *
 * @OA\Tag(
 *     name="Card Webhooks",
 *     description="Card issuer webhook endpoints (internal)"
 * )
 */
class JitFundingWebhookController extends Controller
{
    public function __construct(
        private readonly JitFundingService $jitFundingService,
    ) {
    }

    /**
     * Handle card authorization webhook from issuer.
     *
     * CRITICAL: This endpoint has a 2000ms latency budget.
     *
     * @OA\Post(
     *     path="/api/webhooks/card-issuer/authorization",
     *     summary="JIT funding authorization webhook",
     *     tags={"Card Webhooks"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"authorization_id", "card_token", "amount", "currency", "merchant_name"},
     *             @OA\Property(property="authorization_id", type="string"),
     *             @OA\Property(property="card_token", type="string"),
     *             @OA\Property(property="amount", type="integer", description="Amount in cents"),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="merchant_name", type="string"),
     *             @OA\Property(property="merchant_category", type="string"),
     *             @OA\Property(property="merchant_id", type="string"),
     *             @OA\Property(property="merchant_city", type="string"),
     *             @OA\Property(property="merchant_country", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Authorization decision",
     *         @OA\JsonContent(
     *             @OA\Property(property="approved", type="boolean"),
     *             @OA\Property(property="hold_id", type="string", nullable=true),
     *             @OA\Property(property="decline_reason", type="string", nullable=true)
     *         )
     *     )
     * )
     */
    public function handleAuthorization(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $validated = $request->validate([
            'authorization_id'  => 'required|string',
            'card_token'        => 'required|string',
            'amount'            => 'required|integer|min:1',
            'currency'          => 'required|string|size:3',
            'merchant_name'     => 'required|string',
            'merchant_category' => 'nullable|string',
            'merchant_id'       => 'nullable|string',
            'merchant_city'     => 'nullable|string',
            'merchant_country'  => 'nullable|string',
            'timestamp'         => 'nullable|string',
        ]);

        try {
            $authRequest = AuthorizationRequest::fromWebhook($validated);
            $result = $this->jitFundingService->authorize($authRequest);

            $latencyMs = (microtime(true) - $startTime) * 1000;

            Log::info('JIT Authorization processed', [
                'authorization_id' => $validated['authorization_id'],
                'approved'         => $result['approved'],
                'latency_ms'       => round($latencyMs, 2),
            ]);

            return response()->json([
                'approved'       => $result['approved'],
                'hold_id'        => $result['hold_id'],
                'decline_reason' => $result['approved'] ? null : $result['decision']->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::error('JIT Authorization failed', [
                'authorization_id' => $validated['authorization_id'] ?? 'unknown',
                'error'            => $e->getMessage(),
            ]);

            // On error, decline the transaction for safety
            return response()->json([
                'approved'       => false,
                'hold_id'        => null,
                'decline_reason' => 'Internal error - transaction declined for safety',
            ]);
        }
    }

    /**
     * Handle settlement webhook (post-authorization).
     *
     * @OA\Post(
     *     path="/api/webhooks/card-issuer/settlement",
     *     summary="Card settlement webhook",
     *     tags={"Card Webhooks"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="authorization_id", type="string"),
     *             @OA\Property(property="settlement_id", type="string"),
     *             @OA\Property(property="final_amount", type="integer"),
     *             @OA\Property(property="currency", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Settlement acknowledged")
     * )
     */
    public function settlement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'authorization_id' => 'required|string',
            'settlement_id'    => 'required|string',
            'final_amount'     => 'required|integer',
            'currency'         => 'required|string|size:3',
        ]);

        Log::info('Card settlement received', $validated);

        // In production:
        // 1. Find the hold by authorization_id
        // 2. Convert hold to actual debit
        // 3. Handle any difference between auth and settlement amount

        return response()->json([
            'success' => true,
            'message' => 'Settlement processed',
        ]);
    }
}
