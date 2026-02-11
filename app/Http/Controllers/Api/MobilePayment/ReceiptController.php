<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\MobilePayment;

use App\Domain\MobilePayment\Services\ReceiptService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptService $receiptService,
    ) {
    }

    /**
     * Generate a receipt for a transaction.
     *
     * POST /v1/transactions/{txId}/receipt
     *
     * @OA\Post(
     *     path="/api/v1/transactions/{txId}/receipt",
     *     operationId="mobilePaymentGenerateReceipt",
     *     summary="Generate a receipt for a transaction",
     *     description="Generates a digital receipt for a confirmed transaction. Receipts can only be generated for transactions that have been confirmed on-chain.",
     *     tags={"Mobile Payments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="txId",
     *         in="path",
     *         required=true,
     *         description="Transaction ID",
     *         @OA\Schema(type="string", example="tx_abc123")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Receipt generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="receiptId", type="string", example="rcpt_abc123"),
     *                 @OA\Property(property="transactionId", type="string", example="tx_abc123"),
     *                 @OA\Property(property="amount", type="number", example=25.50),
     *                 @OA\Property(property="asset", type="string", example="USDC"),
     *                 @OA\Property(property="merchant", type="object",
     *                     @OA\Property(property="displayName", type="string", example="Coffee Shop")
     *                 ),
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="hash", type="string", description="On-chain transaction hash")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Receipt cannot be generated (transaction not confirmed)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="RECEIPT_UNAVAILABLE"),
     *                 @OA\Property(property="message", type="string", example="Receipt can only be generated for confirmed transactions.")
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request, string $txId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $receipt = $this->receiptService->generateReceipt($txId, $user->id);

        if (! $receipt) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'RECEIPT_UNAVAILABLE',
                    'message' => 'Receipt can only be generated for confirmed transactions.',
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => $receipt->toApiResponse(),
        ]);
    }
}
